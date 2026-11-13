<x-layouts.app title="Anniversaires">
    <x-page-header title="Anniversaires" subtitle="Les clientes à fêter aujourd'hui et dans les 30 prochains jours." />

    @if ($sansDate > 0)
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-beige-200 bg-beige-50 px-4 py-3 text-sm text-stone-700">
            <span>{{ $sansDate }} {{ $sansDate > 1 ? 'clientes n\'ont' : 'cliente n\'a' }} pas de date de naissance enregistrée.</span>
            @can('clients.view')
                <a href="{{ route('clients.index', ['naissance' => 'manquante']) }}" class="font-medium text-bordeaux-700 hover:text-bordeaux-900">Compléter les fiches</a>
            @endcan
        </div>
    @endif

    <div class="space-y-6">
        @include('anniversaires._liste', [
            'titre' => "Aujourd'hui",
            'liste' => $aujourdhui,
            'vide' => "Aucun anniversaire aujourd'hui.",
        ])

        @include('anniversaires._liste', [
            'titre' => 'Cette semaine',
            'liste' => $semaine,
            'vide' => 'Aucun anniversaire dans les 6 prochains jours.',
        ])

        @include('anniversaires._liste', [
            'titre' => 'Plus tard ce mois-ci',
            'liste' => $mois,
            'vide' => 'Aucun autre anniversaire dans les 30 prochains jours.',
        ])
    </div>

    <p class="mt-6 text-xs text-stone-500">
        « Souhaiter sur WhatsApp » ouvre WhatsApp avec le message déjà écrit : il ne reste qu'à appuyer sur Envoyer.
        Seules les clientes qui ont accepté de recevoir des messages peuvent être contactées.
    </p>
</x-layouts.app>
