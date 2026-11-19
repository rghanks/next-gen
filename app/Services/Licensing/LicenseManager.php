<?php

declare(strict_types=1);

namespace App\Services\Licensing;

use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class LicenseManager
{
    /**
     * In-memory cache for the verification result within a single request.
     *
     * @var array<string, mixed>|null
     */
    protected static ?array $cachedStatus = null;

    /**
     * Get path to the license file.
     */
    public static function getLicenseFilePath(): string
    {
        return config('license.license_file', storage_path('license/license.lic'));
    }

    /**
     * Get path to the public key.
     * Hardcoded to storage/license/license_public.key inside this encrypted file
     * to prevent an unauthorized user from altering config/license.php to point to a fake key.
     */
    public static function getPublicKeyPath(): string
    {
        return storage_path('license/license_public.key');
    }

    /**
     * Get path to the anti-rollback state file.
     */
    public static function getStateFilePath(): string
    {
        return storage_path('license/.sys_state');
    }

    /**
     * Generate an RSA Keypair for the licensing system.
     *
     * @param int $bits Key length (2048 or 4096)
     * @return array{private_key: string, public_key: string}
     */
    public static function generateKeypair(int $bits = 2048): array
    {
        $config = [
            'digest_alg' => 'sha256',
            'private_key_bits' => $bits,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        // On Windows, openssl.cnf location is often not in default compiled path
        $possibleCnfPaths = [
            getenv('OPENSSL_CONF') ?: null,
            'C:\\xampp\\apache\\conf\\openssl.cnf',
            'C:\\xampp\\php\\extras\\ssl\\openssl.cnf',
            'C:\\xampp\\php\\extras\\openssl\\openssl.cnf',
        ];

        foreach ($possibleCnfPaths as $cnfPath) {
            if ($cnfPath && File::exists($cnfPath)) {
                $config['config'] = $cnfPath;
                putenv('OPENSSL_CONF=' . $cnfPath);
                break;
            }
        }

        $res = openssl_pkey_new($config);
        if ($res === false) {
            throw new \RuntimeException('Failed to generate OpenSSL private key: ' . openssl_error_string());
        }

        $exportConfig = !empty($config['config']) ? ['config' => $config['config']] : null;
        $exported = openssl_pkey_export($res, $privateKey, null, $exportConfig);
        if (!$exported) {
            throw new \RuntimeException('Failed to export OpenSSL private key: ' . openssl_error_string());
        }

        $details = openssl_pkey_get_details($res);
        if ($details === false || empty($details['key'])) {
            throw new \RuntimeException('Failed to export OpenSSL public key.');
        }

        return [
            'private_key' => $privateKey,
            'public_key' => $details['key'],
        ];
    }

    /**
     * Vendor tool: Issue and cryptographically sign a new license.
     *
     * @param string $clientName e.g. "Rahul Clinic & Laboratory"
     * @param string $machineId e.g. "F500-AD79-0D79-F9A4"
     * @param string $expiresAt e.g. "2027-12-31 23:59:59"
     * @param string $privateKeyPem The RSA private key PEM string
     * @param array<string, mixed> $extra Optional extra metadata (features, branch, etc.)
     * @return string Formatted base64 license payload
     */
    public static function issueLicense(
        string $clientName,
        string $machineId,
        string $expiresAt,
        string $privateKeyPem,
        array $extra = []
    ): string {
        $expiryCarbon = Carbon::parse($expiresAt);

        $payload = [
            'client_name' => trim($clientName),
            'machine_id' => strtoupper(trim($machineId)),
            'issued_at' => Carbon::now()->toIso8601String(),
            'expires_at' => $expiryCarbon->toIso8601String(),
            'extra' => $extra,
        ];

        $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($payloadJson === false) {
            throw new \InvalidArgumentException('Failed to encode license payload.');
        }

        $privateKeyResource = openssl_pkey_get_private($privateKeyPem);
        if ($privateKeyResource === false) {
            throw new \InvalidArgumentException('Invalid private key provided: ' . openssl_error_string());
        }

        $signature = '';
        $signed = openssl_sign($payloadJson, $signature, $privateKeyResource, OPENSSL_ALGO_SHA256);
        if (!$signed) {
            throw new \RuntimeException('Failed to sign license: ' . openssl_error_string());
        }

        $bundle = [
            'version' => 1,
            'payload' => base64_encode($payloadJson),
            'signature' => base64_encode($signature),
        ];

        $encodedBundle = base64_encode(json_encode($bundle));

        // Format into readable multiline block
        return "-----BEGIN LM-LICENSE-----\n" .
            chunk_split($encodedBundle, 64, "\n") .
            "-----END LM-LICENSE-----\n";
    }

    /**
     * Verify the current system license.
     *
     * @param bool $fresh Force re-verification instead of using cached status
     * @return array{
     *     valid: bool,
     *     code: string,
     *     message: string,
     *     client_name: ?string,
     *     expires_at: ?string,
     *     days_left: ?int,
     *     is_expiring_soon: bool,
     *     current_machine_id: string,
     *     license_machine_id: ?string
     * }
     */
    public static function verify(bool $fresh = false): array
    {
        if (!$fresh && self::$cachedStatus !== null) {
            return self::$cachedStatus;
        }

        $currentMachineId = HardwareFingerprint::getMachineId();

        $defaultResult = [
            'valid' => false,
            'code' => 'UNKNOWN',
            'message' => 'License verification pending.',
            'client_name' => null,
            'expires_at' => null,
            'days_left' => null,
            'is_expiring_soon' => false,
            'current_machine_id' => $currentMachineId,
            'license_machine_id' => null,
        ];

        // 1. Check if public key exists
        $publicKeyPath = self::getPublicKeyPath();
        if (!File::exists($publicKeyPath)) {
            $defaultResult['code'] = 'PUBLIC_KEY_MISSING';
            $defaultResult['message'] = 'Licensing public key is missing from the system. Contact system vendor.';
            self::$cachedStatus = $defaultResult;
            return $defaultResult;
        }

        $publicKey = File::get($publicKeyPath);

        // 2. Check if license file exists
        $licensePath = self::getLicenseFilePath();
        if (!File::exists($licensePath)) {
            $defaultResult['code'] = 'LICENSE_MISSING';
            $defaultResult['message'] = 'No active license found. Please activate the software.';
            self::$cachedStatus = $defaultResult;
            return $defaultResult;
        }

        $licenseContent = File::get($licensePath);
        $parsed = self::parseLicenseString($licenseContent);
        if ($parsed === null) {
            $defaultResult['code'] = 'LICENSE_CORRUPT';
            $defaultResult['message'] = 'The installed license file format is corrupted or invalid.';
            self::$cachedStatus = $defaultResult;
            return $defaultResult;
        }

        // 3. Verify cryptographic RSA signature
        $publicKeyResource = openssl_pkey_get_public($publicKey);
        if ($publicKeyResource === false) {
            $defaultResult['code'] = 'PUBLIC_KEY_INVALID';
            $defaultResult['message'] = 'Licensing public key could not be loaded.';
            self::$cachedStatus = $defaultResult;
            return $defaultResult;
        }

        $signatureOk = openssl_verify(
            $parsed['raw_payload_json'],
            $parsed['binary_signature'],
            $publicKeyResource,
            OPENSSL_ALGO_SHA256
        );

        if ($signatureOk !== 1) {
            $defaultResult['code'] = 'SIGNATURE_INVALID';
            $defaultResult['message'] = 'License signature verification failed. The license file has been tampered with or is invalid.';
            self::$cachedStatus = $defaultResult;
            return $defaultResult;
        }

        $payload = $parsed['payload'];
        $defaultResult['client_name'] = $payload['client_name'] ?? 'Unknown';
        $defaultResult['license_machine_id'] = $payload['machine_id'] ?? null;
        $defaultResult['expires_at'] = $payload['expires_at'] ?? null;

        // 4. Verify Machine ID (Hardware Lock)
        $licenseMachineId = strtoupper(trim((string) ($payload['machine_id'] ?? '')));
        if ($licenseMachineId !== $currentMachineId) {
            $defaultResult['code'] = 'MACHINE_MISMATCH';
            $defaultResult['message'] = sprintf(
                'This license is locked to machine [%s], but this system is [%s]. Software can only run on the authorized hardware.',
                $licenseMachineId,
                $currentMachineId
            );
            self::$cachedStatus = $defaultResult;
            return $defaultResult;
        }

        // 5. Anti-Clock Rollback check
        $now = Carbon::now();
        if (self::hasClockBeenRolledBack($now)) {
            $defaultResult['code'] = 'CLOCK_ROLLBACK';
            $defaultResult['message'] = 'System clock manipulation detected. The system time is earlier than the last recorded activity. Please correct your system date & time.';
            self::$cachedStatus = $defaultResult;
            return $defaultResult;
        }

        // 6. Check Expiry Date
        if (empty($payload['expires_at'])) {
            $defaultResult['code'] = 'EXPIRES_AT_MISSING';
            $defaultResult['message'] = 'License expiration date is not defined.';
            self::$cachedStatus = $defaultResult;
            return $defaultResult;
        }

        $expiresAt = Carbon::parse($payload['expires_at']);
        $graceDays = (int) config('license.grace_period_days', 0);
        $hardCutoff = $expiresAt->copy()->addDays($graceDays);

        if ($now->greaterThan($hardCutoff)) {
            $defaultResult['code'] = 'EXPIRED';
            $defaultResult['days_left'] = 0;
            $defaultResult['message'] = sprintf(
                'Your software license expired on %s. The system is locked. Please contact your vendor for renewal.',
                $expiresAt->format('d M Y, h:i A')
            );
            self::$cachedStatus = $defaultResult;
            return $defaultResult;
        }

        // Calculate days left
        $daysLeft = (int) ceil($now->floatDiffInDays($expiresAt, false));
        $warningDays = (int) config('license.warning_days', 15);

        $defaultResult['valid'] = true;
        $defaultResult['code'] = 'ACTIVE';
        $defaultResult['days_left'] = max(0, $daysLeft);
        $defaultResult['is_expiring_soon'] = ($daysLeft <= $warningDays);
        $defaultResult['message'] = sprintf(
            'License is active for %s. Valid until %s (%d day%s left).',
            $defaultResult['client_name'],
            $expiresAt->format('d M Y'),
            $daysLeft,
            $daysLeft === 1 ? '' : 's'
        );

        // Update last run timestamp for anti-tamper
        self::recordActivityTimestamp($now);

        self::$cachedStatus = $defaultResult;
        return $defaultResult;
    }

    /**
     * Install or replace the license.
     */
    public static function install(string $rawLicenseContent): array
    {
        $parsed = self::parseLicenseString($rawLicenseContent);
        if ($parsed === null) {
            return [
                'success' => false,
                'message' => 'Invalid license format. Please paste the entire license text including headers.',
            ];
        }

        $targetPath = self::getLicenseFilePath();
        $targetDir = dirname($targetPath);

        if (!File::isDirectory($targetDir)) {
            File::makeDirectory($targetDir, 0755, true);
        }

        File::put($targetPath, trim($rawLicenseContent));
        self::$cachedStatus = null;

        $check = self::verify(true);
        if (!$check['valid']) {
            return [
                'success' => false,
                'message' => 'License was saved, but verification failed: ' . $check['message'],
                'details' => $check,
            ];
        }

        return [
            'success' => true,
            'message' => 'License activated successfully! Valid until ' . Carbon::parse($check['expires_at'])->format('d M Y'),
            'details' => $check,
        ];
    }

    /**
     * Parse raw license text into decoded payload and binary signature.
     *
     * @return array{payload: array<string, mixed>, raw_payload_json: string, binary_signature: string}|null
     */
    public static function parseLicenseString(string $content): ?array
    {
        $content = trim($content);

        // Strip headers if present
        $clean = preg_replace('/-----BEGIN [A-Z0-9_\-]+-----/i', '', $content);
        $clean = preg_replace('/-----END [A-Z0-9_\-]+-----/i', '', (string) $clean);
        $clean = preg_replace('/\s+/', '', (string) $clean);

        if (empty($clean)) {
            return null;
        }

        $bundleJson = base64_decode((string) $clean, true);
        if ($bundleJson === false) {
            return null;
        }

        $bundle = json_decode($bundleJson, true);
        if (!is_array($bundle) || empty($bundle['payload']) || empty($bundle['signature'])) {
            return null;
        }

        $payloadJson = base64_decode((string) $bundle['payload'], true);
        $signature = base64_decode((string) $bundle['signature'], true);

        if ($payloadJson === false || $signature === false) {
            return null;
        }

        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            return null;
        }

        return [
            'payload' => $payload,
            'raw_payload_json' => $payloadJson,
            'binary_signature' => $signature,
        ];
    }

    /**
     * Check if system clock has been rolled backwards.
     */
    protected static function hasClockBeenRolledBack(Carbon $now): bool
    {
        // 1. Check persistent state file
        $stateFile = self::getStateFilePath();
        if (File::exists($stateFile)) {
            $content = trim((string) File::get($stateFile));
            if (!empty($content)) {
                $decoded = base64_decode($content, true);
                if ($decoded !== false) {
                    $lastTimestamp = (int) $decoded;
                    if ($lastTimestamp > 0) {
                        $lastTime = Carbon::createFromTimestamp($lastTimestamp);
                        // Allow 30 minutes of natural clock drift / NTP adjustment
                        if ($now->timestamp < ($lastTime->timestamp - 1800)) {
                            Log::warning('License clock rollback detected via state file', [
                                'now' => $now->toIso8601String(),
                                'last_recorded' => $lastTime->toIso8601String(),
                            ]);
                            return true;
                        }
                    }
                }
            }
        }

        // 2. Database History Cross-Check (prevents bypass by simply deleting .sys_state)
        try {
            $latestBilling = \Illuminate\Support\Facades\DB::table('patient_billing')
                ->whereNotNull('created_at')
                ->latest('created_at')
                ->value('created_at');

            if ($latestBilling) {
                $lastDbTime = Carbon::parse($latestBilling);
                if ($now->timestamp < ($lastDbTime->timestamp - 1800)) {
                    Log::warning('License clock rollback detected via database history', [
                        'now' => $now->toIso8601String(),
                        'latest_db_record' => $lastDbTime->toIso8601String(),
                    ]);
                    return true;
                }
            }
        } catch (\Throwable) {
            // DB not ready or during fresh installation
        }

        return false;
    }

    /**
     * Record last activity timestamp to prevent system clock rollbacks.
     */
    protected static function recordActivityTimestamp(Carbon $now): void
    {
        $stateFile = self::getStateFilePath();
        $stateDir = dirname($stateFile);

        if (!File::isDirectory($stateDir)) {
            File::makeDirectory($stateDir, 0755, true);
        }

        File::put($stateFile, base64_encode((string) $now->timestamp));
    }
}
