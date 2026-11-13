<x-layouts.app title="Utilisateurs">
    <x-page-header title="Utilisateurs" subtitle="Comptes de connexion, rôles et droits d'accès.">
        @can('create', \App\Models\User::class)
            <x-slot:actions>
                <a href="{{ route('utilisateurs.create') }}" class="btn-primary">Nouveau compte</a>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <form method="GET" action="{{ route('utilisateurs.index') }}" class="card mb-6 grid gap-4 sm:grid-cols-4">
        <div class="sm:col-span-2">
            <label for="q" class="label">Rechercher</label>
            <input id="q" name="q" value="{{ $filtres['q'] }}" class="input" placeholder="Nom ou adresse e-mail">
        </div>
        <div>
            <label for="role" class="label">Rôle</label>
            <select id="role" name="role" class="input">
                <option value="">Tous les rôles</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->value }}" @selected($filtres['role'] === $role->value)>{{ $role->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="statut" class="label">Statut</label>
            <select id="statut" name="statut" class="input">
                <option value="tous" @selected($filtres['statut'] === 'tous')>Tous</option>
                <option value="actifs" @selected($filtres['statut'] === 'actifs')>Actifs</option>
                <option value="inactifs" @selected($filtres['statut'] === 'inactifs')>Désactivés</option>
            </select>
        </div>
        <div class="flex gap-3 sm:col-span-4">
            <button type="submit" class="btn-primary">Filtrer</button>
            <a href="{{ route('utilisateurs.index') }}" class="btn-secondary">Réinitialiser</a>
        </div>
    </form>

    @if ($utilisateurs->isEmpty())
        <div class="card text-center text-sm text-stone-500">Aucun compte ne correspond à votre recherche.</div>
    @else
        <div class="card overflow-hidden p-0">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-beige-50 text-xs uppercase tracking-wide text-stone-500">
                        <tr>
                            <th class="px-6 py-3">Utilisateur</th>
                            <th class="px-3 py-3">Rôle</th>
                            <th class="px-3 py-3">Droits en plus</th>
                            <th class="px-3 py-3">Dernière connexion</th>
                            <th class="px-3 py-3">Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($utilisateurs as $compte)
                            <tr class="border-t border-beige-100 {{ $compte->is_active ? '' : 'bg-stone-50 text-stone-400' }}">
                                <td class="px-6 py-4">
                                    <a href="{{ route('utilisateurs.show', $compte) }}" class="font-medium text-bordeaux-800 hover:text-bordeaux-900">{{ $compte->name }}</a>
                                    @if ($compte->is(auth()->user()))
                                        <span class="badge ml-1 bg-beige-100 text-bordeaux-800">Vous</span>
                                    @endif
                                    <p class="mt-0.5 text-xs text-stone-500">{{ $compte->email }}</p>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4">{{ $compte->role->label() }}</td>
                                <td class="px-3 py-4">{{ $compte->extra_permissions_count ?: '—' }}</td>
                                <td class="whitespace-nowrap px-3 py-4">
                                    {{ $compte->last_login_at ? $compte->last_login_at->translatedFormat('d M Y à H:i') : 'Jamais' }}
                                </td>
                                <td class="px-3 py-4">
                                    <span class="badge {{ $compte->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-stone-100 text-stone-500' }}">
                                        {{ $compte->is_active ? 'Actif' : 'Désactivé' }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-6">{{ $utilisateurs->links() }}</div>
    @endif
</x-layouts.app>
