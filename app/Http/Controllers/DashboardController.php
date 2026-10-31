<?php

namespace App\Http\Controllers;

use App\Models\License;
use App\Support\Downloads;
use App\Support\InstallCatalog;
use App\Support\LicensePolicy\PolicyResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Signed-in area: overview and the guided installer. Licences and Profile are Livewire pages. */
class DashboardController extends Controller
{
    public function overview(Request $request): View
    {
        $user = $request->user();
        License::forgetExpiredKeys();

        // Results of the signed links in emails (verify address, unsubscribe from releases).
        $notices = [
            'verified' => [
                '1' => ['success', 'Email verified. Thanks!'],
                'expired' => ['error', 'That verification link has expired. Send a new one below.'],
                'invalid' => ['error', 'That verification link is not valid. Send a new one below.'],
            ],
            'unsubscribed' => [
                '1' => ['success', 'You will no longer get emails about new releases.'],
                'invalid' => ['error', 'That unsubscribe link is not valid. Change it in your profile instead.'],
            ],
        ];
        $notice = null;
        foreach ($notices as $param => $messages) {
            if ($request->filled($param)) {
                $notice = $messages[$request->query($param)] ?? $messages['invalid'];
            }
        }

        $all = Downloads::all();

        return view('dashboard.overview', [
            'user' => $user,
            'notice' => $notice,
            'cliVersion' => $all['cli']['releases'][0]['version'] ?? null,
            'consoleVersion' => $all['console']['releases'][0]['version'] ?? null,
            'licences' => $user->licenses()->with('activation', 'tokenable')->latest('id')->get(),
            'limit' => $user->licenseLimit(),
        ]);
    }

    public function install(Request $request, ?string $product = null, ?string $target = null): View|RedirectResponse
    {
        $catalog = InstallCatalog::product($product);
        if ($product !== null && ! $catalog) {
            return redirect()->route('dashboard.install');
        }

        $all = Downloads::all();
        $targetInfo = $catalog['targets'][$target] ?? null;
        $mode = $request->query('mode') === 'update' ? 'update' : 'install';

        return view('dashboard.install', [
            'productId' => $product,
            'product' => $catalog,
            'targetId' => $target,
            'target' => $targetInfo && $targetInfo['status'] === 'ready' ? $targetInfo : null,
            'mode' => $mode,
            'versions' => array_map(fn ($p) => $p['releases'][0]['version'] ?? null, $all),
            'steps' => $catalog && $targetInfo && $targetInfo['status'] === 'ready'
                ? InstallCatalog::steps($product, $target, $mode)
                : [],
        ]);
    }

    public function resendVerification(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return back()->with('status', 'Email already verified.');
        }
        if (! $user->hasDeliverableEmail()) {
            return back()->with('error', 'Your account has no deliverable email address. Add a public email on GitHub and sign in again.');
        }
        if (! $user->sendVerificationSafely()) {
            return back()->with('error', 'Could not send the verification email. Please try again later.');
        }

        return back()->with('status', "Verification email sent to {$user->email}.");
    }
    /**
     * Remote Tunnel connector downloads: listed only for a licence that includes remote_tunnel
     * (via Premium Features or individually); others see a locked page with an upgrade link.
     */
    public function connector(Request $request): View
    {
        $source = PolicyResolver::featureSource($request->user(), 'remote_tunnel');

        return view('dashboard.connector', [
            'source' => $source,
            'releases' => $source ? (array) config('connector.releases') : [],
            'compatibility' => (array) config('connector.compatibility'),
            'image' => (string) config('connector.image'),
            'gatewayImage' => (string) config('connector.gateway_image'),
        ]);
    }

    /** A connector file, after the licence check on the server (never a public link). */
    public function connectorFile(Request $request, string $version, string $file): RedirectResponse
    {
        abort_unless(PolicyResolver::includes($request->user(), 'remote_tunnel'), 403, 'Your licence does not include Remote Tunnel.');
        $release = collect((array) config('connector.releases'))->firstWhere('version', $version);
        $entry = $release['files'][$file] ?? null;
        abort_if($entry === null, 404);

        return redirect()->away(config('connector.base_url').'/'.rawurlencode($version).'/'.rawurlencode($entry['file']));
    }
}
