<?php

namespace App\Providers\Filament;

use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            // Profile page (name, email, password) + links back to the site in the user menu.
            ->profile(isSimple: false)
            ->userMenuItems([
                Action::make('dashboard')
                    ->label('My dashboard')
                    ->icon(Heroicon::OutlinedSquares2x2)
                    ->url(fn () => rtrim(config('atglance.frontend_url'), '/').'/dashboard'),
                Action::make('website')
                    ->label('Back to website')
                    ->icon(Heroicon::OutlinedGlobeAlt)
                    ->url(fn () => config('atglance.frontend_url')),
            ])
            // AtGlance design system (design-doc.md): light only, teal/mint brand, Inter, console logo.
            ->brandName('AtGlance CMS')
            ->brandLogo('/branding/atglance-logo.png')
            ->brandLogoHeight('2rem')
            ->favicon('/branding/favicon.ico')
            ->font('Inter')
            ->darkMode(false)
            ->colors([
                'primary' => Color::hex('#2CB7D9'),
                'success' => Color::hex('#1FA874'),
                'warning' => Color::hex('#D98A0B'),
                'danger' => Color::hex('#E45757'),
                'info' => Color::hex('#2CB7D9'),
                'gray' => Color::Slate,
            ])
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): View => view('filament.theme-styles'))
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn (): View => view('filament.auth.github-login-button'),
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
