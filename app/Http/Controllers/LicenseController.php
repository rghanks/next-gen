<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Licensing\HardwareFingerprint;
use App\Services\Licensing\LicenseManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LicenseController extends Controller
{
    /**
     * Show the license activation / management screen.
     */
    public function activate(): View
    {
        $status = LicenseManager::verify(true);
        $machineId = HardwareFingerprint::getMachineId();

        return view('license.activate', [
            'status' => $status,
            'machineId' => $machineId,
        ]);
    }

    /**
     * Process license submission (file upload or pasted string).
     */
    public function submit(Request $request): RedirectResponse
    {
        $content = '';

        if ($request->hasFile('license_file')) {
            $file = $request->file('license_file');
            if ($file && $file->isValid()) {
                $content = (string) file_get_contents($file->getRealPath());
            }
        } elseif ($request->filled('license_key')) {
            $content = (string) $request->input('license_key');
        }

        if (empty(trim($content))) {
            return redirect()
                ->route('license.activate')
                ->with('error', 'Please provide a license file or paste the license key block.');
        }

        $result = LicenseManager::install($content);

        if ($result['success']) {
            return redirect()
                ->route('lab.billing.index')
                ->with('success', $result['message']);
        }

        return redirect()
            ->route('license.activate')
            ->with('error', $result['message']);
    }

    /**
     * Return machine ID as JSON.
     */
    public function machineId(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'machine_id' => HardwareFingerprint::getMachineId(),
        ]);
    }
}
