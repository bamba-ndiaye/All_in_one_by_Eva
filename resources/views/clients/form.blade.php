@php $editing = $client->exists; @endphp

<x-layouts.app :title="$editing ? 'Modifier la fiche' : 'Nouveau client'">
    <x-page-header :title="$editing ? 'Modifier '.$client->nomComplet() : 'Nouveau client'"
                   subtitle="Les numéros sont enregistrés au format international (+221)." />

    <form method="POST"
          action="{{ $editing ? route('clients.update', $client) : route('clients.store') }}"
          class="card max-w-3xl space-y-6"
          x-data="{ loading: false }" @submit="loading = true">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <div class="grid gap-6 sm:grid-cols-2">
            <div>
                <label for="prenom" class="label">Prénom</label>
                <input id="prenom" name="prenom" class="input" required maxlength="100" value="{{ old('prenom', $client->prenom) }}">
            </div>
            <div>
                <label for="nom" class="label">Nom</label>
                <input id="nom" name="nom" class="input" required maxlength="100" value="{{ old('nom', $client->nom) }}">
            </div>
            <div>
                <label for="telephone" class="label">Téléphone</label>
                <input id="telephone" name="telephone" type="tel" class="input" required maxlength="30" inputmode="tel"
                       placeholder="77 123 45 67" value="{{ old('telephone', $editing ? $client->telephoneAffiche() : '') }}">
            </div>
            <div>
                <label for="whatsapp" class="label">WhatsApp (si différent)</label>
                <input id="whatsapp" name="whatsapp" type="tel" class="input" maxlength="30" inputmode="tel"
                       value="{{ old('whatsapp', $client->whatsapp ? $client->whatsappAffiche() : '') }}">
            </div>
            <div>
                <label for="email" class="label">E-mail (facultatif)</label>
                <input id="email" name="email" type="email" class="input" maxlength="150" value="{{ old('email', $client->email) }}">
            </div>
            <div>
                <label for="date_naissance" class="label">Date de naissance (facultatif)</label>
                <input id="date_naissance" name="date_naissance" type="date" class="input" max="{{ now()->toDateString() }}"
                       value="{{ old('date_naissance', $client->date_naissance?->toDateString()) }}">
            </div>
            <div>
                <label for="canal_prefere" class="label">Canal préféré</label>
                <select id="canal_prefere" name="canal_prefere" class="input">
                    <option value="">Aucune préférence</option>
                    @foreach ($canaux as $canal)
                        <option value="{{ $canal->value }}" @selected(old('canal_prefere', $client->canal_prefere?->value) === $canal->value)>{{ $canal->label() }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label for="notes" class="label">Notes utiles (facultatif)</label>
            <textarea id="notes" name="notes" rows="3" maxlength="2000" class="input">{{ old('notes', $client->notes) }}</textarea>
        </div>

        <div class="rounded-xl border border-beige-200 p-4">
            <input type="hidden" name="consentement_promo" value="0">
            <label class="flex items-start gap-3 text-sm text-stone-700">
                <input type="checkbox" name="consentement_promo" value="1"
                       class="mt-0.5 h-4 w-4 rounded border-beige-300 text-bordeaux-700 focus:ring-bordeaux-500"
                       @checked((bool) old('consentement_promo', $client->consentement_promo))>
                <span>
                    Le client accepte de recevoir des messages promotionnels (WhatsApp, appel, SMS).
                    <span class="mt-1 block text-xs text-stone-500">Décochez cette case pour enregistrer le retrait du consentement : la date sera conservée.</span>
                </span>
            </label>
        </div>

        <div class="flex flex-wrap gap-3 border-t border-beige-100 pt-6">
            <button type="submit" class="btn-primary" :disabled="loading">
                <span x-show="! loading">Enregistrer</span>
                <span x-show="loading" x-cloak>Enregistrement…</span>
            </button>
            <a href="{{ $editing ? route('clients.show', $client) : route('clients.index') }}" class="btn-secondary">Annuler</a>
        </div>
    </form>
</x-layouts.app>