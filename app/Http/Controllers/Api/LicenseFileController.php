<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\LicenseFile\Issuer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * POST /api/license/generate (Sanctum) and POST /dashboard/licences/file (web session):
 * downloads a new offline licence file (.atgl) for the signed-in user.
 *
 * All licence values come from the account and plan. The body may only carry an optional
 * `install_id` (the console's Installation ID) to bind the file to one console.
 */
class LicenseFileController extends Controller
{
    public function generate(Request $request): Response|RedirectResponse
    {
        $request->validate(['install_id' => ['nullable', 'string', 'max:64']]);

        try {
            $issued = Issuer::fromConfig()->issue($request->user(), $request->input('install_id'));
        } catch (HttpExceptionInterface $e) {
            // The dashboard form gets its error back on the page instead of an error page.
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return back()->withInput()->withErrors(['license_file' => $e->getMessage()]);
            }
            throw $e;
        }

        return response($issued['bytes'], 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="atglance-'.$issued['license']->license_id.'.atgl"',
            'Content-Length' => (string) strlen($issued['bytes']),
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
