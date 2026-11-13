{{-- $titre, $liste, $vide, $service, $user --}}
<section class="card overflow-hidden p-0">
    <h2 class="px-6 pt-6 text-lg font-semibold">{{ $titre }}</h2>

    @if ($liste->isEmpty())
        <p class="px-6 py-5 text-sm text-stone-500">{{ $vide }}</p>
    @else
        <ul class="mt-4 divide-y divide-beige-100">
            @foreach ($liste as $a)
                @php $client = $a['client']; @endphp
                <li class="flex flex-wrap items-center gap-4 px-6 py-4">
                    <div class="flex h-12 w-12 shrink-0 flex-col items-center justify-center rounded-xl bg-bordeaux-50 text-bordeaux-800">
                        <span class="text-lg font-semibold leading-none">{{ $a['date']->format('j') }}</span>
                        <span class="text-[11px] uppercase">{{ $a['date']->translatedFormat('M') }}</span>
                    </div>

                    <div class="min-w-0 flex-1">
                        @can('view', $client)
                            <a href="{{ route('clients.show', $client) }}" class="font-medium text-bordeaux-800 hover:text-bordeaux-900">{{ $client->nomComplet() }}</a>
                        @else
                            <span class="font-medium">{{ $client->nomComplet() }}</span>
                        @endcan
                        <p class="mt-0.5 text-sm text-stone-500">
                            {{ $a['age'] }} ans
                            · {{ $a['dans'] === 0 ? "aujourd'hui" : ($a['dans'] === 1 ? 'demain' : ucfirst($a['date']->translatedFormat('l j F'))) }}
                            · {{ $client->telephoneAffiche() }}
                        </p>
                    </div>

                    <div class="shrink-0">
                        @if ($a['souhaite'])
                            <span class="badge bg-emerald-50 text-emerald-700">Déjà souhaité</span>
                        @elseif ($service->peutSouhaiter($user, $client))
                            <form method="POST" action="{{ route('anniversaires.souhaiter', $client) }}" target="_blank"
                                  onsubmit="setTimeout(() => window.location.reload(), 1500)">
                                @csrf
                                <button type="submit" class="btn-primary">Souhaiter sur WhatsApp</button>
                            </form>
                        @elseif (! $client->consentement_promo)
                            <span class="badge bg-stone-100 text-stone-500" title="La cliente n'a pas accepté de recevoir des messages">Pas de consentement</span>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</section>
