@php
    $canCreate = auth()->user()->can('create', \App\Models\Client::class);
    $canExport = auth()->user()->can('export', \App\Models\Client::class);
@endphp

<x-layouts.app title="Clients">
    <x-page-header title="Clients" subtitle="Fiches clients, coordonnées et consentements.">
        <x-slot:actions>
            @if ($canExport)
                <a href="{{ route('clients.export', request()->except('page')) }}" class="btn-secondary">Exporter en CSV</a>
            @endif
            @if ($canCreate)
                <a href="{{ route('clients.create') }}" class="btn-primary">Nouveau client</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <form method="GET" action="{{ route('clients.index') }}" class="card mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <div class="lg:col-span-2">
            <label for="q" class="label">Rechercher</label>
            <input id="q" name="q" value="{{ $filters['q'] }}" class="input" placeholder="Nom, prénom ou numéro de téléphone">
        </div>
        <div>
            <label for="statut" class="label">Statut</label>
            <select id="statut" name="statut" class="input">
                <option value="actifs" @selected($filters['statut'] === 'actifs')>Actifs</option>
                <option value="archives" @selected($filters['statut'] === 'archives')>Archivés</option>
                <option value="tous" @selected($filters['statut'] === 'tous')>Tous</option>
            </select>
        </div>
        <div>
            <label for="consentement" class="label">Messages promo</label>
            <select id="consentement" name="consentement" class="input">
                <option value="">Tous</option>
                <option value="oui" @selected($filters['consentement'] === 'oui')>Consentement donné</option>
                <option value="non" @selected($filters['consentement'] === 'non')>Pas de consentement</option>
            </select>
        </div>
        <div>
            <label for="canal" class="label">Canal préféré</label>
            <select id="canal" name="canal" class="input">
                <option value="">Tous</option>
                @foreach (\App\Enums\CanalCommunication::cases() as $canal)
                    <option value="{{ $canal->value }}" @selected($filters['canal'] === $canal->value)>{{ $canal->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex flex-wrap items-center gap-4 sm:col-span-2 lg:col-span-5">
            <label class="inline-flex items-center gap-2 text-sm text-stone-700">
                <input type="checkbox" name="naissance" value="manquante"
                       class="h-4 w-4 rounded border-beige-300 text-bordeaux-700 focus:ring-bordeaux-500"
                       @checked($filters['naissance'] === 'manquante')>
                Date de naissance non renseignée
            </label>
            <div class="ml-auto flex gap-3">
                <button type="submit" class="btn-primary">Filtrer</button>
                <a href="{{ route('clients.index') }}" class="btn-secondary">Réinitialiser</a>
            </div>
        </div>
    </form>

    @if ($clients->isEmpty())
        <div class="card text-center text-sm text-stone-500">Aucun client ne correspond à votre recherche.</div>
    @else
        <p class="mb-3 text-sm text-stone-500">{{ $clients->total() }} client(s)</p>

        <div class="card overflow-hidden p-0">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-beige-50 text-xs uppercase tracking-wide text-stone-500">
                        <tr>
                            <th class="px-6 py-3">Client</th>
                            <th class="px-3 py-3">Téléphone</th>
                            <th class="px-3 py-3">Messages promo</th>
                            <th class="px-3 py-3">Inscrit le</th>
                            <th class="px-3 py-3">Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($clients as $client)
                            <tr class="border-t border-beige-100 {{ $client->is_active ? '' : 'bg-stone-50 text-stone-400' }}">
                                <td class="px-6 py-4">
                                    <a href="{{ route('clients.show', $client) }}" class="font-medium text-bordeaux-800 hover:text-bordeaux-900">{{ $client->nomComplet() }}</a>
                                    @if ($client->email)
                                        <p class="mt-0.5 text-xs text-stone-500">{{ $client->email }}</p>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-3 py-4">{{ $client->telephoneAffiche() }}</td>
                                <td class="px-3 py-4">
                                    <span class="badge {{ $client->consentement_promo ? 'bg-emerald-50 text-emerald-700' : 'bg-stone-100 text-stone-500' }}">
                                        {{ $client->consentement_promo ? 'Oui' : 'Non' }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4">{{ $client->created_at->format('d/m/Y') }}</td>
                                <td class="px-3 py-4">
                                    <span class="badge {{ $client->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-stone-100 text-stone-500' }}">
                                        {{ $client->is_active ? 'Actif' : 'Archivé' }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-6">{{ $clients->links() }}</div>
    @endif
</x-layouts.app>