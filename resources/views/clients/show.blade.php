<x-layouts.app :title="$client->nomComplet()">
    <x-page-header :title="$client->nomComplet()"
                   :subtitle="'Inscrit(e) le '.$client->created_at->translatedFormat('d F Y')">
        <x-slot:actions>
            <a href="{{ route('clients.index') }}" class="btn-secondary">Retour aux clients</a>
            @can('update', $client)
                <a href="{{ route('clients.edit', $client) }}" class="btn-primary">Modifier</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <section class="card">
                <h2 class="text-lg font-semibold">Coordonnées</h2>
                <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="text-stone-500">Téléphone</dt><dd class="mt-0.5 font-medium">{{ $client->telephoneAffiche() }}</dd></div>
                    <div>
                        <dt class="text-stone-500">WhatsApp</dt>
                        <dd class="mt-0.5 font-medium">{{ $client->whatsapp ? $client->whatsappAffiche() : 'Même numéro que le téléphone' }}</dd>
                    </div>
                    <div><dt class="text-stone-500">E-mail</dt><dd class="mt-0.5 font-medium">{{ $client->email ?? '—' }}</dd></div>
                    <div>
                        <dt class="text-stone-500">Date de naissance</dt>
                        <dd class="mt-0.5 font-medium">{{ $client->date_naissance?->translatedFormat('d F Y') ?? 'Non renseignée' }}</dd>
                    </div>
                    <div><dt class="text-stone-500">Canal préféré</dt><dd class="mt-0.5 font-medium">{{ $client->canal_prefere?->label() ?? '—' }}</dd></div>
                    <div>
                        <dt class="text-stone-500">Statut</dt>
                        <dd class="mt-0.5">
                            <span class="badge {{ $client->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-stone-100 text-stone-500' }}">
                                {{ $client->is_active ? 'Actif' : 'Archivé' }}
                            </span>
                        </dd>
                    </div>
                </dl>
            </section>

            <section class="card">
                <h2 class="text-lg font-semibold">Notes</h2>
                <p class="mt-3 whitespace-pre-line text-sm {{ $client->notes ? '' : 'text-stone-400' }}">{{ $client->notes ?? 'Aucune note.' }}</p>
            </section>

            <section class="card">
                <h2 class="text-lg font-semibold">Historique</h2>
                <p class="mt-3 text-sm text-stone-500">
                    Les rendez-vous, prestations réalisées, montants payés, dernière visite et points de fidélité
                    apparaîtront ici dès que ces modules seront en place.
                </p>
            </section>
        </div>

        <div class="space-y-6">
            <section class="card">
                <h2 class="text-lg font-semibold">Messages promotionnels</h2>
                <p class="mt-3">
                    <span class="badge {{ $client->consentement_promo ? 'bg-emerald-50 text-emerald-700' : 'bg-stone-100 text-stone-500' }}">
                        {{ $client->consentement_promo ? 'Consentement donné' : 'Pas de consentement' }}
                    </span>
                </p>
                <dl class="mt-4 space-y-2 text-sm">
                    @if ($client->date_consentement)
                        <div class="flex justify-between gap-4"><dt class="text-stone-500">Donné le</dt><dd>{{ $client->date_consentement->format('d/m/Y') }}</dd></div>
                    @endif
                    @if ($client->date_retrait_consentement)
                        <div class="flex justify-between gap-4"><dt class="text-stone-500">Retiré le</dt><dd>{{ $client->date_retrait_consentement->format('d/m/Y') }}</dd></div>
                    @endif
                </dl>
            </section>

            @can('archive', $client)
                <section class="card">
                    <h2 class="text-lg font-semibold">Archivage</h2>
                    <form method="POST" action="{{ route('clients.toggle', $client) }}" class="mt-3"
                          @if ($client->is_active) onsubmit="return confirm('Archiver cette fiche ? Elle disparaîtra de la liste active mais son historique sera conservé.')" @endif>
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn-secondary w-full">{{ $client->is_active ? 'Archiver' : 'Réactiver' }}</button>
                    </form>
                    <p class="mt-3 text-xs text-stone-500">Une fiche n'est jamais supprimée : son historique est conservé.</p>
                </section>
            @endcan
        </div>
    </div>
</x-layouts.app>