@php
    $editing = $compte->exists;
    $roleInitial = (string) old('role', $compte->role?->value ?? 'receptionniste');
@endphp

<x-layouts.app :title="$editing ? 'Modifier le compte' : 'Nouveau compte'">
    <x-page-header :title="$editing ? 'Modifier le compte de '.$compte->name : 'Nouveau compte'"
                   subtitle="Le rôle donne les droits de base ; vous pouvez en ajouter d'autres individuellement." />

    <form method="POST"
          action="{{ $editing ? route('utilisateurs.update', $compte) : route('utilisateurs.store') }}"
          class="card max-w-3xl space-y-6"
          x-data="{ loading: false, role: @js($roleInitial), droitsParRole: @js($droitsParRole) }"
          @submit="loading = true">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <div class="grid gap-6 sm:grid-cols-2">
            <div>
                <label for="name" class="label">Nom complet</label>
                <input id="name" name="name" class="input" required maxlength="100" autocomplete="off"
                       value="{{ old('name', $compte->name) }}">
            </div>
            <div>
                <label for="email" class="label">Adresse e-mail (identifiant de connexion)</label>
                <input id="email" name="email" type="email" class="input" required maxlength="150" autocomplete="off"
                       value="{{ old('email', $compte->email) }}">
            </div>
            <div>
                <label for="role" class="label">Rôle</label>
                @if ($soiMeme)
                    <input type="hidden" name="role" value="{{ $compte->role->value }}">
                    <select id="role" class="input" disabled>
                        <option>{{ $compte->role->label() }}</option>
                    </select>
                    <p class="mt-1.5 text-xs text-stone-500">Vous ne pouvez pas changer votre propre rôle.</p>
                @else
                    <select id="role" name="role" class="input" x-model="role" required>
                        @foreach ($roles as $role)
                            <option value="{{ $role->value }}">{{ $role->label() }}</option>
                        @endforeach
                    </select>
                @endif
            </div>
        </div>

        <fieldset class="rounded-xl border border-beige-200 p-4">
            <legend class="px-2 text-sm font-medium text-stone-700">Mot de passe</legend>
            @if ($editing)
                <p class="mb-4 text-xs text-stone-500">Laissez vide pour garder le mot de passe actuel. Un nouveau mot de passe déconnecte la personne de tous ses appareils.</p>
            @endif
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="password" class="label">{{ $editing ? 'Nouveau mot de passe' : 'Mot de passe' }}</label>
                    <input id="password" name="password" type="password" class="input" minlength="10" maxlength="100"
                           autocomplete="new-password" @required(! $editing)>
                    <p class="mt-1.5 text-xs text-stone-500">10 caractères minimum.</p>
                </div>
                <div>
                    <label for="password_confirmation" class="label">Confirmer le mot de passe</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" class="input"
                           minlength="10" maxlength="100" autocomplete="new-password" @required(! $editing)>
                </div>
            </div>
        </fieldset>

        <fieldset x-show="role !== 'administratrice'">
            <legend class="label">Droits</legend>
            <p class="mb-4 text-xs text-stone-500">
                Les cases grisées et cochées font déjà partie du rôle choisi. Cochez les autres pour accorder un droit en plus.
            </p>
            <div class="space-y-5">
                @foreach ($groupes as $groupe => $permissions)
                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-stone-500">{{ $groupe }}</p>
                        <div class="grid gap-2 sm:grid-cols-2">
                            @foreach ($permissions as $permission)
                                @php $attribuable = in_array($permission, $attribuables, true); @endphp
                                <label class="flex items-center gap-3 rounded-lg border border-beige-200 px-3 py-2 text-sm"
                                       :class="(droitsParRole[role] || []).includes(@js($permission)) ? 'bg-beige-50 text-stone-500' : ''">
                                    {{-- Droit inclus dans le rôle : affiché coché, non envoyé. --}}
                                    <input type="checkbox" checked disabled
                                           class="h-4 w-4 rounded border-beige-300 text-bordeaux-700"
                                           x-show="(droitsParRole[role] || []).includes(@js($permission))" x-cloak>
                                    <input type="checkbox" name="permissions[]" value="{{ $permission }}"
                                           class="h-4 w-4 rounded border-beige-300 text-bordeaux-700 focus:ring-bordeaux-500"
                                           x-show="! (droitsParRole[role] || []).includes(@js($permission))"
                                           :disabled="(droitsParRole[role] || []).includes(@js($permission)) || {{ $attribuable ? 'false' : 'true' }}"
                                           @checked(in_array($permission, $selection, true))>
                                    <span>
                                        {{ $catalogue[$permission] }}
                                        @unless ($attribuable)
                                            <span class="block text-xs text-stone-400">Vous ne pouvez pas modifier ce droit.</span>
                                        @endunless
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </fieldset>

        <div x-show="role === 'administratrice'" x-cloak
             class="rounded-xl border border-beige-200 bg-beige-50 p-4 text-sm text-stone-700">
            L'administratrice a accès à tout : aucun droit à cocher.
        </div>

        @unless ($editing)
            <div>
                <input type="hidden" name="is_active" value="0">
                <label class="inline-flex items-center gap-2 text-sm text-stone-700">
                    <input type="checkbox" name="is_active" value="1"
                           class="h-4 w-4 rounded border-beige-300 text-bordeaux-700 focus:ring-bordeaux-500"
                           @checked((bool) old('is_active', true))>
                    Compte actif (la personne peut se connecter)
                </label>
            </div>
        @endunless

        <div class="flex flex-wrap gap-3 border-t border-beige-100 pt-6">
            <button type="submit" class="btn-primary" :disabled="loading">
                <span x-show="! loading">Enregistrer</span>
                <span x-show="loading" x-cloak>Enregistrement…</span>
            </button>
            <a href="{{ $editing ? route('utilisateurs.show', $compte) : route('utilisateurs.index') }}" class="btn-secondary">Annuler</a>
        </div>
    </form>
</x-layouts.app>
