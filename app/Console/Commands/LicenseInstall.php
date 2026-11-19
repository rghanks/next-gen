<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Licensing\LicenseManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class LicenseInstall extends Command
{
    protected $signature = 'license:install {file? : Path to license file (.lic)}';
    protected $description = 'Install or activate a new license file';

    public function handle(): int
    {
        $filePath = $this->argument('file');

        if (empty($filePath)) {
            $this->info('Please paste the complete license block (including BEGIN/END headers), then press Enter and Ctrl+Z (or Ctrl+D) to submit:');
            $content = '';
            while (($line = fgets(STDIN)) !== false) {
                $content .= $line;
            }
        } else {
            if (!File::exists($filePath)) {
                $this->error("License file not found at: {$filePath}");
                return self::FAILURE;
            }
            $content = File::get($filePath);
        }

        if (empty(trim($content))) {
            $this->error('No license content provided.');
            return self::FAILURE;
        }

        $result = LicenseManager::install($content);

        if ($result['success']) {
            $this->info('✓ ' . $result['message']);
            return self::SUCCESS;
        }

        $this->error('✗ ' . $result['message']);
        return self::FAILURE;
    }
}
