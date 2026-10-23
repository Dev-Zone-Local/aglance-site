<?php

namespace App\Livewire;

use App\Models\FeatureCatalog;
use App\Models\User;
use App\Support\LicenseFile\Issuer;
use App\Support\LicensePolicy\PolicyResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Dashboard → Licences. Management Console licences are licence files (.atgl): the user
 * generates one (with the console's Installation ID and an emailed code when the policy asks
 * for them) and uploads it in the console. Each file counts toward the account's limit until
 * it expires.
 *
 * The download itself is a plain form POST to dashboard.licences.file; this component only
 * sends the emailed code and shows the policy and the issued files.
 */
#[Layout('components.layouts.app')]
#[Title('Licences')]
class Licences extends Component
{
    /** "We sent a 5-digit code to …" once a code was emailed. */
    public string $codeMessage = '';

    public function sendCode(): void
    {
        if (! $this->throttle('code', 3)) {
            return;
        }

        $this->attempt(function () {
            Issuer::sendCode($this->user());
            $this->codeMessage = "We sent a 5-digit code to {$this->user()->email}. It expires in 10 minutes.";
            $this->toast($this->codeMessage);
        });
    }

    public function render(): View
    {
        $user = $this->user();
        $policy = PolicyResolver::for($user);
        $active = Issuer::activeCount($user);

        return view('livewire.licences', [
            'user' => $user,
            'policy' => $policy,
            'featureNames' => FeatureCatalog::options(),
            'licenceFiles' => $user->licenseFiles()->latest('issued_at')->limit(50)->get(),
            'activeCount' => $active,
            'limit' => $policy['max_files'],
            'atLimit' => $active >= $policy['max_files'],
            'limitMessage' => Issuer::limitMessage($policy['max_files']),
        ]);
    }

    private function attempt(callable $action): void
    {
        try {
            $action();
        } catch (HttpExceptionInterface $e) {
            $this->toast($e->getMessage() ?: 'Something went wrong. Please try again.', 'error');
        }
    }

    private function throttle(string $action, int $perMinute): bool
    {
        $key = "licences:{$action}:".$this->user()->id;
        if (RateLimiter::tooManyAttempts($key, $perMinute)) {
            $this->toast('Too many attempts. Wait a minute and try again.', 'error');

            return false;
        }
        RateLimiter::hit($key, 60);

        return true;
    }

    private function toast(string $message, string $type = 'success'): void
    {
        $this->dispatch('toast', message: $message, type: $type);
    }

    private function user(): User
    {
        return auth()->user();
    }
}
