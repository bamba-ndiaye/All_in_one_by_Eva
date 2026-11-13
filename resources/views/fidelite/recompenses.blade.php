@php $peutGerer = auth()->user()->can('loyalty.manage'); @endphp

<x-layouts.app title="Récompenses">
    <x-page-header title="Fidélité" subtitle="Ce que les clientes peuvent obtenir avec leurs points.">
        @if ($peutGerer)
            <x-slot:actions>
                <a href="{{ route('fidelite.recompenses.create') }}" class="btn-primary">Nouvelle récompense</a>
            </x-slot:actions>
        @endif
    </x-page-header>

    @include('fidelite._tabs')

    @if ($recompenses->isEmpty())
        <div class="card text-center text-sm text-stone-500">Aucune récompense pour l'instant.</div>
    @else
        <div class="card overflow-hidden p-0">
            <table class="w-full text-left text-sm">
                <thead class="bg-beige-50 text-xs uppercase tracking-wide text-stone-500">
                    <tr>
                        <th class="px-6 py-3">Récompense</th>
                        <th class="px-3 py-3 text-right">Points</th>
                        <th class="px-3 py-3">Statut</th>
                        @if ($peutGerer)
                            <th class="px-6 py-3"><span class="sr-only">Actions</span></th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($recompenses as $recompense)
                        <tr class="border-t border-beige-100 {{ $recompense->is_active ? '' : 'bg-stone-50 text-stone-400' }}">
                            <td class="px-6 py-3.5">
                                <span class="font-medium">{{ $recompense->nom }}</span>
                                @if ($recompense->description)
                                    <p class="mt-0.5 text-xs text-stone-500">{{ $recompense->description }}</p>
                                @endif
                            </td>
                            <td class="px-3 py-3.5 text-right font-medium">{{ $recompense->points }}</td>
                            <td class="px-3 py-3.5">
                                <span class="badge {{ $recompense->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-stone-100 text-stone-500' }}">
                                    {{ $recompense->is_active ? 'Proposée' : 'Retirée' }}
                                </span>
                            </td>
                            @if ($peutGerer)
                                <td class="whitespace-nowrap px-6 py-3.5 text-right">
                                    <a href="{{ route('fidelite.recompenses.edit', $recompense) }}" class="font-medium text-bordeaux-700 hover:text-bordeaux-900">Modifier</a>
                                    <form method="POST" action="{{ route('fidelite.recompenses.toggle', $recompense) }}" class="ml-4 inline">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="font-medium text-stone-500 hover:text-bordeaux-900">
                                            {{ $recompense->is_active ? 'Retirer' : 'Proposer' }}
                                        </button>
                                    </form>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-layouts.app>
