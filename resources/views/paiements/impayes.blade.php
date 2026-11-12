@php $canCreate = auth()->user()->can('create', \App\Models\Paiement::class); @endphp

<x-layouts.app title="Impayés">
    <x-page-header title="Caisse" subtitle="Rendez-vous terminés dont le prix n'est pas entièrement réglé." />

    @include('paiements._tabs')

    @if ($voitRevenu)
        <div class="mb-6 max-w-sm">
            <x-stat-card label="Total des impayés" :value="\App\Support\Fcfa::format($totalImpaye)" icon="receipt" hint="Reste à encaisser" />
        </div>
    @endif

    @if ($rendezVous->isEmpty())
        <div class="card text-center text-sm text-stone-500">Aucun impayé.</div>
    @else
        <div class="card overflow-hidden p-0">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-beige-50 text-xs uppercase tracking-wide text-stone-500">
                        <tr>
                            <th class="px-6 py-3">Rendez-vous</th>
                            <th class="px-3 py-3">Client</th>
                            <th class="px-3 py-3">Prestations</th>
                            <th class="px-3 py-3 text-right">Total</th>
                            <th class="px-3 py-3 text-right">Payé</th>
                            <th class="px-3 py-3 text-right">Reste à payer</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rendezVous as $rdv)
                            @php $reste = (int) $rdv->prix_prevu - (int) ($rdv->montant_paye ?? 0); @endphp
                            <tr class="border-t border-beige-100">
                                <td class="whitespace-nowrap px-6 py-3">{{ $rdv->date->format('d/m/Y') }} · {{ $rdv->debut() }}</td>
                                <td class="px-3 py-3">{{ $rdv->client->nomComplet() }}</td>
                                <td class="px-3 py-3">{{ $rdv->lignes->pluck('nom')->join(', ') }}</td>
                                <td class="whitespace-nowrap px-3 py-3 text-right">{{ $rdv->prixFormate() }}</td>
                                <td class="whitespace-nowrap px-3 py-3 text-right">{{ \App\Support\Fcfa::format((int) ($rdv->montant_paye ?? 0)) }}</td>
                                <td class="whitespace-nowrap px-3 py-3 text-right font-semibold text-bordeaux-800">{{ \App\Support\Fcfa::format($reste) }}</td>
                                <td class="whitespace-nowrap px-6 py-3 text-right">
                                    @if ($canCreate)
                                        <a href="{{ route('paiements.create', ['rendez_vous' => $rdv->id]) }}" class="font-medium text-bordeaux-700 hover:text-bordeaux-900">Encaisser</a>
                                    @endif
                                    @can('view', $rdv)
                                        <a href="{{ route('rendez-vous.show', $rdv) }}" class="ml-3 font-medium text-stone-600 hover:text-bordeaux-800">Voir</a>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-6">{{ $rendezVous->links() }}</div>
    @endif
</x-layouts.app>