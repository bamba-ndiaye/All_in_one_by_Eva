@php
    $peutGerer = auth()->user()->can('update', $produit);
    $peutBouger = auth()->user()->can('mouvement', $produit);
    $typeInitial = old('type', 'entree');
@endphp

<x-layouts.app :title="$produit->nom">
    <x-page-header :title="$produit->nom" :subtitle="$produit->reference ? 'Réf. '.$produit->reference : null">
        <x-slot:actions>
            <a href="{{ route('produits.index') }}" class="btn-secondary">Retour au stock</a>
            @if ($peutGerer)
                <a href="{{ route('produits.edit', $produit) }}" class="btn-primary">Modifier</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <section class="card">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p class="text-sm text-stone-500">En stock</p>
                        <p class="mt-1 font-serif text-3xl font-semibold text-bordeaux-900">{{ $produit->quantiteFormatee() }}</p>
                    </div>
                    @if (! $produit->is_active)
                        <span class="badge bg-stone-100 text-stone-500">Désactivé</span>
                    @elseif ($produit->estEnRupture())
                        <span class="badge bg-red-50 text-red-700">Rupture de stock</span>
                    @elseif ($produit->estEnAlerte())
                        <span class="badge bg-amber-50 text-amber-700">Stock bas : à réapprovisionner</span>
                    @else
                        <span class="badge bg-emerald-50 text-emerald-700">Stock suffisant</span>
                    @endif
                </div>
                <dl class="mt-6 grid gap-4 border-t border-beige-100 pt-4 text-sm sm:grid-cols-3">
                    <div><dt class="text-stone-500">Seuil d'alerte</dt><dd class="mt-0.5 font-medium">{{ $produit->quantiteFormatee($produit->seuil_alerte) }}</dd></div>
                    @if ($voitCouts)
                        <div><dt class="text-stone-500">Prix d'achat</dt><dd class="mt-0.5 font-medium">{{ $produit->prixFormate($produit->prix_achat) }}</dd></div>
                    @endif
                    <div><dt class="text-stone-500">Prix de vente</dt><dd class="mt-0.5 font-medium">{{ $produit->prix_vente === null ? 'Non vendu' : $produit->prixFormate($produit->prix_vente) }}</dd></div>
                </dl>
            </section>

            <section class="card overflow-hidden p-0">
                <h2 class="px-6 pt-6 text-lg font-semibold">Historique des mouvements</h2>
                @if ($mouvements->isEmpty())
                    <p class="px-6 py-6 text-sm text-stone-500">Aucun mouvement pour l'instant.</p>
                @else
                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-beige-50 text-xs uppercase tracking-wide text-stone-500">
                                <tr>
                                    <th class="px-6 py-3">Date</th>
                                    <th class="px-3 py-3">Mouvement</th>
                                    <th class="px-3 py-3 text-right">Quantité</th>
                                    <th class="px-3 py-3 text-right">Stock après</th>
                                    <th class="px-6 py-3">Par</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($mouvements as $mouvement)
                                    <tr class="border-t border-beige-100 align-top">
                                        <td class="whitespace-nowrap px-6 py-3">{{ $mouvement->created_at?->translatedFormat('d M Y, H:i') }}</td>
                                        <td class="px-3 py-3">
                                            {{ $mouvement->type->label() }}
                                            @if ($mouvement->motif)
                                                <p class="mt-0.5 text-xs text-stone-500">{{ $mouvement->motif }}</p>
                                            @endif
                                        </td>
                                        <td @class(['whitespace-nowrap px-3 py-3 text-right font-medium',
                                                    'text-emerald-700' => $mouvement->quantite > 0,
                                                    'text-red-700' => $mouvement->quantite < 0])>
                                            {{ $mouvement->quantite > 0 ? '+' : '' }}{{ $mouvement->quantite }}
                                        </td>
                                        <td class="px-3 py-3 text-right">{{ $mouvement->quantite_apres }}</td>
                                        <td class="px-6 py-3 text-stone-500">{{ $mouvement->user?->name ?? '—' }}</td>
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
            @if ($peutBouger)
                <section class="card">
                    <h2 class="text-lg font-semibold">Nouveau mouvement</h2>
                    <form method="POST" action="{{ route('produits.mouvement', $produit) }}" class="mt-4 space-y-4"
                          x-data="{ type: @js($typeInitial), loading: false }" @submit="loading = true">
                        @csrf
                        <div>
                            <label for="type" class="label">Type</label>
                            <select id="type" name="type" class="input" x-model="type">
                                @foreach ($types as $type)
                                    <option value="{{ $type->value }}">{{ $type->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="quantite" class="label">
                                <span x-show="type !== 'inventaire'">Quantité</span>
                                <span x-show="type === 'inventaire'" x-cloak>Quantité comptée sur l'étagère</span>
                            </label>
                            <input id="quantite" name="quantite" type="number" step="1" inputmode="numeric" required
                                   :min="type === 'inventaire' ? 0 : 1" class="input" value="{{ old('quantite') }}">
                            <p x-show="type === 'inventaire'" x-cloak class="mt-1 text-xs text-stone-500">
                                Le stock sera corrigé à ce nombre ({{ $produit->quantiteFormatee() }} actuellement).
                            </p>
                        </div>
                        <div>
                            <label for="motif" class="label">
                                Motif
                                <span x-show="type !== 'perte' && type !== 'inventaire'" class="font-normal text-stone-400">(facultatif)</span>
                            </label>
                            <input id="motif" name="motif" class="input" maxlength="255" value="{{ old('motif') }}"
                                   :required="type === 'perte' || type === 'inventaire'"
                                   :placeholder="type === 'entree' ? 'Fournisseur, n° de facture…' : (type === 'perte' ? 'Flacon cassé, produit périmé…' : '')">
                        </div>
                        <button type="submit" class="btn-primary w-full" :disabled="loading">
                            <span x-show="! loading">Enregistrer</span>
                            <span x-show="loading" x-cloak>Enregistrement…</span>
                        </button>
                    </form>
                    <p class="mt-3 text-xs text-stone-500">Un mouvement ne se supprime pas. En cas d'erreur, faites un inventaire.</p>
                </section>
            @endif

            @if ($peutGerer)
                <section class="card">
                    <h2 class="text-lg font-semibold">Statut</h2>
                    <form method="POST" action="{{ route('produits.toggle', $produit) }}" class="mt-3"
                          @if ($produit->is_active) onsubmit="return confirm('Désactiver ce produit ? Il n\'apparaîtra plus dans la liste.')" @endif>
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn-secondary w-full">{{ $produit->is_active ? 'Désactiver' : 'Réactiver' }}</button>
                    </form>
                    <p class="mt-3 text-xs text-stone-500">Un produit n'est jamais supprimé : son historique est conservé.</p>
                </section>
            @endif
        </div>
    </div>
</x-layouts.app>
