# Lab Information System (LIS) & Billing Module Assumptions

## 1. Environment & Tech Stack
- **PHP Version**: 8.2.30.
- **Laravel Framework**: 12.x (`v12.72.0`). PHP 8.2 typed syntax, readonly properties, backed enums, constructor property promotion, strict types throughout.
- **Database**: MariaDB 12.1 / MySQL 8 compatible (`lmpath_db` configured with InnoDB, utf8mb4 charset and collation).
- **Queues & Cache**: Database driver configured (`QUEUE_CONNECTION=database`, `CACHE_STORE=database`) with Redis support pre-wired for environments with active Redis.
- **Static Analysis & Testing**: Pest PHP 3.8.7 (`pestphp/pest` + `pestphp/pest-plugin-laravel`), PHPUnit 11.5.56, PHPStan / Larastan 3.15, Laravel Pint.

## 2. Legacy Schema & Dual-Write Compatibility
- Legacy tables inspected and mirrored under `App\Models\Legacy\*`:
  - `pi` (patients)
  - `visitlog` (visits)
  - `patient_billing` (invoice header)
  - `patient_gen_bill` (bill line items)
  - `billing_payment_type` (payment records)
  - `hospital_services` (test/package definitions with `input_attributes`, `group_attributes`)
  - `table_investigation_group_test_index` (group-to-test mappings)
  - `lab_investigations` (1:1 with `patient_gen_bill.id`, holding legacy JSON `test_results` and status)
  - `lab_sample_labels` (specimen legacy records)
  - `hospital_service_charges` (rate master across OPD/ER locations)
  - `hospital_print_settings` (letterhead, header/footer, signature blocks)
- Dual-write contract: Every bill/order creation generates new domain entities (`lab_orders`, `lab_order_items`, `specimens`, `specimen_items`) while maintaining corresponding rows in `patient_billing`, `patient_gen_bill`, and `lab_investigations`. Any result entry or status transition synchronizes `lab_investigations.test_results` and status so legacy reports and OPD/IPD screens continue to function without modifying existing legacy controllers.

## 3. Monetary Representation & Number Sequences
- All currency calculations adhere strictly to 2 decimal places (`decimal(12,2)`) with rounded paise calculations. No floats are used in financial calculations.
- Sequence generation utilizes `lab_bill_sequences` with row-level locks (`SELECT ... FOR UPDATE`) per financial year to guarantee concurrency safety without gaps or race collisions.

## 4. Frontend Architecture
- Blade + jQuery + Vite. Modular JS event delegation; zero inline business logic.
- Compact, high-density layout (13px base font, 28–32px row heights, 4/8px spacing) inspired by Material Design 3.
- Central keyboard shortcut management via `shortcuts.js` and `config/lab-shortcuts.php`.

