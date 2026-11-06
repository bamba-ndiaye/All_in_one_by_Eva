@php $editing = $service->exists; @endphp

<x-layouts.app :title="$editing ? 'Modifier la prestation' : 'Nouvelle prestation'">
    <x-page-header :title="$editing ? 'Modifier la prestation' : 'Nouvelle prestation'"
                   subtitle="Les prix sont en FCFA, sans décimales." />

    <form method="POST"
          action="{{ $editing ? route('services.update', $service) : route('services.store') }}"
          class="card max-w-3xl space-y-6"
          x-data="{ loading: false }" @submit="loading = true">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <div class="grid gap-6 sm:grid-cols-2">
            <div>
                <label for="categorie_service_id" class="label">Catégorie</label>
                <select id="categorie_service_id" name="categorie_service_id" class="input" required>
                    <option value="">Choisir…</option>
                    @foreach ($categories as $categorie)
                        <option value="{{ $categorie->id }}"
                            @selected((int) old('categorie_service_id', $service->categorie_service_id) === $categorie->id)>
                            {{ $categorie->nom }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="nom" class="label">Nom de la prestation</label>
                <input id="nom" name="nom" class="input" required maxlength="150"
                       value="{{ old('nom', $service->nom) }}">
            </div>
        </div>

        <div>
            <label for="description" class="label">Description (facultative)</label>
            <textarea id="description" name="description" rows="3" maxlength="1000"
                      class="input">{{ old('description', $service->description) }}</textarea>
        </div>

        <div class="grid gap-6 sm:grid-cols-3">
            <div>
                <label for="prix" class="label">Prix (FCFA)</label>
                <input id="prix" name="prix" type="number" min="0" step="1" inputmode="numeric" class="input" required
                       value="{{ old('prix', $service->prix) }}">
            </div>

            <div>
                <label for="duree_minutes" class="label">Durée (minutes)</label>
                <input id="duree_minutes" name="duree_minutes" type="number" min="5" max="720" step="5"
                       inputmode="numeric" class="input" required
                       value="{{ old('duree_minutes', $service->duree_minutes) }}">
            </div>

            <div>
                <label for="zone" class="label">Zone du salon</label>
                <select id="zone" name="zone" class="input" required>
                    @foreach (\App\Enums\ZoneSalon::cases() as $zone)
                        <option value="{{ $zone->value }}"
                            @selected(old('zone', $service->zone?->value) === $zone->value)>
                            {{ $zone->label() }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <input type="hidden" name="is_active" value="0">
            <label class="inline-flex items-center gap-2 text-sm text-stone-700">
                <input type="checkbox" name="is_active" value="1"
                       class="h-4 w-4 rounded border-beige-300 text-bordeaux-700 focus:ring-bordeaux-500"
                       @checked((bool) old('is_active', $service->is_active))>
                Prestation active (proposée pour les rendez-vous)
            </label>
        </div>

        <div class="flex flex-wrap gap-3 border-t border-beige-100 pt-6">
            <button type="submit" class="btn-primary" :disabled="loading">
                <span x-show="! loading">Enregistrer</span>
                <span x-show="loading" x-cloak>Enregistrement…</span>
            </button>
            <a href="{{ route('services.index') }}" class="btn-secondary">Annuler</a>
        </div>
    </form>
</x-layouts.app>