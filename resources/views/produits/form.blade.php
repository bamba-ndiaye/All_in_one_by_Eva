@php $editing = $produit->exists; @endphp

<x-layouts.app :title="$editing ? 'Modifier le produit' : 'Nouveau produit'">
    <x-page-header :title="$editing ? 'Modifier '.$produit->nom : 'Nouveau produit'"
                   :subtitle="$editing ? 'La quantité se corrige depuis la fiche, par un mouvement ou un inventaire.' : 'Seuls le nom, l\'unité et la quantité sont vraiment nécessaires.'" />

    <form method="POST" action="{{ $editing ? route('produits.update', $produit) : route('produits.store') }}"
          class="card max-w-3xl space-y-6" x-data="{ loading: false }" @submit="loading = true">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="sm:col-span-2">
                <label for="nom" class="label">Nom du produit</label>
                <input id="nom" name="nom" class="input" required maxlength="100" placeholder="Shampoing kératine 500 ml"
                       value="{{ old('nom', $produit->nom) }}">
            </div>
            <div>
                <label for="unite" class="label">Unité</label>
                <input id="unite" name="unite" class="input" required maxlength="20" list="unites" placeholder="flacon"
                       value="{{ old('unite', $produit->unite) }}">
                <datalist id="unites">
                    @foreach (['pièce', 'flacon', 'tube', 'pot', 'boîte', 'paquet', 'sachet', 'litre'] as $u)
                        <option value="{{ $u }}">
                    @endforeach
                </datalist>
            </div>
        </div>

        <div @class(['grid gap-4', 'sm:grid-cols-2' => $editing, 'sm:grid-cols-3' => ! $editing])>
            @unless ($editing)
                <div>
                    <label for="quantite_initiale" class="label">Quantité en stock aujourd'hui</label>
                    <input id="quantite_initiale" name="quantite_initiale" type="number" min="0" step="1" inputmode="numeric"
                           class="input" value="{{ old('quantite_initiale', 0) }}">
                </div>
            @endunless
            <div>
                <label for="seuil_alerte" class="label">Alerter à partir de</label>
                <input id="seuil_alerte" name="seuil_alerte" type="number" min="0" step="1" inputmode="numeric" required
                       class="input" value="{{ old('seuil_alerte', $produit->seuil_alerte) }}">
                <p class="mt-1 text-xs text-stone-500">« Stock bas » quand il en reste ce nombre ou moins.</p>
            </div>
            <div>
                <label for="reference" class="label">Référence (facultatif)</label>
                <input id="reference" name="reference" class="input" maxlength="50" value="{{ old('reference', $produit->reference) }}">
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="prix_achat" class="label">Prix d'achat (FCFA, facultatif)</label>
                <input id="prix_achat" name="prix_achat" type="number" min="0" step="1" inputmode="numeric" class="input"
                       value="{{ old('prix_achat', $produit->prix_achat) }}">
            </div>
            <div>
                <label for="prix_vente" class="label">Prix de vente (FCFA, facultatif)</label>
                <input id="prix_vente" name="prix_vente" type="number" min="0" step="1" inputmode="numeric" class="input"
                       value="{{ old('prix_vente', $produit->prix_vente) }}">
                <p class="mt-1 text-xs text-stone-500">Laissez vide si le produit sert seulement au salon.</p>
            </div>
        </div>

        <div class="flex flex-wrap gap-3 border-t border-beige-100 pt-6">
            <button type="submit" class="btn-primary" :disabled="loading">
                <span x-show="! loading">{{ $editing ? 'Enregistrer' : 'Ajouter le produit' }}</span>
                <span x-show="loading" x-cloak>Enregistrement…</span>
            </button>
            <a href="{{ $editing ? route('produits.show', $produit) : route('produits.index') }}" class="btn-secondary">Annuler</a>
        </div>
    </form>
</x-layouts.app>
