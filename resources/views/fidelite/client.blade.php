<x-layouts.app :title="'Fidélité — '.$client->nomComplet()">
    <x-page-header :title="$client->nomComplet()" subtitle="Points de fidélité">
        <x-slot:actions>
            <a href="{{ route('fidelite.index') }}" class="btn-secondary">Retour à la fidélité</a>
            @can('view', $client)
                <a href="{{ route('clients.show', $client) }}" class="btn-secondary">Fiche cliente</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <section class="card">
                <p class="text-sm text-stone-500">Solde actuel</p>
                <p @class(['mt-1 font-serif text-4xl font-semibold', 'text-bordeaux-900' => $solde >= 0, 'text-red-700' => $solde < 0])>
                    {{ $solde }} <span class="text-xl">points</span>
                </p>
                @php $prochaine = $recompenses->first(fn ($r) => $r->points > $solde); @endphp
                @if ($solde < 0)
                    <p class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                        Solde négatif : un paiement a été annulé après l'utilisation de ses points.
                        Les {{ abs($solde) }} points manquants seront repris sur les prochains achats.
                    </p>
                @elseif ($prochaine)
                    <p class="mt-2 text-sm text-stone-500">
                        Encore {{ $prochaine->points - $solde }} points pour « {{ $prochaine->nom }} »
                        @if ($fcfaParPoint > 0)
                            (environ {{ \App\Support\Fcfa::format(($prochaine->points - $solde) * $fcfaParPoint) }} de prestations).
                        @endif
                    </p>
                @endif
            </section>

            <section class="card overflow-hidden p-0">
                <h2 class="px-6 pt-6 text-lg font-semibold">Historique des points</h2>
                @if ($mouvements->isEmpty())
                    <p class="px-6 py-6 text-sm text-stone-500">Aucun point pour l'instant. Ils s'ajoutent automatiquement à chaque encaissement.</p>
                @else
                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-beige-50 text-xs uppercase tracking-wide text-stone-500">
                                <tr>
                                    <th class="px-6 py-3">Date</th>
                                    <th class="px-3 py-3">Détail</th>
                                    <th class="px-6 py-3 text-right">Points</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($mouvements as $mouvement)
                                    <tr class="border-t border-beige-100 align-top">
                                        <td class="whitespace-nowrap px-6 py-3">{{ $mouvement->created_at?->translatedFormat('d M Y, H:i') }}</td>
                                        <td class="px-3 py-3">
                                            {{ $mouvement->type->label() }}
                                            <p class="mt-0.5 text-xs text-stone-500">
                                                @if ($mouvement->paiement)
                                                    Paiement {{ $mouvement->paiement->numero() }}
                                                @elseif ($mouvement->motif)
                                                    {{ $mouvement->motif }}
                                                @endif
                                                @if ($mouvement->user) · par {{ $mouvement->user->name }} @endif
                                            </p>
                                        </td>
                                        <td @class(['whitespace-nowrap px-6 py-3 text-right font-medium',
                                                    'text-emerald-700' => $mouvement->points > 0,
                                                    'text-red-700' => $mouvement->points < 0])>
                                            {{ $mouvement->points > 0 ? '+' : '' }}{{ $mouvement->points }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="px-6 py-4">{{ $mouvements->links() }}</div>
                @endif
            </section>
        </div>

        <div class="space-y-6">
            @if ($peutUtiliser)
                <section class="card">
                    <h2 class="text-lg font-semibold">Offrir une récompense</h2>
                    @if ($recompenses->isEmpty())
                        <p class="mt-3 text-sm text-stone-500">Aucune récompense n'est proposée.</p>
                    @else
                        <form method="POST" action="{{ route('fidelite.utiliser', $client) }}" class="mt-4 space-y-3"
                              x-data="{ loading: false }" @submit="loading = true">
                            @csrf
                            @foreach ($recompenses as $recompense)
                                @php $possible = $recompense->points <= $solde; @endphp
                                <label @class(['flex items-center gap-3 rounded-lg border px-3 py-2.5 text-sm',
                                               'border-beige-200 cursor-pointer hover:border-bordeaux-300' => $possible,
                                               'border-beige-100 text-stone-400' => ! $possible])>
                                    <input type="radio" name="recompense_id" value="{{ $recompense->id }}" @disabled(! $possible) required
                                           class="h-4 w-4 border-beige-300 text-bordeaux-700 focus:ring-bordeaux-500">
                                    <span class="flex-1">{{ $recompense->nom }}</span>
                                    <span class="shrink-0 text-xs font-medium">{{ $recompense->points }} pts</span>
                                </label>
                            @endforeach
                            <button type="submit" class="btn-primary w-full" :disabled="loading"
                                    onclick="return confirm('Confirmer : les points seront déduits du solde.')">
                                Utiliser les points
                            </button>
                        </form>
                    @endif
                </section>
            @endif

            @if ($peutCorriger)
                <section class="card">
                    <h2 class="text-lg font-semibold">Corriger les points</h2>
                    <form method="POST" action="{{ route('fidelite.corriger', $client) }}" class="mt-4 space-y-3">
                        @csrf
                        <div>
                            <label for="points" class="label">Points à ajouter ou retirer</label>
                            <input id="points" name="points" type="number" step="1" required class="input" placeholder="20 ou -20" value="{{ old('points') }}">
                        </div>
                        <div>
                            <label for="motif" class="label">Motif</label>
                            <input id="motif" name="motif" required minlength="5" maxlength="255" class="input"
                                   placeholder="Geste commercial, erreur de saisie…" value="{{ old('motif') }}">
                        </div>
                        <button type="submit" class="btn-secondary w-full">Enregistrer la correction</button>
                    </form>
                </section>
            @endif
        </div>
    </div>
</x-layouts.app>
