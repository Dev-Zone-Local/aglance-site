<ol class="mb-6 flex flex-wrap items-center gap-2" aria-label="Progress">
    @foreach ($steps as $i => $label)
        <li class="flex items-center gap-2" @if ($i === $current) aria-current="step" @endif>
            <span @class([
                'inline-flex h-7 min-w-7 items-center justify-center rounded-full px-2 text-xs font-medium',
                'bg-ag-ink text-ag-mint' => $i < $current,
                'bg-ag-gradient text-ag-ink shadow-ag-pill' => $i === $current,
                'bg-white text-ag-subtle shadow-ag' => $i > $current,
            ])>
                @if ($i < $current)
                    <x-glyph name="check" :size="13" /><span class="sr-only">Done:</span>
                @else
                    {{ $i + 1 }}
                @endif
            </span>
            <span @class(['text-sm', 'font-medium text-ag-ink' => $i === $current, 'text-ag-subtle' => $i !== $current])>{{ $label }}</span>
            @unless ($loop->last)<span class="mx-1 h-px w-6 bg-ag-line" aria-hidden="true"></span>@endunless
        </li>
    @endforeach
</ol>
