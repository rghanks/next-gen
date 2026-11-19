<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Licensing\LicenseManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyLicense
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Skip excluded routes (activation page, uploads, etc.)
        if ($this->shouldSkip($request)) {
            return $next($request);
        }

        // 2. Perform verification
        $status = LicenseManager::verify();

        if (!$status['valid']) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'error' => 'LICENSE_REQUIRED',
                    'code' => $status['code'],
                    'message' => $status['message'],
                    'machine_id' => $status['current_machine_id'],
                ], Response::HTTP_FORBIDDEN);
            }

            return redirect()
                ->route('license.activate')
                ->with('license_error', $status['message'])
                ->with('license_code', $status['code']);
        }

        // 3. Share license status with all Blade views for expiring soon banners
        view()->share('licenseStatus', $status);

        return $next($request);
    }

    /**
     * Determine if verification should be skipped for this request.
     */
    protected function shouldSkip(Request $request): bool
    {
        // Skip debugbar / static / health check routes
        if ($request->is('_debugbar/*', 'sanctum/*', 'up')) {
            return true;
        }

        $excludedNames = config('license.excluded_routes', [
            'license.activate',
            'license.submit',
            'license.upload',
            'license.machine-id',
        ]);

        foreach ($excludedNames as $name) {
            if ($request->routeIs($name)) {
                return true;
            }
        }

        return false;
    }
}
