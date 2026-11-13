@php $editing = $recompense->exists; @endphp

<x-layouts.app :title="$editing ? 'Modifier la récompense' : 'Nouvelle récompense'">
    <x-page-header :title="$editing ? 'Modifier « '.$recompense->nom.' »' : 'Nouvelle récompense'"
                   subtitle="Une récompense déjà utilisée reste dans l'historique des clientes, même si vous la modifiez." />

    <form method="POST" action="{{ $editing ? route('fidelite.recompenses.update', $recompense) : route('fidelite.recompenses.store') }}"
          class="card max-w-2xl space-y-6" x-data="{ loading: false }" @submit="loading = true">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="sm:col-span-2">
                <label for="nom" class="label">Nom</label>
                <input id="nom" name="nom" class="input" required maxlength="100" placeholder="Brushing offert"
                       value="{{ old('nom', $recompense->nom) }}">
            </div>
            <div>
                <label for="points" class="label">Points nécessaires</label>
                <input id="points" name="points" type="number" min="1" step="1" inputmode="numeric" required class="input"
                       value="{{ old('points', $recompense->points) }}">
            </div>
        </div>

        <div>
            <label for="description" class="label">Précision (facultatif)</label>
            <input id="description" name="description" class="input" maxlength="255" placeholder="Valable sur cheveux courts et mi-longs"
                   value="{{ old('description', $recompense->description) }}">
        </div>

        <div class="flex flex-wrap gap-3 border-t border-beige-100 pt-6">
            <button type="submit" class="btn-primary" :disabled="loading">{{ $editing ? 'Enregistrer' : 'Ajouter la récompense' }}</button>
            <a href="{{ route('fidelite.recompenses.index') }}" class="btn-secondary">Annuler</a>
        </div>
    </form>
</x-layouts.app>
