@php $jourIso = $periode->date->dayOfWeekIso; @endphp

@if ($employes->isEmpty())
    <div class="card text-center text-sm text-stone-500">
        @if (! $voitTout && ! $monEmploye)
            Aucune fiche employé n'est liée à votre compte : demandez à l'administratrice de la relier à votre fiche.
        @else
            Aucune personne ne travaille ce jour et aucun rendez-vous n'est enregistré.
        @endif
    </div>
@else
    <div class="grid gap-6 lg:grid-cols-2 xl:grid-cols-3">
        @foreach ($employes as $employe)
            @php
                $liste = $parJour->get($periode->date->toDateString(), collect())->where('employe_id', $employe->id);
                $plages = $employe->disponibilites->filter(fn ($d) => $d->jour_semaine->value === $jourIso);
            @endphp

            <section class="card overflow-hidden p-0">
                <header class="border-b border-beige-200 px-5 py-4">
                    <h2 class="text-lg font-semibold">{{ $employe->nomComplet() }}</h2>
                    <p class="mt-0.5 text-xs text-stone-500">
                        {{ $employe->fonction }} ·
                        @if ($plages->isEmpty())
                            Ne travaille pas ce jour
                        @else
                            {{ $plages->map(fn ($d) => $d->debut().' – '.$d->fin())->join(' · ') }}
                        @endif
                    </p>
                </header>

                <div class="space-y-3 p-4">
                    @forelse ($liste as $rdv)
                        @include('rendez-vous._carte')
                    @empty
                        <p class="py-2 text-center text-sm text-stone-400">Aucun rendez-vous.</p>
                    @endforelse

                    @if ($canCreate && $plages->isNotEmpty())
                        <a href="{{ route('rendez-vous.create', ['date' => $periode->date->toDateString(), 'employe' => $employe->id]) }}"
                           class="block rounded-xl border border-dashed border-beige-300 py-2 text-center text-sm font-medium text-bordeaux-700 hover:bg-beige-50">
                            Ajouter un rendez-vous
                        </a>
                    @endif
                </div>
            </section>
        @endforeach
    </div>
@endif