<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use App\Models\admin\PatientBilling;
use App\Models\admin\PatientGenBill;
use App\Models\admin\BillingPaymentType;
use App\Models\RefUsers;
use App\Helpers\HospitalHelper;
use App\Cache\DoctorCache;
use App\Cache\InsuranceCache;
use App\Cache\HospitalServiceCache;

class BillingController extends Controller
{
    /**
     * V2 OPD Billing Main Interface
     * Supports Tab 1: New Bill & Tab 2: All Bills
     */
    public function index(Request $request)
    {
        $org_id = Auth::user()->org_id;

        // Fetch active doctors for hospital from Redis cache
        $hospitalDoctors = DoctorCache::all($org_id);

        $doctors = $hospitalDoctors->map(function ($d) {
            $specName = $d->spec_name ?: 'General';
            return [
                'id' => (string) $d->id,
                'name' => $d->name,
                'spec' => $specName,
                'specialization' => $specName,
            ];
        })->values();

        // Extract unique specializations
        $specs = $doctors->pluck('specialization')
            ->filter()
            ->unique()
            ->values()
            ->all();

        // Insurance & Patient Categories from Redis cache
        $categories = InsuranceCache::all($org_id)
            ->pluck('insurance_agen', 'id')
            ->all();

      

        // Cached OPD Service Catalog
        $opdCatalog = HospitalServiceCache::getOpdCatalog($org_id);

        $user = Auth::user();
        $isRefundApprovalEnabled = \App\Helpers\HospitalHelper::isSettingEnabled($org_id, 'refund_approval_system');
        $isAdmin = (bool) ($user->hasRole('Admin') || $user->hasRole('Super Admin') || (int)($user->user_type ?? 0) === 1 || $user->can('module-permission', ['refund', 'can_update']));

        $isRefByEnabled = HospitalHelper::isSettingEnabled($org_id, 'refered_by_in_visit');
        $refUsers = $isRefByEnabled
            ? RefUsers::where('hospital_id', $org_id)->where('status', 1)->orderBy('name', 'ASC')->get(['id', 'name', 'mobile_no'])
            : collect();

        $initialData = [
            'doctors' => $doctors,
            'specs' => $specs,
            'categories' => $categories,
            'services' => $opdCatalog,
            'ref_users' => $refUsers,
            'is_ref_by_enabled' => (bool)$isRefByEnabled,
            'consult_service_id' => \App\Models\admin\HospitalSer::where('flag', 'consultation_charge')->where('active', 1)->value('id') ?? \App\Models\admin\HospitalSer::where('flag', 'consultation_charge')->value('id') ?? \App\Models\admin\HospitalSer::where('flag', 'doctor_consultation')->value('id'),
            'reg_service_id' => \App\Models\admin\HospitalSer::where('flag', 'registration_charge')->where('active', 1)->value('id') ?? \App\Models\admin\HospitalSer::where('flag', 'registration_charge')->value('id'),
            'user_name' => $user->name ?: (trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: ''),
            'refund_approval_system' => $isRefundApprovalEnabled,
            'is_admin' => $isAdmin,
        ];

        $userPermissions = [
            'is_admin' => $isAdmin,
            'add_referred_by' => [
                'can_create' => (bool) $user->can('module-permission', ['add_referred_by', 'can_create']),
            ],
            'opd_billing' => [
                'can_create' => (bool) $user->can('module-permission', ['opd_billing', 'can_create']),
                'can_read'   => (bool) $user->can('module-permission', ['opd_billing', 'can_read']),
                'can_update' => (bool) $user->can('module-permission', ['opd_billing', 'can_update']),
            ],
            'bill_cancel' => [
                'can_update' => (bool) $user->can('module-permission', ['bill_cancel', 'can_update']),
            ],
            'refund' => [
                'can_create' => (bool) $user->can('module-permission', ['refund', 'can_create']),
                'can_read'   => (bool) $user->can('module-permission', ['refund', 'can_read']),
                'can_update' => (bool) $user->can('module-permission', ['refund', 'can_update']),
            ],
            'opd_discount' => [
                'can_update' => (bool) $user->can('module-permission', ['opd_discount', 'can_update']),
            ],
            'opd_visits' => [
                'can_create' => (bool) $user->can('module-permission', ['opd_visits', 'can_create']),
                'can_read'   => (bool) $user->can('module-permission', ['opd_visits', 'can_read']),
            ],
        ];

        return view('v2.billing.index', compact('initialData', 'userPermissions'));
    }

