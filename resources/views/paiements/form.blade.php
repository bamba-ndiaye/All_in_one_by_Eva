@php $enRdv = $rdv !== null; @endphp

<x-layouts.app :title="$enRdv ? 'Encaisser un rendez-vous' : 'Vente sans rendez-vous'">
    <x-page-header :title="$enRdv ? 'Encaisser un rendez-vous' : 'Vente sans rendez-vous'"
                   subtitle="Montants en FCFA, sans décimales." />

    <form method="POST" action="{{ route('paiements.store') }}"
          class="grid gap-6 lg:grid-cols-3"
          x-data="caisseForm(@js($etat))"
          @submit="loading = true">
        @csrf

        @if ($enRdv)
            <input type="hidden" name="rendez_vous_id" value="{{ $rdv->id }}">
        @else
            <input type="hidden" name="client_id" :value="client ? client.id : ''">
        @endif

        <div class="space-y-6 lg:col-span-2">
            @if ($enRdv)
                <section class="card">
                    <h2 class="text-lg font-semibold">Rendez-vous</h2>
                    <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                        <div><dt class="text-stone-500">Client</dt><dd class="mt-0.5 font-medium">{{ $rdv->client->nomComplet() }}</dd></div>
                        <div><dt class="text-stone-500">Date</dt><dd class="mt-0.5 font-medium">{{ $rdv->date->format('d/m/Y') }} · {{ $rdv->debut() }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-stone-500">Prestations</dt><dd class="mt-0.5">{{ $rdv->lignes->pluck('nom')->join(', ') }}</dd></div>
                        <div><dt class="text-stone-500">Total à payer</dt><dd class="mt-0.5 font-medium">{{ $rdv->prixFormate() }}</dd></div>
                        <div><dt class="text-stone-500">Déjà payé</dt><dd class="mt-0.5 font-medium">{{ \App\Support\Fcfa::format($rdv->montantPaye()) }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-stone-500">Reste à payer</dt><dd class="mt-0.5 font-serif text-xl font-semibold text-bordeaux-900">{{ \App\Support\Fcfa::format($rdv->resteAPayer()) }}</dd></div>
                    </dl>
                </section>
            @else
                <section class="card space-y-5">
                    <h2 class="text-lg font-semibold">Client et objet</h2>

                    <template x-if="client">
                        <div class="flex items-center justify-between gap-4 rounded-xl bg-beige-50 px-4 py-3">
                            <div>
                                <p class="font-medium" x-text="client.nom"></p>
                                <p class="text-sm text-stone-500" x-text="client.telephone"></p>
                            </div>
                            <button type="button" class="text-sm font-medium text-bordeaux-700 hover:text-bordeaux-900" @click="client = null">Changer</button>
                        </div>
                    </template>

                    <div x-show="! client" x-cloak>
                        <label for="recherche" class="label">Rechercher un client (nom ou téléphone)</label>
                        <input id="recherche" type="search" class="input" autocomplete="off" placeholder="Au moins 2 caractères"
                               x-model="recherche" @input.debounce.300ms="chercher()">

                        <ul class="mt-2 divide-y divide-beige-100 rounded-xl border border-beige-200" x-show="resultats.length > 0" x-cloak>
                            <template x-for="c in resultats" :key="c.id">
                                <li>
                                    <button type="button" class="flex w-full items-center justify-between gap-4 px-4 py-2 text-left text-sm hover:bg-beige-50" @click="choisirClient(c)">
                                        <span x-text="c.nom"></span>
                                        <span class="text-stone-500" x-text="c.telephone"></span>
                                    </button>
                                </li>
                            </template>
                        </ul>

                        <p class="mt-2 text-sm text-stone-500" x-show="recherche.trim().length >= 2 && ! recherchant && resultats.length === 0" x-cloak>
                            Aucun client trouvé.
                        </p>
                    </div>

                    <div>
                        <label for="libelle" class="label">Ce qui est payé</label>
                        <input id="libelle" name="libelle" class="input" maxlength="150" required
                               placeholder="Par exemple : vente de shampoing" value="{{ old('libelle') }}">
                    </div>
                </section>
            @endif

            <section class="card space-y-5">
                <h2 class="text-lg font-semibold">Paiement</h2>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="montant" class="label">Montant (FCFA)</label>
                        <input id="montant" name="montant" type="number" min="1" step="1" inputmode="numeric" class="input" required
                               @if ($enRdv) max="{{ $rdv->resteAPayer() }}" @endif
                               x-model="montant">
                        @if ($enRdv)
                            <button type="button" class="mt-2 text-sm font-medium text-bordeaux-700 hover:text-bordeaux-900" @click="solder()">Solder (payer tout le reste)</button>
                        @endif
                        <p class="mt-1.5 text-xs text-red-700" x-show="depasse" x-cloak>Le montant dépasse le reste à payer.</p>
                    </div>

                    <div>
                        <label for="mode_paiement_id" class="label">Mode de paiement</label>
                        <select id="mode_paiement_id" name="mode_paiement_id" class="input" required>
                            @foreach ($modes as $mode)
                                <option value="{{ $mode->id }}" @selected((int) old('mode_paiement_id', $modes->first()?->id) === $mode->id)>{{ $mode->nom }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="reference" class="label">Référence (facultatif)</label>
                        <input id="reference" name="reference" class="input" maxlength="100"
                               placeholder="Numéro de transaction Wave, Orange Money, carte…" value="{{ old('reference') }}">
                    </div>

                    @if ($peutChoisirDate)
                        <div>
                            <label for="date" class="label">Date du paiement</label>
                            <input id="date" name="date" type="date" class="input" max="{{ now()->toDateString() }}"
                                   value="{{ old('date', now()->toDateString()) }}">
                        </div>
                    @endif
                </div>
            </section>
        </div>

        <aside class="card h-fit space-y-4 lg:sticky lg:top-6">
            <h2 class="text-lg font-semibold">Résumé</h2>
            <dl class="space-y-3 text-sm">
                <template x-if="enRdv">
                    <div class="space-y-3">
                        <div class="flex justify-between"><dt class="text-stone-500">Total à payer</dt><dd class="font-medium" x-text="fmt(du)"></dd></div>
                        <div class="flex justify-between"><dt class="text-stone-500">Déjà payé</dt><dd class="font-medium" x-text="fmt(paye)"></dd></div>
                        <div class="flex justify-between"><dt class="text-stone-500">Ce paiement</dt><dd class="font-medium" x-text="fmt(valeur)"></dd></div>
                        <div class="flex justify-between border-t border-beige-100 pt-3">
                            <dt class="text-stone-500">Reste après ce paiement</dt>
                            <dd class="font-semibold" :class="depasse ? 'text-red-700' : 'text-bordeaux-900'" x-text="fmt(resteApres)"></dd>
                        </div>
                    </div>
                </template>
                <template x-if="! enRdv">
                    <div class="flex justify-between"><dt class="text-stone-500">Montant encaissé</dt><dd class="font-semibold" x-text="fmt(valeur)"></dd></div>
                </template>
            </dl>

            <div class="flex flex-col gap-3 border-t border-beige-100 pt-4">
                <button type="submit" class="btn-primary" :disabled="loading || valeur < 1 || depasse || (! enRdv && ! client)">
                    <span x-show="! loading">Enregistrer le paiement</span>
                    <span x-show="loading" x-cloak>Enregistrement…</span>
                </button>
                <p class="text-xs text-stone-500" x-show="! enRdv && ! client" x-cloak>Choisissez d'abord un client.</p>
                <a href="{{ $enRdv ? route('rendez-vous.show', $rdv) : route('paiements.index') }}" class="btn-secondary">Annuler</a>
            </div>
        </aside>
    </form>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('caisseForm', (cfg) => ({
                loading: false,
                urls: cfg.urls,
                enRdv: cfg.enRdv,
                du: cfg.du,
                paye: cfg.paye,
                reste: cfg.reste,
                montant: cfg.montant,
                client: cfg.client,
                recherche: '',
                resultats: [],
                recherchant: false,

                get valeur() {
                    const n = parseInt(this.montant, 10);
                    return isNaN(n) ? 0 : n;
                },

                get resteApres() { return this.reste - this.valeur; },

                get depasse() { return this.enRdv && this.valeur > this.reste; },

                fmt(n) { return new Intl.NumberFormat('fr-FR').format(n) + ' FCFA'; },

                solder() { this.montant = String(this.reste); },

                async chercher() {
                    const q = this.recherche.trim();
                    if (q.length < 2) { this.resultats = []; return; }

                    this.recherchant = true;
                    try {
                        const reponse = await fetch(this.urls.clients + '?q=' + encodeURIComponent(q), {
                            headers: { 'Accept': 'application/json' },
                            credentials: 'same-origin',
                        });
                        const donnees = await reponse.json();
                        this.resultats = donnees.clients || [];
                    } catch (e) {
                        this.resultats = [];
                    }
                    this.recherchant = false;
                },

                choisirClient(c) {
                    this.client = c;
                    this.resultats = [];
                    this.recherche = '';
                },
            }));
        });
    </script>
</x-layouts.app>