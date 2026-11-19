<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Licensing\HardwareFingerprint;
use App\Services\Licensing\LicenseManager;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class LicenseGenerate extends Command
{
    protected $signature = 'license:generate 
                            {--client= : Client / Lab name}
                            {--machine= : Target Machine ID (defaults to current system if omitted)}
                            {--expires= : Expiration date (YYYY-MM-DD, e.g. 2027-12-31)}
                            {--days= : Validity duration in days from today}
                            {--output= : File path to save the generated license}
                            {--new-keys : Force generation of a new RSA keypair}';

    protected $description = 'Vendor Tool: Generate and cryptographically sign a system license';

    public function handle(): int
    {
        $this->newLine();
        $this->info('=====================================================');
        $this->info('              LM-PATH LICENSE GENERATOR              ');
        $this->info('=====================================================');

        $privateKeyPath = storage_path('license/license_private.key');
        $publicKeyPath = LicenseManager::getPublicKeyPath();

        // 1. Check or generate RSA keys
        if (!File::exists($privateKeyPath) || !File::exists($publicKeyPath) || $this->option('new-keys')) {
            if ($this->option('new-keys') || $this->confirm('RSA Keypair not found. Would you like to generate a new keypair?', true)) {
                $this->info('Generating new 2048-bit RSA Keypair...');
                $keys = LicenseManager::generateKeypair(2048);

                File::ensureDirectoryExists(dirname($privateKeyPath));
                File::put($privateKeyPath, $keys['private_key']);
                File::put($publicKeyPath, $keys['public_key']);

                $this->info("✓ Private key saved to: {$privateKeyPath}");
                $this->info("✓ Public key saved to:  {$publicKeyPath}");
                $this->warn('IMPORTANT: Keep the private key confidential! Only the public key goes to clients.');
            } else {
                $this->error('Cannot generate license without an RSA keypair.');
                return self::FAILURE;
            }
        }

        $privateKey = File::get($privateKeyPath);

        // 2. Client Name
        $client = $this->option('client');
        if (empty($client)) {
            $client = $this->ask('Enter Client / Laboratory Name', 'Rahul Clinic & Laboratory');
        }

        // 3. Machine ID
        $machine = $this->option('machine');
        if (empty($machine)) {
            $currentMachine = HardwareFingerprint::getMachineId();
            $machine = $this->ask('Enter Target Machine ID', $currentMachine);
        }
        $machine = strtoupper(trim((string) $machine));

        // 4. Expiration Date
        $expires = $this->option('expires');
        $days = $this->option('days');

        if (!empty($days)) {
            $expiryCarbon = Carbon::now()->addDays((int) $days)->endOfDay();
        } elseif (!empty($expires)) {
            $expiryCarbon = Carbon::parse($expires)->endOfDay();
        } else {
            $defaultExpiry = Carbon::now()->addYear()->format('Y-m-d');
            $expiresInput = $this->ask('Enter Expiration Date (YYYY-MM-DD)', $defaultExpiry);
            $expiryCarbon = Carbon::parse($expiresInput)->endOfDay();
        }

        $this->newLine();
        $this->info('Generating license with parameters:');
        $this->line("  Client Name:    <fg=cyan>{$client}</>");
        $this->line("  Machine ID:     <fg=cyan>{$machine}</>");
        $this->line("  Expires On:     <fg=yellow>{$expiryCarbon->format('d M Y, h:i A')}</> (" . (int) ceil(Carbon::now()->floatDiffInDays($expiryCarbon)) . " days)");

        // 5. Sign the license
        try {
            $licenseText = LicenseManager::issueLicense(
                clientName: $client,
                machineId: $machine,
                expiresAt: $expiryCarbon->toIso8601String(),
                privateKeyPem: $privateKey
            );
        } catch (\Throwable $e) {
            $this->error('Error issuing license: ' . $e->getMessage());
            return self::FAILURE;
        }

        // 6. Output destination
        $outputPath = $this->option('output');
        if (empty($outputPath)) {
            $outputPath = LicenseManager::getLicenseFilePath();
        }

        File::ensureDirectoryExists(dirname($outputPath));
        File::put($outputPath, $licenseText);

        $this->newLine();
        $this->info("✓ License successfully generated and saved to: {$outputPath}");
        $this->newLine();

        $this->line('License string (ready to copy):');
        $this->line('<fg=gray>' . $licenseText . '</>');

        return self::SUCCESS;
    }
}
