<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\LicenceManager;
use App\Support\LicenseFile\Issuer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * POST /api/licenses/{id}/file (Sanctum) and POST /dashboard/licences/{id}/file (web session):
 * downloads the licence file (.atgl) of one of the user's licences, for a Management Console
 * without internet access. The Installation ID is required or optional per policy; with one, the
 * file works on that console only.
 *
 * Limits, features and validity come from the user's policy; the request may only carry the
 * Installation ID.
 */
class LicenseFileController extends Controller
{
    public function generate(Request $request, int $id): Response|RedirectResponse
    {
        $request->validate(['install_id' => ['nullable', 'string', 'max:64']]);

        try {
            $licence = LicenceManager::for($request->user())->find($id);
            $issued = Issuer::fromConfig()->issue($licence, $request->input('install_id'));
        } catch (HttpExceptionInterface $e) {
            // The dashboard form gets its error back on the page instead of an error page.
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return back()->withInput()->withErrors(['license_file' => $e->getMessage(), 'file_licence_id' => (string) $id]);
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
