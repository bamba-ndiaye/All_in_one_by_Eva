@php
    $editing = $employe->exists;
    $canEditCommission = auth()->user()->can('staff.view_earnings');
@endphp

<x-layouts.app :title="$editing ? 'Modifier la fiche' : 'Nouvel employé'">
    <x-page-header :title="$editing ? 'Modifier '.$employe->nomComplet() : 'Nouvel employé'"
                   subtitle="Les horaires se règlent sur une page dédiée, après l'enregistrement." />

    <form method="POST"
          action="{{ $editing ? route('employes.update', $employe) : route('employes.store') }}"
          class="card max-w-3xl space-y-6"
          x-data="{ loading: false, type: @js((string) old('commission_type', $employe->commission_type?->value ?? '')) }"
          @submit="loading = true">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <div class="grid gap-6 sm:grid-cols-2">
            <div>
                <label for="prenom" class="label">Prénom</label>
                <input id="prenom" name="prenom" class="input" required maxlength="100" value="{{ old('prenom', $employe->prenom) }}">
            </div>
            <div>
                <label for="nom" class="label">Nom</label>
                <input id="nom" name="nom" class="input" required maxlength="100" value="{{ old('nom', $employe->nom) }}">
            </div>
            <div>
                <label for="fonction" class="label">Fonction</label>
                <input id="fonction" name="fonction" class="input" required maxlength="100"
                       placeholder="Coiffeuse, esthéticienne…" value="{{ old('fonction', $employe->fonction) }}">
            </div>
            <div>
                <label for="telephone" class="label">Téléphone</label>
                <input id="telephone" name="telephone" type="tel" class="input" maxlength="30"
                       value="{{ old('telephone', $employe->telephone) }}">
            </div>
        </div>

        <div>
            <label for="specialites" class="label">Spécialités (facultatif)</label>
            <textarea id="specialites" name="specialites" rows="2" maxlength="1000" class="input">{{ old('specialites', $employe->specialites) }}</textarea>
        </div>

        <fieldset>
            <legend class="label">Prestations proposées</legend>
            <div class="space-y-5">
                @foreach ($categories as $categorie)
                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-stone-500">{{ $categorie->nom }}</p>
                        <div class="grid gap-2 sm:grid-cols-2">
                            @foreach ($categorie->services as $service)
                                <label class="flex items-center gap-3 rounded-lg border border-beige-200 px-3 py-2 text-sm">
                                    <input type="checkbox" name="services[]" value="{{ $service->id }}"
                                           class="h-4 w-4 rounded border-beige-300 text-bordeaux-700 focus:ring-bordeaux-500"
                                           @checked(in_array($service->id, $selected, true))>
                                    <span>
                                        {{ $service->nom }}
                                        @unless ($service->is_active)
                                            <span class="badge ml-1 bg-stone-100 text-stone-500">Désactivée</span>
                                        @endunless
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </fieldset>

        <div>
            <label for="user_id" class="label">Compte de connexion (facultatif)</label>
            <select id="user_id" name="user_id" class="input">
                <option value="">Aucun compte</option>
                @foreach ($comptes as $compte)
                    <option value="{{ $compte->id }}" @selected((int) old('user_id', $employe->user_id) === $compte->id)>
                        {{ $compte->name }} — {{ $compte->email }} ({{ $compte->role->label() }})
                    </option>
                @endforeach
            </select>
            <p class="mt-1.5 text-xs text-stone-500">Un compte lié permettra à cette personne de voir son propre planning.</p>
        </div>

        @if ($canEditCommission)
            <fieldset class="rounded-xl border border-beige-200 p-4">
                <legend class="px-2 text-sm font-medium text-stone-700">Commission</legend>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="commission_type" class="label">Type de commission</label>
                        <select id="commission_type" name="commission_type" class="input" x-model="type">
                            <option value="">Aucune</option>
                            @foreach ($types as $type)
                                <option value="{{ $type->value }}">{{ $type->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div x-show="type !== ''" x-cloak>
                        <label for="commission_valeur" class="label">
                            <span x-show="type === 'pourcentage'">Pourcentage (0 à 100)</span>
                            <span x-show="type === 'fixe'" x-cloak>Montant (FCFA)</span>
                        </label>
                        <input id="commission_valeur" name="commission_valeur" type="number" min="0" step="1"
                               inputmode="numeric" class="input" value="{{ old('commission_valeur', $employe->commission_valeur) }}">
                    </div>
                </div>
            </fieldset>
        @endif

        <div>
            <input type="hidden" name="is_active" value="0">
            <label class="inline-flex items-center gap-2 text-sm text-stone-700">
                <input type="checkbox" name="is_active" value="1"
                       class="h-4 w-4 rounded border-beige-300 text-bordeaux-700 focus:ring-bordeaux-500"
                       @checked((bool) old('is_active', $employe->is_active))>
                Fiche active (peut recevoir des rendez-vous)
            </label>
        </div>

        <div class="flex flex-wrap gap-3 border-t border-beige-100 pt-6">
            <button type="submit" class="btn-primary" :disabled="loading">
                <span x-show="! loading">Enregistrer</span>
                <span x-show="loading" x-cloak>Enregistrement…</span>
            </button>
            <a href="{{ $editing ? route('employes.show', $employe) : route('employes.index') }}" class="btn-secondary">Annuler</a>
        </div>
    </form>
</x-layouts.app>