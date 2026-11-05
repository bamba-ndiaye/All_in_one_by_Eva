@php
    $user = auth()->user();
    $catalogue = config('permissions.catalogue');
@endphp

<x-layouts.app title="Tableau de bord">
    <x-page-header :title="'Bonjour '.$user->name" subtitle="Bienvenue chez All in One by Eva." />

    <div class="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
        <x-stat-card label="Votre rôle" :value="$user->role->label()" icon="user-cog" />
        <x-stat-card label="Permissions actives" :value="count($user->allPermissions())" icon="lock"
                     hint="Droits du rôle et droits individuels" />
        <x-stat-card label="Prochaine étape" value="Clients et catalogue" icon="sparkles" hint="Sprint 1" />
    </div>

    <div class="card mt-8">
        <h2 class="text-lg font-semibold">Ce que vous pouvez faire</h2>
        <div class="mt-4 flex flex-wrap gap-2">
            @foreach ($user->allPermissions() as $permission)
                <span class="badge bg-beige-100 text-bordeaux-800">{{ $catalogue[$permission] ?? $permission }}</span>
            @endforeach
        </div>
    </div>
</x-layouts.app>