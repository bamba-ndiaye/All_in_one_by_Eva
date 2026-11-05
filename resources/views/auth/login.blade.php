<x-layouts.guest title="Connexion">
    <div class="mb-6 text-center">
        <h1 class="text-2xl font-semibold">Connexion</h1>
        <p class="mt-1 text-sm text-stone-500">Espace réservé au personnel du salon.</p>
    </div>

    <form method="POST" action="{{ route('login.store') }}" class="space-y-5"
          x-data="{ loading: false }" @submit="loading = true">
        @csrf

        <div>
            <label for="email" class="label">Adresse e-mail</label>
            <input id="email" name="email" type="email" class="input" value="{{ old('email') }}"
                   required autofocus autocomplete="username" inputmode="email">
        </div>

        <div>
            <label for="password" class="label">Mot de passe</label>
            <input id="password" name="password" type="password" class="input"
                   required autocomplete="current-password">
        </div>

        <button type="submit" class="btn-primary w-full" :disabled="loading">
            <span x-show="! loading" class="inline-flex items-center gap-2">
                <x-icon name="lock" class="h-4 w-4" /> Se connecter
            </span>
            <span x-show="loading" x-cloak>Connexion en cours…</span>
        </button>
    </form>
</x-layouts.guest>