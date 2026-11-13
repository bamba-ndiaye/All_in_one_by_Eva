@php
    $statut = $campagne->statut;
    $pourcent = $total > 0 ? intdiv($envoyes * 100, $total) : 0;
@endphp

<x-layouts.app :title="$campagne->titre">
    <x-page-header :title="$campagne->titre" :subtitle="$campagne->cibleLibelle()">
        <x-slot:actions>
            <a href="{{ route('campagnes.index') }}" class="btn-secondary">Toutes les campagnes</a>
            @if ($campagne->estBrouillon())
                <a href="{{ route('campagnes.edit', $campagne) }}" class="btn-secondary">Modifier</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <section class="card">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold">Message</h2>
                    <span class="badge {{ $statut->badge() }}">{{ $statut->label() }}</span>
                </div>
                <p class="mt-3 whitespace-pre-line rounded-xl bg-beige-50 p-4 text-sm text-stone-800">{{ $exemple ? $campagne->messagePour($exemple) : $campagne->message }}</p>
                @if ($exemple)
                    <p class="mt-2 text-xs text-stone-500">Exemple tel que le recevra {{ $exemple->prenom }}.</p>
                @endif
            </section>

            @if ($destinataires)
                <section class="card overflow-hidden p-0">
                    <div class="px-6 pt-6">
                        <div class="flex items-center justify-between text-sm">
                            <h2 class="text-lg font-semibold">Destinataires</h2>
                            <span class="font-medium text-bordeaux-800">{{ $envoyes }} / {{ $total }} envoyés</span>
                        </div>
                        <div class="mt-3 h-2 overflow-hidden rounded-full bg-beige-100" role="progressbar" aria-valuenow="{{ $pourcent }}" aria-valuemin="0" aria-valuemax="100">
                            <div class="h-full rounded-full bg-bordeaux-700" style="width: {{ $pourcent }}%"></div>
                        </div>
                    </div>

                    <ul class="mt-4 divide-y divide-beige-100">
                        @foreach ($destinataires as $d)
                            <li class="flex flex-wrap items-center gap-3 px-6 py-3">
                                <div class="min-w-0 flex-1">
                                    <span class="font-medium">{{ $d->client->nomComplet() }}</span>
                                    <p class="text-xs text-stone-500">{{ $d->client->telephoneAffiche() }}</p>
                                </div>
                                @if ($d->envoye_at)
                                    <span class="badge bg-emerald-50 text-emerald-700" title="Par {{ $d->envoyePar?->name }}">Envoyé · {{ $d->envoye_at->format('d/m H:i') }}</span>
                                @elseif ($statut === \App\Enums\StatutCampagne::EnCours)
                                    @if ($d->client->consentement_promo && $d->client->is_active)
                                        <form method="POST" action="{{ route('campagnes.envoyer', [$campagne, $d]) }}" target="_blank"
                                              onsubmit="setTimeout(() => window.location.reload(), 1500)">
                                            @csrf
                                            <button type="submit" class="btn-primary">Envoyer</button>
                                        </form>
                                    @else
                                        <span class="badge bg-stone-100 text-stone-500">Ne souhaite plus de messages</span>
                                    @endif
                                @else
                                    <span class="badge bg-stone-100 text-stone-500">Non envoyé</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    <div class="px-6 py-4">{{ $destinataires->links() }}</div>
                </section>
            @endif
        </div>

        <div class="space-y-6">
            @if ($campagne->estBrouillon())
                <section class="card">
                    <h2 class="text-lg font-semibold">Prête à partir ?</h2>
                    <p class="mt-3 font-serif text-3xl font-semibold text-bordeaux-900">{{ $nbCible }}</p>
                    <p class="text-sm text-stone-500">{{ $nbCible > 1 ? 'clientes concernées' : 'cliente concernée' }} aujourd'hui</p>
                    @if ($nbCible > 0)
                        <form method="POST" action="{{ route('campagnes.lancer', $campagne) }}" class="mt-4"
                              onsubmit="return confirm('Lancer la campagne ? Le message et la liste ne pourront plus être modifiés.')">
                            @csrf
                            <button type="submit" class="btn-primary w-full">Lancer la campagne</button>
                        </form>
                        <p class="mt-3 text-xs text-stone-500">Après le lancement, un bouton « Envoyer » ouvre WhatsApp avec le message prêt, pour chaque cliente.</p>
                    @else
                        <p class="mt-3 text-sm text-stone-500">Aucune cliente ne correspond : modifiez le public.</p>
                    @endif
                </section>
            @elseif ($statut === \App\Enums\StatutCampagne::EnCours)
                <section class="card">
                    <h2 class="text-lg font-semibold">Arrêter l'envoi</h2>
                    <p class="mt-2 text-sm text-stone-500">Les clientes pas encore contactées ne le seront pas.</p>
                    <form method="POST" action="{{ route('campagnes.cloturer', $campagne) }}" class="mt-4"
                          onsubmit="return confirm('Clôturer la campagne ?')">
                        @csrf
                        <button type="submit" class="btn-secondary w-full">Clôturer la campagne</button>
                    </form>
                </section>
            @endif

            @if ($campagne->lancee_at)
                <section class="card text-sm">
                    <p class="text-stone-500">Lancée le</p>
                    <p class="font-medium">{{ $campagne->lancee_at->translatedFormat('d F Y à H:i') }}</p>
                </section>
            @endif
        </div>
    </div>
</x-layouts.app>
