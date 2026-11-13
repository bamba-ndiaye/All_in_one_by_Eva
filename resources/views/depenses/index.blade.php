@php
    $aujourdhui = now()->startOfDay();
    $lundi = $aujourdhui->copy()->startOfWeek(\Carbon\CarbonInterface::MONDAY);
    $debutMois = $aujourdhui->copy()->startOfMonth();
    $lien = fn ($debut, $fin) => route('depenses.index', array_filter([
        'du' => $debut->toDateString(),
        'au' => $fin->toDateString(),
        'categorie' => $categorie,
        'statut' => $statut === 'toutes' ? null : $statut,
    ]));
    $sousTitre = $du->isSameDay($au)
        ? ucfirst($du->translatedFormat('l j F Y'))
        : 'Du '.$du->translatedFormat('j F Y').' au '.$au->translatedFormat('j F Y');
@endphp

<x-layouts.app title="Dépenses">
    <x-page-header title="Dépenses" :subtitle="$sousTitre">
        <x-slot:actions>
            <a href="{{ route('depenses.create') }}" class="btn-primary">Nouvelle dépense</a>
        </x-slot:actions>
    </x-page-header>

    <form method="GET" action="{{ route('depenses.index') }}" class="card mb-6 space-y-4">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label for="du" class="label">Du</label>
                <input id="du" type="date" name="du" value="{{ $du->toDateString() }}" class="input">
            </div>
            <div>
                <label for="au" class="label">Au</label>
                <input id="au" type="date" name="au" value="{{ $au->toDateString() }}" class="input">
            </div>
            <div>
                <label for="categorie" class="label">Catégorie (liste)</label>
                <select id="categorie" name="categorie" class="input">
                    <option value="">Toutes</option>
                    @foreach ($categories as $categorieChoix)
                        <option value="{{ $categorieChoix->id }}" @selected($categorie === $categorieChoix->id)>{{ $categorieChoix->nom }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="statut" class="label">Statut (liste)</label>
                <select id="statut" name="statut" class="input">
                    <option value="toutes" @selected($statut === 'toutes')>Toutes</option>
                    <option value="valides" @selected($statut === 'valides')>Valides</option>
                    <option value="annulees" @selected($statut === 'annulees')>Annulées</option>
                </select>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <button type="submit" class="btn-primary">Filtrer</button>
            <a href="{{ route('depenses.index') }}" class="btn-secondary">Réinitialiser</a>

            <span class="ml-auto flex flex-wrap gap-4 text-sm">
                <a href="{{ $lien($aujourdhui, $aujourdhui) }}" class="font-medium text-bordeaux-700 hover:text-bordeaux-900">Aujourd'hui</a>
                <a href="{{ $lien($lundi, $aujourdhui) }}" class="font-medium text-bordeaux-700 hover:text-bordeaux-900">Cette semaine</a>
                <a href="{{ $lien($debutMois, $aujourdhui) }}" class="font-medium text-bordeaux-700 hover:text-bordeaux-900">Ce mois</a>
            </span>
        </div>
    </form>

    <div class="mb-6 grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
        <x-stat-card label="Dépenses du jour" :value="\App\Support\Fcfa::format($totalAujourdhui)" icon="receipt" hint="Annulations exclues" />
        <x-stat-card label="Dépenses du mois" :value="\App\Support\Fcfa::format($totalMois)" icon="receipt" hint="{{ ucfirst($aujourdhui->translatedFormat('F Y')) }}" />
        <x-stat-card label="Total de la période" :value="\App\Support\Fcfa::format($totalPeriode)" icon="wallet" hint="Toutes catégories, annulations exclues" />
    </div>

    @if ($voitResultat)
        <section class="card mb-6">
            <h2 class="text-lg font-semibold">Résultat estimé de la période</h2>
            <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-3">
                <div><dt class="text-stone-500">Recettes encaissées</dt><dd class="mt-0.5 font-medium">{{ \App\Support\Fcfa::format($resultat['encaisse']) }}</dd></div>
                <div><dt class="text-stone-500">Dépenses enregistrées</dt><dd class="mt-0.5 font-medium">{{ \App\Support\Fcfa::format($resultat['depenses']) }}</dd></div>
                <div>
                    <dt class="text-stone-500">Résultat estimé</dt>
                    <dd class="mt-0.5 font-serif text-xl font-semibold {{ $resultat['resultat'] < 0 ? 'text-red-700' : 'text-bordeaux-900' }}">
                        {{ \App\Support\Fcfa::format($resultat['resultat']) }}
                    </dd>
                </div>
            </dl>
            <p class="mt-4 text-xs text-stone-500">
                Indicateur de gestion : recettes encaissées moins dépenses enregistrées sur la période.
                Ce n'est pas un bénéfice comptable définitif.
            </p>
        </section>
    @endif

    <section class="card mb-6">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-stone-500">Par catégorie sur la période</h2>
        @if ($parCategorie->isEmpty())
            <p class="mt-3 text-sm text-stone-500">Aucune dépense sur cette période.</p>
        @else
            <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($parCategorie as $ligne)
                    <div class="flex justify-between gap-4 rounded-lg bg-beige-50 px-3 py-2">
                        <dt>{{ $ligne['categorie'] }}</dt>
                        <dd class="font-medium">{{ \App\Support\Fcfa::format($ligne['total']) }}</dd>
                    </div>
                @endforeach
            </dl>
        @endif
    </section>

    @if ($depenses->isEmpty())
        <div class="card text-center text-sm text-stone-500">Aucune dépense ne correspond à votre recherche.</div>
    @else
        <div class="card overflow-hidden p-0">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-beige-50 text-xs uppercase tracking-wide text-stone-500">
                        <tr>
                            <th class="px-6 py-3">Date</th>
                            <th class="px-3 py-3">Catégorie</th>
                            <th class="px-3 py-3">Description</th>
                            <th class="px-3 py-3">Justificatif</th>
                            <th class="px-3 py-3 text-right">Montant</th>
                            <th class="px-3 py-3">Statut</th>
                            <th class="px-6 py-3">Par</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($depenses as $d)
                            @php $annulee = $d->estAnnulee(); @endphp
                            <tr class="border-t border-beige-100 {{ $annulee ? 'bg-stone-50 text-stone-400' : '' }}">
                                <td class="whitespace-nowrap px-6 py-3">{{ $d->date->format('d/m/Y') }}</td>
                                <td class="px-3 py-3">{{ $d->categorie->nom }}</td>
                                <td class="px-3 py-3">
                                    <a href="{{ route('depenses.show', $d) }}" class="font-medium text-bordeaux-800 hover:text-bordeaux-900">{{ $d->description }}</a>
                                </td>
                                <td class="px-3 py-3">{{ $d->aUnJustificatif() ? 'Oui' : '—' }}</td>
                                <td class="whitespace-nowrap px-3 py-3 text-right font-medium {{ $annulee ? 'line-through' : '' }}">{{ $d->montantFormate() }}</td>
                                <td class="px-3 py-3">
                                    @if ($annulee)
                                        <span class="badge bg-stone-100 text-stone-500">Annulée</span>
                                    @else
                                        <span class="badge bg-emerald-50 text-emerald-700">Valide</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3">{{ $d->user?->name ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-6">{{ $depenses->links() }}</div>
    @endif
</x-layouts.app>