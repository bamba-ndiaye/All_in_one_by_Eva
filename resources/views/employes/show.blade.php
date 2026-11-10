@php
    $canManage = auth()->user()->can('update', $employe);
    $canSeeEarnings = auth()->user()->can('staff.view_earnings');
    $horaires = $employe->horairesParJour();
@endphp

<x-layouts.app :title="$employe->nomComplet()">
    <x-page-header :title="$employe->nomComplet()" :subtitle="$employe->fonction">
        <x-slot:actions>
            <a href="{{ route('employes.index') }}" class="btn-secondary">Retour à l'équipe</a>
            @if ($canManage)
                <a href="{{ route('employes.disponibilites.edit', $employe) }}" class="btn-secondary">Horaires</a>
                <a href="{{ route('employes.edit', $employe) }}" class="btn-primary">Modifier</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <section class="card">
                <h2 class="text-lg font-semibold">Informations</h2>
                <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="text-stone-500">Téléphone</dt><dd class="mt-0.5 font-medium">{{ $employe->telephone ?? '—' }}</dd></div>
                    <div>
                        <dt class="text-stone-500">Statut</dt>
                        <dd class="mt-0.5">
                            <span class="badge {{ $employe->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-stone-100 text-stone-500' }}">
                                {{ $employe->is_active ? 'Active' : 'Désactivée' }}
                            </span>
                        </dd>
                    </div>
                    <div class="sm:col-span-2"><dt class="text-stone-500">Spécialités</dt><dd class="mt-0.5">{{ $employe->specialites ?? '—' }}</dd></div>
                    @if ($canManage)
                        <div class="sm:col-span-2">
                            <dt class="text-stone-500">Compte de connexion</dt>
                            <dd class="mt-0.5">{{ $employe->user ? $employe->user->name.' ('.$employe->user->email.')' : 'Aucun compte lié' }}</dd>
                        </div>
                    @endif
                </dl>
            </section>

            <section class="card">
                <h2 class="text-lg font-semibold">Prestations proposées</h2>
                @if ($servicesParCategorie->isEmpty())
                    <p class="mt-4 text-sm text-stone-500">Aucune prestation n'est encore associée à cette personne.</p>
                @else
                    <div class="mt-4 space-y-4">
                        @foreach ($servicesParCategorie as $categorie => $services)
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-stone-500">{{ $categorie }}</p>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    @foreach ($services as $service)
                                        <span class="badge bg-beige-100 text-bordeaux-800">{{ $service->nom }}</span>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="card">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold">Horaires de travail</h2>
                    @if ($canManage)
                        <a href="{{ route('employes.disponibilites.edit', $employe) }}" class="text-sm font-medium text-bordeaux-700 hover:text-bordeaux-900">Modifier</a>
                    @endif
                </div>
                <table class="mt-4 w-full text-sm">
                    <tbody>
                        @foreach (\App\Enums\JourSemaine::cases() as $jour)
                            <tr class="border-t border-beige-100">
                                <td class="w-32 py-2.5 font-medium">{{ $jour->label() }}</td>
                                <td class="py-2.5 {{ $horaires->has($jour->value) ? '' : 'text-stone-400' }}">
                                    {{ $horaires->has($jour->value) ? $horaires->get($jour->value)->join('  ·  ') : 'Repos' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        </div>

        <div class="space-y-6">
            @if ($canSeeEarnings)
                <section class="card">
                    <h2 class="text-lg font-semibold">Commission</h2>
                    <p class="mt-3 font-serif text-xl font-semibold text-bordeaux-900">{{ $employe->commissionLibelle() }}</p>
                </section>

                <section class="card">
                    <h2 class="text-lg font-semibold">Performances</h2>
                    <p class="mt-3 text-sm text-stone-500">
                        Le nombre de prestations réalisées et les montants correspondants seront
                        calculés à partir des rendez-vous et de la caisse, dès que ces modules seront en place.
                    </p>
                </section>
            @endif

            @if ($canManage)
                <section class="card">
                    <h2 class="text-lg font-semibold">Statut</h2>
                    <form method="POST" action="{{ route('employes.toggle', $employe) }}" class="mt-3"
                          @if ($employe->is_active) onsubmit="return confirm('Désactiver cette personne ? Elle ne pourra plus recevoir de nouveaux rendez-vous.')" @endif>
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn-secondary w-full">
                            {{ $employe->is_active ? 'Désactiver' : 'Réactiver' }}
                        </button>
                    </form>
                    <p class="mt-3 text-xs text-stone-500">Une fiche n'est jamais supprimée : son historique est conservé.</p>
                </section>
            @endif
        </div>
    </div>
</x-layouts.app>