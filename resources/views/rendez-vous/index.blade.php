@php
    $canCreate = auth()->user()->can('create', \App\Models\RendezVous::class);
    $titre = $voitTout ? 'Rendez-vous' : 'Mon planning';
    $aujourdhui = now()->startOfDay();
    $lien = fn (string $vue, $date) => route('rendez-vous.index', array_filter([
        'vue' => $vue,
        'date' => $date->toDateString(),
        'employe' => $filtreEmploye,
    ]));
@endphp

<x-layouts.app :title="$titre">
    <x-page-header :title="$titre" :subtitle="$periode->titre()">
        @if ($canCreate)
            <x-slot:actions>
                <a href="{{ route('rendez-vous.create', array_filter([
                    'date' => $periode->vue === 'jour' ? $periode->date->toDateString() : null,
                    'employe' => $filtreEmploye,
                ])) }}" class="btn-primary">Nouveau rendez-vous</a>
            </x-slot:actions>
        @endif
    </x-page-header>

    <div class="card mb-6 flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ $lien($periode->vue, $periode->precedent()) }}" class="btn-secondary" aria-label="Période précédente">‹</a>
            <a href="{{ $lien($periode->vue, $aujourdhui) }}" class="btn-secondary">Aujourd'hui</a>
            <a href="{{ $lien($periode->vue, $periode->suivant()) }}" class="btn-secondary" aria-label="Période suivante">›</a>

            <div class="ml-2 flex gap-1 rounded-lg bg-beige-100 p-1">
                @foreach (['jour' => 'Jour', 'semaine' => 'Semaine', 'mois' => 'Mois'] as $vue => $libelle)
                    <a href="{{ $lien($vue, $periode->date) }}" @class([
                        'rounded-md px-3 py-1.5 text-sm font-medium',
                        'bg-white text-bordeaux-800 shadow-sm' => $periode->vue === $vue,
                        'text-stone-600 hover:text-bordeaux-800' => $periode->vue !== $vue,
                    ])>{{ $libelle }}</a>
                @endforeach
            </div>
        </div>

        <form method="GET" action="{{ route('rendez-vous.index') }}" class="flex flex-wrap items-end gap-3">
            <input type="hidden" name="vue" value="{{ $periode->vue }}">
            <div>
                <label for="date" class="label">Aller à la date</label>
                <input id="date" type="date" name="date" value="{{ $periode->date->toDateString() }}" class="input">
            </div>
            @if ($voitTout)
                <div>
                    <label for="employe" class="label">Personne</label>
                    <select id="employe" name="employe" class="input">
                        <option value="">Toute l'équipe</option>
                        @foreach ($listeEmployes as $employeListe)
                            <option value="{{ $employeListe->id }}" @selected($filtreEmploye === $employeListe->id)>{{ $employeListe->nomComplet() }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <button type="submit" class="btn-secondary">Afficher</button>
        </form>
    </div>

    @if ($periode->vue === 'jour')
        @include('rendez-vous._jour')
    @elseif ($periode->vue === 'semaine')
        @include('rendez-vous._semaine')
    @else
        @include('rendez-vous._mois')
    @endif
</x-layouts.app>