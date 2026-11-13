@php
    $user = auth()->user();
    $futur = $rendezVous->date->toDateString() > now()->toDateString();
    $libelles = [
        'confirme' => 'Confirmer',
        'client_arrive' => 'Client arrivé',
        'client_absent' => 'Client absent',
        'en_cours' => 'Démarrer la prestation',
        'termine' => 'Terminer',
    ];
    $transitions = collect($rendezVous->statut->transitions())
        ->filter(fn ($vers) => $user->can('changeStatus', [$rendezVous, $vers]) && ! ($vers->estTerrain() && $futur));
    $peutAnnuler = $transitions->contains(\App\Enums\StatutRendezVous::Annule);
    $peutModifier = $user->can('update', $rendezVous) && $rendezVous->statut->estModifiable();
    $paye = $rendezVous->montantPaye();
    $reste = (int) $rendezVous->prix_prevu - $paye;
    $annulationBloquee = $peutAnnuler && $paye > 0;
@endphp

<x-layouts.app title="Rendez-vous">
    <x-page-header :title="ucfirst($rendezVous->date->translatedFormat('l j F Y'))"
                   :subtitle="$rendezVous->debut().' – '.$rendezVous->fin().' · '.$rendezVous->employe->nomComplet()">
        <x-slot:actions>
            <a href="{{ route('rendez-vous.index', ['date' => $rendezVous->date->toDateString()]) }}" class="btn-secondary">Retour à l'agenda</a>
            @if ($peutModifier)
                <a href="{{ route('rendez-vous.edit', $rendezVous) }}" class="btn-primary">Modifier ou déplacer</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <section class="card">
                <h2 class="text-lg font-semibold">Détails</h2>
                <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-stone-500">Client</dt>
                        <dd class="mt-0.5 font-medium">
                            @can('view', $rendezVous->client)
                                <a href="{{ route('clients.show', $rendezVous->client) }}" class="text-bordeaux-800 hover:text-bordeaux-900">{{ $rendezVous->client->nomComplet() }}</a>
                            @else
                                {{ $rendezVous->client->nomComplet() }}
                            @endcan
                        </dd>
                    </div>
                    <div>
                        <dt class="text-stone-500">Statut</dt>
                        <dd class="mt-0.5"><span class="badge {{ $rendezVous->statut->badge() }}">{{ $rendezVous->statut->label() }}</span></dd>
                    </div>
                    <div><dt class="text-stone-500">Personne</dt><dd class="mt-0.5 font-medium">{{ $rendezVous->employe->nomComplet() }}</dd></div>
                    <div><dt class="text-stone-500">Zone du salon</dt><dd class="mt-0.5 font-medium">{{ $rendezVous->zone->label() }}</dd></div>
                    <div><dt class="text-stone-500">Horaire</dt><dd class="mt-0.5 font-medium">{{ $rendezVous->debut() }} – {{ $rendezVous->fin() }} ({{ \App\Models\Service::formatDuree($rendezVous->dureeMinutes()) }})</dd></div>
                    <div><dt class="text-stone-500">Prix prévu</dt><dd class="mt-0.5 font-medium">{{ $rendezVous->prixFormate() }}</dd></div>
                    @if ($rendezVous->commentaire)
                        <div class="sm:col-span-2"><dt class="text-stone-500">Commentaire</dt><dd class="mt-0.5 whitespace-pre-line">{{ $rendezVous->commentaire }}</dd></div>
                    @endif
                    @if ($rendezVous->motif_annulation)
                        <div class="sm:col-span-2"><dt class="text-stone-500">Motif d'annulation</dt><dd class="mt-0.5">{{ $rendezVous->motif_annulation }}</dd></div>
                    @endif
                </dl>
            </section>

            <section class="card overflow-hidden p-0">
                <h2 class="border-b border-beige-200 px-6 py-4 text-lg font-semibold">Prestations</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-beige-50 text-xs uppercase tracking-wide text-stone-500">
                            <tr>
                                <th class="px-6 py-3">Prestation</th>
                                <th class="px-3 py-3">Forfait</th>
                                <th class="px-3 py-3">Durée</th>
                                <th class="px-6 py-3 text-right">Prix</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rendezVous->lignes as $ligne)
                                <tr class="border-t border-beige-100">
                                    <td class="px-6 py-3">{{ $ligne->nom }}</td>
                                    <td class="px-3 py-3 text-stone-500">{{ $ligne->forfait?->nom ?? '—' }}</td>
                                    <td class="whitespace-nowrap px-3 py-3">{{ \App\Models\Service::formatDuree($ligne->duree_appliquee) }}</td>
                                    <td class="whitespace-nowrap px-6 py-3 text-right">{{ \App\Support\Fcfa::format($ligne->prix_applique) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="border-t border-beige-100 px-6 py-3 text-xs text-stone-500">Prix et durées enregistrés à la réservation : une modification ultérieure du catalogue ne les change pas.</p>
            </section>

            @if ($voitCaisse)
                <section class="card overflow-hidden p-0">
                    <div class="flex items-center justify-between gap-3 border-b border-beige-200 px-6 py-4">
                        <h2 class="text-lg font-semibold">Paiement</h2>
                        @if ($encaissable && $reste > 0)
                            <a href="{{ route('paiements.create', ['rendez_vous' => $rendezVous->id]) }}" class="btn-primary">Encaisser</a>
                        @endif
                    </div>

                    <dl class="grid gap-4 px-6 py-4 text-sm sm:grid-cols-3">
                        <div><dt class="text-stone-500">Prix prévu</dt><dd class="mt-0.5 font-medium">{{ $rendezVous->prixFormate() }}</dd></div>
                        <div><dt class="text-stone-500">Déjà payé</dt><dd class="mt-0.5 font-medium">{{ \App\Support\Fcfa::format($paye) }}</dd></div>
                        <div>
                            <dt class="text-stone-500">Reste à payer</dt>
                            <dd class="mt-0.5 font-medium">
                                @if ($reste > 0)
                                    <span class="text-bordeaux-800">{{ \App\Support\Fcfa::format($reste) }}</span>
                                @elseif ($rendezVous->prix_prevu > 0)
                                    <span class="text-emerald-700">Entièrement payé</span>
                                @else
                                    —
                                @endif
                            </dd>
                        </div>
                    </dl>

                    @if ($paiements->isNotEmpty())
                        <div class="overflow-x-auto border-t border-beige-100">
                            <table class="w-full text-left text-sm">
                                <thead class="bg-beige-50 text-xs uppercase tracking-wide text-stone-500">
                                    <tr>
                                        <th class="px-6 py-3">Reçu</th>
                                        <th class="px-3 py-3">Date</th>
                                        <th class="px-3 py-3">Mode</th>
                                        <th class="px-3 py-3 text-right">Montant</th>
                                        <th class="px-6 py-3">Statut</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($paiements as $p)
                                        @php $annule = $p->estAnnule(); @endphp
                                        <tr class="border-t border-beige-100 {{ $annule ? 'text-stone-400' : '' }}">
                                            <td class="whitespace-nowrap px-6 py-3"><a href="{{ route('paiements.show', $p) }}" class="font-medium text-bordeaux-800 hover:text-bordeaux-900">{{ $p->numero() }}</a></td>
                                            <td class="whitespace-nowrap px-3 py-3">{{ $p->date->format('d/m/Y') }}</td>
                                            <td class="px-3 py-3">{{ $p->mode->nom }}</td>
                                            <td class="whitespace-nowrap px-3 py-3 text-right {{ $p->montant < 0 ? 'text-red-700' : '' }} {{ $annule ? 'line-through' : '' }}">{{ $p->montantFormate() }}</td>
                                            <td class="px-6 py-3">
                                                @if ($annule)
                                                    <span class="badge bg-stone-100 text-stone-500">Annulé</span>
                                                @else
                                                    <span class="badge {{ $p->type->badge() }}">{{ $p->type->label() }}</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="border-t border-beige-100 px-6 py-4 text-sm text-stone-500">Aucun paiement enregistré.</p>
                    @endif

                    @if (! $encaissable && $reste > 0)
                        <p class="border-t border-beige-100 px-6 py-3 text-xs text-stone-500">Ce rendez-vous ne peut pas être encaissé dans son état actuel.</p>
                    @endif
                </section>
            @endif
        </div>

        <div class="space-y-6">
            <section class="card">
                <h2 class="text-lg font-semibold">Actions</h2>

                <div class="mt-4 space-y-3">
                    @foreach ($transitions as $vers)
                        @continue($vers === \App\Enums\StatutRendezVous::Annule)

                        <form method="POST" action="{{ route('rendez-vous.statut', $rendezVous) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="statut" value="{{ $vers->value }}">
                            <button type="submit" class="btn-primary w-full">{{ $libelles[$vers->value] ?? $vers->label() }}</button>
                        </form>
                    @endforeach

                    @if ($peutAnnuler && ! $annulationBloquee)
                        <details class="rounded-xl border border-beige-200 p-3">
                            <summary class="cursor-pointer text-sm font-medium text-red-700">Annuler le rendez-vous</summary>
                            <form method="POST" action="{{ route('rendez-vous.statut', $rendezVous) }}" class="mt-3 space-y-3"
                                  onsubmit="return confirm('Annuler ce rendez-vous ? Le créneau sera libéré.')">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="statut" value="annule">
                                <div>
                                    <label for="motif" class="label">Motif (facultatif)</label>
                                    <input id="motif" name="motif" maxlength="255" class="input">
                                </div>
                                <button type="submit" class="btn-danger w-full">Confirmer l'annulation</button>
                            </form>
                        </details>
                    @endif

                    @if ($annulationBloquee)
                        <p class="rounded-xl bg-beige-50 p-3 text-xs text-stone-600">
                            Ce rendez-vous a reçu un paiement : faites d'abord annuler le paiement (remboursement) avant de l'annuler.
                        </p>
                    @endif

                    @if ($transitions->isEmpty())
                        <p class="text-sm text-stone-500">Aucune action possible : ce rendez-vous est « {{ $rendezVous->statut->label() }} ».</p>
                    @endif
                </div>

                @if ($futur && $rendezVous->statut->transitions() !== [])
                    <p class="mt-4 text-xs text-stone-500">Les statuts « arrivé », « en cours », « terminé » et « absent » seront disponibles le jour du rendez-vous.</p>
                @endif
            </section>

            @if ($journal->isNotEmpty())
                <section class="card">
                    <h2 class="text-lg font-semibold">Historique des modifications</h2>
                    <ul class="mt-4 space-y-3 text-sm">
                        @foreach ($journal as $entree)
                            <li>
                                <p>{{ $entree->description }}</p>
                                <p class="text-xs text-stone-500">{{ $entree->created_at->format('d/m/Y H:i') }} · {{ $entree->user?->name ?? 'Système' }}</p>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>
    </div>
</x-layouts.app>