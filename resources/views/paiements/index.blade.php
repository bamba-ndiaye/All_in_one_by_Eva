@php
    $canCreate = auth()->user()->can('create', \App\Models\Paiement::class);
    $aujourdhui = now()->startOfDay();
    $lundi = $aujourdhui->copy()->startOfWeek(\Carbon\CarbonInterface::MONDAY);
    $debutMois = $aujourdhui->copy()->startOfMonth();
    $lien = fn ($debut, $fin) => route('paiements.index', array_filter([
        'du' => $debut->toDateString(),
        'au' => $fin->toDateString(),
        'mode' => $mode,
        'type' => $type,
    ]));
    $sousTitre = $du->isSameDay($au)
        ? ucfirst($du->translatedFormat('l j F Y'))
        : 'Du '.$du->translatedFormat('j F Y').' au '.$au->translatedFormat('j F Y');
@endphp

<x-layouts.app title="Caisse">
    <x-page-header title="Caisse" :subtitle="$sousTitre">
        @if ($canCreate)
            <x-slot:actions>
                <a href="{{ route('paiements.create') }}" class="btn-primary">Vente sans rendez-vous</a>
            </x-slot:actions>
        @endif
    </x-page-header>

    @include('paiements._tabs')

    <form method="GET" action="{{ route('paiements.index') }}" class="card mb-6 space-y-4">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @if ($voitRevenu)
                <div>
                    <label for="du" class="label">Du</label>
                    <input id="du" type="date" name="du" value="{{ $du->toDateString() }}" class="input">
                </div>
                <div>
                    <label for="au" class="label">Au</label>
                    <input id="au" type="date" name="au" value="{{ $au->toDateString() }}" class="input">
                </div>
            @endif
            <div>
                <label for="mode" class="label">Mode de paiement</label>
                <select id="mode" name="mode" class="input">
                    <option value="">Tous</option>
                    @foreach ($modes as $modeChoix)
                        <option value="{{ $modeChoix->id }}" @selected($mode === $modeChoix->id)>{{ $modeChoix->nom }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="type" class="label">Type</label>
                <select id="type" name="type" class="input">
                    <option value="">Tous</option>
                    @foreach (\App\Enums\TypePaiement::cases() as $typeChoix)
                        <option value="{{ $typeChoix->value }}" @selected($type === $typeChoix->value)>{{ $typeChoix->label() }}s</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <button type="submit" class="btn-primary">Filtrer</button>
            <a href="{{ route('paiements.index') }}" class="btn-secondary">Réinitialiser</a>

            @if ($voitRevenu)
                <span class="ml-auto flex flex-wrap gap-4 text-sm">
                    <a href="{{ $lien($aujourdhui, $aujourdhui) }}" class="font-medium text-bordeaux-700 hover:text-bordeaux-900">Aujourd'hui</a>
                    <a href="{{ $lien($lundi, $aujourdhui) }}" class="font-medium text-bordeaux-700 hover:text-bordeaux-900">Cette semaine</a>
                    <a href="{{ $lien($debutMois, $aujourdhui) }}" class="font-medium text-bordeaux-700 hover:text-bordeaux-900">Ce mois</a>
                </span>
            @endif
        </div>
    </form>

    @if ($voitRevenu)
        <div class="mb-6 grid gap-6 lg:grid-cols-3">
            <x-stat-card label="Total encaissé" :value="\App\Support\Fcfa::format($total)" icon="wallet" hint="Annulations déduites" />

            <div class="card lg:col-span-2">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-stone-500">Par mode de paiement</h2>
                @if ($parMode->isEmpty())
                    <p class="mt-3 text-sm text-stone-500">Aucune opération.</p>
                @else
                    <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2">
                        @foreach ($parMode as $ligne)
                            <div class="flex justify-between gap-4 rounded-lg bg-beige-50 px-3 py-2">
                                <dt>{{ $ligne['mode'] }}</dt>
                                <dd class="font-medium">{{ \App\Support\Fcfa::format($ligne['total']) }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </div>
        </div>
    @else
        <div class="card mb-6 text-sm text-stone-600">
            Vous voyez les paiements du jour. Les totaux et les autres périodes sont réservés à l'administratrice.
        </div>
    @endif

    @if ($paiements->isEmpty())
        <div class="card text-center text-sm text-stone-500">Aucun paiement sur cette période.</div>
    @else
        <div class="card overflow-hidden p-0">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-beige-50 text-xs uppercase tracking-wide text-stone-500">
                        <tr>
                            <th class="px-6 py-3">Reçu</th>
                            <th class="px-3 py-3">Date</th>
                            <th class="px-3 py-3">Client</th>
                            <th class="px-3 py-3">Détail</th>
                            <th class="px-3 py-3">Mode</th>
                            <th class="px-3 py-3 text-right">Montant</th>
                            <th class="px-3 py-3">Statut</th>
                            <th class="px-6 py-3">Par</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($paiements as $p)
                            @php $annule = $p->estAnnule(); @endphp
                            <tr class="border-t border-beige-100 {{ $annule ? 'bg-stone-50 text-stone-400' : '' }}">
                                <td class="whitespace-nowrap px-6 py-3">
                                    <a href="{{ route('paiements.show', $p) }}" class="font-medium text-bordeaux-800 hover:text-bordeaux-900">{{ $p->numero() }}</a>
                                </td>
                                <td class="whitespace-nowrap px-3 py-3">{{ $p->date->format('d/m/Y') }}</td>
                                <td class="px-3 py-3">{{ $p->client->nomComplet() }}</td>
                                <td class="px-3 py-3">
                                    {{ $p->rendezVous ? $p->rendezVous->lignes->pluck('nom')->join(', ') : $p->libelle }}
                                </td>
                                <td class="px-3 py-3">
                                    {{ $p->mode->nom }}
                                    @if ($p->reference)
                                        <span class="block text-xs text-stone-500">{{ $p->reference }}</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-3 py-3 text-right font-medium {{ $p->montant < 0 ? 'text-red-700' : '' }} {{ $annule ? 'line-through' : '' }}">
                                    {{ $p->montantFormate() }}
                                </td>
                                <td class="px-3 py-3">
                                    @if ($annule)
                                        <span class="badge bg-stone-100 text-stone-500">Annulé</span>
                                    @else
                                        <span class="badge {{ $p->type->badge() }}">{{ $p->type->label() }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3">{{ $p->user?->name ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-6">{{ $paiements->links() }}</div>
    @endif
</x-layouts.app>