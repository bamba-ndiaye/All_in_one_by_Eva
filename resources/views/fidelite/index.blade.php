<x-layouts.app title="Fidélité">
    <x-page-header title="Fidélité"
                   :subtitle="$fcfaParPoint > 0
                       ? 'Chaque encaissement rapporte 1 point par tranche de '.\App\Support\Fcfa::format($fcfaParPoint).'.'
                       : 'Le gain de points est désactivé (FIDELITE_FCFA_PAR_POINT = 0).'" />

    @include('fidelite._tabs')

    <div class="mb-6 grid gap-6 sm:grid-cols-3">
        <x-stat-card label="Clientes avec des points" :value="$nbClientes" icon="users" />
        <x-stat-card label="Points à utiliser" :value="number_format($pointsEnCours, 0, '', ' ')" icon="gift" hint="Total des soldes" />
        <x-stat-card label="Récompenses ce mois" :value="$utilisationsMois" icon="sparkles" />
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <form method="GET" action="{{ route('fidelite.index') }}" class="card mb-6 flex flex-wrap items-end gap-3">
                <div class="min-w-0 flex-1">
                    <label for="q" class="label">Trouver une cliente</label>
                    <input id="q" name="q" value="{{ $q }}" class="input" placeholder="Nom, prénom ou numéro">
                </div>
                <button type="submit" class="btn-primary">Rechercher</button>
                @if ($q !== '')
                    <a href="{{ route('fidelite.index') }}" class="btn-secondary">Effacer</a>
                @endif
            </form>

            @if ($clients->isEmpty())
                <div class="card text-center text-sm text-stone-500">
                    {{ $q === '' ? 'Aucune cliente n\'a encore de points : ils arrivent avec les encaissements.' : 'Aucune cliente ne correspond à votre recherche.' }}
                </div>
            @else
                <div class="card overflow-hidden p-0">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-beige-50 text-xs uppercase tracking-wide text-stone-500">
                            <tr>
                                <th class="px-6 py-3">Cliente</th>
                                <th class="px-6 py-3 text-right">Points</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($clients as $client)
                                @php
                                    $points = (int) $client->points;
                                    $atteignable = $recompenses->first(fn ($r) => $r->points <= $points);
                                @endphp
                                <tr class="border-t border-beige-100">
                                    <td class="px-6 py-3.5">
                                        <a href="{{ route('fidelite.client', $client) }}" class="font-medium text-bordeaux-800 hover:text-bordeaux-900">{{ $client->nomComplet() }}</a>
                                        <p class="mt-0.5 text-xs text-stone-500">{{ $client->telephoneAffiche() }}</p>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-3.5 text-right">
                                        <span @class(['font-serif text-lg font-semibold', 'text-bordeaux-900' => $points >= 0, 'text-red-700' => $points < 0])>{{ $points }}</span>
                                        @if ($points < 0)
                                            <span class="badge ml-2 bg-amber-50 text-amber-700">Solde négatif</span>
                                        @endif
                                        @if ($atteignable)
                                            <span class="badge ml-2 bg-emerald-50 text-emerald-700" title="Au moins une récompense est disponible">Récompense possible</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-6">{{ $clients->links() }}</div>
            @endif
        </div>

        <section class="card h-fit">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold">Récompenses</h2>
                <a href="{{ route('fidelite.recompenses.index') }}" class="text-sm font-medium text-bordeaux-700 hover:text-bordeaux-900">Voir tout</a>
            </div>
            @if ($recompenses->isEmpty())
                <p class="mt-4 text-sm text-stone-500">Aucune récompense proposée pour l'instant.</p>
            @else
                <ul class="mt-4 divide-y divide-beige-100 text-sm">
                    @foreach ($recompenses as $recompense)
                        <li class="flex items-center justify-between gap-3 py-2.5">
                            <span>{{ $recompense->nom }}</span>
                            <span class="badge shrink-0 bg-beige-100 text-bordeaux-800">{{ $recompense->points }} pts</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
</x-layouts.app>
