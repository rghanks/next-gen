# Laboratory Information System (LIS) & Billing Desk Module

A production-grade, modular monolith Laboratory Information System (LIS) and Billing Desk built with Laravel 12, PHP 8.2 strict typing, MySQL 8 / MariaDB, and a compact Blade + jQuery frontend with centralized keyboard navigation.

---

## 1. Architecture Overview

The workspace uses a muted slate-blue palette, a compact navigation rail, and shared light/dark themes. Billing places patient and order details above an investigation grid with payment details alongside it. Result entry uses an investigation navigator, parameter grid, and order/sample details panel. The result worklist supports combined patient, accession, investigation, date, priority, and reporting-status filters, preserving filters when switching status.

With XAMPP serving this checkout, open `http://localhost/lmpath/public/lab/billing`. CSS and JavaScript are served directly from `public/` with file-versioned URLs; this UI does not require a Vite build. Laravel-generated API and navigation URLs support installation in a subdirectory. Existing billing, specimen, verification, reporting, and legacy dual-write actions remain in use.

The layout and theme are in `resources/views/lab/layouts/app.blade.php` and `public/css/lab.css`. Billing interactions are in `public/js/lab-billing.js`, and the keyboard registry is in `public/js/shortcuts.js`. Worklist query regression coverage is in `tests/Feature/LabWorklistTest.php`.

Located in `app/Domain/Lab`:
```
app/
├── Domain/Lab/
│   ├── Actions/               # CreateLabBill, CancelLabBill, CollectSpecimen, ReceiveSpecimen, RejectSpecimen,
│   │                          # SaveResults, ValidateResults, VerifyResults, ReleaseReport, AmendReport
│   ├── Events/                # LabBillCreated, LabBillCancelled
│   ├── Exceptions/            # InvalidStateTransitionException, OptimisticLockException
│   ├── Models/                # LabOrder, LabOrderItem, Specimen, SpecimenItem, LabParameter,
│   │                          # LabReferenceRange, LabResultValue, LabCriticalAcknowledgement, LabReport,
│   │                          # LabOrderEvent, LabBillSequence, LabDiscountApproval, LabBillCancellation,
│   │                          # LabPriceList, LabPriceListItem
│   ├── Policies/              # LabOrderPolicy
│   ├── Services/              # PriceResolver, BillNumberGenerator, AccessionGenerator, ReferenceRangeResolver,
│   │                          # FormulaEvaluator, FlagCalculator, CriticalValueChecker, DeltaChecker,
│   │                          # ReportRenderer, DiagnosticReportMapper, LabFeatureManager
│   └── States/                # LabState Backed Enum (Single Source of Truth state machine)
├── Http/
│   ├── Controllers/Lab/       # BillingController, SpecimenController, ResultController, VerificationController,
│   │                          # ReportController, AuditController (Thin controllers)
│   ├── Requests/Lab/          # CreateBillRequest, CancelBillRequest
│   └── Resources/Lab/         # LabOrderResource
└── Models/Legacy/             # Patient (pi), VisitLog (visitlog), PatientBilling (patient_billing),
                               # PatientGenBill (patient_gen_bill), BillingPaymentType (billing_payment_type),
                               # HospitalService (hospital_services), InvestigationGroupTestIndex,
                               # LabInvestigation (lab_investigations), LabSampleLabel,
                               # HospitalServiceCharge, HospitalPrintSetting
```

---

## 2. Dual-Write Rule & Legacy Compatibility

Existing OPD/IPD billing and legacy laboratory screens function without modification:
- **Billing Dual-Write**: Creating a lab bill writes atomically to `lab_orders`, `lab_order_items`, `specimens`, `specimen_items`, while simultaneously populating `patient_billing`, `patient_gen_bill`, `lab_investigations` (1:1 with `patient_gen_bill.id`), and `billing_payment_type`.
- **Result & Verification Dual-Write**: Saving or verifying parameters serializes discrete values and reference ranges into `lab_investigations.test_results` JSON format (`{"SERVICE_NAME": {"param_id": {"value": "...", "biological_reference_range": "..."}}}`) and updates legacy approval status (`approved`, `approved_by`, `approval_time`).
- Existing controllers like `PatientController.php` are untouched.

