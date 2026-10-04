<x-filament-panels::page>
    {{-- Sidebar layout matching the management console settings page (ag-tabs--vertical). --}}
    <style>
        .agdl-products.fi-vertical { align-items: flex-start; gap: 24px; }
        .agdl-products > .fi-tabs.fi-vertical {
            position: sticky; top: 88px; flex: 0 0 240px; width: 240px;
            display: flex; flex-direction: column; gap: 4px; padding: 8px;
            background: var(--ag-surface, #f4f6f8); border-radius: 20px;
            box-shadow: none; --tw-ring-color: transparent;
        }
        .agdl-products > .fi-tabs .fi-tabs-item {
            justify-content: flex-start; width: 100%; padding: 11px 14px; border-radius: 12px;
            color: #6b7280; font-size: 14px; font-weight: 500; background: transparent;
        }
        .agdl-products > .fi-tabs .fi-tabs-item:hover { color: var(--ag-ink, #14171b); background: rgba(255,255,255,.6); }
        .agdl-products > .fi-tabs .fi-tabs-item.fi-active {
            background: #fff; color: var(--ag-ink, #14171b); box-shadow: 0 2px 10px rgba(20,23,27,.08);
        }
        .agdl-products > .fi-tabs .fi-tabs-item svg { color: #9ca3af; }
        .agdl-products > .fi-tabs .fi-tabs-item.fi-active svg { color: #0f9b8e; }
        /* One row per item: icon, name, version on the right (like the console sidebar). */
        .agdl-products > .fi-tabs .fi-tabs-item {
            display: flex; flex-wrap: nowrap; align-items: center; gap: 10px;
        }
        .agdl-products > .fi-tabs .fi-tabs-item-label { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .agdl-products > .fi-tabs .fi-tabs-item .fi-badge {
            margin-inline-start: auto; flex: none; padding: 2px 8px; border-radius: 999px;
            background: var(--ag-surface, #f4f6f8) !important; color: #6b7280 !important;
            font-size: 11px; font-weight: 500; --tw-ring-color: transparent !important; box-shadow: none !important;
        }
        .agdl-products > .fi-tabs .fi-tabs-item:not(.fi-active) .fi-badge { background: rgba(255,255,255,.7) !important; }
        .agdl-products > .fi-tabs .fi-tabs-item.fi-active .fi-badge { background: #e8fbf5 !important; color: #0f7f74 !important; }
        /* Right side: one white card holding the product form and its Save button. */
        .agdl-products > .fi-sc-tabs-tab.fi-active {
            margin-inline-start: 0; min-width: 0; flex: 1;
            background: #fff; border-radius: 20px; padding: 24px;
            box-shadow: 0 8px 30px rgba(17,20,24,.06);
        }
        .agdl-products > .fi-sc-tabs-tab .fi-section {
            box-shadow: none !important; --tw-ring-color: transparent !important; background: transparent;
        }
        .agdl-products > .fi-sc-tabs-tab .fi-section-header { padding: 0 0 16px; }
        .agdl-products > .fi-sc-tabs-tab .fi-section-content-ctn .fi-section-content { padding: 0; }

        .agdl-savebar { margin-top: 20px; display: flex; justify-content: flex-start; }
        .agdl-savebar .fi-btn { padding: 11px 20px; border-radius: 999px; font-weight: 500; }

        @media (max-width: 900px) {
            .agdl-products.fi-vertical { flex-direction: column; }
            .agdl-products > .fi-tabs.fi-vertical { position: static; width: 100%; flex-basis: auto; }
        }
    </style>

    <form wire:submit="save">
        {{ $this->form }}
    </form>
</x-filament-panels::page>
