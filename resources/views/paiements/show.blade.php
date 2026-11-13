@php
    $user = auth()->user();
    $estPaiement = $paiement->type === \App\Enums\TypePaiement::Paiement;
    $annule = $paiement->estAnnule();
    $peutAnnuler = $estPaiement && ! $annule && $user->can('annuler', $paiement);
@endphp

<x-layouts.app :title="'Paiement '.$paiement->numero()">
    <x-page-header :title="'Paiement '.$paiement->numero()"
                   :subtitle="$paiement->date->translatedFormat('d F Y').' · '.$paiement->created_at->format('H:i')">
        <x-slot:actions>
            <a href="{{ route('paiements.index') }}" class="btn-secondary">Retour à la caisse</a>
            <a href="{{ route('paiements.recu', $paiement) }}" target="_blank" rel="noopener" class="btn-primary">Voir le reçu</a>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            @if ($annule)
                <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900">
                    Ce paiement a été annulé.
                    <a href="{{ route('paiements.show', $paiement->annulation) }}" class="font-medium underline">Voir l'annulation {{ $paiement->annulation->numero() }}</a>
                </div>
            @endif

            @if (! $estPaiement && $paiement->origine)
                <div class="rounded-xl border border-beige-300 bg-beige-50 p-4 text-sm text-stone-700">
                    Cette ligne annule le paiement
                    <a href="{{ route('paiements.show', $paiement->origine) }}" class="font-medium text-bordeaux-800 underline">{{ $paiement->origine->numero() }}</a>.
                </div>
            @endif

            <section class="card">
                <h2 class="text-lg font-semibold">Détails</h2>
                <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-stone-500">Type</dt>
                        <dd class="mt-0.5"><span class="badge {{ $paiement->type->badge() }}">{{ $paiement->type->label() }}</span></dd>
                    </div>
                    <div>
                        <dt class="text-stone-500">Montant</dt>
                        <dd class="mt-0.5 font-serif text-xl font-semibold {{ $paiement->montant < 0 ? 'text-red-700' : 'text-bordeaux-900' }}">{{ $paiement->montantFormate() }}</dd>
                    </div>
                    <div><dt class="text-stone-500">Client</dt><dd class="mt-0.5 font-medium">{{ $paiement->client->nomComplet() }}</dd></div>
                    <div><dt class="text-stone-500">Mode de paiement</dt><dd class="mt-0.5 font-medium">{{ $paiement->mode->nom }}</dd></div>
                    <div><dt class="text-stone-500">Référence</dt><dd class="mt-0.5">{{ $paiement->reference ?? '—' }}</dd></div>
                    <div><dt class="text-stone-500">Enregistré par</dt><dd class="mt-0.5">{{ $paiement->user?->name ?? '—' }}</dd></div>
                    <div><dt class="text-stone-500">Total à payer</dt><dd class="mt-0.5">{{ \App\Support\Fcfa::format($paiement->montant_total) }}</dd></div>
                    <div><dt class="text-stone-500">Reste à payer après cette opération</dt><dd class="mt-0.5">{{ \App\Support\Fcfa::format($paiement->reste_apres) }}</dd></div>

                    @if ($paiement->rendezVous)
                        <div class="sm:col-span-2">
                            <dt class="text-stone-500">Rendez-vous</dt>
                            <dd class="mt-0.5">
                                @can('view', $paiement->rendezVous)
                                    <a href="{{ route('rendez-vous.show', $paiement->rendezVous) }}" class="font-medium text-bordeaux-800 hover:text-bordeaux-900">
                                        {{ $paiement->rendezVous->date->format('d/m/Y') }} · {{ $paiement->rendezVous->lignes->pluck('nom')->join(', ') }}
                                    </a>
                                @else
                                    {{ $paiement->rendezVous->date->format('d/m/Y') }} · {{ $paiement->rendezVous->lignes->pluck('nom')->join(', ') }}
                                @endcan
                            </dd>
                        </div>
                    @elseif ($paiement->libelle)
                        <div class="sm:col-span-2"><dt class="text-stone-500">Objet</dt><dd class="mt-0.5">{{ $paiement->libelle }}</dd></div>
                    @endif

                    @if ($paiement->motif)
                        <div class="sm:col-span-2"><dt class="text-stone-500">Motif</dt><dd class="mt-0.5">{{ $paiement->motif }}</dd></div>
                    @endif
                </dl>
            </section>
        </div>

        <div class="space-y-6">
            @if ($peutAnnuler)
                <section class="card space-y-4">
                    <h2 class="text-lg font-semibold">Corriger ou annuler</h2>
                    <p class="text-xs text-stone-500">Un paiement n'est jamais modifié : on l'annule par une ligne négative, et la trace est conservée.</p>

                    <details class="rounded-xl border border-beige-200 p-3">
                        <summary class="cursor-pointer text-sm font-medium text-bordeaux-800">Corriger ce paiement</summary>
                        <form method="POST" action="{{ route('paiements.corriger', $paiement) }}" class="mt-3 space-y-3"
                              onsubmit="return confirm('Annuler ce paiement et enregistrer le paiement corrigé ?')">
                            @csrf
                            <div>
                                <label for="corr_montant" class="label">Nouveau montant (FCFA)</label>
                                <input id="corr_montant" name="montant" type="number" min="1" step="1" inputmode="numeric" class="input" required value="{{ $paiement->montant }}">
                            </div>
                            <div>
                                <label for="corr_mode" class="label">Mode de paiement</label>
                                <select id="corr_mode" name="mode_paiement_id" class="input" required>
                                    @foreach ($modes as $mode)
                                        <option value="{{ $mode->id }}" @selected($paiement->mode_paiement_id === $mode->id)>{{ $mode->nom }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="corr_reference" class="label">Référence (facultatif)</label>
                                <input id="corr_reference" name="reference" class="input" maxlength="100" value="{{ $paiement->reference }}">
                            </div>
                            <div>
                                <label for="corr_motif" class="label">Motif de la correction</label>
                                <input id="corr_motif" name="motif" class="input" required minlength="5" maxlength="255">
                            </div>
                            <button type="submit" class="btn-primary w-full">Enregistrer la correction</button>
                        </form>
                    </details>

                    <details class="rounded-xl border border-beige-200 p-3">
                        <summary class="cursor-pointer text-sm font-medium text-red-700">Annuler ce paiement</summary>
                        <form method="POST" action="{{ route('paiements.annuler', $paiement) }}" class="mt-3 space-y-3"
                              onsubmit="return confirm('Annuler définitivement ce paiement ?')">
                            @csrf
                            <div>
                                <label for="annul_motif" class="label">Motif de l'annulation</label>
                                <input id="annul_motif" name="motif" class="input" required minlength="5" maxlength="255">
                            </div>
                            <button type="submit" class="btn-danger w-full">Confirmer l'annulation</button>
                        </form>
                    </details>
                </section>
            @endif
        </div>
    </div>
</x-layouts.app>