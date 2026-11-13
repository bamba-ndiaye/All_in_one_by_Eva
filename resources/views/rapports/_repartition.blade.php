{{-- $lignes : collection de ['nom', 'valeur'] ; $vide ; $nombres (true = pas de FCFA) --}}
@php
    $nombres ??= false;
    $total = max(1, (int) $lignes->sum('valeur'));
@endphp

@if ($lignes->isEmpty())
    <p class="mt-4 text-sm text-stone-500">{{ $vide }}</p>
@else
    <ul class="mt-4 space-y-3 text-sm">
        @foreach ($lignes as $l)
            @php $part = (int) round(max(0, $l['valeur']) * 100 / $total); @endphp
            <li>
                <div class="flex justify-between gap-3">
                    <span>{{ $l['nom'] }}</span>
                    <span class="font-medium">{{ $nombres ? $l['valeur'] : \App\Support\Fcfa::format($l['valeur']) }} <span class="font-normal text-stone-400">· {{ $part }} %</span></span>
                </div>
                <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-beige-100">
                    <div class="h-full rounded-full bg-bordeaux-600" style="width: {{ $part }}%"></div>
                </div>
            </li>
        @endforeach
    </ul>
@endif
