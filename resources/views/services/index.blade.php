@php $canManage = auth()->user()->can('create', \App\Models\Service::class); @endphp

<x-layouts.app title="Prestations">
    <x-page-header title="Prestations" subtitle="Catalogue des services, tarifs et durées.">
        @if ($canManage)
            <x-slot:actions>
                <a href="{{ route('services.create') }}" class="btn-primary">Nouvelle prestation</a>
            </x-slot:actions>
        @endif
    </x-page-header>

    @include('services._tabs')

    <form method="GET" action="{{ route('services.index') }}" class="card mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <div class="lg:col-span-2">
            <label for="q" class="label">Rechercher</label>
            <input id="q" name="q" value="{{ $filters['q'] }}" class="input" placeholder="Nom de la prestation">
        </div>
        <div>
            <label for="categorie" class="label">Catégorie</label>
            <select id="categorie" name="categorie" class="input">
                <option value="">Toutes</option>
                @foreach ($categories as $categorie)
                    <option value="{{ $categorie->id }}" @selected($filters['categorie'] === $categorie->id)>{{ $categorie->nom }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="zone" class="label">Zone</label>
            <select id="zone" name="zone" class="input">
                <option value="">Toutes</option>
                @foreach (\App\Enums\ZoneSalon::cases() as $zone)
                    <option value="{{ $zone->value }}" @selected($filters['zone'] === $zone->value)>{{ $zone->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="statut" class="label">Statut</label>
            <select id="statut" name="statut" class="input">
                <option value="tous" @selected($filters['statut'] === 'tous')>Toutes</option>
                <option value="actifs" @selected($filters['statut'] === 'actifs')>Actives</option>
                <option value="inactifs" @selected($filters['statut'] === 'inactifs')>Désactivées</option>
            </select>
        </div>
        <div class="flex gap-3 sm:col-span-2 lg:col-span-5">
            <button type="submit" class="btn-primary">Filtrer</button>
            <a href="{{ route('services.index') }}" class="btn-secondary">Réinitialiser</a>
        </div>
    </form>

    @if ($total === 0)
        <div class="card text-center text-sm text-stone-500">
            Aucune prestation ne correspond à votre recherche.
        </div>
    @else
        @foreach ($categories as $categorie)
            @php $liste = $services->get($categorie->id, collect()); @endphp
            @continue($liste->isEmpty())

            <section class="card mb-6 overflow-hidden p-0">
                <h2 class="border-b border-beige-200 px-6 py-4 text-lg font-semibold">{{ $categorie->nom }}</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-beige-50 text-xs uppercase tracking-wide text-stone-500">
                            <tr>
                                <th class="px-6 py-3">Prestation</th>
                                <th class="px-3 py-3">Durée</th>
                                <th class="px-3 py-3">Zone</th>
                                <th class="px-3 py-3 text-right">Prix</th>
                                <th class="px-3 py-3">Statut</th>
                                @if ($canManage)
                                    <th class="px-6 py-3"></th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($liste as $service)
                                <tr class="border-t border-beige-100 {{ $service->is_active ? '' : 'bg-stone-50 text-stone-400' }}">
                                    <td class="px-6 py-4">
                                        <p class="font-medium">{{ $service->nom }}</p>
                                        @if ($service->description)
                                            <p class="mt-0.5 max-w-md text-xs text-stone-500">{{ $service->description }}</p>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4">{{ $service->dureeFormatee() }}</td>
                                    <td class="px-3 py-4">
                                        <span class="badge {{ $service->zone === \App\Enums\ZoneSalon::Femmes ? 'bg-bordeaux-50 text-bordeaux-700' : 'bg-beige-100 text-stone-700' }}">
                                            {{ $service->zone->label() }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-right font-medium">{{ $service->prixFormate() }}</td>
                                    <td class="px-3 py-4">
                                        <span class="badge {{ $service->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-stone-100 text-stone-500' }}">
                                            {{ $service->is_active ? 'Active' : 'Désactivée' }}
                                        </span>
                                    </td>
                                    @if ($canManage)
                                        <td class="whitespace-nowrap px-6 py-4 text-right">
                                            @can('update', $service)
                                                <a href="{{ route('services.edit', $service) }}" class="text-sm font-medium text-bordeaux-700 hover:text-bordeaux-900">Modifier</a>
                                                <form method="POST" action="{{ route('services.toggle', $service) }}" class="ml-3 inline"
                                                      @if ($service->is_active) onsubmit="return confirm('Désactiver cette prestation ? Elle ne sera plus proposée pour les nouveaux rendez-vous.')" @endif>
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="text-sm font-medium text-stone-600 hover:text-bordeaux-800">
                                                        {{ $service->is_active ? 'Désactiver' : 'Activer' }}
                                                    </button>
                                                </form>
                                            @endcan
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach
    @endif
</x-layouts.app>