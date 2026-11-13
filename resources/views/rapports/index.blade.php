@php
    $libelles = ['mois' => 'Ce mois', 'mois-dernier' => 'Mois dernier', '30-jours' => '30 derniers jours', 'annee' => 'Cette année'];
    $titrePeriode = 'Du '.$debut->translatedFormat('j F Y').' au '.$fin->translatedFormat('j F Y');
    $comparaison = 'du '.$precedente[0]->translatedFormat('j M').' au '.$precedente[1]->translatedFormat('j M Y');
    $requete = request()->only(['periode', 'du', 'au']);
@endphp

<x-layouts.app title="Rapports">
    <x-page-header title="Rapports" :subtitle="$titrePeriode">
        @if ($peutExporter)
            <x-slot:actions>
                <a href="{{ route('rapports.export', $requete) }}" class="btn-secondary">Exporter (Excel / CSV)</a>
            </x-slot:actions>
        @endif
    </x-page-header>

    {{-- Choix de la période --}}
    <form method="GET" action="{{ route('rapports.index') }}" class="card mb-6 space-y-4" x-data="{ periode: @js($periode) }">
        <div class="flex flex-wrap gap-2">
            @foreach ($libelles as $valeur => $libelle)
                <a href="{{ route('rapports.index', ['periode' => $valeur]) }}" @class([
                    'rounded-full px-4 py-2 text-sm font-medium transition',
                    'bg-bordeaux-700 text-white' => $periode === $valeur,
                    'bg-beige-100 text-bordeaux-800 hover:bg-beige-200' => $periode !== $valeur,
                ])>{{ $libelle }}</a>
            @endforeach
            <button type="button" @click="periode = 'libre'" @class([
                'rounded-full px-4 py-2 text-sm font-medium transition',
                'bg-bordeaux-700 text-white' => $periode === 'libre',
                'bg-beige-100 text-bordeaux-800 hover:bg-beige-200' => $periode !== 'libre',
            ])>Dates au choix</button>
        </div>

        <div class="flex flex-wrap items-end gap-3" x-show="periode === 'libre'" x-cloak>
            <input type="hidden" name="periode" value="libre">
            <div>
                <label for="du" class="label">Du</label>
                <input id="du" type="date" name="du" value="{{ $debut->toDateString() }}" class="input">
            </div>
            <div>
                <label for="au" class="label">Au</label>
                <input id="au" type="date" name="au" value="{{ $fin->toDateString() }}" class="input">
            </div>
            <button type="submit" class="btn-primary">Afficher</button>
        </div>
    </form>

    {{-- Finances --}}
    @if ($finances)
        <div class="mb-6 grid gap-6 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card label="Encaissé" :value="\App\Support\Fcfa::format($finances['encaisse'])" icon="wallet"
                         :hint="$finances['evolution'] === null
                             ? 'Rien à comparer '.$comparaison
                             : ($finances['evolution'] >= 0 ? '+' : '').$finances['evolution'].' % par rapport '.$comparaison" />
            <x-stat-card label="Dépenses" :value="\App\Support\Fcfa::format($finances['depenses'])" icon="receipt" />
            <x-stat-card label="Résultat estimé" :value="\App\Support\Fcfa::format($finances['resultat'])" icon="trending-up" hint="Encaissé moins dépenses" />
            <x-stat-card label="Panier moyen" :value="\App\Support\Fcfa::format($finances['panier_moyen'])" icon="sparkles"
                         :hint="$finances['nb_paiements'].' paiement'.($finances['nb_paiements'] > 1 ? 's' : '')" />
        </div>

        <div class="mb-6 grid gap-6 lg:grid-cols-2">
            <section class="card">
                <h2 class="text-lg font-semibold">Encaissé par mode de paiement</h2>
                @include('rapports._repartition', ['lignes' => $finances['par_mode']->map(fn ($m) => ['nom' => $m['mode'], 'valeur' => $m['total']]), 'vide' => 'Aucun encaissement sur la période.'])
            </section>
            <section class="card">
                <h2 class="text-lg font-semibold">Dépenses par catégorie</h2>
                @include('rapports._repartition', ['lignes' => $finances['par_categorie']->map(fn ($c) => ['nom' => $c['categorie'], 'valeur' => $c['total']]), 'vide' => 'Aucune dépense sur la période.'])
            </section>
        </div>
    @endif

    {{-- Activité --}}
    <div class="mb-6 grid gap-6 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Rendez-vous" :value="$activite['total']" icon="calendar-days" :hint="$activite['termines'].' terminé'.($activite['termines'] > 1 ? 's' : '')" />
        <x-stat-card label="Annulations" :value="$activite['taux_annulation'] === null ? '—' : $activite['taux_annulation'].' %'" icon="circle-alert" hint="Part des rendez-vous annulés" />
        <x-stat-card label="Absences" :value="$activite['taux_absence'] === null ? '—' : $activite['taux_absence'].' %'" icon="circle-alert" hint="Clientes non venues" />
        <x-stat-card label="Nouvelles clientes" :value="$activite['nouvelles_clientes']" icon="users" />
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="card overflow-hidden p-0">
            <h2 class="px-6 pt-6 text-lg font-semibold">Prestations les plus demandées</h2>
            @if ($prestations->isEmpty())
                <p class="px-6 py-5 text-sm text-stone-500">Aucune prestation terminée sur la période.</p>
            @else
                <table class="mt-4 w-full text-left text-sm">
                    <thead class="bg-beige-50 text-xs uppercase tracking-wide text-stone-500">
                        <tr><th class="px-6 py-2.5">Prestation</th><th class="px-3 py-2.5 text-right">Nombre</th><th class="px-6 py-2.5 text-right">Montant</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($prestations as $p)
                            <tr class="border-t border-beige-100">
                                <td class="px-6 py-3">{{ $p['nom'] }}</td>
                                <td class="px-3 py-3 text-right font-medium">{{ $p['nombre'] }}</td>
                                <td class="whitespace-nowrap px-6 py-3 text-right">{{ \App\Support\Fcfa::format($p['montant']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>

        <section class="card">
            <h2 class="text-lg font-semibold">Rendez-vous par statut</h2>
            @include('rapports._repartition', [
                'lignes' => $activite['par_statut']->map(fn ($s) => ['nom' => $s['statut']->label(), 'valeur' => $s['nombre']]),
                'vide' => 'Aucun rendez-vous sur la période.',
                'nombres' => true,
            ])
        </section>
    </div>

    {{-- Équipe --}}
    @if ($equipe)
        <section class="card mt-6 overflow-hidden p-0">
            <h2 class="px-6 pt-6 text-lg font-semibold">Équipe</h2>
            @if ($equipe->isEmpty())
                <p class="px-6 py-5 text-sm text-stone-500">Aucune prestation terminée sur la période.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="mt-4 w-full text-left text-sm">
                        <thead class="bg-beige-50 text-xs uppercase tracking-wide text-stone-500">
                            <tr>
                                <th class="px-6 py-2.5">Employé</th>
                                <th class="px-3 py-2.5 text-right">Rendez-vous</th>
                                <th class="px-3 py-2.5 text-right">Prestations</th>
                                <th class="px-3 py-2.5 text-right">Montant</th>
                                <th class="px-6 py-2.5 text-right">Commission estimée</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($equipe as $e)
                                <tr class="border-t border-beige-100">
                                    <td class="px-6 py-3">
                                        <span class="font-medium">{{ $e['employe']->nomComplet() }}</span>
                                        <p class="text-xs text-stone-500">{{ $e['employe']->commissionLibelle() }}</p>
                                    </td>
                                    <td class="px-3 py-3 text-right">{{ $e['rdv'] }}</td>
                                    <td class="px-3 py-3 text-right">{{ $e['prestations'] }}</td>
                                    <td class="whitespace-nowrap px-3 py-3 text-right">{{ \App\Support\Fcfa::format($e['montant']) }}</td>
                                    <td class="whitespace-nowrap px-6 py-3 text-right font-medium">{{ \App\Support\Fcfa::format($e['commission']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="px-6 py-4 text-xs text-stone-500">Calcul sur les prix des prestations terminées, avant remise éventuelle à la caisse.</p>
            @endif
        </section>
    @endif
</x-layouts.app>
