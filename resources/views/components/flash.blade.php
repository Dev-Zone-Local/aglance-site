{{--
    Toast for session flash messages: ->with('status', '...') or ->with('error', '...').
    Livewire components show toasts with $this->dispatch('toast', message: '...', type: 'success').
--}}
@php
    $initial = collect([
        session('status') ? ['message' => session('status'), 'type' => 'success'] : null,
        session('error') ? ['message' => session('error'), 'type' => 'error'] : null,
    ])->filter()->values();
@endphp
<div
    x-data="{
        toasts: @js($initial),
        add(t) { this.toasts.push(t); setTimeout(() => this.toasts.shift(), 6000); },
        init() { this.toasts.forEach(() => setTimeout(() => this.toasts.shift(), 6000)); },
    }"
    x-on:toast.window="add({ message: $event.detail.message, type: $event.detail.type || 'success' })"
    class="pointer-events-none fixed right-4 top-4 z-[60] flex w-[min(380px,calc(100vw-32px))] flex-col gap-2"
    aria-live="polite"
>
    <template x-for="(t, i) in toasts" :key="i">
        <div
            class="pointer-events-auto flex items-start gap-2.5 rounded-input px-4 py-3 text-sm shadow-ag-strong"
            :class="t.type === 'error' ? 'bg-ag-danger-soft text-ag-danger-text' : 'bg-white text-ag-ink'"
            :role="t.type === 'error' ? 'alert' : 'status'"
        >
            <span class="min-w-0 flex-1" x-text="t.message"></span>
            <button type="button" x-on:click="toasts.splice(i, 1)" class="text-ag-muted hover:text-ag-ink" aria-label="Dismiss">
                <x-glyph name="x" :size="14" />
            </button>
        </div>
    </template>
</div>
