<div class="overflow-x-auto">
    <div class="min-w-[32rem] space-y-2">
        <div class="grid grid-cols-7 gap-2 text-center text-xs font-medium uppercase tracking-wide text-stone-500">
            @foreach (['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'] as $nomJour)
                <div>{{ $nomJour }}</div>
            @endforeach
        </div>

        @foreach ($periode->semaines() as $semaine)
            <div class="grid grid-cols-7 gap-2">
                @foreach ($semaine as $jour)
                    @php
                        $nombre = $parJour->get($jour->toDateString(), collect())
                            ->filter(fn ($rdv) => $rdv->statut->bloqueLeCreneau())
                            ->count();
                        $dansLeMois = $jour->month === $periode->date->month;
                    @endphp

                    <a href="{{ $lien('jour', $jour) }}" @class([
                        'min-h-20 rounded-lg border p-2 text-sm hover:bg-beige-50',
                        'border-beige-200 bg-white' => $dansLeMois,
                        'border-transparent bg-stone-50 text-stone-400' => ! $dansLeMois,
                        'ring-2 ring-bordeaux-300' => $jour->isSameDay($aujourdhui),
                    ])>
                        <span class="font-medium">{{ $jour->format('j') }}</span>
                        @if ($nombre > 0)
                            <span class="mt-1 block rounded-full bg-bordeaux-50 px-2 py-0.5 text-center text-xs font-medium text-bordeaux-700">{{ $nombre }} rdv</span>
                        @endif
                    </a>
                @endforeach
            </div>
        @endforeach
    </div>
</div>

<p class="mt-4 text-xs text-stone-500">Les rendez-vous annulés ou « client absent » ne sont pas comptés.</p>