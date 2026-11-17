@php
    $fcfa = fn (int $n) => \App\Support\Fcfa::format($n);
    $prenom = \Illuminate\Support\Str::before($user->name, ' ') ?: $user->name;
@endphp

<x-layouts.app title="Tableau de bord">
    <x-page-header :title="'Bonjour '.$prenom" :subtitle="ucfirst($aujourdhui->translatedFormat('l j F Y')).' · '.config('app.name')">
        <x-slot:actions>
            @can('appointments.manage')
                <a href="{{ route('rendez-vous.create') }}" class="btn-primary"><x-icon name="circle-plus" class="h-4 w-4" /> Rendez-vous</a>
            @endcan
            @can('payments.record')
                <a href="{{ route('paiements.create') }}" class="btn-secondary"><x-icon name="wallet" class="h-4 w-4" /> Encaisser</a>
            @endcan
            @can('clients.create')
                <a href="{{ route('clients.create') }}" class="btn-secondary"><x-icon name="users" class="h-4 w-4" /> Nouvelle cliente</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    {{-- ================= Finances (administratrice) ================= --}}
    @if ($finances)
        @php
            $netParJour = array_map(fn ($j) => $j['encaisse'] - $j['depenses'], $serie);
        @endphp
        <div class="mb-6 grid gap-6 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card label="Encaissé ce mois" :value="$fcfa($finances['encaisse'])" icon="wallet" :evolution="$finances['evolution']">
                <x-sparkline :valeurs="array_column($serie, 'encaisse')" class="text-bordeaux-600" label="Recettes par jour sur 30 jours" />
                <p class="mt-1 text-[11px] text-stone-400">Par jour, 30 derniers jours</p>
            </x-stat-card>
            <x-stat-card label="Dépenses ce mois" :value="$fcfa($finances['depenses'])" icon="receipt">
                <x-sparkline :valeurs="array_column($serie, 'depenses')" class="text-[#b8862f]" label="Dépenses par jour sur 30 jours" />
                <p class="mt-1 text-[11px] text-stone-400">Par jour, 30 derniers jours</p>
            </x-stat-card>
            <x-stat-card label="Résultat estimé" :value="$fcfa($finances['resultat'])" icon="trending-up" hint="Encaissé moins dépenses">
                <x-sparkline :valeurs="$netParJour" class="text-stone-500" label="Solde par jour sur 30 jours" />
                <p class="mt-1 text-[11px] text-stone-400">Solde par jour, 30 derniers jours</p>
            </x-stat-card>
            <x-stat-card label="Panier moyen" :value="$fcfa($finances['panier_moyen'])" icon="sparkles"
                         :hint="$finances['nb_paiements'].' '.($finances['nb_paiements'] > 1 ? 'paiements' : 'paiement').' ce mois'" />
        </div>

        <section class="card mb-6"
                 x-data="{
                     periode: '30j',
                     jours: @js($serie),
                     mois: @js($serieMois),
                     get points() { return this.periode === '12m' ? this.mois : (this.periode === '7j' ? this.jours.slice(-7) : this.jours); },
                     total(cle) { return this.points.reduce((s, p) => s + p[cle], 0); },
                     fcfa(n) { return new Intl.NumberFormat('fr-FR').format(n) + ' FCFA'; },
                 }">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-semibold">Recettes et dépenses</h2>
                <div class="inline-flex rounded-lg border border-beige-200 bg-beige-50 p-0.5 text-sm" role="group" aria-label="Période du graphique">
                    @foreach (['7j' => '7 jours', '30j' => '30 jours', '12m' => '12 mois'] as $cle => $libelle)
                        <button type="button" @click="periode = '{{ $cle }}'"
                                :aria-pressed="(periode === '{{ $cle }}').toString()"
                                :class="periode === '{{ $cle }}' ? 'bg-white text-bordeaux-900 shadow-sm' : 'text-stone-500 hover:text-stone-800'"
                                class="rounded-md px-3 py-1 font-medium transition">{{ $libelle }}</button>
                    @endforeach
                </div>
            </div>

            {{-- Légende avec les totaux de la période --}}
            <dl class="mt-4 flex flex-wrap gap-x-6 gap-y-2 text-sm">
                <div class="flex items-center gap-2">
                    <span class="h-0.5 w-5 rounded-full bg-bordeaux-600" aria-hidden="true"></span>
                    <dt class="text-stone-500">Recettes</dt>
                    <dd class="font-semibold tabular-nums text-stone-800" x-text="fcfa(total('encaisse'))"></dd>
                </div>
                <div class="flex items-center gap-2">
                    <span class="h-0.5 w-5 rounded-full bg-[#b8862f]" aria-hidden="true"></span>
                    <dt class="text-stone-500">Dépenses</dt>
                    <dd class="font-semibold tabular-nums text-stone-800" x-text="fcfa(total('depenses'))"></dd>
                </div>
                <div class="flex items-center gap-2">
                    <dt class="text-stone-500">Solde</dt>
                    <dd class="font-semibold tabular-nums text-stone-800" x-text="fcfa(total('encaisse') - total('depenses'))"></dd>
                </div>
            </dl>

            <div class="relative mt-4 h-64">
                <canvas data-graphique="recettes-depenses" data-serie="{{ json_encode($serie) }}" data-serie-mois="{{ json_encode($serieMois) }}"
                        :data-periode="periode" x-effect="$el.dispatchEvent(new CustomEvent('periode', { detail: periode }))"
                        role="img" aria-label="Courbe des recettes et des dépenses"></canvas>
            </div>

            <details class="mt-4 text-sm">
                <summary class="cursor-pointer font-medium text-bordeaux-700 hover:text-bordeaux-900">Voir les chiffres</summary>
                <div class="mt-3 max-h-64 overflow-y-auto">
                    <table class="w-full text-left" x-show="periode !== '12m'">
                        <thead class="sticky top-0 bg-beige-50 text-xs uppercase tracking-wide text-stone-500">
                            <tr><th class="px-3 py-2">Jour</th><th class="px-3 py-2 text-right">Recettes</th><th class="px-3 py-2 text-right">Dépenses</th></tr>
                        </thead>
                        <tbody class="tabular-nums">
                            @foreach (array_reverse($serie) as $i => $j)
                                <tr class="border-t border-beige-100" @if ($i >= 7) x-show="periode === '30j'" @endif>
                                    <td class="px-3 py-1.5">{{ \Illuminate\Support\Carbon::parse($j['date'])->format('d/m') }}</td>
                                    <td class="px-3 py-1.5 text-right">{{ $fcfa($j['encaisse']) }}</td>
                                    <td class="px-3 py-1.5 text-right">{{ $fcfa($j['depenses']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <table class="w-full text-left" x-show="periode === '12m'" x-cloak>
                        <thead class="sticky top-0 bg-beige-50 text-xs uppercase tracking-wide text-stone-500">
                            <tr><th class="px-3 py-2">Mois</th><th class="px-3 py-2 text-right">Recettes</th><th class="px-3 py-2 text-right">Dépenses</th></tr>
                        </thead>
                        <tbody class="tabular-nums">
                            @foreach (array_reverse($serieMois) as $m)
                                <tr class="border-t border-beige-100">
                                    <td class="px-3 py-1.5">{{ $m['titre'] }}</td>
                                    <td class="px-3 py-1.5 text-right">{{ $fcfa($m['encaisse']) }}</td>
                                    <td class="px-3 py-1.5 text-right">{{ $fcfa($m['depenses']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        </section>

        <div class="mb-6 grid gap-6 lg:grid-cols-2">
            <section class="card">
                <h2 class="text-lg font-semibold">Encaissé par mode ce mois</h2>
                @include('rapports._repartition', [
                    'lignes' => $finances['par_mode']->map(fn ($m) => ['nom' => $m['mode'], 'valeur' => $m['total']]),
                    'vide' => 'Aucun encaissement ce mois-ci.',
                ])
            </section>
            <section class="card">
                <h2 class="text-lg font-semibold">Prestations les plus demandées ce mois</h2>
                @include('rapports._repartition', [
                    'lignes' => $prestations->map(fn ($p) => ['nom' => $p['nom'], 'valeur' => $p['nombre']]),
                    'vide' => 'Aucune prestation terminée ce mois-ci.',
                    'nombres' => true,
                ])
            </section>
        </div>
    @endif

    {{-- ================= Journée ================= --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="min-w-0 lg:col-span-2">
            @if ($rendezVous !== null)
                <section class="card overflow-hidden p-0">
                    <div class="flex items-center justify-between px-6 pt-6">
                        <h2 class="text-lg font-semibold">{{ $voitTousLesRdv ? "Rendez-vous d'aujourd'hui" : "Mes rendez-vous d'aujourd'hui" }}</h2>
                        <a href="{{ route('rendez-vous.index') }}" class="text-sm font-medium text-bordeaux-700 hover:text-bordeaux-900">Agenda</a>
                    </div>
                    @if ($rendezVous->isEmpty())
                        <p class="px-6 py-6 text-sm text-stone-500">Aucun rendez-vous aujourd'hui.</p>
                    @else
                        @php
                            $compte = fn (array $statuts) => $rendezVous->filter(fn ($r) => in_array($r->statut, $statuts, true))->count();
                            $groupes = [
                                ['nom' => 'Terminés', 'valeur' => $compte([\App\Enums\StatutRendezVous::Termine]), 'classe' => 'text-bordeaux-600', 'pastille' => 'bg-bordeaux-600'],
                                ['nom' => 'En cours', 'valeur' => $compte([\App\Enums\StatutRendezVous::ClientArrive, \App\Enums\StatutRendezVous::EnCours]), 'classe' => 'text-bordeaux-300', 'pastille' => 'bg-bordeaux-300'],
                                ['nom' => 'À venir', 'valeur' => $compte([\App\Enums\StatutRendezVous::EnAttente, \App\Enums\StatutRendezVous::Confirme]), 'classe' => 'text-beige-400', 'pastille' => 'bg-beige-400'],
                                ['nom' => 'Absentes', 'valeur' => $compte([\App\Enums\StatutRendezVous::ClientAbsent]), 'classe' => 'text-stone-400', 'pastille' => 'bg-stone-400'],
                            ];
                            $prochain = $rendezVous->first(fn ($r) => $r->statut->estModifiable());
                        @endphp
                        <div class="mt-4 flex flex-wrap items-center gap-x-8 gap-y-4 border-y border-beige-100 bg-beige-50/60 px-6 py-5">
                            <x-anneau :segments="$groupes" :total="$rendezVous->count()" taille="h-24 w-24"
                                      :label="$groupes[0]['valeur'].' rendez-vous terminés sur '.$rendezVous->count()">
                                <span class="font-serif text-2xl font-semibold tabular-nums text-bordeaux-900">{{ $groupes[0]['valeur'] }}<span class="text-base text-stone-400">/{{ $rendezVous->count() }}</span></span>
                                <span class="text-[11px] text-stone-500">terminés</span>
                            </x-anneau>
                            <div class="min-w-0 flex-1">
                                <ul class="grid grid-cols-2 gap-x-6 gap-y-2 text-sm">
                                    @foreach ($groupes as $g)
                                        <li class="flex items-center gap-2">
                                            <span class="h-2.5 w-2.5 shrink-0 rounded-full {{ $g['pastille'] }}"></span>
                                            <span class="text-stone-600">{{ $g['nom'] }}</span>
                                            <span class="ml-auto font-semibold tabular-nums text-stone-800">{{ $g['valeur'] }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                                @if ($prochain)
                                    <p class="mt-3 truncate text-xs text-stone-500">
                                        Prochain : <span class="font-semibold text-bordeaux-900">{{ $prochain->debut() }}</span> · {{ $prochain->client->nomComplet() }}
                                    </p>
                                @endif
                            </div>
                        </div>
                        <ul class="divide-y divide-beige-100">
                            @foreach ($rendezVous as $rdv)
                                @php
                                    $aEncaisser = $peutEncaisser
                                        && $rdv->statut === \App\Enums\StatutRendezVous::Termine
                                        && $rdv->resteAPayer() > 0;
                                @endphp
                                <li class="flex items-center gap-3 pr-6 transition hover:bg-beige-50">
                                    <a href="{{ route('rendez-vous.show', $rdv) }}" class="flex min-w-0 flex-1 items-center gap-4 py-3 pl-6">
                                        <span class="w-14 shrink-0 font-semibold tabular-nums text-bordeaux-900">{{ $rdv->debut() }}</span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate font-medium">{{ $rdv->client->nomComplet() }}</span>
                                            <span class="block truncate text-xs text-stone-500">avec {{ $rdv->employe->nomComplet() }} · jusqu'à {{ $rdv->fin() }}</span>
                                        </span>
                                        <span class="badge hidden shrink-0 sm:inline-flex {{ $rdv->statut->badge() }}">{{ $rdv->statut->label() }}</span>
                                    </a>
                                    @if ($aEncaisser)
                                        <a href="{{ route('paiements.create', ['rendez_vous' => $rdv->id]) }}" class="btn-primary shrink-0 !px-3 !py-1.5 text-xs">
                                            Encaisser {{ \App\Support\Fcfa::format($rdv->resteAPayer()) }}
                                        </a>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @elseif ($voitSiens)
                <section class="card text-sm text-stone-500">
                    Votre compte n'est relié à aucune fiche employé : demandez à l'administratrice de le lier pour voir votre planning ici.
                </section>
            @endif
        </div>

        <div class="min-w-0 space-y-6">
            @if ($encaisseJour !== null)
                <section class="card">
                    <h2 class="text-lg font-semibold">Caisse du jour</h2>
                    <div class="mt-4 flex items-center gap-5">
                        @if ($prevuJour > 0)
                            @php $pourcent = (int) round($encaisseJour * 100 / $prevuJour); @endphp
                            <x-anneau :segments="[['valeur' => min($encaisseJour, $prevuJour), 'classe' => 'text-bordeaux-600']]" :total="$prevuJour"
                                      taille="h-24 w-24" :label="$pourcent.' % du montant prévu aujourd\'hui est encaissé'">
                                <span class="text-lg font-semibold tabular-nums text-bordeaux-900">{{ $pourcent }} %</span>
                                <span class="text-[10px] text-stone-500">du prévu</span>
                            </x-anneau>
                        @endif
                        <div class="min-w-0">
                            <p class="whitespace-nowrap font-serif text-2xl font-semibold text-bordeaux-900">{{ $fcfa($encaisseJour) }}</p>
                            <p class="mt-1 text-xs text-stone-500">
                                {{ $prevuJour > 0 ? 'sur '.$fcfa($prevuJour).' prévus aujourd\'hui' : 'encaissés aujourd\'hui' }}
                            </p>
                        </div>
                    </div>

                    {{-- 7 derniers jours en barres --}}
                    @php $maxJour = max(1, ...array_column($caisse7Jours, 'total')); @endphp
                    <div class="mt-5" x-data="{ info: null }">
                        <div class="flex h-20 items-end gap-1.5" @mouseleave="info = null"
                             role="img" aria-label="Encaissé sur les 7 derniers jours">
                            @foreach ($caisse7Jours as $j)
                                @php $texte = $j['jour'].' : '.$fcfa($j['total']); @endphp
                                <div class="group flex h-full flex-1 cursor-default flex-col justify-end"
                                     @mouseenter="info = @js($texte)" @click="info = @js($texte)">
                                    <div @class([
                                            'rounded-t-[4px] transition-colors',
                                            'bg-bordeaux-600' => $j['aujourdhui'],
                                            'bg-bordeaux-200 group-hover:bg-bordeaux-400' => ! $j['aujourdhui'] && $j['total'] > 0,
                                            'bg-beige-200' => $j['total'] === 0,
                                         ])
                                         style="height: {{ max(3, round($j['total'] * 100 / $maxJour)) }}%"></div>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-1.5 flex gap-1.5 text-center text-[10px]">
                            @foreach ($caisse7Jours as $j)
                                <span @class(['flex-1', 'font-semibold text-bordeaux-900' => $j['aujourdhui'], 'text-stone-400' => ! $j['aujourdhui']])>{{ $j['initiale'] }}</span>
                            @endforeach
                        </div>
                        <p class="mt-2 min-h-4 text-xs text-stone-500" x-text="info ?? '7 derniers jours'">7 derniers jours</p>
                    </div>
                    @if ($impayes->isNotEmpty())
                        @php $resteDu = $impayes->sum(fn ($r) => $r->resteAPayer()); @endphp
                        <a href="{{ route('paiements.impayes') }}" class="mt-4 flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900 hover:bg-amber-100">
                            <x-icon name="triangle-alert" class="h-4 w-4 shrink-0" />
                            <span>{{ $impayes->count() }} {{ $impayes->count() > 1 ? 'rendez-vous impayés' : 'rendez-vous impayé' }} · {{ $fcfa($resteDu) }}</span>
                        </a>
                    @else
                        <p class="mt-2 text-sm text-stone-500">Aucun impayé.</p>
                    @endif
                </section>
            @endif

            @if ($anniversaires !== null)
                <section class="card">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold">Anniversaires</h2>
                        <a href="{{ route('anniversaires.index') }}" class="text-sm font-medium text-bordeaux-700 hover:text-bordeaux-900">Tout voir</a>
                    </div>
                    @if ($anniversaires->isEmpty())
                        <p class="mt-3 text-sm text-stone-500">Aucun anniversaire cette semaine.</p>
                    @else
                        <ul class="mt-4 space-y-3 text-sm">
                            @foreach ($anniversaires->take(5) as $a)
                                @php
                                    $c = $a['client'];
                                    $lettre = fn ($texte) => mb_substr(preg_replace('/[^\p{L}]/u', '', (string) $texte), 0, 1);
                                    $initiales = mb_strtoupper($lettre($c->prenom).$lettre($c->nom));
                                @endphp
                                <li class="flex items-center gap-3">
                                    <span @class([
                                        'flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                                        'bg-bordeaux-600 text-white ring-2 ring-bordeaux-200 ring-offset-2' => $a['dans'] === 0,
                                        'bg-bordeaux-50 text-bordeaux-800' => $a['dans'] > 0,
                                    ])>{{ $initiales }}</span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate font-medium text-stone-800">{{ $c->nomComplet() }}</span>
                                        <span class="block text-xs text-stone-500">{{ $a['age'] }} ans</span>
                                    </span>
                                    <span @class([
                                        'shrink-0 rounded-full px-2.5 py-1 text-xs',
                                        'bg-bordeaux-50 font-semibold text-bordeaux-800' => $a['dans'] === 0,
                                        'bg-beige-50 text-stone-600' => $a['dans'] > 0,
                                    ])>
                                        {{ $a['dans'] === 0 ? "Aujourd'hui 🎉" : ($a['dans'] === 1 ? 'Demain' : $a['date']->translatedFormat('l j')) }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endif

            @if ($stockBas !== null)
                <section class="card">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold">Stock bas</h2>
                        <a href="{{ route('produits.index', ['etat' => 'alerte']) }}" class="text-sm font-medium text-bordeaux-700 hover:text-bordeaux-900">Tout voir</a>
                    </div>
                    @if ($stockBas->isEmpty())
                        <p class="mt-3 text-sm text-stone-500">Rien à commander.</p>
                    @else
                        <ul class="mt-4 space-y-4 text-sm">
                            @foreach ($stockBas as $produit)
                                @php
                                    $niveauStock = $produit->seuil_alerte > 0 ? min(100, (int) round(max(0, $produit->quantite) * 100 / $produit->seuil_alerte)) : 0;
                                @endphp
                                <li>
                                    <div class="flex items-center justify-between gap-3">
                                        <a href="{{ route('produits.show', $produit) }}" class="truncate hover:text-bordeaux-800">{{ $produit->nom }}</a>
                                        <span @class(['badge shrink-0', 'bg-red-50 text-red-700' => $produit->estEnRupture(), 'bg-amber-50 text-amber-700' => ! $produit->estEnRupture()])>
                                            {{ $produit->estEnRupture() ? 'Rupture' : 'Reste '.$produit->quantite }}
                                        </span>
                                    </div>
                                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-beige-100" role="img"
                                         aria-label="{{ $produit->quantite }} en stock pour un seuil d'alerte de {{ $produit->seuil_alerte }}">
                                        <div class="h-full rounded-full {{ $produit->estEnRupture() ? 'bg-red-500' : 'bg-amber-400' }}" style="width: {{ $niveauStock }}%"></div>
                                    </div>
                                    <p class="mt-1 text-[11px] text-stone-400">{{ max(0, $produit->quantite) }} en stock · alerte à {{ $produit->seuil_alerte }}</p>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endif
        </div>
    </div>
    {{-- ================= Heures de pointe ================= --}}
    @if ($affluence !== null)
        @php
            $niveau = function (int $n) use ($affluence) {
                if ($n === 0) return 'bg-beige-100';
                return ['bg-bordeaux-200', 'bg-bordeaux-400', 'bg-bordeaux-600', 'bg-bordeaux-800'][min(3, (int) ceil($n * 4 / $affluence['max']) - 1)];
            };
            $rdvs = fn (int $n) => $n.' rendez-vous';
        @endphp
        <section class="card mt-6" x-data="{ info: null }">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="text-lg font-semibold">Heures de pointe</h2>
                <span class="text-sm text-stone-500">8 dernières semaines · {{ $rdvs($affluence['total']) }}</span>
            </div>

            @if ($affluence['pic'] === null)
                <p class="mt-3 text-sm text-stone-500">Pas encore assez de rendez-vous pour voir les heures chargées.</p>
            @else
                <p class="mt-1 text-sm text-stone-600">
                    Le plus chargé : <strong class="font-semibold text-bordeaux-900">{{ mb_strtolower($affluence['pic']['jour']->label()) }} vers {{ $affluence['pic']['heure'] }} h</strong>
                    ({{ $rdvs($affluence['pic']['nombre']) }} en 8 semaines)
                </p>

                <div class="mt-5 grid gap-[3px] text-[11px] text-stone-500"
                     style="grid-template-columns: 2.25rem repeat({{ count($affluence['heures']) }}, minmax(0, 1fr))"
                     role="img" aria-label="Rendez-vous par jour et par heure ; le plus chargé : {{ $affluence['pic']['jour']->label() }} vers {{ $affluence['pic']['heure'] }} h"
                     @mouseleave="info = null">
                    <span></span>
                    @foreach ($affluence['heures'] as $heure)
                        <span class="text-center tabular-nums">{{ $heure }}{{ $loop->first ? ' h' : '' }}</span>
                    @endforeach

                    @foreach ($affluence['jours'] as $ligne)
                        <span class="flex items-center font-medium text-stone-600">{{ mb_substr($ligne['jour']->label(), 0, 3) }}</span>
                        @foreach ($ligne['cases'] as $i => $nombre)
                            @php $texte = $ligne['jour']->label().' '.$affluence['heures'][$i].' h : '.($nombre ? $rdvs($nombre) : 'aucun rendez-vous'); @endphp
                            <span class="h-7 rounded-[4px] transition hover:ring-2 hover:ring-bordeaux-900/40 {{ $niveau($nombre) }}"
                                  @mouseenter="info = @js($texte)" @click="info = @js($texte)"
                                  data-nombre="{{ $nombre }}"></span>
                        @endforeach
                    @endforeach
                </div>

                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-xs text-stone-500">
                    <p class="min-h-4 font-medium text-stone-700" x-text="info ?? 'Survolez ou touchez une case pour voir le détail.'">Survolez ou touchez une case pour voir le détail.</p>
                    <div class="flex items-center gap-1.5" aria-hidden="true">
                        <span>Calme</span>
                        @foreach (['bg-beige-100', 'bg-bordeaux-200', 'bg-bordeaux-400', 'bg-bordeaux-600', 'bg-bordeaux-800'] as $c)
                            <span class="h-3 w-3 rounded-[3px] {{ $c }}"></span>
                        @endforeach
                        <span>Chargé</span>
                    </div>
                </div>

                <details class="mt-4 text-sm">
                    <summary class="cursor-pointer font-medium text-bordeaux-700 hover:text-bordeaux-900">Voir les chiffres</summary>
                    <div class="mt-3 overflow-x-auto">
                        <table class="w-full text-center text-xs tabular-nums">
                            <thead class="bg-beige-50 uppercase tracking-wide text-stone-500">
                                <tr>
                                    <th class="px-2 py-2 text-left">Jour</th>
                                    @foreach ($affluence['heures'] as $heure)<th class="px-2 py-2">{{ $heure }} h</th>@endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($affluence['jours'] as $ligne)
                                    <tr class="border-t border-beige-100">
                                        <td class="px-2 py-1.5 text-left">{{ $ligne['jour']->label() }}</td>
                                        @foreach ($ligne['cases'] as $nombre)<td class="px-2 py-1.5">{{ $nombre ?: '·' }}</td>@endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </details>
            @endif
        </section>
    @endif
</x-layouts.app>
