@php
    $canManage = auth()->user()->can('create', \App\Models\Employe::class);
    $canSeeEarnings = auth()->user()->can('staff.view_earnings');
    $jours = \App\Enums\JourSemaine::cases();
@endphp

<x-layouts.app title="Équipe">
    <x-page-header title="Équipe" subtitle="Fiches, prestations proposées et horaires de travail.">
        @if ($canManage)
            <x-slot:actions>
                <a href="{{ route('employes.create') }}" class="btn-primary">Nouvel employé</a>
            </x-slot:actions>
        @endif
    </x-page-header>

    <form method="GET" action="{{ route('employes.index') }}" class="card mb-6 grid gap-4 sm:grid-cols-3">
        <div class="sm:col-span-2">
            <label for="q" class="label">Rechercher</label>
            <input id="q" name="q" value="{{ $filters['q'] }}" class="input" placeholder="Nom ou prénom">
        </div>
        <div>
            <label for="statut" class="label">Statut</label>
            <select id="statut" name="statut" class="input">
                <option value="tous" @selected($filters['statut'] === 'tous')>Tous</option>
                <option value="actifs" @selected($filters['statut'] === 'actifs')>Actifs</option>
                <option value="inactifs" @selected($filters['statut'] === 'inactifs')>Désactivés</option>
            </select>
        </div>
        <div class="flex gap-3 sm:col-span-3">
            <button type="submit" class="btn-primary">Filtrer</button>
            <a href="{{ route('employes.index') }}" class="btn-secondary">Réinitialiser</a>
        </div>
    </form>

    @if ($employes->isEmpty())
        <div class="card text-center text-sm text-stone-500">Aucun employé ne correspond à votre recherche.</div>
    @else
        <div class="card overflow-hidden p-0">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-beige-50 text-xs uppercase tracking-wide text-stone-500">
                        <tr>
                            <th class="px-6 py-3">Employé</th>
                            <th class="px-3 py-3">Téléphone</th>
                            <th class="px-3 py-3">Prestations</th>
                            <th class="px-3 py-3">Jours travaillés</th>
                            @if ($canSeeEarnings)
                                <th class="px-3 py-3">Commission</th>
                            @endif
                            <th class="px-3 py-3">Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($employes as $employe)
                            @php $joursActifs = $employe->disponibilites->map(fn ($d) => $d->jour_semaine->value)->unique()->all(); @endphp
                            <tr class="border-t border-beige-100 {{ $employe->is_active ? '' : 'bg-stone-50 text-stone-400' }}">
                                <td class="px-6 py-4">
                                    <a href="{{ route('employes.show', $employe) }}" class="font-medium text-bordeaux-800 hover:text-bordeaux-900">{{ $employe->nomComplet() }}</a>
                                    <p class="mt-0.5 text-xs text-stone-500">{{ $employe->fonction }}</p>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4">{{ $employe->telephone ?? '—' }}</td>
                                <td class="px-3 py-4">{{ $employe->services_count }}</td>
                                <td class="px-3 py-4">
                                    <div class="flex gap-1">
                                        @foreach ($jours as $jour)
                                            @php $travaille = in_array($jour->value, $joursActifs, true); @endphp
                                            <span title="{{ $jour->label() }}" @class([
                                                'flex h-6 w-6 items-center justify-center rounded-full text-[11px] font-medium',
                                                'bg-bordeaux-700 text-white' => $travaille,
                                                'bg-stone-100 text-stone-400' => ! $travaille,
                                            ])>{{ $jour->initiale() }}</span>
                                        @endforeach
                                    </div>
                                </td>
                                @if ($canSeeEarnings)
                                    <td class="whitespace-nowrap px-3 py-4">{{ $employe->commissionLibelle() }}</td>
                                @endif
                                <td class="px-3 py-4">
                                    <span class="badge {{ $employe->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-stone-100 text-stone-500' }}">
                                        {{ $employe->is_active ? 'Active' : 'Désactivée' }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-layouts.app>