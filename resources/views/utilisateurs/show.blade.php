@php
    $acteur = auth()->user();
    $peutModifier = $acteur->can('update', $compte);
    $peutBasculer = $acteur->can('toggle', $compte) && ! $compte->is($acteur);
@endphp

<x-layouts.app :title="$compte->name">
    <x-page-header :title="$compte->name" :subtitle="$compte->role->label()">
        <x-slot:actions>
            <a href="{{ route('utilisateurs.index') }}" class="btn-secondary">Retour aux utilisateurs</a>
            @if ($peutModifier)
                <a href="{{ route('utilisateurs.edit', $compte) }}" class="btn-primary">Modifier</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <section class="card">
                <h2 class="text-lg font-semibold">Informations</h2>
                <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="text-stone-500">Adresse e-mail</dt><dd class="mt-0.5 font-medium">{{ $compte->email }}</dd></div>
                    <div><dt class="text-stone-500">Rôle</dt><dd class="mt-0.5 font-medium">{{ $compte->role->label() }}</dd></div>
                    <div>
                        <dt class="text-stone-500">Statut</dt>
                        <dd class="mt-0.5">
                            <span class="badge {{ $compte->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-stone-100 text-stone-500' }}">
                                {{ $compte->is_active ? 'Actif' : 'Désactivé' }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-stone-500">Dernière connexion</dt>
                        <dd class="mt-0.5">{{ $compte->last_login_at ? $compte->last_login_at->translatedFormat('d F Y à H:i') : 'Jamais connecté' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-stone-500">Fiche employé liée</dt>
                        <dd class="mt-0.5">
                            @if ($compte->employe)
                                @can('view', $compte->employe)
                                    <a href="{{ route('employes.show', $compte->employe) }}" class="font-medium text-bordeaux-700 hover:text-bordeaux-900">{{ $compte->employe->nomComplet() }}</a>
                                @else
                                    {{ $compte->employe->nomComplet() }}
                                @endcan
                            @else
                                Aucune
                            @endif
                        </dd>
                    </div>
                </dl>
            </section>

            <section class="card">
                <h2 class="text-lg font-semibold">Droits</h2>
                @if ($compte->isAdmin())
                    <p class="mt-4 text-sm text-stone-600">L'administratrice a accès à tout.</p>
                @else
                    <div class="mt-4 space-y-4">
                        @foreach ($groupes as $groupe => $permissions)
                            @php
                                $accordees = array_values(array_filter($permissions,
                                    fn ($p) => in_array($p, $roleDroits, true) || in_array($p, $extras, true)));
                            @endphp
                            @continue($accordees === [])
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-stone-500">{{ $groupe }}</p>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    @foreach ($accordees as $permission)
                                        @if (in_array($permission, $roleDroits, true))
                                            <span class="badge bg-beige-100 text-bordeaux-800">{{ $catalogue[$permission] }}</span>
                                        @else
                                            <span class="badge bg-bordeaux-700 text-white" title="Accordé individuellement">{{ $catalogue[$permission] }} · en plus</span>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="card">
                <h2 class="text-lg font-semibold">Dernières actions</h2>
                @if ($activite->isEmpty())
                    <p class="mt-4 text-sm text-stone-500">Aucune action enregistrée pour ce compte.</p>
                @else
                    <ul class="mt-4 divide-y divide-beige-100 text-sm">
                        @foreach ($activite as $action)
                            <li class="flex items-start justify-between gap-4 py-2.5">
                                <span>{{ $action->description ?? $action->action }}</span>
                                <span class="shrink-0 text-xs text-stone-500">{{ $action->created_at?->translatedFormat('d M Y, H:i') }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>

        <div class="space-y-6">
            @if ($peutBasculer)
                <section class="card">
                    <h2 class="text-lg font-semibold">Accès</h2>
                    <form method="POST" action="{{ route('utilisateurs.toggle', $compte) }}" class="mt-3"
                          @if ($compte->is_active) onsubmit="return confirm('Désactiver ce compte ? La personne sera déconnectée et ne pourra plus se connecter.')" @endif>
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn-secondary w-full">
                            {{ $compte->is_active ? 'Désactiver le compte' : 'Réactiver le compte' }}
                        </button>
                    </form>
                    <p class="mt-3 text-xs text-stone-500">Un compte n'est jamais supprimé : son journal d'actions est conservé.</p>
                </section>
            @endif
        </div>
    </div>
</x-layouts.app>
