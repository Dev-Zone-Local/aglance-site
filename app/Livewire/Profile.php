<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Dashboard → Profile: display name, password, release emails. */
#[Layout('components.layouts.app')]
#[Title('Profile')]
class Profile extends Component
{
    public string $name = '';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $notifyUpdates = false;

    public function mount(): void
    {
        $this->name = (string) $this->user()->name;
        $this->notifyUpdates = (bool) $this->user()->notify_updates;
    }

    public function saveName(): void
    {
        $data = $this->validate(['name' => ['required', 'string', 'max:255']]);
        $this->user()->forceFill(['name' => trim($data['name'])])->save();
        $this->dispatch('toast', message: 'Profile saved', type: 'success');
    }

    /**
     * Needs the current password when the account has one (GitHub-only accounts can set a
     * first password). Signs out other sessions.
     */
    public function savePassword(): void
    {
        $user = $this->user();
        $key = 'profile:password:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->dispatch('toast', message: 'Too many attempts. Wait a minute and try again.', type: 'error');

            return;
        }
        RateLimiter::hit($key, 60);

        $this->validate([
            'current_password' => [$user->password ? 'required' : 'nullable', 'string'],
            'password' => ['required', 'string', 'min:6', 'max:128', 'confirmed'],
        ], ['password.confirmed' => 'Passwords do not match']);

        if ($user->password && ! Hash::check($this->current_password, $user->password)) {
            $this->addError('current_password', 'The current password is incorrect.');

            return;
        }

        $user->forceFill(['password' => $this->password])->save();
        DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', session()->getId())->delete();

        $this->reset('current_password', 'password', 'password_confirmation');
        $this->dispatch('toast', message: 'Password updated.', type: 'success');
    }

    /** Checkbox: email me about new releases. */
    public function updatedNotifyUpdates(bool $value): void
    {
        $this->user()->forceFill(['notify_updates' => $value])->save();
        $this->dispatch('toast', message: $value ? 'You will get emails about new releases.' : 'Release emails turned off.', type: 'success');
    }

    public function render(): View
    {
        return view('livewire.profile', ['user' => $this->user()]);
    }

    private function user(): User
    {
        return auth()->user();
    }
}
