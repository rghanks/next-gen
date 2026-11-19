<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Lab\Models\LabParameter;
use App\Domain\Lab\Models\LabPriceList;
use App\Domain\Lab\Models\LabPriceListItem;
use App\Domain\Lab\Models\LabReferenceRange;
use App\Models\Legacy\HospitalService;
use App\Models\Legacy\HospitalServiceCharge;
use App\Models\Legacy\InvestigationGroupTestIndex;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateManglaTestMaster extends Command
{
    protected $signature = 'lab:migrate-mangla-test-master {--force : Bypass confirmation}';

    protected $description = 'Clear current DB tables data and import onboarded test master from mangla_empath_db_backup';

    public function handle(): int
    {
        $backupDb = 'mangla_empath_db_backup';

        $this->info("Checking connection to {$backupDb}...");
        $dbCheck = DB::select("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?", [$backupDb]);
        if (empty($dbCheck)) {
            $this->error("Database {$backupDb} not found!");
            return Command::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('This will wipe all transactional data, old test master, and referring doctors. Continue?', false)) {
            $this->warn('Operation aborted by user.');
            return Command::SUCCESS;
        }

        $this->info('Starting database migration...');

        // 1. CLEAR CURRENT DATA
        $this->info('1. Clearing transactional data, old test master, and referring doctors...');
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');

        $tablesToTruncate = [
            'lab_critical_acknowledgements',
            'lab_result_values',
            'lab_reports',
            'lab_order_events',
            'specimen_items',
            'specimens',
            'lab_order_items',
            'lab_orders',
            'lab_investigations',
            'lab_discount_approvals',
            'lab_sample_labels',
            'lab_bill_cancellations',
            'lab_bill_sequences',
            'patient_gen_bill',
            'patient_billing',
            'visitlog',
            'pi',
            'referring_doctors',
            'lab_reference_ranges',
            'lab_parameters',
            'lab_price_list_items',
            'table_investigation_group_test_index',
            'hospital_service_charges',
            'hospital_services',
        ];

        foreach ($tablesToTruncate as $table) {
            DB::table($table)->truncate();
            $this->line("   - Truncated table: {$table}");
        }

        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        $this->info('All target tables cleared successfully.');

        // 2. RESOLVE ONBOARDED TESTS AND ALL CHILD TESTS
        $this->info("2. Resolving onboarded tests (flag = 'test') and recursive child tests from {$backupDb}...");

        $directOnboarded = DB::select("
            SELECT s.id, s.service, s.flag, s.is_group 
            FROM {$backupDb}.hospital_services s 
            JOIN {$backupDb}.hospital_services_index hsi ON hsi.service = s.id 
            WHERE s.flag = 'test'
        ");

        $allServiceIds = collect($directOnboarded)->pluck('id')->unique();
        $this->line("   - Found {$allServiceIds->count()} direct onboarded tests.");

        $currentParentIds = $allServiceIds;
        $depth = 0;
        while ($currentParentIds->isNotEmpty()) {
            $depth++;
            $children = collect(DB::select("
                SELECT DISTINCT test_id 
                FROM {$backupDb}.table_investigation_group_test_index 
                WHERE group_id IN (" . $currentParentIds->implode(',') . ")
            "))->pluck('test_id');

            $newIds = $children->diff($allServiceIds);
            if ($newIds->isEmpty()) {
                break;
            }
            $this->line("   - Depth {$depth}: found {$newIds->count()} new child tests.");
            $allServiceIds = $allServiceIds->merge($newIds);
            $currentParentIds = $newIds;
        }

        $this->info("Total unique test services to import: {$allServiceIds->count()}");

        // 3. COPY HOSPITAL_SERVICES
        $this->info('3. Importing services into hospital_services...');
        $sourceServices = DB::select("
            SELECT * FROM {$backupDb}.hospital_services 
            WHERE id IN (" . $allServiceIds->implode(',') . ")
        ");

        $insertedCount = 0;
        foreach (array_chunk($sourceServices, 100) as $chunk) {
            $insertData = array_map(function ($row) {
                return (array) $row;
            }, $chunk);
            DB::table('hospital_services')->insert($insertData);
            $insertedCount += count($insertData);
        }
        $this->line("   - Inserted {$insertedCount} records into hospital_services.");

        // 4. COPY TABLE_INVESTIGATION_GROUP_TEST_INDEX
        $this->info('4. Importing group test relationships...');
        $groupLinks = DB::select("
            SELECT * FROM {$backupDb}.table_investigation_group_test_index 
            WHERE group_id IN (" . $allServiceIds->implode(',') . ")
        ");

        $insertedLinks = 0;
        if (! empty($groupLinks)) {
            foreach (array_chunk($groupLinks, 100) as $chunk) {
                $insertData = array_map(function ($row) {
                    return (array) $row;
                }, $chunk);
                DB::table('table_investigation_group_test_index')->insert($insertData);
                $insertedLinks += count($insertData);
            }
        }
        $this->line("   - Inserted {$insertedLinks} group links into table_investigation_group_test_index.");

        // 5. COPY HOSPITAL_SERVICE_CHARGES
        $this->info('5. Importing hospital service charges...');
        $charges = DB::select("
            SELECT * FROM {$backupDb}.hospital_service_charges 
            WHERE service IN (" . $allServiceIds->implode(',') . ")
        ");

        $insertedCharges = 0;
        if (! empty($charges)) {
            foreach (array_chunk($charges, 100) as $chunk) {
                $insertData = array_map(function ($row) {
                    return (array) $row;
                }, $chunk);
                DB::table('hospital_service_charges')->insert($insertData);
                $insertedCharges += count($insertData);
            }
        }
        $this->line("   - Inserted {$insertedCharges} charge records into hospital_service_charges.");

        // 6. POPULATE LAB_PRICE_LIST_ITEMS
        $this->info('6. Syncing General Payer Price List items...');
        $generalList = LabPriceList::firstOrCreate(
            ['payer_category' => 'general'],
            ['name' => 'General Payer Price List', 'is_active' => true]
        );

        $priceListItems = [];
        $now = now();
        // Group charges by service so each service only has 1 price list item per price list
        $groupedCharges = collect($charges)->groupBy('service');
        foreach ($groupedCharges as $serviceId => $serviceCharges) {
            // Prefer insurance 73 (standard general payer in old empath) or first available
            $preferred = $serviceCharges->firstWhere('insurance', 73) ?? $serviceCharges->first();
            $rate = is_numeric($preferred->opd ?? null) && (float)$preferred->opd > 0
                ? (float)$preferred->opd
                : (is_numeric($preferred->charges ?? null) ? (float)$preferred->charges : 100.0);

            $priceListItems[] = [
                'price_list_id' => $generalList->id,
                'service_id' => (int) $serviceId,
                'charge' => $rate,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (! empty($priceListItems)) {
            foreach (array_chunk($priceListItems, 100) as $chunk) {
                DB::table('lab_price_list_items')->insert($chunk);
            }
        }
        $this->line("   - Populated " . count($priceListItems) . " unique items in lab_price_list_items.");

        // 7. GENERATE LAB PARAMETERS & REFERENCE RANGES
        $this->info('7. Generating normalized lab parameters and reference ranges...');
        $unitsMap = collect(DB::select("SELECT id, unit_name FROM {$backupDb}.inv_test_units"))
            ->pluck('unit_name', 'id')
            ->toArray();

        $services = HospitalService::where('flag', 'test')->get();
        $paramCount = 0;
        $rangeCount = 0;

        foreach ($services as $service) {
            $inputAttrs = $service->input_attributes;
            if (is_string($inputAttrs)) {
                $inputAttrs = json_decode($inputAttrs, true);
            }

            if (! is_array($inputAttrs)) {
                $inputAttrs = [];
            }

            $shortCode = $service->short_code ?: strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $service->service), 0, 8));
            if (! $shortCode) {
                $shortCode = 'TEST' . $service->id;
            }

            $unit = $inputAttrs['unit'] ?? null;
            if (is_numeric($unit) && isset($unitsMap[(int) $unit])) {
                $unit = $unitsMap[(int) $unit];
            } elseif (is_numeric($unit)) {
                $unit = null;
            }

            $inputType = 'numeric';
            if (isset($inputAttrs['input_type']) && in_array($inputAttrs['input_type'], ['text', 'options', 'culture'], true)) {
                $inputType = $inputAttrs['input_type'];
            }

            $formula = null;
            if (! empty($inputAttrs['formula']) && is_string($inputAttrs['formula'])) {
                $formula = $inputAttrs['formula'];
            }

            $parameter = LabParameter::updateOrCreate(
                [
                    'service_id' => $service->id,
                    'short_code' => $shortCode,
                ],
                [
                    'name' => $service->service,
                    'unit' => $unit,
                    'method' => $service->comment ?: 'Automated Analyzer',
                    'loinc_code' => $service->loinc_code,
                    'decimal_places' => 2,
                    'input_type' => $inputType,
                    'formula' => $formula,
                    'sort_order' => 1,
                ]
            );
            $paramCount++;

            // Biological reference ranges
            if (! empty($inputAttrs['biological_reference']) && is_array($inputAttrs['biological_reference'])) {
                $bioRef = $inputAttrs['biological_reference'];

                foreach (['Male' => 'male', 'Female' => 'female'] as $genderKey => $genderVal) {
                    if (isset($bioRef[$genderKey]) && is_array($bioRef[$genderKey])) {
                        $genderData = $bioRef[$genderKey];

                        // Adult: 18 - 120 yrs
                        if (isset($genderData['Adult']) && is_array($genderData['Adult'])) {
                            $adult = $genderData['Adult'];
                            LabReferenceRange::updateOrCreate(
                                [
                                    'parameter_id' => $parameter->id,
                                    'sex' => $genderVal,
                                    'age_from_days' => 6570,
                                    'age_to_days' => 43800,
                                ],
                                [
                                    'low' => is_numeric($adult['min'] ?? null) ? (float) $adult['min'] : null,
                                    'high' => is_numeric($adult['max'] ?? null) ? (float) $adult['max'] : null,
                                    'critical_low' => is_numeric($adult['critical_lower'] ?? null) ? (float) $adult['critical_lower'] : null,
                                    'critical_high' => is_numeric($adult['critical_higher'] ?? null) ? (float) $adult['critical_higher'] : null,
                                    'text_range' => ($adult['min'] ?? '') . ' - ' . ($adult['max'] ?? ''),
                                ]
                            );
                            $rangeCount++;
                        }

                        // Child: 1 - 18 yrs
                        if (isset($genderData['Child']) && is_array($genderData['Child'])) {
                            $child = $genderData['Child'];
                            LabReferenceRange::updateOrCreate(
                                [
                                    'parameter_id' => $parameter->id,
                                    'sex' => $genderVal,
                                    'age_from_days' => 365,
                                    'age_to_days' => 6569,
                                ],
                                [
                                    'low' => is_numeric($child['min'] ?? null) ? (float) $child['min'] : null,
                                    'high' => is_numeric($child['max'] ?? null) ? (float) $child['max'] : null,
                                    'critical_low' => is_numeric($child['critical_lower'] ?? null) ? (float) $child['critical_lower'] : null,
                                    'critical_high' => is_numeric($child['critical_higher'] ?? null) ? (float) $child['critical_higher'] : null,
                                    'text_range' => ($child['min'] ?? '') . ' - ' . ($child['max'] ?? ''),
                                ]
                            );
                            $rangeCount++;
                        }

                        // Newborn: 0 - 364 days
                        if (isset($genderData['New Born']) && is_array($genderData['New Born'])) {
                            $newborn = $genderData['New Born'];
                            LabReferenceRange::updateOrCreate(
                                [
                                    'parameter_id' => $parameter->id,
                                    'sex' => $genderVal,
                                    'age_from_days' => 0,
                                    'age_to_days' => 364,
                                ],
                                [
                                    'low' => is_numeric($newborn['min'] ?? null) ? (float) $newborn['min'] : null,
                                    'high' => is_numeric($newborn['max'] ?? null) ? (float) $newborn['max'] : null,
                                    'critical_low' => is_numeric($newborn['critical_lower'] ?? null) ? (float) $newborn['critical_lower'] : null,
                                    'critical_high' => is_numeric($newborn['critical_higher'] ?? null) ? (float) $newborn['critical_higher'] : null,
                                    'text_range' => ($newborn['min'] ?? '') . ' - ' . ($newborn['max'] ?? ''),
                                ]
                            );
                            $rangeCount++;
                        }
                    }
                }
            }
        }

        $this->line("   - Generated {$paramCount} lab parameters.");
        $this->line("   - Generated {$rangeCount} lab reference ranges.");

        $this->newLine();
        $this->info('=== MIGRATION COMPLETED SUCCESSFULLY ===');
        $this->table(
            ['Table', 'Record Count'],
            [
                ['hospital_services', DB::table('hospital_services')->count()],
                ['hospital_service_charges', DB::table('hospital_service_charges')->count()],
                ['table_investigation_group_test_index', DB::table('table_investigation_group_test_index')->count()],
                ['lab_price_list_items', DB::table('lab_price_list_items')->count()],
                ['lab_parameters', DB::table('lab_parameters')->count()],
                ['lab_reference_ranges', DB::table('lab_reference_ranges')->count()],
                ['referring_doctors', DB::table('referring_doctors')->count()],
                ['lab_orders', DB::table('lab_orders')->count()],
                ['patient_gen_bill', DB::table('patient_gen_bill')->count()],
                ['pi', DB::table('pi')->count()],
                ['users (preserved)', DB::table('users')->count()],
            ]
        );

        return Command::SUCCESS;
    }
}
