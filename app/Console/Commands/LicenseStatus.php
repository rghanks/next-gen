<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Licensing\LicenseManager;
use Carbon\Carbon;
use Illuminate\Console\Command;

class LicenseStatus extends Command
{
    protected $signature = 'license:status';
    protected $description = 'Check the current software licensing status';

    public function handle(): int
    {
        $status = LicenseManager::verify(true);

        $this->newLine();
        $this->info('=====================================================');
        $this->info('              SOFTWARE LICENSE STATUS                ');
        $this->info('=====================================================');

        $stateTag = $status['valid'] ? '<fg=black;bg=green;options=bold> VALID & ACTIVE </>' : '<fg=white;bg=red;options=bold> ' . $status['code'] . ' </>';
        $this->line("  Status:          {$stateTag}");
        $this->line("  Message:         {$status['message']}");
        $this->line("  Machine ID:      <fg=cyan>{$status['current_machine_id']}</>");

        if (!empty($status['client_name'])) {
            $this->line("  Licensed To:     <fg=bright-cyan>{$status['client_name']}</>");
        }

        if (!empty($status['expires_at'])) {
            $exp = Carbon::parse($status['expires_at']);
            $this->line("  Expiration Date: <fg=yellow>{$exp->format('d M Y, h:i A')}</>");
            $this->line("  Days Remaining:  <fg=yellow;options=bold>{$status['days_left']} day(s)</>");
        }

        if (!empty($status['license_machine_id']) && $status['license_machine_id'] !== $status['current_machine_id']) {
            $this->line("  Target Machine:  <fg=red>{$status['license_machine_id']}</> (Mismatch!)");
        }

        $this->info('=====================================================');
        $this->newLine();

        return $status['valid'] ? self::SUCCESS : self::FAILURE;
    }
}
