@php $annulee = $depense->estAnnulee(); @endphp

<x-layouts.app :title="'Dépense n° '.$depense->id">
    <x-page-header :title="'Dépense n° '.$depense->id" :subtitle="$depense->date->translatedFormat('d F Y')">
        <x-slot:actions>
            <a href="{{ route('depenses.index') }}" class="btn-secondary">Retour aux dépenses</a>
            @if (! $annulee)
                <a href="{{ route('depenses.edit', $depense) }}" class="btn-primary">Modifier</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            @if ($annulee)
                <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900">
                    Cette dépense a été annulée le {{ $depense->annulee_at->format('d/m/Y à H:i') }}
                    @if ($depense->annuleePar) par {{ $depense->annuleePar->name }} @endif.
                    Elle n'entre plus dans aucun total.
                </div>
            @endif

            <section class="card">
                <h2 class="text-lg font-semibold">Détails</h2>
                <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-stone-500">Montant</dt>
                        <dd class="mt-0.5 font-serif text-xl font-semibold text-bordeaux-900 {{ $annulee ? 'line-through' : '' }}">{{ $depense->montantFormate() }}</dd>
                    </div>
                    <div><dt class="text-stone-500">Catégorie</dt><dd class="mt-0.5 font-medium">{{ $depense->categorie->nom }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-stone-500">Description</dt><dd class="mt-0.5">{{ $depense->description }}</dd></div>
                    <div><dt class="text-stone-500">Date</dt><dd class="mt-0.5">{{ $depense->date->format('d/m/Y') }}</dd></div>
                    <div><dt class="text-stone-500">Enregistrée par</dt><dd class="mt-0.5">{{ $depense->user?->name ?? '—' }}</dd></div>
                    <div>
                        <dt class="text-stone-500">Justificatif</dt>
                        <dd class="mt-0.5">
                            @if ($depense->aUnJustificatif())
                                <a href="{{ route('depenses.justificatif', $depense) }}" target="_blank" rel="noopener" class="font-medium text-bordeaux-700 hover:text-bordeaux-900">Ouvrir le justificatif</a>
                            @else
                                Aucun
                            @endif
                        </dd>
                    </div>
                    @if ($depense->motif_annulation)
                        <div class="sm:col-span-2"><dt class="text-stone-500">Motif d'annulation</dt><dd class="mt-0.5">{{ $depense->motif_annulation }}</dd></div>
                    @endif
                </dl>
            </section>
        </div>

        <div class="space-y-6">
            @if (! $annulee)
                <section class="card space-y-4">
                    <h2 class="text-lg font-semibold">Annulation</h2>
                    <p class="text-xs text-stone-500">Une dépense n'est jamais supprimée : elle est annulée et reste visible, avec son motif.</p>

                    <details class="rounded-xl border border-beige-200 p-3">
                        <summary class="cursor-pointer text-sm font-medium text-red-700">Annuler cette dépense</summary>
                        <form method="POST" action="{{ route('depenses.annuler', $depense) }}" class="mt-3 space-y-3"
                              onsubmit="return confirm('Annuler cette dépense ? Elle ne comptera plus dans les totaux.')">
                            @csrf
                            <div>
                                <label for="motif" class="label">Motif de l'annulation</label>
                                <input id="motif" name="motif" class="input" required minlength="5" maxlength="255">
                            </div>
                            <button type="submit" class="btn-danger w-full">Confirmer l'annulation</button>
                        </form>
                    </details>
                </section>
            @endif
        </div>
    </div>
</x-layouts.app>