@php
    $peutGerer = auth()->user()->can('create', \App\Models\Produit::class);
@endphp

<x-layouts.app title="Stock">
    <x-page-header title="Stock" subtitle="Produits du salon, quantités et alertes de réapprovisionnement.">
        @if ($peutGerer)
            <x-slot:actions>
                <a href="{{ route('produits.create') }}" class="btn-primary">Nouveau produit</a>
            </x-slot:actions>
        @endif
    </x-page-header>

    <div class="mb-6 grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
        <x-stat-card label="Produits suivis" :value="$nbActifs" icon="package" />
        <a href="{{ route('produits.index', ['etat' => 'alerte']) }}" class="block">
            <x-stat-card label="Stock bas" :value="$nbAlerte" icon="circle-alert"
                         :hint="$nbAlerte > 0 ? 'À réapprovisionner — cliquez pour voir' : 'Rien à commander'" />
        </a>
        @if ($voitCouts)
            <x-stat-card label="Valeur du stock" :value="\App\Support\Fcfa::format($valeur)" icon="wallet" hint="Au prix d'achat" />
        @endif
    </div>

    <form method="GET" action="{{ route('produits.index') }}" class="card mb-6 grid gap-4 sm:grid-cols-3">
        <div class="sm:col-span-2">
            <label for="q" class="label">Rechercher</label>
            <input id="q" name="q" value="{{ $filtres['q'] }}" class="input" placeholder="Nom ou référence">
        </div>
        <div>
            <label for="etat" class="label">Afficher</label>
            <select id="etat" name="etat" class="input">
                <option value="tous" @selected($filtres['etat'] === 'tous')>Produits actifs</option>
                <option value="alerte" @selected($filtres['etat'] === 'alerte')>Stock bas seulement</option>
                <option value="inactifs" @selected($filtres['etat'] === 'inactifs')>Produits désactivés</option>
            </select>
        </div>
        <div class="flex gap-3 sm:col-span-3">
            <button type="submit" class="btn-primary">Filtrer</button>
            <a href="{{ route('produits.index') }}" class="btn-secondary">Réinitialiser</a>
        </div>
    </form>

    @if ($produits->isEmpty())
        <div class="card text-center text-sm text-stone-500">
            @if ($filtres['etat'] === 'alerte')
                Aucun produit en stock bas.
            @else
                Aucun produit ne correspond à votre recherche.
            @endif
        </div>
    @else
        <div class="card overflow-hidden p-0">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-beige-50 text-xs uppercase tracking-wide text-stone-500">
                        <tr>
                            <th class="px-6 py-3">Produit</th>
                            <th class="px-3 py-3">En stock</th>
                            <th class="px-3 py-3">Seuil</th>
                            @if ($voitCouts)
                                <th class="px-3 py-3">Prix d'achat</th>
                            @endif
                            <th class="px-3 py-3">Prix de vente</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($produits as $produit)
                            <tr class="border-t border-beige-100 {{ $produit->is_active ? '' : 'bg-stone-50 text-stone-400' }}">
                                <td class="px-6 py-4">
                                    <a href="{{ route('produits.show', $produit) }}" class="font-medium text-bordeaux-800 hover:text-bordeaux-900">{{ $produit->nom }}</a>
                                    @if ($produit->reference)
                                        <p class="mt-0.5 text-xs text-stone-500">Réf. {{ $produit->reference }}</p>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-3 py-4">
                                    <span class="font-medium">{{ $produit->quantiteFormatee() }}</span>
                                    @if ($produit->estEnRupture() && $produit->is_active)
                                        <span class="badge ml-1 bg-red-50 text-red-700">Rupture</span>
                                    @elseif ($produit->estEnAlerte())
                                        <span class="badge ml-1 bg-amber-50 text-amber-700">Stock bas</span>
                                    @endif
                                </td>
                                <td class="px-3 py-4">{{ $produit->seuil_alerte }}</td>
                                @if ($voitCouts)
                                    <td class="whitespace-nowrap px-3 py-4">{{ $produit->prixFormate($produit->prix_achat) }}</td>
                                @endif
                                <td class="whitespace-nowrap px-3 py-4">{{ $produit->prix_vente === null ? 'Non vendu' : $produit->prixFormate($produit->prix_vente) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-6">{{ $produits->links() }}</div>
    @endif
</x-layouts.app>
