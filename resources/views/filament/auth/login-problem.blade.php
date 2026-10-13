{{--
    Admin sign-in safety net. Without it these cases just show the login form again with no reason:
    - Livewire did not load (e.g. the site is served from a sub-folder, so /livewire/livewire.js is a 404):
      the form then does a plain reload. We block that and say what is wrong.
    - The page expired (HTTP 419: CSRF token too old, or the session changed in another tab):
      reload with ?expired=1 so App\Filament\Pages\Auth\Login shows a notice.
--}}
<div id="ag-login-problem" hidden role="alert"
    style="border-radius: .5rem; background: #FDECEC; color: #A42F2F; border: 1px solid #F3C2C2; padding: .75rem 1rem; font-size: .875rem; line-height: 1.4;">
    <strong>Sign-in cannot start.</strong>
    The page scripts did not load, so the Sign in button cannot send your details.
    Reload the page. If this keeps happening, check that the site runs from the web root and that
    <code>APP_URL</code> in <code>.env</code> matches the address in your browser.
</div>
<script>
    (() => {
        const problem = () => document.getElementById('ag-login-problem');
        const showProblem = () => { const el = problem(); if (el) el.hidden = false; };

        document.addEventListener('livewire:init', () => {
            window.Livewire.hook('request', ({ fail }) => {
                fail(({ status, preventDefault }) => {
                    if (status !== 419) return;
                    preventDefault();
                    const url = new URL(window.location.href);
                    url.searchParams.set('expired', '1');
                    window.location.replace(url.toString());
                });
            });
        });

        // A plain form submit only reloads this page; stop it when Livewire is missing.
        document.addEventListener('submit', (event) => {
            if (!window.Livewire) {
                event.preventDefault();
                showProblem();
            }
        }, true);

        window.addEventListener('load', () => { if (!window.Livewire) showProblem(); });
    })();
</script>
