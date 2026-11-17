@props(['label', 'value', 'icon' => 'sparkles', 'hint' => null, 'evolution' => null])

<div {{ $attributes->merge(['class' => 'card flex flex-col']) }}>
    <div class="flex items-center gap-3">
        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-bordeaux-50 text-bordeaux-700">
            <x-icon :name="$icon" class="h-[18px] w-[18px]" />
        </span>
        <p class="min-w-0 text-sm text-stone-500">{{ $label }}</p>
    </div>
    <p class="mt-3 whitespace-nowrap font-serif text-2xl font-semibold tabular-nums text-bordeaux-900">{{ $value }}</p>
    @if ($evolution !== null)
        <p class="mt-1.5 flex flex-wrap items-center gap-x-1.5 text-xs text-stone-500">
            <span @class([
                'inline-flex items-center gap-1 rounded-full px-1.5 py-0.5 font-semibold tabular-nums',
                'bg-emerald-50 text-emerald-700' => $evolution > 0,
                'bg-red-50 text-red-700' => $evolution < 0,
                'bg-stone-100 text-stone-600' => $evolution === 0,
            ])>
                <x-icon :name="$evolution < 0 ? 'trending-down' : 'trending-up'" class="h-3.5 w-3.5" />
                {{ ($evolution > 0 ? '+' : '').$evolution }} %
            </span>
            <span>vs mois dernier à la même date</span>
        </p>
    @endif
    @if ($hint)
        <p class="mt-1.5 text-xs text-stone-400">{{ $hint }}</p>
    @endif
    @if (! $slot->isEmpty())
        <div class="mt-auto pt-4">{{ $slot }}</div>
    @endif
</div>
