<?php

declare(strict_types=1);

namespace App\Domain\Lab\Actions;

use App\Domain\Lab\Events\LabBillCreated;
use App\Domain\Lab\Models\LabDiscountApproval;
use App\Domain\Lab\Models\LabOrder;
use App\Domain\Lab\Models\LabOrderEvent;
use App\Domain\Lab\Models\LabOrderItem;
use App\Domain\Lab\Models\Specimen;
use App\Domain\Lab\Services\AccessionGenerator;
use App\Domain\Lab\Services\BillNumberGenerator;
use App\Domain\Lab\Services\PriceResolver;
use App\Domain\Lab\States\LabState;
use App\Models\Legacy\BillingPaymentType;
use App\Models\Legacy\LabInvestigation;
use App\Models\Legacy\Patient;
use App\Models\Legacy\PatientBilling;
use App\Models\Legacy\PatientGenBill;
use App\Models\Legacy\VisitLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateLabBill
{
    public function __construct(
        protected BillNumberGenerator $billNumberGenerator,
        protected AccessionGenerator $accessionGenerator,
        protected PriceResolver $priceResolver
    ) {}

    /**
     * Create a complete lab bill and order atomically.
     *
     * @param array{
     *     client_token?: ?string,
     *     patient_id?: ?int,
     *     walkin?: array{name: string, age: int, sex: string, phone?: ?string},
     *     visit_id?: ?int,
     *     doctor_id?: ?int,
     *     referral_doctor?: ?string,
     *     payer_category?: string,
     *     priority?: string,
     *     clinical_notes?: ?string,
     *     branch_id?: int,
     *     items: list<array{service_id: int, discount?: float, discount_per?: float, outsource_lab_id?: ?int}>,
     *     bill_discount?: float,
     *     bill_discount_per?: float,
     *     discount_reason?: ?string,
     *     discount_approver_id?: ?int,
     *     payments: list<array{payment_mode: string, amount: float, transaction_id?: ?string, remarks?: ?string}>,
     *     user_id?: ?int
     * } $data
     */
    public function execute(array $data): LabOrder
    {
        $clientToken = $data['client_token'] ?? null;

        // Idempotency check: if client_token already processed, return existing order
        if ($clientToken) {
            $existingOrder = LabOrder::where('client_token', $clientToken)
                ->with(['bill', 'items.service', 'specimens'])
                ->first();
            if ($existingOrder) {
                return $existingOrder;
            }
        }

        return DB::transaction(function () use ($data, $clientToken) {
            $userId = $data['user_id'] ?? 1;
            $branchId = $data['branch_id'] ?? 1;
            $payerCategory = $data['payer_category'] ?? 'general';
            $priority = $data['priority'] ?? 'routine';
            $refDoctor = $data['referral_doctor'] ?? 'Self';
            $doctorId = $data['doctor_id'] ?? 1;

            // 1. Resolve or create patient
            $patient = null;
            if (! empty($data['patient_id'])) {
                $patient = Patient::findOrFail($data['patient_id']);
            } elseif (! empty($data['walkin'])) {
                $walkin = $data['walkin'];
                $uhid = Patient::generateNextUhid();
                $nameParts = explode(' ', trim($walkin['name']), 2);
                $fname = $nameParts[0];
                $lname = $nameParts[1] ?? '';

                $patient = Patient::create([
                    'uhid' => $uhid,
                    'prefix' => '',
                    'Fname' => $fname,
                    'Lname' => $lname,
                    'age' => $walkin['age'],
                    'sex' => strtolower($walkin['sex']),
                    'i_cover' => 0,
                    'mnvs_otp' => 0,
                    'mobile_no' => ! empty($walkin['phone']) ? (int) preg_replace('/\D/', '', $walkin['phone']) : null,
                    'referring_doctor' => $refDoctor,
                    'hospital_id' => $branchId,
                    'login_id' => 'WALK_IN',
                    'vpo' => 'Local',
                    'houseno' => 0,
                    'street' => 'Local',
                    'city' => 'Local',
                    'district' => 'Local',
                    'state' => 'Local',
                    'pin' => 110001,
                    'email_id1' => 'walkin@hospital.local',
                    'email_id2' => '',
                    'Mothername' => '',
                    'dump' => 0,
                    'passcode' => '0',
                    'user_p_level' => 1,
                    'doctor_id' => $doctorId,
                    'org_name' => 'Hospital',
                    'opr_name' => 'Cashier',
                    'opr_id' => $userId,
                    'mars_passcode' => '',
                    'date' => Carbon::today(),
                    'ipd_no' => 0,
                    'time' => Carbon::now()->format('H:i:s'),
                    'admission_status' => 0,
                    'm_count' => 0,
                    'p_lang' => 1,
                    'global_euid' => 0,
                    'abha_add' => '',
                    'abha_mobile' => '',
                    'first_reg_charge' => 0.00,
                ]);
            }

            if (! $patient) {
                throw ValidationException::withMessages([
                    'patient_id' => ['Patient ID or valid walk-in details are required.'],
                ]);
            }

            // 2. Resolve or create visitlog
            $visitId = $data['visit_id'] ?? null;
            if (! $visitId) {
                $visit = VisitLog::create([
                    'patient_id' => $patient->id,
                    'doctor_id' => $doctorId,
                    'hospital_id' => $branchId,
                    'date' => Carbon::now(),
                    'source' => 1,
                    'pres_status' => 0,
                    'fud_status' => 0,
                    'payment_status' => 1,
                    'opd_visit_type' => 1,
                    'edit_lock' => 0,
                    'ch' => 0.00,
                    'tpa_pay' => 0.00,
                    'is_active' => 1,
                    'cash_pay' => 0.00,
                    'card_pay' => 0.00,
                    'online_pay' => 0.00,
                    'is_template' => 0,
                    'social_media_dis_bool' => 0,
                    'queue_status' => 0,
                    'ready_to_upload' => 0,
                    'upload_confirm_status' => 0,
                    'last_upload_call_status' => '',
                    'feed_back_sms_done' => 1,
                    'is_visited' => 1,
                    'on_hold' => 0,
                    'emergency' => $priority === 'stat' ? 1 : 0,
                    'er_status' => 0,
                    'abdm_enabled' => 0,
                    'ref_by' => $refDoctor,
                    'is_cancelled' => 0,
                    'is_base' => 0,
                    'created_by' => $userId,
                ]);
                $visitId = $visit->id;
            }

            // 3. Generate locked bill number
            $fy = $this->billNumberGenerator->currentFinancialYear();
            $billSeqNumber = $this->billNumberGenerator->nextNumber('LB', $fy);

            // 4. Server-side calculate item prices, component expansions, discounts
            $subtotal = 0.00;
            $totalItemDiscount = 0.00;
            $lineItems = [];

            if (empty($data['items'])) {
                throw ValidationException::withMessages([
                    'items' => ['At least one test or package must be selected.'],
                ]);
            }

            foreach ($data['items'] as $rawItem) {
                $serviceId = (int) $rawItem['service_id'];

                // Prioritize explicit rate/charge billed to the patient
                if (isset($rawItem['charge']) && is_numeric($rawItem['charge'])) {
                    $unitCharge = round((float) $rawItem['charge'], 2);
                } elseif (isset($rawItem['price']) && is_numeric($rawItem['price'])) {
                    $unitCharge = round((float) $rawItem['price'], 2);
                } else {
                    $unitCharge = $this->priceResolver->resolve($serviceId, $payerCategory, null, $branchId);
                }

                $qty = isset($rawItem['qty']) && is_numeric($rawItem['qty']) ? max(0.01, (float) $rawItem['qty']) : 1.00;
                $lineTotal = round($unitCharge * $qty, 2);

                $discountAmt = isset($rawItem['discount']) ? round((float) $rawItem['discount'], 2) : 0.00;
                if ($discountAmt > $lineTotal) {
                    $discountAmt = $lineTotal;
                }

                $subtotal += $lineTotal;
                $totalItemDiscount += $discountAmt;

                $lineItems[] = [
                    'service_id' => $serviceId,
                    'charge' => $unitCharge,
                    'qty' => $qty,
                    'discount' => $discountAmt,
                    'discount_per' => isset($rawItem['discount_per']) ? (float) $rawItem['discount_per'] : 0.00,
                    'outsource_lab_id' => $rawItem['outsource_lab_id'] ?? null,
                ];
            }

            $billDiscount = isset($data['bill_discount']) ? round((float) $data['bill_discount'], 2) : 0.00;
            $totalDiscount = $totalItemDiscount + $billDiscount;
            if ($totalDiscount > $subtotal) {
                $totalDiscount = $subtotal;
            }

            $netPayable = round($subtotal - $totalDiscount, 2);

            // 5. Create legacy patient_billing header
            $legacyBilling = PatientBilling::create([
                'bill_number' => $billSeqNumber,
                'visit_id' => $visitId,
                'status' => 1,
                'is_settled' => 1,
                'is_partially_settled' => 0,
                'discount' => $totalDiscount,
                'discount_per' => $subtotal > 0 ? round(($totalDiscount / $subtotal) * 100, 4) : 0.00,
                'discount_type' => $totalDiscount > 0 ? 'Manual' : null,
                'discount_name' => $data['discount_reason'] ?? null,
                'discount_auth_by' => $data['discount_approver_id'] ?? $userId,
                'is_open' => 0,
                'is_cancelled' => 0,
                'receipt_no' => $billSeqNumber,
                'remarks' => $data['clinical_notes'] ?? null,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            // 6. Create LabOrder entity
            $accessionNo = $this->accessionGenerator->generate();
            $order = LabOrder::create([
                'visit_id' => $visitId,
                'patient_id' => $patient->id,
                'bill_id' => $legacyBilling->id,
                'accession_no' => $accessionNo,
                'client_token' => $clientToken,
                'priority' => $priority,
                'status' => LabState::ORDERED,
                'ordered_by' => $userId,
                'branch_id' => $branchId,
                'clinical_notes' => $data['clinical_notes'] ?? null,
            ]);

            // 7. Create specimen container
            $customSampleNo = ! empty($data['custom_sample_no']) ? trim((string) $data['custom_sample_no']) : null;
            $specimenBarcode = $this->accessionGenerator->generateBarcode('blood', null, $customSampleNo);
            $specimen = Specimen::create([
                'lab_order_id' => $order->id,
                'barcode' => $specimenBarcode,
                'specimen_type' => 'blood',
                'container' => 'edta_tube',
                'condition' => 'normal',
            ]);

            // 8. Create legacy patient_gen_bill rows, lab_investigations, and lab_order_items
            foreach ($lineItems as $item) {
                $legacyGenBill = PatientGenBill::create([
                    'bill_id' => $legacyBilling->id,
                    'service_id' => $item['service_id'],
                    'charges' => $item['charge'],
                    'qty' => $item['qty'],
                    'discount' => $item['discount'],
                    'discount_per' => $item['discount_per'],
                    'service_status' => 'booked',
                    'is_settled' => 1,
                    'doctor_id' => $doctorId,
                    'i_cover' => 0,
                    'outsource_lab_id' => $item['outsource_lab_id'],
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);

                // Legacy lab_investigations (1:1 with patient_gen_bill.id)
                LabInvestigation::create([
                    'lab_order_id' => $legacyGenBill->id,
                    'status' => 'collected',
                    'approval_status' => 0,
                    'sample_type' => 'blood',
                    'sample_condition' => 'normal',
                    'is_outsource' => $item['outsource_lab_id'] ? 1 : 0,
                    'is_printed' => 0,
                    'in_process' => 0,
                    'test_results' => json_encode([]),
                ]);

                // Domain LabOrderItem
                $orderItem = LabOrderItem::create([
                    'lab_order_id' => $order->id,
                    'service_id' => $item['service_id'],
                    'gen_bill_id' => $legacyGenBill->id,
                    'status' => LabState::ORDERED,
                    'tat_due_at' => Carbon::now()->addHours(4), // Default 4hr routine TAT
                    'is_outsource' => ! empty($item['outsource_lab_id']),
                    'outsource_lab_id' => $item['outsource_lab_id'],
                    'row_version' => 1,
                ]);

                // Link specimen to order item
                $specimen->items()->attach($orderItem->id);
            }

            // 9. Record payments in legacy billing_payment_type
            $payments = $data['payments'] ?? [];
            $totalPaid = 0.00;

            foreach ($payments as $pay) {
                $payAmt = isset($pay['amount']) ? round((float) $pay['amount'], 2) : 0.00;
                if ($payAmt > 0) {
                    $totalPaid += $payAmt;
                    BillingPaymentType::create([
                        'bill_id' => $legacyBilling->id,
                        'amount' => $payAmt,
                        'payment_mode' => strtolower((string) ($pay['payment_mode'] ?? $pay['mode'] ?? 'cash')),
                        'transaction_type' => 0,
                        'transaction_id' => $pay['transaction_id'] ?? null,
                        'remarks' => $pay['remarks'] ?? null,
                        'payment_date' => Carbon::now(),
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ]);
                }
            }

            // Sync settlement status on legacy patient_billing
            $isSettled = ($netPayable <= 0 || $totalPaid >= $netPayable) ? 1 : 0;
            $isPartiallySettled = ($totalPaid > 0 && $totalPaid < $netPayable) ? 1 : 0;
            $legacyBilling->update([
                'is_settled' => $isSettled,
                'is_partially_settled' => $isPartiallySettled,
            ]);

            // 10. Record discount approval if discount given
            if ($totalDiscount > 0) {
                LabDiscountApproval::create([
                    'bill_id' => $legacyBilling->id,
                    'order_id' => $order->id,
                    'discount_amount' => $totalDiscount,
                    'discount_percent' => $subtotal > 0 ? round(($totalDiscount / $subtotal) * 100, 2) : 0.00,
                    'reason' => $data['discount_reason'] ?? 'Standard discount applied at billing',
                    'requested_by' => $userId,
                    'approved_by' => $data['discount_approver_id'] ?? $userId,
                    'status' => 'approved',
                    'approved_at' => Carbon::now(),
                ]);
            }

            // 11. Write audit log
            LabOrderEvent::create([
                'order_id' => $order->id,
                'from_state' => null,
                'to_state' => LabState::ORDERED->value,
                'user_id' => $userId,
                'meta' => [
                    'bill_id' => $legacyBilling->id,
                    'bill_number' => $billSeqNumber,
                    'subtotal' => $subtotal,
                    'discount' => $totalDiscount,
                    'net_payable' => $netPayable,
                    'items_count' => count($lineItems),
                ],
            ]);

            // 12. Dispatch event after commit
            DB::afterCommit(function () use ($order, $legacyBilling) {
                event(new LabBillCreated($order, $legacyBilling));
            });

            return $order->load(['bill', 'items.service', 'specimens', 'patient']);
        });
    }
}