---

## 3. State Machine & Transitions

Single Source of Truth defined in `App\Domain\Lab\States\LabState`:
```
ordered → collected → received → in_process → resulted → verified → released
```
- **Recollect Rule**: Rejecting a specimen returns the order to `ordered` (clearing collection timestamp and setting condition to `rejected`).
- **Cancellation Rule**: A bill or order can **only** be cancelled before results are entered (`ordered`, `collected`, `received`, `in_process`). A credit note (`CN-...`) is generated with mandatory reason and approver.
- **Amendment Rule**: A released report can **never** be overwritten. Any revision triggers `AmendReport`, generating version `N+1`, linking to the previous version, requiring a mandatory clinical/technical reason, and preserving previous signed PDF versions permanently.
- **Append-Only Audit**: Every transition writes an entry to `lab_order_events` with user, timestamp, from_state, to_state, and payload snapshot.

---

## 4. Keyboard Shortcuts Reference

Centrally mapped in `config/lab-shortcuts.php` and `public/js/shortcuts.js`. Press `?` anywhere to open the cheat sheet overlay.

| Context | Keys | Action |
|---|---|---|
| **Global** | `/` | Focus search |
| **Global** | `?` | Shortcut help overlay |
| **Global** | `Alt+1 … 5` | Quick Switch: Billing / Specimen / Results / Verify / Reports |
| **Global** | `Esc` | Close modal / cancel search |
| **Billing** | `Alt+N` | Start new bill |
| **Billing** | `Alt+P` | Focus patient search / quick walk-in |
| **Billing** | `Alt+T` | Focus test & package search (Enter to add) |
| **Billing** | `Alt+D` | Open bill discount |
| **Billing** | `Alt+M` | Focus payment split panel |
| **Billing** | `Ctrl+Enter` | Save bill & print receipt + barcodes |
| **Billing** | `Del` | Remove selected test row |
| **Worklists**| `J` / `K` or `↑` / `↓` | Move up/down rows |
| **Worklists**| `Enter` | Open selected record |
| **Worklists**| `Space` | Toggle row selection |
| **Results** | `Tab` / `Enter` | Next parameter (down the grid) |
| **Results** | `Ctrl+S` | Save parameter results |
| **Results** | `Ctrl+Enter` | Forward to verification / Sign-off |
| **Reports** | `Alt+R` | Download / Print stored PDF |
| **Reports** | `Alt+W` | Dispatch report via WhatsApp |

---

## 5. Permissions & Security

Managed via Spatie Permissions:
- `lab.bill.create`: Register walk-ins, create bills and orders.
- `lab.bill.discount`: Apply bill and line-item discounts.
- `lab.bill.cancel`: Cancel settled orders and issue credit notes.
- `lab.specimen.collect`: Phlebotomist collection and barcode scan.
- `lab.result.enter`: Lab technician parameter input and formula calculation.
- `lab.result.validate`: Technician step-one sign-off.
- `lab.result.verify`: Pathologist final sign-off and release.
- `lab.report.amend`: Authorize amended versions of released reports.
- `lab.master.manage`: Configure parameters, reference ranges, and pricing.

---

## 6. Commands & Verification

1. **Migrate tables**:
   ```bash
   php artisan migrate
   ```
2. **Seed permissions & migrate parameters**:
   ```bash
   php artisan db:seed --class=LabPermissionSeeder
   php artisan db:seed --class=LabParameterMigrationSeeder
   ```
3. **Backfill legacy JSON results**:
   ```bash
   php artisan lab:backfill-results --limit=50
   ```
4. **Run Pest test suite**:
   ```bash
   php ./vendor/bin/pest
   ```
5. **Static Analysis (Larastan Level 6)**:
   ```bash
   php ./vendor/bin/phpstan analyse
   ```
6. **Code Formatting (Pint)**:
   ```bash
   php ./vendor/bin/pint --test
   ```
