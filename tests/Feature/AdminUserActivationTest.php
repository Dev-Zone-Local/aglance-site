<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminUserActivationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_admin_activates_user_from_edit_form(): void
    {
        $user = User::factory()->unverified()->create();

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->assertSchemaStateSet(['email_verified_at' => false])
            ->fillForm(['email_verified_at' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_saving_keeps_original_verification_time(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()->subYear()]);
        $original = $user->email_verified_at->toDateTimeString();

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm(['name' => 'Renamed'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($original, $user->fresh()->email_verified_at->toDateTimeString());
    }

    public function test_admin_can_deactivate(): void
    {
        $user = User::factory()->create();

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm(['email_verified_at' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_activate_row_action(): void
    {
        $unverified = User::factory()->unverified()->create();
        $verified = User::factory()->create();

        Livewire::test(ListUsers::class)
            ->assertTableActionVisible('verifyEmail', $unverified)
            ->assertTableActionHidden('verifyEmail', $verified)
            ->callTableAction('verifyEmail', $unverified)
            ->assertNotified("{$unverified->email} activated");

        $this->assertTrue($unverified->fresh()->hasVerifiedEmail());
    }
}
