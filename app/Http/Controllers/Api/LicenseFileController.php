<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\LicenseFile\Issuer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Licence files (.atgl) for the signed-in user. Licences are files only.
 *
 * - POST /api/license-files/code: emails the 5-digit code (when the policy requires it).
 * - POST /api/license-files and POST /dashboard/licences/file: downloads a new file. The body may
 *   carry only `install_id` (required or optional per policy) and `code`; limits, features and
 *   validity come from the user's policy.
 */
class LicenseFileController extends Controller
{
    public function code(Request $request): JsonResponse
    {
        Issuer::sendCode($request->user());

        return response()->json(['message' => "We sent a 5-digit code to {$request->user()->email}. It expires in 10 minutes."]);
    }

    public function generate(Request $request): Response|RedirectResponse
    {
        $request->validate([
            'install_id' => ['nullable', 'string', 'max:64'],
            'code' => ['nullable', 'string', 'max:10'],
        ]);

        try {
            $issued = Issuer::fromConfig()->issue($request->user(), $request->input('install_id'), $request->input('code'));
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
