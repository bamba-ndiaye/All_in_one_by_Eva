@php
    $editing = $compte->exists;
    $roleInitial = (string) old('role', $compte->role?->value ?? 'receptionniste');
    $selection = array_values($selection);
@endphp

<x-layouts.app :title="$editing ? 'Modifier le compte' : 'Nouveau compte'">
    <x-page-header :title="$editing ? 'Modifier le compte de '.$compte->name : 'Nouveau compte'"
                   subtitle="Nom, e-mail, rôle et mot de passe : c'est tout ce qu'il faut." />

    <form method="POST"
          action="{{ $editing ? route('utilisateurs.update', $compte) : route('utilisateurs.store') }}"
          class="card max-w-3xl space-y-8"
          x-data="{
              loading: false,
              role: @js($roleInitial),
              droitsParRole: @js($droitsParRole),
              choisis: @js($selection),
              ouvert: {{ $selection !== [] ? 'true' : 'false' }},
              inclus(p) { return (this.droitsParRole[this.role] || []).includes(p) },
              get nbEnPlus() { return this.choisis.filter(p => ! this.inclus(p)).length },
          }"
          @submit="loading = true">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        {{-- 1. Identité --}}
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="name" class="label">Nom complet</label>
                <input id="name" name="name" class="input" required maxlength="100" autocomplete="off"
                       placeholder="Awa Fall" value="{{ old('name', $compte->name) }}">
            </div>
            <div>
                <label for="email" class="label">Adresse e-mail</label>
                <input id="email" name="email" type="email" class="input" required maxlength="150" autocomplete="off"
                       placeholder="awa@exemple.com" value="{{ old('email', $compte->email) }}">
                <p class="mt-1 text-xs text-stone-500">Elle servira d'identifiant de connexion.</p>
            </div>
        </div>

        {{-- 2. Rôle --}}
        <fieldset>
            <legend class="label">Rôle</legend>
            @if ($soiMeme)
                <input type="hidden" name="role" value="{{ $compte->role->value }}">
                <div class="rounded-xl border border-beige-200 bg-beige-50 px-4 py-3 text-sm">
                    <p class="font-medium text-bordeaux-900">{{ $compte->role->label() }}</p>
                    <p class="mt-0.5 text-xs text-stone-500">Vous ne pouvez pas changer votre propre rôle.</p>
                </div>
            @else
                <div @class(['grid gap-3', 'sm:grid-cols-3' => count($roles) >= 3, 'sm:grid-cols-2' => count($roles) < 3])>
                    @foreach ($roles as $role)
                        <label class="cursor-pointer rounded-xl border px-4 py-3 text-sm transition"
                               :class="role === @js($role->value)
                                   ? 'border-bordeaux-600 bg-bordeaux-50 ring-1 ring-bordeaux-600'
                                   : 'border-beige-200 hover:border-beige-300'">
                            <input type="radio" name="role" value="{{ $role->value }}" x-model="role" class="sr-only" required>
                            <span class="block font-medium text-bordeaux-900">{{ $role->label() }}</span>
                            <span class="mt-1 block text-xs text-stone-500">{{ $role->description() }}</span>
                        </label>
                    @endforeach
                </div>
            @endif
        </fieldset>

        {{-- 3. Mot de passe --}}
        <div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="password" class="label">{{ $editing ? 'Nouveau mot de passe' : 'Mot de passe' }}</label>
                    <input id="password" name="password" type="password" class="input" minlength="10" maxlength="100"
                           autocomplete="new-password" placeholder="10 caractères minimum" @required(! $editing)>
                </div>
                <div>
                    <label for="password_confirmation" class="label">Confirmer</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" class="input"
                           minlength="10" maxlength="100" autocomplete="new-password" @required(! $editing)>
                </div>
            </div>
            @if ($editing)
                <p class="mt-1.5 text-xs text-stone-500">Laissez vide pour garder le mot de passe actuel. Un nouveau mot de passe déconnecte la personne de tous ses appareils.</p>
            @endif
        </div>

        {{-- 4. Droits en plus : repliés, facultatifs --}}
        <div x-show="role !== 'administratrice'" class="rounded-xl border border-beige-200">
            <button type="button" @click="ouvert = ! ouvert"
                    class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left text-sm"
                    :aria-expanded="ouvert.toString()">
                <span>
                    <span class="font-medium text-stone-800">Droits en plus</span>
                    <span class="text-stone-500">(facultatif)</span>
                    <span x-show="nbEnPlus > 0" x-cloak class="badge ml-2 bg-bordeaux-700 text-white" x-text="nbEnPlus + ' choisi' + (nbEnPlus > 1 ? 's' : '')"></span>
                </span>
                <span class="text-xs font-medium text-bordeaux-700" x-text="ouvert ? 'Masquer' : 'Afficher'"></span>
            </button>

            <div x-show="ouvert" x-cloak class="space-y-5 border-t border-beige-100 px-4 py-4">
                <p class="text-xs text-stone-500">Le rôle choisi donne déjà ses droits de base. Cochez seulement ce qu'il faut en plus.</p>

                @foreach ($groupes as $groupe => $permissions)
                    <div x-show="{{ \Illuminate\Support\Js::from($permissions) }}.some(p => ! inclus(p))">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-stone-500">{{ $groupe }}</p>
                        <div class="grid gap-x-4 gap-y-1.5 sm:grid-cols-2">
                            @foreach ($permissions as $permission)
                                @php $attribuable = in_array($permission, $attribuables, true); @endphp
                                <label class="flex items-start gap-2.5 py-1 text-sm {{ $attribuable ? '' : 'text-stone-400' }}"
                                       x-show="! inclus(@js($permission))">
                                    <input type="checkbox" name="permissions[]" value="{{ $permission }}" x-model="choisis"
                                           class="mt-0.5 h-4 w-4 rounded border-beige-300 text-bordeaux-700 focus:ring-bordeaux-500"
                                           :disabled="inclus(@js($permission)) || {{ $attribuable ? 'false' : 'true' }}">
                                    <span>
                                        {{ $catalogue[$permission] }}
                                        @unless ($attribuable)
                                            <span class="block text-xs">Vous ne pouvez pas modifier ce droit.</span>
                                        @endunless
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex flex-wrap gap-3 border-t border-beige-100 pt-6">
            <button type="submit" class="btn-primary" :disabled="loading">
                <span x-show="! loading">{{ $editing ? 'Enregistrer' : 'Créer le compte' }}</span>
                <span x-show="loading" x-cloak>Enregistrement…</span>
            </button>
            <a href="{{ $editing ? route('utilisateurs.show', $compte) : route('utilisateurs.index') }}" class="btn-secondary">Annuler</a>
        </div>
    </form>
</x-layouts.app>
