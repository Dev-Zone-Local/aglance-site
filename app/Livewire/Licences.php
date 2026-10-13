<?php

namespace App\Livewire;

use App\Models\License;
use App\Models\User;
use App\Support\LicenceManager;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Dashboard → Licences. Create a licence (key shown once, re-viewable for 30 minutes),
 * activate it with the emailed 5-digit code, and revoke it with the password or a code.
 */
#[Layout('components.layouts.app')]
#[Title('Licences')]
class Licences extends Component
{
    public string $name = '';

    /** Licence waiting for its emailed code. */
    public ?int $pendingId = null;

    public string $pendingMessage = '';

    public string $code = '';

    /** Key on screen: ['id', 'name', 'key', 'visible_until', 'needs_approval']. */
    public ?array $shownKey = null;

    public ?int $revokingId = null;

    public string $revokeMode = 'password';

    public string $revokePassword = '';

    public string $revokeCode = '';

    public ?string $revokeCodeSent = null;

    public function create(): void
    {
        $this->validate(['name' => ['required', 'string', 'max:100']], [], ['name' => 'licence name']);
        if (! $this->throttle('create', 5)) {
            return;
        }

        $this->attempt(function () {
            $result = $this->manager()->create($this->name);
            $licence = $result['licence'];

            $this->showKeyFor($licence, $result['key']);
            $this->pendingId = $licence->id;
            $this->pendingMessage = $result['code_sent']
                ? "We sent a 5-digit code to {$this->user()->email}."
                : 'We could not send the verification email. Use "Resend code" to try again.';
            $this->reset('name', 'code');
        });
    }

    public function confirm(): void
    {
        $this->validate(['code' => ['required', 'digits:5']], [], ['code' => 'code']);
        if (! $this->throttle('confirm', 10)) {
            return;
        }

        $this->attempt(function () {
            $manager = $this->manager();
            $licence = $manager->confirm($manager->find($this->pendingId), $this->code);
            $this->toast(LicenceManager::confirmedMessage($licence));
            $this->reset('pendingId', 'pendingMessage', 'code');
        });
    }

    public function resendCode(): void
    {
        if (! $this->pendingId || ! $this->throttle('confirm-code', 3)) {
            return;
        }

        $this->attempt(function () {
            $manager = $this->manager();
            $manager->sendConfirmCode($manager->find($this->pendingId));
            $this->pendingMessage = $manager->codeSentMessage();
            $this->toast($this->pendingMessage);
        });
    }

    /** Resume an unconfirmed licence from the list. */
    public function enterCode(int $id): void
    {
        $this->attempt(function () use ($id) {
            $this->pendingId = $this->manager()->find($id)->id;
            $this->pendingMessage = 'Enter the 5-digit code we emailed you.';
            $this->reset('code');
            $this->resetErrorBag();
        });
    }

    /** Show a key again (allowed for 30 minutes after creation). */
    public function showKey(int $id): void
    {
        if (! $this->throttle('key', 20)) {
            return;
        }

        $this->attempt(function () use ($id) {
            $manager = $this->manager();
            $licence = $manager->find($id);
            $this->showKeyFor($licence, $manager->plainKey($licence));
        });
    }

    public function dismissKey(): void
    {
        $this->shownKey = null;
    }

    public function startRevoke(int $id): void
    {
        $this->attempt(function () use ($id) {
            $this->revokingId = $this->manager()->find($id)->id;
            $this->revokeMode = $this->user()->password ? 'password' : 'code';
            $this->reset('revokePassword', 'revokeCode', 'revokeCodeSent');
            $this->resetErrorBag();
        });
    }

    public function cancelRevoke(): void
    {
        $this->reset('revokingId', 'revokePassword', 'revokeCode', 'revokeCodeSent');
        $this->resetErrorBag();
    }

    public function sendRevokeCode(): void
    {
        if (! $this->revokingId || ! $this->throttle('revoke-code', 3)) {
            return;
        }

        $this->attempt(function () {
            $manager = $this->manager();
            $manager->sendRevokeCode($manager->find($this->revokingId));
            $this->revokeCodeSent = $manager->codeSentMessage();
            $this->revokeMode = 'code';
            $this->revokeCode = '';
        });
    }

    public function usePassword(): void
    {
        $this->revokeMode = 'password';
        $this->resetErrorBag();
    }

    public function revoke(): void
    {
        if (! $this->revokingId || ! $this->throttle('revoke', 10)) {
            return;
        }
        if ($this->revokeMode === 'code') {
            $this->validate(['revokeCode' => ['required', 'digits:5']], [], ['revokeCode' => 'code']);
        } else {
            $this->validate(['revokePassword' => ['required', 'string']], [], ['revokePassword' => 'password']);
        }

        $this->attempt(function () {
            $manager = $this->manager();
            $licence = $manager->find($this->revokingId);
            try {
                $manager->revoke(
                    $licence,
                    $this->revokeMode === 'password' ? $this->revokePassword : null,
                    $this->revokeMode === 'code' ? $this->revokeCode : null,
                );
            } catch (ValidationException $e) {
                // Show the error under the field on this page.
                $field = $this->revokeMode === 'code' ? 'revokeCode' : 'revokePassword';
                $this->addError($field, collect($e->errors())->flatten()->first());

                return;
            }

            $this->toast("Licence \"{$licence->name}\" revoked");
            if ($this->shownKey && $this->shownKey['id'] === $licence->id) {
                $this->shownKey = null;
            }
            if ($this->pendingId === $licence->id) {
                $this->reset('pendingId', 'pendingMessage', 'code');
            }
            $this->cancelRevoke();
        });
    }

    public function render(): View
    {
        License::forgetExpiredKeys();
        $user = $this->user();
        $licences = $user->licenses()->with('activation', 'tokenable')->latest('id')->get();

        return view('livewire.licences', [
            'user' => $user,
            'licences' => $licences,
            'limit' => $user->licenseLimit(),
            'atLimit' => $this->manager()->atLimit(),
            'pending' => $this->pendingId ? $licences->firstWhere('id', $this->pendingId) : null,
            'revoking' => $this->revokingId ? $licences->firstWhere('id', $this->revokingId) : null,
        ]);
    }

    private function showKeyFor(License $licence, string $key): void
    {
        $this->shownKey = [
            'id' => $licence->id,
            'name' => $licence->name,
            'key' => $key,
            'visible_until' => $licence->key_visible_until?->toIso8601String(),
            'needs_approval' => $licence->requiresApproval() && ! $licence->isApproved(),
        ];
    }

    /** Run an action; show HTTP errors (limit reached, email failed, ...) as a toast. */
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

    private function manager(): LicenceManager
    {
        return LicenceManager::for($this->user());
    }

    private function user(): User
    {
        return auth()->user();
    }
}
