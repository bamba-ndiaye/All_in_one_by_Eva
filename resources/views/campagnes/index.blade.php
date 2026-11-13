<x-layouts.app title="Campagnes">
    <x-page-header title="Campagnes WhatsApp" subtitle="Un même message envoyé à un groupe de clientes, une par une, depuis WhatsApp.">
        <x-slot:actions>
            <a href="{{ route('campagnes.create') }}" class="btn-primary">Nouvelle campagne</a>
        </x-slot:actions>
    </x-page-header>

    @if ($campagnes->isEmpty())
        <div class="card text-center text-sm text-stone-500">
            Aucune campagne pour l'instant. Créez-en une pour annoncer une promotion, une nouveauté ou relancer les clientes absentes.
        </div>
    @else
        <div class="card overflow-hidden p-0">
            <table class="w-full text-left text-sm">
                <thead class="bg-beige-50 text-xs uppercase tracking-wide text-stone-500">
                    <tr>
                        <th class="px-6 py-3">Campagne</th>
                        <th class="px-3 py-3">Public</th>
                        <th class="px-3 py-3">Avancement</th>
                        <th class="px-6 py-3">Statut</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($campagnes as $campagne)
                        <tr class="border-t border-beige-100">
                            <td class="px-6 py-4">
                                <a href="{{ route('campagnes.show', $campagne) }}" class="font-medium text-bordeaux-800 hover:text-bordeaux-900">{{ $campagne->titre }}</a>
                                <p class="mt-0.5 text-xs text-stone-500">Créée le {{ $campagne->created_at->translatedFormat('d M Y') }}</p>
                            </td>
                            <td class="px-3 py-4">{{ $campagne->cibleLibelle() }}</td>
                            <td class="px-3 py-4">
                                @if ($campagne->estBrouillon())
                                    —
                                @else
                                    {{ $campagne->envoyes_count }} / {{ $campagne->destinataires_count }} envoyés
                                @endif
                            </td>
                            <td class="px-6 py-4"><span class="badge {{ $campagne->statut->badge() }}">{{ $campagne->statut->label() }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-6">{{ $campagnes->links() }}</div>
    @endif
</x-layouts.app>
