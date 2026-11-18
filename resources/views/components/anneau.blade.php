{{--
    Anneau (donut) en SVG, sans JavaScript.
    segments : liste de ['valeur' => int, 'classe' => 'text-…'] — la couleur vient de currentColor.
    total    : base des pourcentages (par défaut la somme des segments) ; le reste est la piste grise.
    Le contenu du slot s'affiche au centre.
--}}
@props(['segments' => [], 'total' => null, 'taille' => 'h-28 w-28', 'epaisseur' => 3.2, 'label' => null])

@php
    $segments = array_values(array_filter($segments, fn ($s) => $s['valeur'] > 0));
    $total = max(1, $total ?? array_sum(array_column($segments, 'valeur')));
    $ecart = count($segments) > 1 ? 1.2 : 0; // petit espace entre deux parts (sur 100)
    $depart = 0;
@endphp

<div {{ $attributes->merge(['class' => "relative shrink-0 $taille"]) }}>
    <svg viewBox="0 0 36 36" class="h-full w-full -rotate-90" @if ($label) role="img" aria-label="{{ $label }}" @else aria-hidden="true" @endif>
        <circle cx="18" cy="18" r="15.9155" fill="none" stroke="#f3ece2" stroke-width="{{ $epaisseur }}" />
        @foreach ($segments as $s)
            @php
                $longueur = min(100 - $depart, $s['valeur'] * 100 / $total);
                $visible = max(0.01, $longueur - $ecart);
            @endphp
            <circle cx="18" cy="18" r="15.9155" fill="none" stroke="currentColor" stroke-width="{{ $epaisseur }}"
                    stroke-linecap="{{ count($segments) > 1 ? 'butt' : 'round' }}"
                    stroke-dasharray="{{ round($visible, 3) }} {{ round(100 - $visible, 3) }}"
                    stroke-dashoffset="{{ round(-$depart, 3) }}"
                    class="{{ $s['classe'] }} motion-safe:transition-all motion-safe:duration-700" />
            @php $depart += $longueur; @endphp
        @endforeach
    </svg>
    <div class="absolute inset-0 flex flex-col items-center justify-center text-center leading-tight">
        {{ $slot }}
    </div>
</div>
