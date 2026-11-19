<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Licensing\LicenseManager;

abstract class Controller
{
    /**
     * Execute an action on the controller.
     * Dual-layer license protection: Enforces licensing at the controller level
     * even if someone removes the VerifyLicense middleware from bootstrap/app.php.
     */
    public function callAction($method, $parameters)
    {
        // Don't lock the license activation controller itself
        if (! ($this instanceof LicenseController)) {
            $status = LicenseManager::verify();

            if (! $status['valid']) {
                if (request()->expectsJson() || request()->is('api/*') || request()->is('lab/api/*')) {
                    abort(response()->json([
                        'success' => false,
                        'error' => 'LICENSE_REQUIRED',
                        'message' => $status['message'],
                        'machine_id' => $status['current_machine_id'],
                    ], 403));
                }

                return redirect()
                    ->route('license.activate')
                    ->with('license_error', $status['message'])
                    ->with('license_code', $status['code']);
            }
        }

        return $this->{$method}(...array_values($parameters));
    }
}
