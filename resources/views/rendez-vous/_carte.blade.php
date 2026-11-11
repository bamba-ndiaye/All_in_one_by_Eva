@php $libere = ! $rdv->statut->bloqueLeCreneau(); @endphp

<a href="{{ route('rendez-vous.show', $rdv) }}"
   class="block rounded-xl border border-beige-200 p-3 hover:bg-beige-50 {{ $libere ? 'opacity-60' : '' }}">
    <div class="flex items-center justify-between gap-2">
        <span class="text-sm font-semibold text-bordeaux-900">{{ $rdv->debut() }} – {{ $rdv->fin() }}</span>
        <span class="badge {{ $rdv->statut->badge() }}">{{ $rdv->statut->label() }}</span>
    </div>
    <p class="mt-1 text-sm font-medium {{ $libere ? 'line-through' : '' }}">{{ $rdv->client->nomComplet() }}</p>
    <p class="mt-0.5 text-xs text-stone-500">{{ $rdv->lignes->pluck('nom')->join(', ') }}</p>
    <p class="mt-1 text-xs text-stone-500">{{ $rdv->prixFormate() }} · {{ $rdv->zone->label() }}</p>
</a>