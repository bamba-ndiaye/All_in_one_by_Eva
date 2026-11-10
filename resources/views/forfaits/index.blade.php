@php $canManage = auth()->user()->can('create', \App\Models\Forfait::class); @endphp

<x-layouts.app title="Forfaits">
    <x-page-header title="Prestations" subtitle="Forfaits regroupant plusieurs prestations à un prix propre.">
        @if ($canManage)
            <x-slot:actions>
                <a href="{{ route('services.forfaits.create') }}" class="btn-primary">Nouveau forfait</a>
            </x-slot:actions>
        @endif
    </x-page-header>

    @include('services._tabs')

    @if ($forfaits->isEmpty())
        <div class="card text-center text-sm text-stone-500">Aucun forfait pour l'instant.</div>
    @else
        <div class="card overflow-hidden p-0">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-beige-50 text-xs uppercase tracking-wide text-stone-500">
                        <tr>
                            <th class="px-6 py-3">Forfait</th>
                            <th class="px-3 py-3">Durée</th>
                            <th class="px-3 py-3">Zone</th>
                            <th class="px-3 py-3 text-right">Prix</th>
                            <th class="px-3 py-3 text-right">Économie</th>
                            <th class="px-3 py-3">Statut</th>
                            @if ($canManage)
                                <th class="px-6 py-3"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($forfaits as $forfait)
                            @php $economie = $forfait->economie(); @endphp
                            <tr class="border-t border-beige-100 {{ $forfait->is_active ? '' : 'bg-stone-50 text-stone-400' }}">
                                <td class="px-6 py-4">
                                    <p class="font-medium">{{ $forfait->nom }}</p>
                                    <p class="mt-0.5 max-w-md text-xs text-stone-500">{{ $forfait->services->pluck('nom')->join(', ') }}</p>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4">{{ $forfait->dureeFormatee() }}</td>
                                <td class="px-3 py-4">
                                    <span class="badge {{ $forfait->zone() === \App\Enums\ZoneSalon::Femmes ? 'bg-bordeaux-50 text-bordeaux-700' : 'bg-beige-100 text-stone-700' }}">
                                        {{ $forfait->zone()->label() }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-right font-medium">{{ $forfait->prixFormate() }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-right">
                                    @if ($economie > 0)
                                        <span class="text-emerald-700">{{ \App\Support\Fcfa::format($economie) }}</span>
                                    @elseif ($economie < 0)
                                        <span class="text-red-700" title="Ce forfait coûte plus cher que les prestations séparées">+{{ \App\Support\Fcfa::format(abs($economie)) }}</span>
                                    @else
                                        <span class="text-stone-400">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-4">
                                    <span class="badge {{ $forfait->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-stone-100 text-stone-500' }}">
                                        {{ $forfait->is_active ? 'Actif' : 'Désactivé' }}
                                    </span>
                                </td>
                                @if ($canManage)
                                    <td class="whitespace-nowrap px-6 py-4 text-right">
                                        @can('update', $forfait)
                                            <a href="{{ route('services.forfaits.edit', $forfait) }}" class="text-sm font-medium text-bordeaux-700 hover:text-bordeaux-900">Modifier</a>
                                            <form method="POST" action="{{ route('services.forfaits.toggle', $forfait) }}" class="ml-3 inline"
                                                  @if ($forfait->is_active) onsubmit="return confirm('Désactiver ce forfait ? Il ne sera plus proposé pour les nouveaux rendez-vous.')" @endif>
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="text-sm font-medium text-stone-600 hover:text-bordeaux-800">
                                                    {{ $forfait->is_active ? 'Désactiver' : 'Activer' }}
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
        </div>
    @endif
</x-layouts.app>