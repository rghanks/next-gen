<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Licensing\HardwareFingerprint;
use Illuminate\Console\Command;

class LicenseMachineId extends Command
{
    protected $signature = 'license:machine-id {--details : Show individual hardware fingerprints}';
    protected $description = 'Display the unique Hardware Machine ID for this system';

    public function handle(): int
    {
        $machineId = HardwareFingerprint::getMachineId();

        $this->newLine();
        $this->info('=====================================================');
        $this->info('              SYSTEM MACHINE IDENTIFIER              ');
        $this->info('=====================================================');
        $this->line("  Machine ID: <fg=bright-cyan;options=bold>{$machineId}</>");
        $this->info('=====================================================');
        $this->comment('Share this Machine ID with your vendor to issue your license.');
        $this->newLine();

        if ($this->option('details')) {
            $this->info('Hardware Tokens Collected:');
            $details = HardwareFingerprint::getDetails();
            $rows = [];
            foreach ($details as $k => $v) {
                $rows[] = [$k, $v];
            }
            $this->table(['Component', 'Hardware Identifier'], $rows);
        }

        return self::SUCCESS;
    }
}
