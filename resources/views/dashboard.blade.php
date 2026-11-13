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
        <div class="mb-6 grid gap-6 sm:grid-cols-3">
            <x-stat-card label="Encaissé ce mois" :value="$fcfa($finances['encaisse'])" icon="wallet"
                         :hint="$finances['evolution'] === null ? null : ($finances['evolution'] >= 0 ? '+' : '').$finances['evolution'].' % par rapport au mois dernier à la même date'" />
            <x-stat-card label="Dépenses ce mois" :value="$fcfa($finances['depenses'])" icon="receipt" />
            <x-stat-card label="Résultat estimé" :value="$fcfa($finances['resultat'])" icon="trending-up" hint="Encaissé moins dépenses" />
        </div>

        <section class="card mb-6">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="text-lg font-semibold">Recettes et dépenses</h2>
                <span class="text-sm text-stone-500">30 derniers jours</span>
            </div>
            <div class="relative mt-4 h-64">
                <canvas data-graphique="recettes-depenses" data-serie="{{ json_encode($serie) }}"
                        role="img" aria-label="Courbe des recettes et des dépenses par jour sur les 30 derniers jours"></canvas>
            </div>
            <details class="mt-4 text-sm">
                <summary class="cursor-pointer font-medium text-bordeaux-700 hover:text-bordeaux-900">Voir les chiffres jour par jour</summary>
                <div class="mt-3 max-h-64 overflow-y-auto">
                    <table class="w-full text-left">
                        <thead class="sticky top-0 bg-beige-50 text-xs uppercase tracking-wide text-stone-500">
                            <tr><th class="px-3 py-2">Jour</th><th class="px-3 py-2 text-right">Recettes</th><th class="px-3 py-2 text-right">Dépenses</th></tr>
                        </thead>
                        <tbody class="tabular-nums">
                            @foreach (array_reverse($serie) as $j)
                                <tr class="border-t border-beige-100">
                                    <td class="px-3 py-1.5">{{ \Illuminate\Support\Carbon::parse($j['date'])->format('d/m') }}</td>
                                    <td class="px-3 py-1.5 text-right">{{ $fcfa($j['encaisse']) }}</td>
                                    <td class="px-3 py-1.5 text-right">{{ $fcfa($j['depenses']) }}</td>
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
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            @if ($rendezVous !== null)
                <section class="card overflow-hidden p-0">
                    <div class="flex items-center justify-between px-6 pt-6">
                        <h2 class="text-lg font-semibold">{{ $voitTousLesRdv ? "Rendez-vous d'aujourd'hui" : "Mes rendez-vous d'aujourd'hui" }}</h2>
                        <a href="{{ route('rendez-vous.index') }}" class="text-sm font-medium text-bordeaux-700 hover:text-bordeaux-900">Agenda</a>
                    </div>
                    @if ($rendezVous->isEmpty())
                        <p class="px-6 py-6 text-sm text-stone-500">Aucun rendez-vous aujourd'hui.</p>
                    @else
                        <ul class="mt-4 divide-y divide-beige-100">
                            @foreach ($rendezVous as $rdv)
                                <li>
                                    <a href="{{ route('rendez-vous.show', $rdv) }}" class="flex items-center gap-4 px-6 py-3 transition hover:bg-beige-50">
                                        <span class="w-14 shrink-0 font-semibold tabular-nums text-bordeaux-900">{{ $rdv->debut() }}</span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate font-medium">{{ $rdv->client->nomComplet() }}</span>
                                            <span class="block truncate text-xs text-stone-500">avec {{ $rdv->employe->nomComplet() }} · jusqu'à {{ $rdv->fin() }}</span>
                                        </span>
                                        <span class="badge shrink-0 {{ $rdv->statut->badge() }}">{{ $rdv->statut->label() }}</span>
                                    </a>
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

        <div class="space-y-6">
            @if ($encaisseJour !== null)
                <section class="card">
                    <h2 class="text-lg font-semibold">Caisse du jour</h2>
                    <p class="mt-3 font-serif text-3xl font-semibold text-bordeaux-900">{{ $fcfa($encaisseJour) }}</p>
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
                        <ul class="mt-3 space-y-2 text-sm">
                            @foreach ($anniversaires->take(5) as $a)
                                <li class="flex items-center justify-between gap-3">
                                    <span class="truncate">{{ $a['client']->nomComplet() }}</span>
                                    <span @class(['shrink-0 text-xs', 'font-semibold text-bordeaux-800' => $a['dans'] === 0, 'text-stone-500' => $a['dans'] > 0])>
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
                        <ul class="mt-3 space-y-2 text-sm">
                            @foreach ($stockBas as $produit)
                                <li class="flex items-center justify-between gap-3">
                                    <a href="{{ route('produits.show', $produit) }}" class="truncate hover:text-bordeaux-800">{{ $produit->nom }}</a>
                                    <span @class(['badge shrink-0', 'bg-red-50 text-red-700' => $produit->estEnRupture(), 'bg-amber-50 text-amber-700' => ! $produit->estEnRupture()])>
                                        {{ $produit->estEnRupture() ? 'Rupture' : 'Reste '.$produit->quantite }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endif
        </div>
    </div>
</x-layouts.app>
