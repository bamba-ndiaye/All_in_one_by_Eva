{{--
    Mini-courbe en SVG (aucun JavaScript). La couleur vient de la classe texte (currentColor).
    Si des valeurs sont négatives, une ligne pointillée marque le zéro.
--}}
@props(['valeurs' => [], 'label' => null])

@php
    $valeurs = array_values(array_map('intval', $valeurs));
    $n = count($valeurs);
    $l = 120; $h = 36; $marge = 3;
    $min = min(0, ...($valeurs ?: [0]));
    $max = max(0, ...($valeurs ?: [0]));
    $etendue = ($max - $min) ?: 1;
    $x = fn (int $i) => $n > 1 ? round($i * $l / ($n - 1), 2) : $l / 2;
    $y = fn (int $v) => round($marge + ($max - $v) * ($h - 2 * $marge) / $etendue, 2);
    $points = collect($valeurs)->map(fn ($v, $i) => $x($i).','.$y($v))->implode(' ');
    $zero = $y(0);
@endphp

@if ($n > 1)
    <svg viewBox="0 0 {{ $l }} {{ $h }}" preserveAspectRatio="none" {{ $attributes->merge(['class' => 'h-9 w-full overflow-visible']) }}
         @if ($label) role="img" aria-label="{{ $label }}" @else aria-hidden="true" @endif>
        <polygon points="0,{{ $zero }} {{ $points }} {{ $l }},{{ $zero }}" fill="currentColor" fill-opacity="0.1" />
        @if ($min < 0)
            <line x1="0" x2="{{ $l }}" y1="{{ $zero }}" y2="{{ $zero }}" stroke="#d6d3d1" stroke-dasharray="3 3" vector-effect="non-scaling-stroke" />
        @endif
        <polyline points="{{ $points }}" fill="none" stroke="currentColor" stroke-width="2"
                  stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke" />
    </svg>
@endif
