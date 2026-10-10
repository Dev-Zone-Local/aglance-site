{{-- "Quick fixes" button: known problems and solutions for one product (Admin → Content → Known problems). --}}
<a href="{{ route('known-problems', ['product' => $productId]) }}"
    class="inline-flex shrink-0 items-center gap-2 rounded-full bg-white px-4 py-2 text-sm font-medium text-ag-ink shadow-ag transition-colors hover:bg-ag-success-soft hover:text-ag-success-text">
    <x-glyph name="shield-check" :size="15" class="text-ag-teal" /> Quick fixes
</a>
