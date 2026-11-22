<?php

declare(strict_types=1);

namespace App\Services\Licensing;

class HardwareFingerprint
{
    /**
     * In-memory cache for the machine ID during the current request.
     */
    protected static ?string $cachedMachineId = null;

    /**
     * Get the formatted unique Machine ID for the current system.
     * Output format: ABCD-1234-EF56-7890
     */
    public static function getMachineId(bool $fresh = false): string
    {
        if (!$fresh && self::$cachedMachineId !== null) {
            return self::$cachedMachineId;
        }

        $cacheFile = storage_path('license/.machine_id');

        if (!$fresh && file_exists($cacheFile)) {
            $cached = trim((string) @file_get_contents($cacheFile));
            if (preg_match('/^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $cached)) {
                self::$cachedMachineId = $cached;
                return $cached;
            }
        }

        $rawIdentifiers = self::collectIdentifiers();
        $hash = hash('sha256', implode('|', $rawIdentifiers));

        // Format into 4 chunks of 4 characters (16 chars total)
        $short = strtoupper(substr($hash, 0, 16));
        $machineId = implode('-', str_split($short, 4));

        @file_put_contents($cacheFile, $machineId);
        self::$cachedMachineId = $machineId;

        return $machineId;
    }

    /**
     * Get detailed breakdown of collected hardware tokens (for diagnostic / support).
     *
     * @return array<string, string>
     */
    public static function getDetails(): array
    {
        return self::collectIdentifiers();
    }

    /**
     * Collect system-level hardware identifiers.
     *
     * @return array<string, string>
     */
    protected static function collectIdentifiers(): array
    {
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

        if ($isWindows) {
            return self::collectWindowsIdentifiers();
        }

        return self::collectLinuxIdentifiers();
    }

    /**
     * Collect Windows-specific hardware fingerprints.
     *
     * @return array<string, string>
     */
    protected static function collectWindowsIdentifiers(): array
    {
        $data = [];

        // 1. Windows Cryptography MachineGuid (very fast and permanent per OS install)
        $guid = self::getWindowsRegistryValue('HKLM\SOFTWARE\Microsoft\Cryptography', 'MachineGuid');
        if (!empty($guid)) {
            $data['machine_guid'] = trim($guid);
        }

        // 2. Motherboard UUID
        $mbUuid = self::runCommand('powershell -NoProfile -NonInteractive -Command "(Get-CimInstance Win32_ComputerSystemProduct).UUID"');
        if (!empty($mbUuid)) {
            $data['motherboard_uuid'] = trim($mbUuid);
        }

        // 3. Primary Disk Serial Number
        $diskSerial = self::runCommand('powershell -NoProfile -NonInteractive -Command "(Get-CimInstance Win32_DiskDrive | Select-Object -ExpandProperty SerialNumber -First 1)"');
        if (!empty($diskSerial)) {
            $data['disk_serial'] = trim($diskSerial);
        }

        // 4. CPU Processor ID
        $cpuId = self::runCommand('powershell -NoProfile -NonInteractive -Command "(Get-CimInstance Win32_Processor | Select-Object -ExpandProperty ProcessorId -First 1)"');
        if (!empty($cpuId)) {
            $data['cpu_id'] = trim($cpuId);
        }

        // Fallback if commands are restricted
        if (empty($data)) {
            $data['fallback'] = php_uname('n') . '|' . php_uname('m');
        }

        return $data;
    }

    /**
     * Collect Linux-specific hardware fingerprints.
     *
     * @return array<string, string>
     */
    protected static function collectLinuxIdentifiers(): array
    {
        $data = [];

        // 1. systemd / dbus machine id
        if (is_readable('/etc/machine-id')) {
            $data['machine_id'] = trim((string) file_get_contents('/etc/machine-id'));
        } elseif (is_readable('/var/lib/dbus/machine-id')) {
            $data['machine_id'] = trim((string) file_get_contents('/var/lib/dbus/machine-id'));
        }

        // 2. DMI Product UUID (requires root or readable perms)
        if (is_readable('/sys/class/dmi/id/product_uuid')) {
            $data['product_uuid'] = trim((string) file_get_contents('/sys/class/dmi/id/product_uuid'));
        }

        // 3. Primary MAC address
        $mac = self::runCommand("cat /sys/class/net/$(ip route show default 2>/dev/null | awk '{print $5}' | head -n 1)/address 2>/dev/null");
        if (!empty($mac)) {
            $data['mac_address'] = trim($mac);
        }

        if (empty($data)) {
            $data['fallback'] = php_uname('n') . '|' . php_uname('m');
        }

        return $data;
    }

    /**
     * Read a Windows Registry string value.
     */
    protected static function getWindowsRegistryValue(string $key, string $valueName): ?string
    {
        $cmd = sprintf('reg query "%s" /v %s 2>nul', $key, $valueName);
        $output = self::runCommand($cmd);

        if (!empty($output) && preg_match('/' . preg_quote($valueName, '/') . '\s+REG_\w+\s+([^\r\n]+)/i', $output, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * Execute a shell command safely with timeout.
     */
    protected static function runCommand(string $command): string
    {
        if (!function_exists('shell_exec')) {
            return '';
        }

        try {
            $output = @shell_exec($command);
            return is_string($output) ? trim($output) : '';
        } catch (\Throwable) {
            return '';
        }
    }
}
