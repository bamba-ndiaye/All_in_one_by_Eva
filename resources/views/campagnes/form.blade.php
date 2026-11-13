@php $editing = $campagne->exists; @endphp

<x-layouts.app :title="$editing ? 'Modifier la campagne' : 'Nouvelle campagne'">
    <x-page-header :title="$editing ? 'Modifier « '.$campagne->titre.' »' : 'Nouvelle campagne'"
                   subtitle="Rien n'est envoyé à cette étape : la campagne est d'abord enregistrée en brouillon." />

    <form method="POST" action="{{ $editing ? route('campagnes.update', $campagne) : route('campagnes.store') }}"
          class="card max-w-3xl space-y-6"
          x-data="{
              cible: @js(old('cible', $campagne->cible->value)),
              jours: @js((int) old('jours', $campagne->jours ?? 60)),
              message: @js(old('message', $campagne->message)),
              nombre: null,
              async compter() {
                  const r = await fetch(@js(route('campagnes.apercu')) + '?cible=' + this.cible + '&jours=' + (this.jours || 1), { headers: { Accept: 'application/json' } });
                  this.nombre = r.ok ? (await r.json()).nombre : null;
              },
          }"
          x-init="compter(); $watch('cible', () => compter()); $watch('jours', () => compter())">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <div>
            <label for="titre" class="label">Titre (pour vous, pas envoyé)</label>
            <input id="titre" name="titre" class="input" required maxlength="100" placeholder="Promo Tabaski"
                   value="{{ old('titre', $campagne->titre) }}">
        </div>

        <fieldset>
            <legend class="label">À qui ?</legend>
            <div class="grid gap-3 sm:grid-cols-3">
                @foreach ($cibles as $cible)
                    <label class="cursor-pointer rounded-xl border px-4 py-3 text-sm transition"
                           :class="cible === @js($cible->value) ? 'border-bordeaux-600 bg-bordeaux-50 ring-1 ring-bordeaux-600' : 'border-beige-200 hover:border-beige-300'">
                        <input type="radio" name="cible" value="{{ $cible->value }}" x-model="cible" class="sr-only">
                        <span class="block font-medium text-bordeaux-900">{{ $cible->label() }}</span>
                        <span class="mt-1 block text-xs text-stone-500">{{ $cible->description() }}</span>
                    </label>
                @endforeach
            </div>

            <div class="mt-3 flex items-center gap-2 text-sm" x-show="cible !== 'toutes'" x-cloak>
                <span x-text="cible === 'absentes' ? 'Absentes depuis' : 'Inscrites depuis moins de'"></span>
                <input name="jours" type="number" min="1" max="3650" step="1" class="input w-24" x-model.number="jours" :disabled="cible === 'toutes'">
                <span>jours</span>
            </div>

            <p class="mt-3 text-sm font-medium text-bordeaux-800" x-show="nombre !== null" x-cloak>
                <span x-text="nombre"></span> <span x-text="nombre > 1 ? 'clientes recevront le message' : 'cliente recevra le message'"></span>
                <span class="font-normal text-stone-500">(seulement celles qui acceptent les messages)</span>
            </p>
        </fieldset>

        <div>
            <label for="message" class="label">Message</label>
            <textarea id="message" name="message" rows="5" required minlength="10" maxlength="1000" class="input" x-model="message"></textarea>
            <p class="mt-1 text-xs text-stone-500">
                Écrivez <strong>{prenom}</strong> pour mettre le prénom de chaque cliente.
                <span x-text="message.length + ' / 1000'"></span>
            </p>
        </div>

        <div class="rounded-xl border border-beige-200 bg-beige-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-stone-500">Aperçu pour « Awa »</p>
            <p class="mt-2 whitespace-pre-line text-sm text-stone-800" x-text="message.replaceAll('{prenom}', 'Awa')"></p>
        </div>

        <div class="flex flex-wrap gap-3 border-t border-beige-100 pt-6">
            <button type="submit" class="btn-primary">Enregistrer le brouillon</button>
            <a href="{{ $editing ? route('campagnes.show', $campagne) : route('campagnes.index') }}" class="btn-secondary">Annuler</a>
        </div>
    </form>
</x-layouts.app>
