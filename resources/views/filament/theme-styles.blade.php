{{-- AtGlance look for the Filament admin (design-doc.md): glowing pale background, white borderless
     cards on soft shadows, rounded corners, and gradient primary buttons with ink text. --}}
<style>
    :root {
        --ag-ink: #14171b;
        --ag-bg: #eef1f4;
        --ag-surface: #f4f6f8;
        --ag-line: #e6e9ee;
        --ag-gradient: linear-gradient(135deg, #71f7d4 0%, #4cc9ff 100%);
        --ag-gradient-hover: linear-gradient(135deg, #5ff0c9 0%, #2fbaf2 100%);
        --ag-shadow: 0 8px 30px rgba(17, 20, 24, 0.06);
    }

    body.fi-body {
        color: var(--ag-ink);
        background:
            radial-gradient(900px 520px at 100% 0%, rgba(76, 201, 255, 0.22) 0%, rgba(76, 201, 255, 0) 65%),
            radial-gradient(900px 600px at 0% 100%, rgba(113, 247, 212, 0.35) 0%, rgba(113, 247, 212, 0) 60%),
            var(--ag-bg);
        background-attachment: fixed;
    }

    /* Headings: weight 500, tight letter-spacing */
    .fi-header-heading, .fi-simple-header-heading, .fi-section-header-heading, .fi-modal-heading {
        font-weight: 500 !important;
        letter-spacing: -0.02em;
    }

    /* Cards / sections / tables: white, borderless, 20px radius, soft shadow */
    .fi-section, .fi-ta-ctn, .fi-wi-stats-overview-stat, .fi-simple-main, .fi-fo-repeater-item {
        border-radius: 20px !important;
        box-shadow: var(--ag-shadow) !important;
        --tw-ring-color: transparent !important;
        border-color: transparent !important;
    }
    .fi-ta-header-cell { background: var(--ag-surface); }

    /* Sidebar + top bar blend into the page */
    .fi-sidebar, .fi-topbar > nav, .fi-topbar {
        background: rgba(255, 255, 255, 0.55) !important;
        backdrop-filter: blur(12px);
        box-shadow: none !important;
        --tw-ring-color: transparent !important;
    }
    .fi-sidebar-item-active > a, .fi-sidebar-item.fi-active > a, .fi-sidebar-item-btn.fi-active {
        background: #fff !important;
        box-shadow: 0 2px 10px rgba(20, 23, 27, 0.08);
        border-radius: 12px;
    }

    /* Primary buttons: brand gradient with ink text, fully round */
    .fi-btn { border-radius: 999px !important; }
    .fi-btn.fi-color-primary:not(.fi-outlined):not(.fi-link) {
        background: var(--ag-gradient) !important;
        color: var(--ag-ink) !important;
        --tw-ring-color: transparent !important;
    }
    .fi-btn.fi-color-primary:not(.fi-outlined):not(.fi-link):hover {
        background: var(--ag-gradient-hover) !important;
        box-shadow: 0 6px 18px rgba(76, 201, 255, 0.35);
    }
    .fi-btn.fi-color-primary:not(.fi-outlined):not(.fi-link) .fi-icon,
    .fi-btn.fi-color-primary:not(.fi-outlined):not(.fi-link) svg { color: var(--ag-ink) !important; }

    /* Inputs: surface background, mint focus ring */
    .fi-input-wrp {
        border-radius: 12px !important;
        background: var(--ag-surface) !important;
    }
    .fi-input-wrp:focus-within {
        background: #fff !important;
        --tw-ring-color: #71f7d4 !important;
        box-shadow: 0 0 0 4px rgba(113, 247, 212, 0.3) !important;
    }

    /* Badges: 8px radius */
    .fi-badge { border-radius: 8px !important; }
</style>
