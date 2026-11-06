@php $editing = $forfait->exists; @endphp

<x-layouts.app :title="$editing ? 'Modifier le forfait' : 'Nouveau forfait'">
    <x-page-header :title="$editing ? 'Modifier le forfait' : 'Nouveau forfait'"
                   subtitle="Un forfait regroupe au moins 2 prestations, avec son propre prix en FCFA." />

    <form method="POST"
          action="{{ $editing ? route('services.forfaits.update', $forfait) : route('services.forfaits.store') }}"
          class="grid gap-6 lg:grid-cols-3"
          x-data="{
              loading: false,
              selected: @js(array_map('strval', $selected)),
              services: @js($map),
              prix: @js((string) old('prix', $forfait->prix)),
              get separe() { return this.selected.reduce((t, id) => t + (this.services[id]?.prix ?? 0), 0) },
              get duree() { return this.selected.reduce((t, id) => t + (this.services[id]?.duree ?? 0), 0) },
              get economie() { return this.separe - (parseInt(this.prix) || 0) },
              fmt(n) { return new Intl.NumberFormat('fr-FR').format(n) + ' FCFA' },
              fmtDuree(m) {
                  const h = Math.floor(m / 60), r = m % 60;
                  if (h === 0) return m + ' min';
                  return r === 0 ? h + ' h' : h + ' h ' + String(r).padStart(2, '0');
              }
          }"
          @submit="loading = true">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <div class="card space-y-6 lg:col-span-2">
            <div>
                <label for="nom" class="label">Nom du forfait</label>
                <input id="nom" name="nom" class="input" required maxlength="150" value="{{ old('nom', $forfait->nom) }}">
            </div>

            <div>
                <label for="description" class="label">Description (facultative)</label>
                <textarea id="description" name="description" rows="2" maxlength="1000"
                          class="input">{{ old('description', $forfait->description) }}</textarea>
            </div>

            <div>
                <label for="prix" class="label">Prix du forfait (FCFA)</label>
                <input id="prix" name="prix" type="number" min="0" step="1" inputmode="numeric" class="input max-w-xs"
                       required x-model="prix">
            </div>

            <fieldset>
                <legend class="label">Prestations incluses (2 minimum)</legend>
                <div class="space-y-5">
                    @foreach ($categories as $categorie)
                        <div>
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-stone-500">{{ $categorie->nom }}</p>
                            <div class="space-y-2">
                                @foreach ($categorie->services as $service)
                                    <label class="flex items-center gap-3 rounded-lg border border-beige-200 px-3 py-2 text-sm">
                                        <input type="checkbox" name="services[]" value="{{ $service->id }}" x-model="selected"
                                               class="h-4 w-4 rounded border-beige-300 text-bordeaux-700 focus:ring-bordeaux-500">
                                        <span class="flex-1">
                                            {{ $service->nom }}
                                            @unless ($service->is_active)
                                                <span class="badge ml-1 bg-stone-100 text-stone-500">Désactivée</span>
                                            @endunless
                                        </span>
                                        <span class="text-stone-500">{{ $service->dureeFormatee() }} · {{ $service->prixFormate() }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </fieldset>

            <div>
                <input type="hidden" name="is_active" value="0">
                <label class="inline-flex items-center gap-2 text-sm text-stone-700">
                    <input type="checkbox" name="is_active" value="1"
                           class="h-4 w-4 rounded border-beige-300 text-bordeaux-700 focus:ring-bordeaux-500"
                           @checked((bool) old('is_active', $forfait->is_active))>
                    Forfait actif (proposé pour les rendez-vous)
                </label>
            </div>
        </div>

        <aside class="card h-fit space-y-4 lg:sticky lg:top-6">
            <h2 class="text-lg font-semibold">Résumé</h2>
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between"><dt class="text-stone-500">Prestations choisies</dt><dd class="font-medium" x-text="selected.length"></dd></div>
                <div class="flex justify-between"><dt class="text-stone-500">Durée totale</dt><dd class="font-medium" x-text="fmtDuree(duree)"></dd></div>
                <div class="flex justify-between"><dt class="text-stone-500">Prix séparé</dt><dd class="font-medium" x-text="fmt(separe)"></dd></div>
                <div class="flex justify-between"><dt class="text-stone-500">Prix du forfait</dt><dd class="font-medium" x-text="fmt(parseInt(prix) || 0)"></dd></div>
                <div class="flex justify-between border-t border-beige-100 pt-3">
                    <dt class="text-stone-500">Économie client</dt>
                    <dd class="font-semibold" :class="economie < 0 ? 'text-red-700' : 'text-emerald-700'" x-text="fmt(economie)"></dd>
                </div>
            </dl>
            <p x-show="economie < 0" x-cloak class="text-xs text-red-700">
                Le forfait coûte plus cher que les prestations séparées : vérifiez le prix.
            </p>

            <div class="flex flex-col gap-3 border-t border-beige-100 pt-4">
                <button type="submit" class="btn-primary" :disabled="loading">
                    <span x-show="! loading">Enregistrer</span>
                    <span x-show="loading" x-cloak>Enregistrement…</span>
                </button>
                <a href="{{ route('services.forfaits.index') }}" class="btn-secondary">Annuler</a>
            </div>
        </aside>
    </form>
</x-layouts.app>