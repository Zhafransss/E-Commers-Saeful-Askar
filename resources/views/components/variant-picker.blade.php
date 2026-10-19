@props(['variations', 'variants'])

{{--
    Option buttons per variation label (CAT-03 `variations`). Picking values resolves
    the matching variant; values whose variants are all out of stock are struck through.
    Must sit inside an x-data scope that defines `selected` (label => value).
--}}
<div {{ $attributes->class('space-y-5') }}>
    @foreach ($variations as $variation)
        <fieldset>
            <legend class="mb-2 text-sm font-semibold text-ink">
                {{ $variation['label'] }}:
                <span class="font-normal text-gray-600" x-text="selected['{{ $variation['label'] }}']"></span>
            </legend>
            <div class="flex flex-wrap gap-2">
                @foreach ($variation['values'] as $value)
                    @php
                        $soldOut = collect($variants)
                            ->filter(fn ($v) => collect($v['variation_values'])->contains('value', $value))
                            ->every(fn ($v) => ! $v['in_stock']);
                    @endphp
                    <button type="button"
                            @click="selected['{{ $variation['label'] }}'] = '{{ $value }}'"
                            :class="selected['{{ $variation['label'] }}'] === '{{ $value }}'
                                ? 'border-ink bg-ink text-brand'
                                : 'border-gray-300 bg-white text-ink hover:border-ink'"
                            @class(['inline-flex items-center gap-2 rounded-lg border-2 px-4 py-2 text-sm font-semibold transition', 'text-gray-400 line-through decoration-gray-400' => $soldOut])
                            @if ($soldOut) title="Stok habis" @endif>
                        {{ $value }}
                    </button>
                @endforeach
            </div>
        </fieldset>
    @endforeach
</div>
