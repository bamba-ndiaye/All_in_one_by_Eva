<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-7">
    @foreach ($periode->jours() as $jour)
        @php
            $liste = $parJour->get($jour->toDateString(), collect())
                ->filter(fn ($rdv) => $rdv->statut->bloqueLeCreneau())
                ->values();
        @endphp

        <section @class(['card p-3', 'ring-2 ring-bordeaux-300' => $jour->isSameDay($aujourdhui)])>
            <a href="{{ $lien('jour', $jour) }}" class="block border-b border-beige-100 pb-2">
                <p class="text-xs uppercase tracking-wide text-stone-500">{{ $jour->translatedFormat('D') }}</p>
                <p class="font-serif text-lg font-semibold text-bordeaux-900">{{ $jour->format('j') }}</p>
                <p class="text-xs text-stone-500">{{ $liste->count() }} rdv</p>
            </a>

            <div class="mt-2 space-y-2">
                @foreach ($liste as $rdv)
                    <a href="{{ route('rendez-vous.show', $rdv) }}"
                       class="block rounded-lg bg-beige-50 px-2 py-1.5 text-xs hover:bg-beige-100">
                        <span class="font-semibold">{{ $rdv->debut() }}</span> {{ $rdv->client->nomComplet() }}
                        <span class="block text-stone-500">{{ $rdv->employe->prenom }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endforeach
</div>

<p class="mt-4 text-xs text-stone-500">Les rendez-vous annulés ou « client absent » ne sont affichés que dans la vue Jour.</p>