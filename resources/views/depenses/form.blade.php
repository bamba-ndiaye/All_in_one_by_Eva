@php $editing = $depense->exists; @endphp

<x-layouts.app :title="$editing ? 'Modifier la dépense' : 'Nouvelle dépense'">
    <x-page-header :title="$editing ? 'Modifier la dépense n° '.$depense->id : 'Nouvelle dépense'"
                   subtitle="Montant en FCFA, sans décimales." />

    <form method="POST"
          action="{{ $editing ? route('depenses.update', $depense) : route('depenses.store') }}"
          enctype="multipart/form-data"
          class="card max-w-3xl space-y-6"
          x-data="{ loading: false }" @submit="loading = true">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <div class="grid gap-6 sm:grid-cols-2">
            <div>
                <label for="categorie_depense_id" class="label">Catégorie</label>
                <select id="categorie_depense_id" name="categorie_depense_id" class="input" required>
                    <option value="">Choisir…</option>
                    @foreach ($categories as $categorie)
                        <option value="{{ $categorie->id }}" @selected((int) old('categorie_depense_id', $depense->categorie_depense_id) === $categorie->id)>
                            {{ $categorie->nom }}@unless ($categorie->is_active) (désactivée)@endunless
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="montant" class="label">Montant (FCFA)</label>
                <input id="montant" name="montant" type="number" min="1" step="1" inputmode="numeric" class="input" required
                       value="{{ old('montant', $depense->montant) }}">
            </div>

            <div>
                <label for="date" class="label">Date de la dépense</label>
                <input id="date" name="date" type="date" class="input" required max="{{ now()->toDateString() }}"
                       value="{{ old('date', $depense->date?->toDateString() ?? now()->toDateString()) }}">
            </div>
        </div>

        <div>
            <label for="description" class="label">Description</label>
            <input id="description" name="description" class="input" required maxlength="255"
                   placeholder="Par exemple : facture d'électricité d'octobre"
                   value="{{ old('description', $depense->description) }}">
        </div>

        <div>
            <p class="label">Justificatif (facultatif)</p>

            @if ($editing && $depense->aUnJustificatif())
                <p class="text-sm">
                    <a href="{{ route('depenses.justificatif', $depense) }}" target="_blank" rel="noopener" class="font-medium text-bordeaux-700 hover:text-bordeaux-900">Voir le justificatif enregistré</a>
                </p>
                <p class="mt-1.5 text-xs text-stone-500">
                    Un justificatif enregistré ne peut pas être remplacé. Si le fichier est erroné, annulez cette dépense et créez-en une nouvelle.
                </p>
            @else
                <input id="justificatif" name="justificatif" type="file" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf" class="input">
                <p class="mt-1.5 text-xs text-stone-500">Photo (JPG, PNG) ou PDF, 2 Mo maximum. Il ne pourra plus être remplacé une fois enregistré.</p>
            @endif
        </div>

        <div class="flex flex-wrap gap-3 border-t border-beige-100 pt-6">
            <button type="submit" class="btn-primary" :disabled="loading">
                <span x-show="! loading">Enregistrer</span>
                <span x-show="loading" x-cloak>Enregistrement…</span>
            </button>
            <a href="{{ $editing ? route('depenses.show', $depense) : route('depenses.index') }}" class="btn-secondary">Annuler</a>
        </div>
    </form>
</x-layouts.app>