    /**
     * AJAX query for Tab 2: All Bills list with server-side filtering, summary stats and pagination
     */
    public function listBills(Request $request)
    {
        $org_id = Auth::user()->org_id;

        $no = trim($request->input('bill_no', $request->input('no', '')));
        $pt = trim($request->input('search', $request->input('pt', '')));
        $from = $request->input('from_date', $request->input('from'));
        $to = $request->input('to_date', $request->input('to'));
        $status = $request->input('status', 'all'); // 'all', 'paid', 'due'
        $page = max(1, (int)$request->input('page', 1));
        $size = min(50, max(5, (int)$request->input('per_page', $request->input('size', 10))));

        $query = PatientBilling::query()
            ->join('visitlog', 'visitlog.id', '=', 'patient_billing.visit_id')
            ->join('pi', 'pi.id', '=', 'visitlog.patient_id')
            ->leftJoin('doctor_db', 'doctor_db.id', '=', 'visitlog.doctor_id')
            ->leftJoin('health_insurance_agen', 'health_insurance_agen.id', '=', 'pi.i_cover')
            ->leftJoin('users', 'users.id', '=', 'patient_billing.created_by')
            ->where('visitlog.hospital_id', $org_id);

        // 1. Bill number search
        if (!empty($no)) {
            $digitsOnly = preg_replace('/\D/', '', $no);
            $query->where(function ($q) use ($no, $digitsOnly) {
                if (!empty($digitsOnly)) {
                    $q->where('patient_billing.id', $digitsOnly)
                      ->orWhere('patient_billing.receipt_no', $digitsOnly);
                }
            });
        }

        // 2. Patient search (UHID, name, mobile)
        if (!empty($pt)) {
            $digitsPt = preg_replace('/\D/', '', $pt);
            $query->where(function ($q) use ($pt, $digitsPt) {
                if (!empty($digitsPt) && strlen($digitsPt) >= 3) {
                    $q->where('pi.mobile_no', 'LIKE', '%' . $digitsPt . '%')
                      ->orWhere('pi.id', 'LIKE', '%' . $digitsPt . '%');
                }
                $q->orWhereRaw("CONCAT(TRIM(pi.Fname), ' ', TRIM(COALESCE(pi.Lname, ''))) LIKE ?", ['%' . $pt . '%']);
            });
        }

        // 3. Date range filter
        if (!empty($from)) {
            $query->whereDate('patient_billing.created_at', '>=', $from);
        }
        if (!empty($to)) {
            $query->whereDate('patient_billing.created_at', '<=', $to);
        }

        // Subquery: Payments total per bill
        $query->leftJoin(
            DB::raw('(
                SELECT
                    bill_id,
                    SUM(COALESCE(amount, 0)) AS total_paid
                FROM billing_payment_type
                WHERE (is_deleted = 0 OR is_deleted IS NULL)
                GROUP BY bill_id
            ) as bpt_totals'),
            'bpt_totals.bill_id',
            '=',
            'patient_billing.id'
        );

        // Subquery: Items total per bill
        $query->leftJoin(
            DB::raw('(
                SELECT
                    bill_id,
                    SUM(COALESCE((charges * qty) - discount, 0)) AS items_subtotal,
                    SUM(COALESCE(discount, 0)) AS items_discount
                FROM patient_gen_bill
                WHERE (is_cancelled != 1 OR is_cancelled IS NULL)
                GROUP BY bill_id
            ) as pgb_totals'),
            'pgb_totals.bill_id',
            '=',
            'patient_billing.id'
        );

        $query->select([
            'patient_billing.id as bill_id',
            'patient_billing.id as bill_number',
            'patient_billing.is_cancelled',
            'patient_billing.receipt_no',
            'patient_billing.created_at as bill_date',
            'patient_billing.discount as bill_discount',
            'patient_billing.discount_type',
            'patient_billing.remarks',
            'visitlog.id as visit_id',
            'visitlog.doctor_id',
            'doctor_db.name as doctor_name',
            'pi.id as patient_id',
            'pi.prefix as patient_title',
            'pi.Fname as patient_first',
            'pi.Lname as patient_last',
            'pi.age as patient_age',
            'pi.dob as patient_dob',
            'pi.sex as patient_gender',
            'pi.mobile_no as patient_mobile',
            'pi.i_cover as cat_id',
            'health_insurance_agen.insurance_agen as cat_name',
            'users.name as created_by_name',
            'patient_billing.is_settled',
            'patient_billing.is_locked',
            DB::raw('COALESCE(pgb_totals.items_subtotal, 0) as calc_subtotal'),
            DB::raw('COALESCE(pgb_totals.items_discount, 0) as calc_item_discount'),
            DB::raw('COALESCE(bpt_totals.total_paid, 0) as total_paid')
        ]);

        $allRows = $query->orderBy('patient_billing.id', 'DESC')->get();

        // Calculate payable, paid, due and apply status filter
        $mapped = $allRows->map(function ($row) {
            $isCancelled = (int) ($row->is_cancelled ?? 0) === 1;
            $subtotal = (float) $row->calc_subtotal;
            $billDiscount = (float) ($row->bill_discount ?: 0);
            $itemDiscount = (float) ($row->calc_item_discount ?: 0);
            $totalDiscount = $billDiscount + $itemDiscount;
            
            $billed = max(0, $subtotal - $billDiscount);
            $paid = (float) $row->total_paid;
            $due = $isCancelled ? 0 : max(0, round($billed - $paid, 2));

            $pName = trim(($row->patient_title ? $row->patient_title . ' ' : '') . $row->patient_first . ' ' . ($row->patient_last ?: ''));
            $rawDoc = trim($row->doctor_name ?: '');
            if ($rawDoc && $rawDoc !== '—' && $rawDoc !== '-') {
                $cleanDoc = trim(preg_replace('/^(dr\.?\s*)+/i', '', $rawDoc));
                $docName = $cleanDoc ? 'Dr. ' . $cleanDoc : '—';
            } else {
                $docName = '—';
            }

            $age = $row->patient_age ?: '';
            if (!empty($row->patient_dob)) {
                try {
                    $age = Carbon::parse($row->patient_dob)->age . ' yrs';
                } catch (\Throwable $e) {}
            } elseif (!empty($age)) {
                $age = $age . ' yrs';
            }

            return [
                'id' => (int) $row->bill_id,
                'no' => (string) $row->bill_id,
                'bill_number' => (string) $row->bill_id,
                'is_cancelled' => $isCancelled ? 1 : 0,
                'is_settled' => (int) ($row->is_settled ?? 0),
                'is_locked' => (int) ($row->is_locked ?? 0),
                'date' => Carbon::parse($row->bill_date)->format('d-m-Y, g:i A'),
                'created_at' => $row->bill_date,
                'patient_id' => $row->patient_id,
                'uhid' => (string) $row->patient_id,
                'patient_name' => $pName,
                'age' => $age,
                'gender' => $row->patient_gender ?: 'Male',
                'mobile' => $row->patient_mobile ?: '',
                'doctor' => $docName,
                'category' => $row->cat_name ?: 'General',
                'subtotal' => $subtotal,
                'discount' => $totalDiscount,
                'bill_discount' => $billDiscount,
                'item_discount' => $itemDiscount,
                'billed' => $billed,
                'payable' => $billed,
                'paid' => $paid,
                'due' => $due,
                'is_paid' => !$isCancelled && $due <= 0,
                'is_due' => !$isCancelled && $due > 0,
                'created_by' => $row->created_by_name ?: '',
                'remarks' => $row->remarks ?: ''
            ];
        });

        // Filter by payment status
        if ($status === 'paid') {
            $mapped = $mapped->filter(fn($r) => !$r['is_cancelled'] && $r['due'] <= 0)->values();
        } elseif ($status === 'due') {
            $mapped = $mapped->filter(fn($r) => !$r['is_cancelled'] && $r['due'] > 0)->values();
        }

        // Summary Aggregates for All Bills strip
        $activeBills = $mapped->filter(fn($r) => !$r['is_cancelled']);
        $summary = [
            'total_count' => $mapped->count(),
            'subtotal' => round($activeBills->sum('subtotal'), 2),
            'discount' => round($activeBills->sum('discount'), 2),
            'billed' => round($activeBills->sum('billed'), 2),
            'paid' => round($activeBills->sum('paid'), 2),
            'due' => round($activeBills->sum('due'), 2),
        ];

        // Pagination slice
        $totalRecords = $mapped->count();
        $totalPages = (int) ceil($totalRecords / $size);
        $offset = ($page - 1) * $size;
        $pagedRows = $mapped->slice($offset, $size)->values();

        // For the paginated rows, fetch all payment/refund ledger movements (Direct, Settlements, Refunds)
        $billIds = $pagedRows->pluck('id')->filter()->values()->all();
        $movementsByBill = [];
        $refundedByBill = [];
        $refundBillIds = [];
        $referencedRefundBillIds = [];

        if (!empty($billIds)) {
            // Check if any of these bills are refund transaction bills themselves
            $refundBillIds = DB::table('refund_remarks')
                ->whereIn('bill_id', $billIds)
                ->where('status', 'issued')
                ->pluck('bill_id')
                ->map(fn($v) => (int)$v)
                ->all();
            // 1. Direct Collections on bill (transaction_type = 0, amount > 0)
            $directPayments = DB::table('billing_payment_type as bpt')
                ->leftJoin('users as u', 'u.id', '=', 'bpt.created_by')
                ->whereIn('bpt.bill_id', $billIds)
                ->where(function ($q) {
                    $q->where('bpt.is_deleted', 0)->orWhereNull('bpt.is_deleted');
                })
                ->where('bpt.transaction_type', 0)
                ->where('bpt.amount', '>', 0)
                ->select([
                    'bpt.id as payment_id',
                    'bpt.bill_id',
                    'bpt.amount',
                    'bpt.payment_mode',
                    'bpt.transaction_type',
                    'bpt.transaction_id',
                    'bpt.remarks',
                    'bpt.created_at',
                    DB::raw("CONCAT(TRIM(COALESCE(u.first_name, '')), ' ', TRIM(COALESCE(u.last_name, ''))) as staff_name")
                ])
                ->get();

            foreach ($directPayments as $dp) {
                $bId = (int) $dp->bill_id;
                $amt = (float) $dp->amount;
                if ($amt <= 0) continue;

                $dt = Carbon::parse($dp->created_at);
                $movementsByBill[$bId][] = [
                    'id' => (int) $dp->payment_id,
                    'receipt_no' => $dp->payment_id,
                    'amount' => $amt,
                    'mode' => ucfirst(str_replace('_', ' ', $dp->payment_mode ?: '')),
                    'txn' => $dp->transaction_id ?: '',
                    'date' => $dt->format('d-m-Y'),
                    'time' => $dt->format('g:i A'),
                    'at' => $dp->created_at,
                    'by' => trim($dp->staff_name) ?: '',
                    'out' => false,
                    'event_type' => 'PAYMENT'
                ];
            }

            // 2. Settlement Payments via bill_reference
            $settlements = DB::table('bill_reference as br')
                ->join('billing_payment_type as bpt', 'bpt.bill_id', '=', 'br.ref_bill')
                ->leftJoin('users as u', 'u.id', '=', 'bpt.created_by')
                ->whereIn('br.bill_id', $billIds)
                ->where(function ($q) {
                    $q->where('bpt.is_deleted', 0)->orWhereNull('bpt.is_deleted');
                })
                ->where('bpt.amount', '>', 0)
                ->select([
                    'br.bill_id',
                    'bpt.id as payment_id',
                    'bpt.amount',
                    'bpt.payment_mode',
                    'bpt.transaction_type',
                    'bpt.transaction_id',
                    'bpt.remarks',
                    'bpt.created_at',
                    DB::raw("CONCAT(TRIM(COALESCE(u.first_name, '')), ' ', TRIM(COALESCE(u.last_name, ''))) as staff_name")
                ])
                ->get();

            foreach ($settlements as $st) {
                $bId = (int) $st->bill_id;
                $amt = (float) $st->amount;
                if ($amt <= 0) continue;

                $dt = Carbon::parse($st->created_at);
                $movementsByBill[$bId][] = [
                    'id' => (int) $st->payment_id,
                    'receipt_no' =>  $st->payment_id,
                    'amount' => $amt,
                    'mode' => ucfirst(str_replace('_', ' ', $st->payment_mode ?: '')),
                    'txn' => $st->transaction_id ?: '',
                    'date' => $dt->format('d-m-Y'),
                    'time' => $dt->format('g:i A'),
                    'at' => $st->created_at,
                    'by' => trim($st->staff_name) ?: '',
                    'out' => false,
                    'event_type' => 'SETTLEMENT'
                ];
            }

            // 3. Direct refunds on the bill (transaction_type = 1: debit / refund)
            $directRefunds = DB::table('billing_payment_type as bpt')
                ->leftJoin('users as u', 'u.id', '=', 'bpt.created_by')
                ->whereIn('bpt.bill_id', $billIds)
                ->where(function ($q) {
                    $q->where('bpt.is_deleted', 0)->orWhereNull('bpt.is_deleted');
                })
                ->where(function ($q) {
                    $q->where('bpt.transaction_type', 1)
                      ->orWhere('bpt.amount', '<', 0);
                })
                ->select([
                    'bpt.id as payment_id',
                    'bpt.bill_id',
                    'bpt.amount',
                    'bpt.payment_mode',
                    'bpt.transaction_type',
                    'bpt.transaction_id',
                    'bpt.remarks',
                    'bpt.created_at',
                    DB::raw("CONCAT(TRIM(COALESCE(u.first_name, '')), ' ', TRIM(COALESCE(u.last_name, ''))) as staff_name")
                ])
                ->get();

            foreach ($directRefunds as $dr) {
                $bId = (int) $dr->bill_id;
                $amt = abs((float) $dr->amount);
                if ($amt <= 0) continue;

                $dt = Carbon::parse($dr->created_at);
                $movementsByBill[$bId][] = [
                    'id' => (int) $dr->payment_id,
                    'receipt_no' => $dr->payment_id,
                    'amount' => $amt,
                    'mode' => ucfirst(str_replace('_', ' ', $dr->payment_mode ?: '')),
                    'txn' => $dr->transaction_id ?: '',
                    'date' => $dt->format('d-m-Y'),
                    'time' => $dt->format('g:i A'),
                    'at' => $dr->created_at,
                    'by' => trim($dr->staff_name) ?: '',
                    'out' => true,
                    'event_type' => 'REFUND'
                ];
            }

            // 4. Refunds via refund_remarks (linked to original bill via bill_ref_id)
            $refundRemarks = DB::table('refund_remarks as rr')
                ->join('billing_payment_type as bpt', 'bpt.bill_id', '=', 'rr.bill_id')
                ->leftJoin('users as u', 'u.id', '=', 'bpt.created_by')
                ->where('rr.status', 'issued')
                ->where(function ($q) {
                    $q->where('bpt.is_deleted', 0)->orWhereNull('bpt.is_deleted');
                })
                ->where('bpt.transaction_type', 1)
                ->select([
                    'rr.bill_ref_id',
                    'bpt.id as payment_id',
                    'bpt.amount',
                    'bpt.payment_mode',
                    'bpt.transaction_type',
                    'bpt.transaction_id',
                    'bpt.remarks',
                    'bpt.created_at',
                    DB::raw("CONCAT(TRIM(COALESCE(u.first_name, '')), ' ', TRIM(COALESCE(u.last_name, ''))) as staff_name")
                ])
                ->get();

            foreach ($refundRemarks as $rf) {
                $refIds = json_decode($rf->bill_ref_id, true) ?: [];
                foreach ($refIds as $refBillId) {
                    $bId = (int) $refBillId;
                    if (in_array($bId, $billIds)) {
                        $referencedRefundBillIds[] = $bId;
                        $amt = abs((float) $rf->amount);
                        if ($amt <= 0) continue;

                        $dt = Carbon::parse($rf->created_at);
                        $movementsByBill[$bId][] = [
                            'id' => (int) $rf->payment_id,
                            'receipt_no' =>  $rf->payment_id,
                            'amount' => $amt,
                            'mode' => ucfirst(str_replace('_', ' ', $rf->payment_mode ?: '')),
                            'txn' => $rf->transaction_id ?: '',
                            'date' => $dt->format('d-m-Y'),
                            'time' => $dt->format('g:i A'),
                            'at' => $rf->created_at,
                            'by' => trim($rf->staff_name) ?: '',
                            'out' => true,
                            'event_type' => 'REFUND'
                        ];
                    }
                }
            }
        }

        // Attach movements to paged rows, sort chronologically, and eliminate duplicate events
        $pagedRows = $pagedRows->map(function ($r) use ($movementsByBill, $refundBillIds, $referencedRefundBillIds) {
            $bId = $r['id'];
            $moves = $movementsByBill[$bId] ?? [];

            $uniqueMoves = [];
            $seen = [];
            foreach ($moves as $m) {
                $key = $m['event_type'] . '_' . $m['id'];
                if (!isset($seen[$key])) {
                    $seen[$key] = true;
                    $uniqueMoves[] = $m;
                }
            }

            usort($uniqueMoves, function ($a, $b) {
                return strtotime($a['at']) <=> strtotime($b['at']);
            });

            // Refund total is dynamically summed directly from unique outbound refund movements
            $rfAmt = (float) collect($uniqueMoves)->where('out', true)->sum('amount');
            $isRefundBill = in_array($bId, $refundBillIds);
            $hasRefunds = ($rfAmt > 0) || in_array($bId, $referencedRefundBillIds) || $isRefundBill;

            $r['movements'] = $uniqueMoves;
            $r['refunded'] = $rfAmt;
            $r['is_refund_bill'] = $isRefundBill ? 1 : 0;
            $r['is_refunded'] = $isRefundBill ? 1 : 0;
            $r['has_refunds'] = $hasRefunds ? 1 : 0;
            return $r;
        });

        return response()->json([
            'status' => 'success',
            'success' => true,
            'summary' => $summary,
            'total' => $totalRecords,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $size,
                'total_records' => $totalRecords,
                'total_pages' => $totalPages,
                'start' => $totalRecords > 0 ? $offset + 1 : 0,
                'end' => min($offset + $size, $totalRecords)
            ],
            'bills' => $pagedRows
        ]);
    }

    /**
     * Fetch complete bill details for viewing receipt document / collecting due
     */
    public function billDetails(Request $request, $id)
    {
        $org_id = Auth::user()->org_id;

        $bill = PatientBilling::join('visitlog', 'visitlog.id', '=', 'patient_billing.visit_id')
            ->join('pi', 'pi.id', '=', 'visitlog.patient_id')
            ->leftJoin('doctor_db', 'doctor_db.id', '=', 'visitlog.doctor_id')
            ->leftJoin('doctor_specialities', 'doctor_specialities.id', '=', 'doctor_db.key_spec')
            ->leftJoin('health_insurance_agen', 'health_insurance_agen.id', '=', 'pi.i_cover')
            ->leftJoin('ref_users', 'ref_users.id', '=', 'visitlog.ref_by')
            ->leftJoin('users', 'users.id', '=', 'patient_billing.created_by')
            ->where('patient_billing.id', $id)
            ->where('visitlog.hospital_id', $org_id)
            ->select([
                'patient_billing.*',
                'visitlog.doctor_id as doctor_id',
                'visitlog.ref_by as ref_by',
                'ref_users.name as ref_doctor_name',
                'doctor_db.name as doctor_name',
                'doctor_specialities.name as doctor_spec',
                'pi.id as patient_id',
                'pi.prefix as patient_title',
                'pi.Fname as patient_first',
                'pi.Lname as patient_last',
                'pi.age as patient_age',
                'pi.dob as patient_dob',
                'pi.sex as patient_gender',
                'pi.mobile_no as patient_mobile',
                'pi.abha_number as patient_abha',
                'pi.i_cover as cat_id',
                'health_insurance_agen.insurance_agen as cat_name',
                'users.name as created_by_name'
            ])
            ->first();

        if (!$bill) {
            return response()->json(['status' => 'error', 'success' => false, 'message' => 'Bill not found.'], 404);
        }

        // Line items
        $items = PatientGenBill::join('hospital_services', 'hospital_services.id', '=', 'patient_gen_bill.service_id')
            ->leftJoin('hospital_service_charges', function ($join) use ($org_id, $bill) {
                $join->on('hospital_service_charges.service', '=', 'patient_gen_bill.service_id')
                    ->where('hospital_service_charges.hospital_id', '=', $org_id)
                    ->where('hospital_service_charges.insurance', '=', $bill->i_cover);
            })
            ->where('patient_gen_bill.bill_id', $bill->id)
            ->where(function ($q) {
                $q->where('patient_gen_bill.is_cancelled', '!=', 1)->orWhereNull('patient_gen_bill.is_cancelled');
            })
            ->select([
                'patient_gen_bill.*',
                'hospital_services.service as service_name',
                'hospital_services.short_code',
                'hospital_services.flag',
                'hospital_service_charges.is_editable'
            ])
            ->get()
            ->map(function ($it) {
                $rate = (float) $it->charges;
                $qty = (float) ($it->qty ?: 1);
                $disc = (float) ($it->discount ?: 0);
                $amt = round(($rate * $qty) - $disc, 2);
                return [
                    'id' => (int) $it->service_id,
                    'service_id' => (int) $it->service_id,
                    'primary_id' => (int) $it->id,
                    'flag' => $it->flag,
                    'name' => $it->service_name,
                    'service_name' => $it->service_name,
                    'what' => $it->service_name,
                    'sub' => $it->short_code ?: '',
                    'rate' => $rate,
                    'charges' => $rate,
                    'qty' => $qty,
                    'disc' => $disc,
                    'discount' => $disc,
                    'amount' => $amt,
                    'is_editable' => (int) ($it->is_editable ?? 0),
                ];
            });

        // Payments ledger
        $payments = BillingPaymentType::leftJoin('users', 'users.id', '=', 'billing_payment_type.created_by')
            ->where('billing_payment_type.bill_id', $bill->id)
            ->where(function ($q) {
                $q->where('billing_payment_type.is_deleted', 0)->orWhereNull('billing_payment_type.is_deleted');
            })
            ->select([
                'billing_payment_type.*',
                'users.name as staff_name'
            ])
            ->orderBy('billing_payment_type.id', 'ASC')
            ->get()
            ->map(function ($p) {
                return [
                    'id' => $p->id,
                    'mode' => ucfirst($p->payment_mode ?: ''),
                    'amount' => (float) $p->amount,
                    'amt' => (float) $p->amount,
                    'txn' => $p->transaction_id ?: '',
                    'date' => Carbon::parse($p->created_at)->format('d-m-Y, g:i A'),
                    'at' => Carbon::parse($p->created_at)->format('d-m-Y, g:i A'),
                    'collected_by' => $p->staff_name ?: '',
                    'by' => $p->staff_name ?: '',
                ];
            });

        $subtotal = (float) $items->sum(fn($it) => $it['rate'] * $it['qty']);
        $itemDiscounts = (float) $items->sum('disc');
        $billDiscount = (float) ($bill->discount ?: 0);
        $totalDiscount = $itemDiscounts + $billDiscount;
        $payable = max(0, $subtotal - $billDiscount);
        $paid = (float) $payments->sum('amount');
        $due = max(0, round($payable - $paid, 2));

        $pName = trim(($bill->patient_title ? $bill->patient_title . ' ' : '') . $bill->patient_first . ' ' . ($bill->patient_last ?: ''));
        $age = $bill->patient_age ?: '';
        if (!empty($bill->patient_dob)) {
            try { $age = Carbon::parse($bill->patient_dob)->age . ' yrs'; } catch (\Throwable $e) {}
        } elseif (!empty($age)) {
            $age = $age . ' yrs';
        }

        $isCancelled = (int)($bill->is_cancelled ?? 0) === 1;

        $isRefundBill = DB::table('refund_remarks')
            ->where('bill_id', $bill->id)
            ->where('status', 'issued')
            ->exists();

        $hasReferencedRefunds = DB::table('refund_remarks')
            ->where(function($q) use ($bill) {
                $q->whereRaw('JSON_CONTAINS(bill_ref_id, ?)', [json_encode((string)$bill->id)])
                  ->orWhereRaw('JSON_CONTAINS(bill_ref_id, ?)', [json_encode((int)$bill->id)]);
            })
            ->where('status', 'issued')
            ->exists();

        $directRefundAmt = (float) DB::table('billing_payment_type')
            ->where('bill_id', $bill->id)
            ->where(function ($q) {
                $q->where('is_deleted', 0)->orWhereNull('is_deleted');
            })
            ->where(function ($q) {
                $q->where('transaction_type', 1)->orWhere('amount', '<', 0);
            })
            ->sum(DB::raw('ABS(amount)'));

        $linkedRefundAmt = (float) DB::table('refund_remarks as rr')
            ->join('billing_payment_type as bpt', 'bpt.bill_id', '=', 'rr.bill_id')
            ->where('rr.status', 'issued')
            ->where(function ($q) {
                $q->where('bpt.is_deleted', 0)->orWhereNull('bpt.is_deleted');
            })
            ->where('bpt.transaction_type', 1)
            ->where(function($q) use ($bill) {
                $q->whereRaw('JSON_CONTAINS(rr.bill_ref_id, ?)', [json_encode((string)$bill->id)])
                  ->orWhereRaw('JSON_CONTAINS(rr.bill_ref_id, ?)', [json_encode((int)$bill->id)]);
            })
            ->sum(DB::raw('ABS(bpt.amount)'));

        $totalRefunded = $directRefundAmt + $linkedRefundAmt;
        $hasRefunds = $isRefundBill || $hasReferencedRefunds || ($totalRefunded > 0);

        $billData = [
            'id' => (int)$bill->id,
            'no' => (string)$bill->id,
            'bill_number' => (string)$bill->id,
            'is_cancelled' => $isCancelled ? 1 : 0,
            'is_refund_bill' => $isRefundBill ? 1 : 0,
            'is_refunded' => $isRefundBill ? 1 : 0,
            'has_refunds' => $hasRefunds ? 1 : 0,
            'refunded' => $totalRefunded,
            'is_settled' => (int)($bill->is_settled ?? 0),
            'is_locked' => (int)($bill->is_locked ?? 0),
            'date' => Carbon::parse($bill->created_at)->format('d-m-Y, g:i A'),
            'time' => Carbon::parse($bill->created_at)->format('g:i A'),
            'doctor_id' => $bill->doctor_id,
            'visit_id' => $bill->visit_id,
            'doctor' => $bill->doctor_name ? ('Dr. ' . trim(preg_replace('/^(dr\.?\s*)+/i', '', $bill->doctor_name))) : '—',
            'doctor_name' => $bill->doctor_name ? ('Dr. ' . trim(preg_replace('/^(dr\.?\s*)+/i', '', $bill->doctor_name))) : '',
            'doctor_spec' => $bill->doctor_spec ?: '',
            'specialization' => $bill->doctor_spec ?: '',
            'category' => $bill->cat_name ?: 'General',
            'collected_by' => $bill->created_by_name ?: 'Front desk',
            'by' => $bill->created_by_name ?: 'Front desk',
            'remarks' => $bill->remarks ?: '',
            'patient' => [
                'id' => $bill->patient_id,
                'uhid' => (string)$bill->patient_id,
                'name' => $pName,
                'age' => $age,
                'gender' => $bill->patient_gender ?: 'Male',
                'mobile' => $bill->patient_mobile ?: '',
                'mobile_no' => $bill->patient_mobile ?: '',
                'abha' => $bill->patient_abha ?: '',
                'category' => $bill->cat_name ?: 'General',
            ],
            'items' => $items,
            'lines' => $items,
            'subtotal' => $subtotal,
            'item_discount' => $itemDiscounts,
            'bill_discount' => $billDiscount,
            'total_discount' => $totalDiscount,
            'discount' => $billDiscount,
            'billed' => $payable,
            'payable' => $payable,
            'total' => $payable,
            'paid' => $paid,
            'due' => $due,
            'payments' => $payments,
            'pays' => $payments,
        ];

        return response()->json($billData);
    }

    /**
     * Collect Due Amount on an existing bill
     */
    public function collectDue(Request $request)
    {
        $org_id = Auth::user()->org_id;

        $validator = Validator::make($request->all(), [
            'bill_id' => 'required|integer',
            'amount' => 'required|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'success' => false, 'message' => $validator->errors()->first()], 422);
        }

        $bill = PatientBilling::join('visitlog', 'visitlog.id', '=', 'patient_billing.visit_id')
            ->where('patient_billing.id', $request->bill_id)
            ->where('visitlog.hospital_id', $org_id)
            ->select('patient_billing.*')
            ->first();

        if (!$bill) {
            return response()->json(['status' => 'error', 'success' => false, 'message' => 'Bill not found.'], 404);
        }

        // Calculate current subtotal, discount, payable and paid
        $subtotal = (float) PatientGenBill::where('bill_id', $bill->id)
            ->where(function ($q) { $q->where('is_cancelled', '!=', 1)->orWhereNull('is_cancelled'); })
            ->sum(DB::raw('(charges * qty) - discount'));

        $billDiscount = (float) ($bill->discount ?: 0);
        $payable = max(0, $subtotal - $billDiscount);

        $currentPaid = (float) BillingPaymentType::where('bill_id', $bill->id)
            ->where(function ($q) { $q->where('is_deleted', 0)->orWhereNull('is_deleted'); })
            ->sum('amount');
        
        $currentDue = max(0, round($payable - $currentPaid, 2));

        $collectAmount = (float) $request->amount;
        if ($collectAmount > $currentDue + 0.01) {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => 'Amount exceeds the due balance of ₹' . number_format($currentDue, 2)
            ], 422);
        }

        $mode = $request->input('payment_mode', $request->input('mode', ''));
        $txn = $request->input('txn_id', $request->input('txn', ''));

        DB::beginTransaction();
        try {
            $payment = new BillingPaymentType();
            $payment->bill_id = $bill->id;
            $payment->payment_mode = strtolower($mode);
            $payment->amount = $collectAmount;
            $payment->transaction_id = $txn;
            $payment->created_by = Auth::id();
            $payment->created_at = Carbon::now();
            $payment->save();

            // Check if now fully settled
            $newPaid = $currentPaid + $collectAmount;
            $newDue = max(0, round($payable - $newPaid, 2));

            if ($newDue <= 0.01) {
                $bill->is_settled = 1;
                $bill->is_partially_settled = 0;
            } else {
                $bill->is_settled = 0;
                $bill->is_partially_settled = 1;
            }
            $bill->save();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'success' => true,
                'bill_no' => $bill->id,
                'message' => '₹' . number_format($collectAmount, 2) . ' collected successfully.',
                'new_due' => $newDue,
                'new_paid' => $newPaid,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'success' => false, 'message' => 'Payment collection failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update an existing payment receipt (Amount, Mode, Transaction ID, Reason)
     */
    public function updateReceipt(Request $request)
    {
        $user = Auth::user();
        $canOpdBilling = $user->can('module-permission', ['opd_billing', 'can_update']);
        $canRefund = $user->can('module-permission', ['refund', 'can_update']);

        if (!$canOpdBilling && !$canRefund) {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => 'You do not have permission to edit receipts. OPD billing or refund update permission is required.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'payment_id' => 'required|integer|exists:billing_payment_type,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_mode' => 'required|string',
            'transaction_id' => 'nullable|string|max:100',
            'remarks' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $org_id = $user->org_id;
        $payment = BillingPaymentType::find($request->payment_id);
        if (!$payment) {
            return response()->json(['status' => 'error', 'success' => false, 'message' => 'Payment receipt not found.'], 404);
        }

        $newAmount = round((float) $request->amount, 2);
        $isRefundReceipt = ($payment->transaction_type == 1 || (float)$payment->amount < 0 || DB::table('refund_remarks')->where('bill_id', $payment->bill_id)->exists());

        if ($isRefundReceipt) {
            // Refund receipt flow
            $refRemark = DB::table('refund_remarks')->where('bill_id', $payment->bill_id)->first();
            $origBillIds = [];
            if ($refRemark && !empty($refRemark->bill_ref_id)) {
                $origBillIds = json_decode($refRemark->bill_ref_id, true) ?: [];
            }
            if (empty($origBillIds)) {
                $origBillIds = [$payment->bill_id];
            }

            // Verify org access
            $hasAccess = DB::table('patient_billing')
                ->join('visitlog', 'visitlog.id', '=', 'patient_billing.visit_id')
                ->whereIn('patient_billing.id', array_merge([$payment->bill_id], $origBillIds))
                ->where('visitlog.hospital_id', $org_id)
                ->exists();

            if (!$hasAccess) {
                return response()->json(['status' => 'error', 'success' => false, 'message' => 'Associated bill or refund not found.'], 404);
            }

            // Total paid on original bills
            $totalPaidOnOrig = (float) DB::table('billing_payment_type')
                ->whereIn('bill_id', $origBillIds)
                ->where(function ($q) { $q->where('is_deleted', 0)->orWhereNull('is_deleted'); })
                ->where('transaction_type', 0)
                ->where('amount', '>', 0)
                ->sum('amount');

            // Other refunds on these bills
            $otherRefunds = (float) DB::table('billing_payment_type as bpt')
                ->where('bpt.id', '!=', $payment->id)
                ->where(function ($q) { $q->where('bpt.is_deleted', 0)->orWhereNull('bpt.is_deleted'); })
                ->where(function ($q) use ($origBillIds) {
                    $q->whereIn('bpt.bill_id', $origBillIds)
                      ->where(function($q2) { $q2->where('bpt.transaction_type', 1)->orWhere('bpt.amount', '<', 0); })
                      ->orWhereExists(function ($sub) use ($origBillIds) {
                          $sub->select(DB::raw(1))
                              ->from('refund_remarks as rr')
                              ->whereColumn('rr.bill_id', 'bpt.bill_id')
                              ->where(function ($w) use ($origBillIds) {
                                  foreach ($origBillIds as $obId) {
                                      $w->orWhereRaw('JSON_CONTAINS(rr.bill_ref_id, ?)', [json_encode((string)$obId)])
                                        ->orWhereRaw('JSON_CONTAINS(rr.bill_ref_id, ?)', [json_encode((int)$obId)]);
                                  }
                              });
                      });
                })
                ->sum(DB::raw('ABS(bpt.amount)'));

            if ($totalPaidOnOrig > 0 && ($otherRefunds + $newAmount) > ($totalPaidOnOrig + 0.05)) {
                return response()->json([
                    'status' => 'error',
                    'success' => false,
                    'message' => 'Total refund amount (₹' . number_format($otherRefunds + $newAmount, 2) . ') cannot exceed total amount collected of ₹' . number_format($totalPaidOnOrig, 2) . ' on the original bill(s).'
                ], 422);
            }

            $bill = PatientBilling::find($payment->bill_id);
        } else {
            // Standard collection receipt flow
            if (!$canOpdBilling) {
                return response()->json([
                    'status' => 'error',
                    'success' => false,
                    'message' => 'You do not have permission to edit payment collection receipts. OPD billing update permission is required.'
                ], 403);
            }

            $bill = PatientBilling::join('visitlog', 'visitlog.id', '=', 'patient_billing.visit_id')
                ->where('patient_billing.id', $payment->bill_id)
                ->where('visitlog.hospital_id', $org_id)
                ->select('patient_billing.*')
                ->first();

            if (!$bill) {
                return response()->json(['status' => 'error', 'success' => false, 'message' => 'Associated bill not found.'], 404);
            }

            // Check if bill is cancelled
            if ($bill->is_cancelled == 1) {
                return response()->json(['status' => 'error', 'success' => false, 'message' => 'Receipts for cancelled bills cannot be edited.'], 422);
            }

            // Check autoLock and settled/locked status
            $bill->autoLockIfExpired();
            if ($bill->is_locked == 1 || $bill->is_settled == 1) {
                return response()->json(['status' => 'error', 'success' => false, 'message' => 'This bill is locked or settled and its receipts cannot be edited.'], 400);
            }

            // Calculate maximum allowable payment for this bill
            $itemsSubtotal = (float) PatientGenBill::where('bill_id', $bill->id)
                ->where(function ($q) { $q->where('is_cancelled', '!=', 1)->orWhereNull('is_cancelled'); })
                ->sum(DB::raw('(charges * qty) - COALESCE(discount, 0)'));
            $billDiscount = (float) ($bill->discount ?: 0);
            $totalPayable = max(0, round($itemsSubtotal - $billDiscount, 2));

            $otherPayments = (float) BillingPaymentType::where('bill_id', $bill->id)
                ->where('id', '!=', $payment->id)
                ->where(function ($q) { $q->where('is_deleted', 0)->orWhereNull('is_deleted'); })
                ->where('transaction_type', 0)
                ->sum('amount');

            if (($otherPayments + $newAmount) > ($totalPayable + 0.05)) {
                return response()->json([
                    'status' => 'error',
                    'success' => false,
                    'message' => 'Total payments (₹' . number_format($otherPayments + $newAmount, 2) . ') cannot exceed the bill payable total of ₹' . number_format($totalPayable, 2) . '.'
                ], 422);
            }
        }

        try {
            DB::beginTransaction();

            // Normalize mode
            $mode = strtolower(trim($request->payment_mode));
            if ($mode === 'bank transfer' || $mode === 'bank_transfer') {
                $mode = 'neft_rtgs';
            }

            $payment->amount = ($payment->amount < 0) ? -$newAmount : $newAmount;
            $payment->payment_mode = $mode;
            $payment->transaction_id = ($mode === 'cash') ? null : trim($request->transaction_id ?: '');
            if ($request->filled('remarks')) {
                $payment->remarks = trim($request->remarks);
            }
            $payment->updated_by = $user->id;
            $payment->save();

            // If this is a refund with refund_remarks, update remarks on refund_remarks too
            if ($isRefundReceipt && $request->filled('remarks')) {
                DB::table('refund_remarks')
                    ->where('bill_id', $payment->bill_id)
                    ->update(['remarks' => trim($request->remarks), 'updated_at' => now()]);
            }

            DB::commit();

            $rcptPrefix = $isRefundReceipt ? 'RF-' : 'RC-';
            return response()->json([
                'status' => 'success',
                'success' => true,
                'message' => ($isRefundReceipt ? 'Refund receipt ' : 'Receipt ') . $rcptPrefix . $payment->id . ' updated successfully.',
                'bill_id' => $bill ? $bill->id : $payment->bill_id,
                'payment_id' => $payment->id,
                'amount' => $newAmount,
                'mode' => $mode,
                'is_refund' => $isRefundReceipt
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'success' => false, 'message' => 'Failed to update receipt: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get today's draft bill for a patient if one exists
     */
    public function patientDraftToday(Request $request, $patientId)
    {
        $org_id = Auth::user()->org_id;
        $today = Carbon::today();
        $doctorId = $request->query('doctor_id') ?: $request->doctor_id;

        $billQuery = PatientBilling::join('visitlog', 'visitlog.id', '=', 'patient_billing.visit_id')
            ->where('visitlog.hospital_id', $org_id)
            ->where(function ($q) use ($patientId) {
                $q->where('visitlog.patient_id', $patientId)
                  ->orWhere('visitlog.patient_id', preg_replace('/[^0-9]/', '', (string)$patientId));
            })
            ->whereDate('patient_billing.created_at', $today)
            ->where(function ($q) {
                $q->whereNull('patient_billing.status')
                  ->orWhere('patient_billing.status', 0);
            })
            ->where(function ($q) {
                $q->whereNull('patient_billing.is_cancelled')
                  ->orWhere('patient_billing.is_cancelled', 0);
            });

        if (!empty($doctorId)) {
            $billQuery->where('visitlog.doctor_id', $doctorId);
        }

        $bill = $billQuery->orderBy('patient_billing.id', 'desc')
            ->select('patient_billing.id')
            ->first();

        if (!$bill) {
            return response()->json(['success' => false, 'message' => 'No draft bill for today']);
        }

        return app(\App\Http\Controllers\RegistrationController::class)->getDraftBill($bill->id);
    }

    /**
     * List all refunds with filtering, summary stats and pagination
     */
    public function listRefunds(Request $request)
    {
        $user = Auth::user();
        if (!$user->can('module-permission', ['refund', 'can_read']) && !$user->can('module-permission', ['refund', 'can_create']) && !$user->can('module-permission', ['refund', 'can_update'])) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized. Refund permission required.'], 403);
        }

        $org_id = $user->org_id;

        $refundNo = trim($request->input('refund_no', $request->input('no', '')));
        $pt = trim($request->input('search', $request->input('pt', '')));
        $from = $request->input('from_date', $request->input('from'));
        $to = $request->input('to_date', $request->input('to'));
        $statusGroup = trim($request->input('status_group', $request->input('group', 'all')));
        $page = max(1, (int)$request->input('page', 1));
        $size = min(50, max(5, (int)$request->input('per_page', $request->input('size', 10))));

        $baseQuery = DB::table('refund_remarks')
            ->join('patient_billing', 'patient_billing.id', '=', 'refund_remarks.bill_id')
            ->join('visitlog', 'visitlog.id', '=', 'patient_billing.visit_id')
            ->join('pi', 'pi.id', '=', 'visitlog.patient_id')
            ->leftJoin('doctor_db', 'doctor_db.id', '=', 'visitlog.doctor_id')
            ->leftJoin('users as u_init', 'u_init.id', '=', 'refund_remarks.initiated_by')
            ->leftJoin('users as u_app', 'u_app.id', '=', 'refund_remarks.approved_by')
            ->leftJoin('users as u_iss', 'u_iss.id', '=', 'refund_remarks.issued_by')
            ->where('visitlog.hospital_id', $org_id);

        if (!empty($refundNo)) {
            $baseQuery->where('refund_remarks.id', 'like', "%{$refundNo}%");
        }

        if (!empty($pt)) {
            $digitsPt = preg_replace('/\D/', '', $pt);
            $baseQuery->where(function ($q) use ($pt, $digitsPt) {
                if (!empty($digitsPt) && strlen($digitsPt) >= 3) {
                    $q->where('pi.mobile_no', 'like', "%{$digitsPt}%")
                      ->orWhere('pi.id', 'like', "%{$digitsPt}%");
                }
                $q->orWhereRaw("CONCAT(TRIM(pi.Fname), ' ', TRIM(COALESCE(pi.Lname, ''))) like ?", ['%' . $pt . '%']);
            });
        }

        if (!empty($from)) {
            $baseQuery->whereDate('refund_remarks.created_at', '>=', Carbon::parse($from)->startOfDay());
        }
        if (!empty($to)) {
            $baseQuery->whereDate('refund_remarks.created_at', '<=', Carbon::parse($to)->endOfDay());
        }

        // Summary calculations across groups
        $allStatsQuery = clone $baseQuery;
        $allStats = $allStatsQuery->select(
            'refund_remarks.id',
            'refund_remarks.bill_id',
            'refund_remarks.status'
        )->get();

        $allRefundBillIds = $allStats->pluck('bill_id')->filter()->unique()->values()->all();
        $paymentsMap = [];
        if (!empty($allRefundBillIds)) {
            $payments = DB::table('billing_payment_type')
                ->whereIn('bill_id', $allRefundBillIds)
                ->where(function ($q) {
                    $q->where('amount', '<', 0)->orWhere('transaction_type', 1);
                })
                ->select('bill_id', DB::raw('ABS(amount) as amt'))
                ->get();
            foreach ($payments as $p) {
                $paymentsMap[$p->bill_id] = (float) $p->amt;
            }
        }

        $totalCount = $allStats->count();
        $totalSum = 0.0;
        $openCount = 0;
        $openSum = 0.0;
        $completedCount = 0;
        $completedSum = 0.0;
        $rejectedCount = 0;
        $rejectedSum = 0.0;
        $pendingCount = 0;
        $pendingSum = 0.0;
        $approvedCount = 0;
        $approvedSum = 0.0;

        foreach ($allStats as $st) {
            $amt = $paymentsMap[$st->bill_id] ?? 0.0;
            $stLower = strtolower($st->status ?: 'issued');
            
            if ($stLower === 'issued') {
                $totalSum += $amt;
                $completedCount++;
                $completedSum += $amt;
            } elseif ($stLower === 'rejected') {
                $rejectedCount++;
                $rejectedSum += $amt;
            } elseif ($stLower === 'pending') {
                $openCount++;
                $openSum += $amt;
                $pendingCount++;
                $pendingSum += $amt;
            } elseif ($stLower === 'approved') {
                $openCount++;
                $openSum += $amt;
                $approvedCount++;
                $approvedSum += $amt;
            }
        }

        // Filter by status group if requested
        $query = clone $baseQuery;
        if ($statusGroup === 'open' || $statusGroup === 'in_process') {
            $query->whereIn('refund_remarks.status', ['pending', 'approved']);
        } elseif ($statusGroup === 'issued' || $statusGroup === 'completed') {
            $query->where('refund_remarks.status', 'issued');
        } elseif ($statusGroup === 'rejected') {
            $query->where('refund_remarks.status', 'rejected');
        }

        $groupCount = (clone $query)->count();

        $records = $query->select(
            'refund_remarks.id as refund_id',
            'refund_remarks.bill_id',
            'refund_remarks.bill_ref_id',
            'refund_remarks.status',
            'refund_remarks.remarks',
            'refund_remarks.created_at as refund_date',
            'refund_remarks.initiated_at',
            'refund_remarks.approved_at',
            'refund_remarks.issued_at',
            'pi.id as patient_id',
            'pi.Fname as first_name',
            'pi.Lname as last_name',
            'pi.mobile_no as patient_mobile',
            'pi.sex as patient_gender',
            'doctor_db.name as doctor_name',
            DB::raw("COALESCE(u_init.name, '') as initiated_by_name"),
            DB::raw("COALESCE(u_app.name, '') as approved_by_name"),
            DB::raw("COALESCE(u_iss.name, '') as issued_by_name")
        )
        ->orderBy('refund_remarks.id', 'desc')
        ->offset(($page - 1) * $size)
        ->limit($size)
        ->get();

        // Collect all referenced bill IDs to fetch their details in one query
        $allRefIds = [];
        foreach ($records as $r) {
            $refArr = json_decode($r->bill_ref_id, true) ?: [$r->bill_id];
            foreach ((array)$refArr as $bId) {
                if (is_numeric($bId)) $allRefIds[] = (int)$bId;
            }
        }
        $allRefIds = array_unique($allRefIds);

        $refBillsDetails = [];
        if (!empty($allRefIds)) {
            $billsData = DB::table('patient_billing')
                ->whereIn('id', $allRefIds)
                ->select('id', 'bill_number', 'created_at')
                ->get();
            foreach ($billsData as $bd) {
                $refBillsDetails[$bd->id] = [
                    'id' => $bd->id,
                    'bill_no' => $bd->bill_number ?: $bd->id,
                    'date' => Carbon::parse($bd->created_at)->format('d M Y'),
                ];
            }
        }

        $rows = [];
        foreach ($records as $r) {
            $pName = trim(($r->first_name ?? '') . ' ' . ($r->last_name ?? ''));
            $refBills = json_decode($r->bill_ref_id, true) ?: [$r->bill_id];
            $refBills = array_values(array_filter((array)$refBills));
            if (empty($refBills)) {
                $refBills = [$r->bill_id];
            }
            $billNoStr = implode(', ', $refBills);
            
            // Fetch refund payment row
            $pay = DB::table('billing_payment_type')
                ->where('bill_id', $r->bill_id)
                ->where(function ($q) {
                    $q->where('amount', '<', 0)->orWhere('transaction_type', 1);
                })
                ->orderBy('id', 'desc')
                ->first();

            if (!$pay) {
                $pay = DB::table('billing_payment_type')->where('bill_id', $r->bill_id)->first();
            }

            $amt = $pay ? abs((float)$pay->amount) : 0.0;
            $mode = $pay ? ucfirst($pay->payment_mode ?: '') : 'Cash';
            $status = strtolower($r->status ?: 'issued');

            // Extract rejection reason if recorded with prefix
            $rejWhy = '';
            $displayRemarks = $r->remarks ?: '';
            if ($status === 'rejected') {
                if (preg_match('/Rejected:\s*([^|]+)/i', $displayRemarks, $m)) {
                    $rejWhy = trim($m[1]);
                } else {
                    $rejWhy = $displayRemarks;
                }
            }

            // Distribute allocation among referenced bills
            $allocations = [];
            $allocCount = count($refBills);
            $leftAmt = $amt;
            foreach ($refBills as $idx => $bId) {
                $bDetail = $refBillsDetails[$bId] ?? null;
                $bShare = ($idx === $allocCount - 1) ? $leftAmt : round($amt / $allocCount, 2);
                $leftAmt -= $bShare;
                $allocations[] = [
                    'bill_id' => $bId,
                    'bill_no' => $bId,
                    'date' => $bDetail ? $bDetail['date'] : Carbon::parse($r->refund_date)->format('d M Y'),
                    'amount' => $bShare,
                ];
            }

            $rows[] = [
                'id' => $r->refund_id,
                'refund_no' => $r->refund_id,
                'payment_id' => $pay ? (int)$pay->id : null,
                'transaction_id' => $pay ? ($pay->transaction_id ?: '') : '',
                'bill_id' => $r->bill_id,
                'bills' => $refBills,
                'bill_no' => $billNoStr,
                'date' => Carbon::parse($r->refund_date)->format('d M Y, h:i A'),
                'raw_date' => $r->refund_date,
                'patient_name' => $pName ?: 'Unknown Patient',
                'patient_uhid' => (string)$r->patient_id,
                'patient_mobile' => $r->patient_mobile ?: '',
                'patient_gender' => $r->patient_gender ?: '',
                'doctor_name' => $r->doctor_name ?: '—',
                'reason' => $displayRemarks ?: 'Service cancelled / Refund',
                'remarks' => $displayRemarks,
                'mode' => $mode,
                'amount' => $amt,
                'status' => $status,
                'status_text' => ucfirst($status === 'issued' ? 'completed' : $status),
                'initiated_at' => $r->initiated_at ? Carbon::parse($r->initiated_at)->format('d M Y, h:i A') : Carbon::parse($r->refund_date)->format('d M Y, h:i A'),
                'raw_initiated_at' => $r->initiated_at ?: $r->refund_date,
                'initiated_by' => $r->initiated_by_name ?: ($r->first_name ? ($r->first_name . ' (Staff)') : 'Front Desk'),
                'approved_at' => $r->approved_at ? Carbon::parse($r->approved_at)->format('d M Y, h:i A') : null,
                'raw_approved_at' => $r->approved_at,
                'approved_by' => $r->approved_by_name ?: ($status === 'approved' || $status === 'issued' ? 'Hospital Admin' : null),
                'issued_at' => $r->issued_at ? Carbon::parse($r->issued_at)->format('d M Y, h:i A') : ($status === 'issued' ? Carbon::parse($r->refund_date)->format('d M Y, h:i A') : null),
                'raw_issued_at' => $r->issued_at ?: ($status === 'issued' ? $r->refund_date : null),
                'issued_by' => $r->issued_by_name ?: ($status === 'issued' ? ($r->initiated_by_name ?: 'Front Desk') : null),
                'rejected_at' => ($status === 'rejected' && $r->approved_at) ? Carbon::parse($r->approved_at)->format('d M Y, h:i A') : null,
                'rejected_by' => ($status === 'rejected') ? ($r->approved_by_name ?: 'Hospital Admin') : null,
                'rejected_why' => $rejWhy,
                'allocations' => $allocations,
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => $rows,
            'summary' => [
                'total_count' => $totalCount,
                'total_amount' => $totalSum,
                'open_count' => $openCount,
                'open_amount' => $openSum,
                'completed_count' => $completedCount,
                'completed_amount' => $completedSum,
                'rejected_count' => $rejectedCount,
                'rejected_amount' => $rejectedSum,
                'pending_count' => $pendingCount,
                'pending_amount' => $pendingSum,
                'approved_count' => $approvedCount,
                'approved_amount' => $approvedSum,
                'from_date' => $from ? Carbon::parse($from)->format('d M Y') : null,
                'to_date' => $to ? Carbon::parse($to)->format('d M Y') : null,
            ],
            'meta' => [
                'current_page' => $page,
                'per_page' => $size,
                'total' => $groupCount,
                'last_page' => ceil($groupCount / $size) ?: 1,
            ]
        ]);
    }

    /**
     * Get patient's bills with refundable amounts
     */
    public function patientRefundableBills(Request $request, $patientId)
    {
        $user = Auth::user();
        if (!$user->can('module-permission', ['refund', 'can_create']) && !$user->can('module-permission', ['refund', 'can_update'])) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized. Refund permission required.'], 403);
        }

        $org_id = $user->org_id;
        $numericId = preg_replace('/[^0-9]/', '', (string)$patientId);

        $patient = null;
        $ptRow = DB::table('pi')->where('id', $numericId ?: $patientId)->first();
        if ($ptRow) {
            $age = $ptRow->age ?: '';
            if (!empty($ptRow->dob)) {
                try {
                    $age = Carbon::parse($ptRow->dob)->age;
                } catch (\Throwable $e) {}
            }
            $pTitle = $ptRow->prefix ? trim($ptRow->prefix) . ' ' : '';
            $patient = [
                'id' => $ptRow->id,
                'uhid' => (string)$ptRow->id,
                'name' => trim($pTitle . trim(($ptRow->Fname ?? '') . ' ' . ($ptRow->Lname ?? ''))),
                'gender' => $ptRow->sex ?: 'Male',
                'mobile' => $ptRow->mobile_no ?: '',
                'age' => $age ? (string)$age : '',
            ];
        }

        $bills = PatientBilling::join('visitlog', 'visitlog.id', '=', 'patient_billing.visit_id')
            ->join('pi', 'pi.id', '=', 'visitlog.patient_id')
            ->leftJoin('doctor_db', 'doctor_db.id', '=', 'visitlog.doctor_id')
            ->where('visitlog.hospital_id', $org_id)
            ->where(function ($q) use ($patientId, $numericId) {
                $q->where('visitlog.patient_id', $patientId);
                if ($numericId) {
                    $q->orWhere('visitlog.patient_id', $numericId);
                }
            })
            ->where(function ($q) {
                $q->whereNull('patient_billing.status')->orWhere('patient_billing.status', 1);
            })
            ->select(
                'patient_billing.id as bill_id',
                'patient_billing.id as bill_number',
                'patient_billing.discount',
                'patient_billing.is_cancelled',
                'patient_billing.created_at',
                'doctor_db.name as doctor_name',
                'pi.id as patient_id',
                'pi.prefix as patient_prefix',
                'pi.Fname as first_name',
                'pi.Lname as last_name',
                'pi.age as patient_age',
                'pi.dob as patient_dob',
                'pi.sex as patient_gender',
                'pi.mobile_no as patient_mobile'
            )
            ->orderBy('patient_billing.id', 'desc')
            ->get();

        if (!$patient && $bills->isNotEmpty()) {
            $first = $bills->first();
            $age = $first->patient_age ?: '';
            if (!empty($first->patient_dob)) {
                try {
                    $age = Carbon::parse($first->patient_dob)->age;
                } catch (\Throwable $e) {}
            }
            $pTitle = $first->patient_prefix ? trim($first->patient_prefix) . ' ' : '';
            $patient = [
                'id' => $first->patient_id,
                'uhid' => (string)$first->patient_id,
                'name' => trim($pTitle . trim(($first->first_name ?? '') . ' ' . ($first->last_name ?? ''))),
                'gender' => $first->patient_gender ?: 'Male',
                'mobile' => $first->patient_mobile ?: '',
                'age' => $age ? (string)$age : '',
            ];
        }

        $items = [];
        $billIds = $bills->pluck('bill_id')->filter()->values()->all();

        if (!empty($billIds)) {
            // Active items subtotal
            $pgbActive = DB::table('patient_gen_bill')
                ->whereIn('bill_id', $billIds)
                ->where(function ($q) { $q->where('is_cancelled', '!=', 1)->orWhereNull('is_cancelled'); })
                ->groupBy('bill_id')
                ->selectRaw('bill_id, SUM(COALESCE((charges * qty) - discount, 0)) as total')
                ->pluck('total', 'bill_id');

            // All items subtotal (for cancelled bills)
            $pgbAll = DB::table('patient_gen_bill')
                ->whereIn('bill_id', $billIds)
                ->groupBy('bill_id')
                ->selectRaw('bill_id, SUM(COALESCE((charges * qty) - discount, 0)) as total')
                ->pluck('total', 'bill_id');

            // 1. Direct Collections
            $directPayments = DB::table('billing_payment_type as bpt')
                ->whereIn('bpt.bill_id', $billIds)
                ->where(function ($q) {
                    $q->where('bpt.is_deleted', 0)->orWhereNull('bpt.is_deleted');
                })
                ->where(function ($q) {
                    $q->whereNull('bpt.transaction_type')->orWhere('bpt.transaction_type', 0);
                })
                ->where('bpt.amount', '>', 0)
                ->select('bpt.id', 'bpt.bill_id', 'bpt.amount', 'bpt.created_at')
                ->get();

            // 2. Settlement Collections
            $settlements = DB::table('bill_reference as br')
                ->join('billing_payment_type as bpt', 'bpt.bill_id', '=', 'br.ref_bill')
                ->whereIn('br.bill_id', $billIds)
                ->where(function ($q) {
                    $q->where('bpt.is_deleted', 0)->orWhereNull('bpt.is_deleted');
                })
                ->where('bpt.amount', '>', 0)
                ->select('bpt.id', 'br.bill_id', 'bpt.amount', 'bpt.created_at')
                ->get();

            // 3. Direct Refunds on bill
            $directRefunds = DB::table('billing_payment_type as bpt')
                ->whereIn('bpt.bill_id', $billIds)
                ->where(function ($q) {
                    $q->where('bpt.is_deleted', 0)->orWhereNull('bpt.is_deleted');
                })
                ->where(function ($q) {
                    $q->where('bpt.transaction_type', 1)
                      ->orWhere('bpt.amount', '<', 0);
                })
                ->select('bpt.id', 'bpt.bill_id', 'bpt.amount', 'bpt.created_at')
                ->get();

            // 4. Linked Refunds via refund_remarks
            $refundRemarks = DB::table('refund_remarks as rr')
                ->join('billing_payment_type as bpt', 'bpt.bill_id', '=', 'rr.bill_id')
                ->where('rr.status', 'issued')
                ->where(function ($q) {
                    $q->where('bpt.is_deleted', 0)->orWhereNull('bpt.is_deleted');
                })
                ->where('bpt.transaction_type', 1)
                ->select('rr.bill_ref_id', 'bpt.id', 'bpt.amount', 'bpt.created_at')
                ->get();

            // 5. In-process (Held) Refunds via refund_remarks
            $heldRemarks = DB::table('refund_remarks as rr')
                ->join('billing_payment_type as bpt', 'bpt.bill_id', '=', 'rr.bill_id')
                ->whereIn('rr.status', ['pending', 'approved'])
                ->where(function ($q) {
                    $q->where('bpt.is_deleted', 0)->orWhereNull('bpt.is_deleted');
                })
                ->where('bpt.transaction_type', 1)
                ->select('rr.bill_ref_id', 'bpt.id', 'bpt.amount', 'bpt.created_at')
                ->get();

            // Build collections, refunds, and held maps
            $collectionsByBill = [];
            $refundsByBill = [];
            $heldByBill = [];

            foreach ($directPayments as $dp) {
                $collectionsByBill[(int)$dp->bill_id][$dp->id] = (float)$dp->amount;
            }
            foreach ($settlements as $st) {
                $collectionsByBill[(int)$st->bill_id][$st->id] = (float)$st->amount;
            }
            foreach ($directRefunds as $dr) {
                $refundsByBill[(int)$dr->bill_id][$dr->id] = abs((float)$dr->amount);
            }
            foreach ($refundRemarks as $rf) {
                $refIds = json_decode($rf->bill_ref_id, true) ?: [];
                foreach ($refIds as $refBillId) {
                    $bId = (int)$refBillId;
                    if (in_array($bId, $billIds)) {
                        $refundsByBill[$bId][$rf->id] = abs((float)$rf->amount);
                    }
                }
            }
            foreach ($heldRemarks as $hr) {
                $refIds = json_decode($hr->bill_ref_id, true) ?: [];
                foreach ($refIds as $refBillId) {
                    $bId = (int)$refBillId;
                    if (in_array($bId, $billIds)) {
                        $heldByBill[$bId][$hr->id] = abs((float)$hr->amount);
                    }
                }
            }

            foreach ($bills as $b) {
                $isCx = (bool)$b->is_cancelled;
                $bId = (int)$b->bill_id;

                $subtotal = $isCx ? (float)($pgbAll[$bId] ?? 0) : (float)($pgbActive[$bId] ?? 0);
                $discount = (float)($b->discount ?: 0);
                $payable = max(0, round($subtotal - $discount, 2));

                $paid = isset($collectionsByBill[$bId]) ? round(array_sum($collectionsByBill[$bId]), 2) : 0.0;
                $refunded = isset($refundsByBill[$bId]) ? round(array_sum($refundsByBill[$bId]), 2) : 0.0;
                $held = isset($heldByBill[$bId]) ? round(array_sum($heldByBill[$bId]), 2) : 0.0;
                $refundable = $isCx ? 0.0 : max(0, round($paid - $refunded - $held, 2));

                $status = 'Unpaid';
                if ($isCx) {
                    $status = 'Cancelled';
                } elseif ($held > 0) {
                    $status = 'Refund in process';
                } elseif ($refundable <= 0 && $refunded > 0) {
                    $status = 'Fully refunded';
                } elseif ($refunded > 0 && $refundable > 0) {
                    $status = 'Part refunded';
                } elseif ($paid >= $payable && $payable > 0) {
                    $status = 'Paid';
                } elseif ($paid > 0) {
                    $status = 'Part-paid';
                }

                $items[] = [
                    'id' => $bId,
                    'bill_no' => (string)$bId,
                    'bill_number' => (string)$bId,
                    'date' => Carbon::parse($b->created_at)->format('d-m-Y, g:i A'),
                    'doctor' => $b->doctor_name ? ('Dr. ' . trim(preg_replace('/^(dr\.?\s*)+/i', '', $b->doctor_name))) : '—',
                    'billed' => $payable,
                    'paid' => $paid,
                    'refunded' => $refunded,
                    'held' => $held,
                    'refundable' => $refundable,
                    'is_cancelled' => $isCx,
                    'status' => $status
                ];
            }
        }

        return response()->json([
            'status' => 'success',
            'patient' => $patient,
            'bills' => $items
        ]);
    }



}
