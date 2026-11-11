@php $editing = $rdv !== null; @endphp

<x-layouts.app :title="$editing ? 'Modifier le rendez-vous' : 'Nouveau rendez-vous'">
    <x-page-header :title="$editing ? 'Modifier le rendez-vous' : 'Nouveau rendez-vous'"
                   subtitle="Choisissez le client, les prestations, la personne, puis un créneau libre." />

    <form method="POST"
          action="{{ $editing ? route('rendez-vous.update', $rdv) : route('rendez-vous.store') }}"
          class="grid gap-6 lg:grid-cols-3"
          x-data="rdvForm(@js($etat))"
          @submit="loading = true">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <input type="hidden" name="client_id" :value="client ? client.id : ''">

        <div class="space-y-6 lg:col-span-2">
            {{-- Client --}}
            <section class="card">
                <h2 class="text-lg font-semibold">Client</h2>

                <template x-if="client">
                    <div class="mt-4 flex items-center justify-between gap-4 rounded-xl bg-beige-50 px-4 py-3">
                        <div>
                            <p class="font-medium" x-text="client.nom"></p>
                            <p class="text-sm text-stone-500" x-text="client.telephone"></p>
                        </div>
                        <button type="button" class="text-sm font-medium text-bordeaux-700 hover:text-bordeaux-900" @click="client = null">Changer</button>
                    </div>
                </template>

                <div x-show="! client" x-cloak class="mt-4">
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
                        @can('create', \App\Models\Client::class)
                            <a href="{{ route('clients.create') }}" class="font-medium text-bordeaux-700 hover:text-bordeaux-900">Créer une fiche client</a>
                        @endcan
                    </p>
                </div>
            </section>

            {{-- Prestations --}}
            <section class="card">
                <h2 class="text-lg font-semibold">Prestations</h2>

                <div class="mt-4 space-y-5">
                    @foreach ($categories as $categorie)
                        <div>
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-stone-500">{{ $categorie->nom }}</p>
                            <div class="grid gap-2 sm:grid-cols-2">
                                @foreach ($categorie->services as $service)
                                    <label class="flex items-center gap-3 rounded-lg border border-beige-200 px-3 py-2 text-sm">
                                        <input type="checkbox" name="services[]" value="{{ $service->id }}" x-model="services"
                                               class="h-4 w-4 rounded border-beige-300 text-bordeaux-700 focus:ring-bordeaux-500">
                                        <span class="flex-1">
                                            {{ $service->nom }}
                                            @unless ($service->is_active)
                                                <span class="badge ml-1 bg-stone-100 text-stone-500">Désactivée</span>
                                            @endunless
                                        </span>
                                        <span class="text-xs text-stone-500">{{ $service->dureeFormatee() }} · {{ $service->prixFormate() }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                @if ($forfaits->isNotEmpty())
                    <div class="mt-6">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-stone-500">Forfaits</p>
                        <div class="space-y-2">
                            @foreach ($forfaits as $forfait)
                                <label class="flex items-center gap-3 rounded-lg border border-beige-200 px-3 py-2 text-sm">
                                    <input type="checkbox" name="forfaits[]" value="{{ $forfait->id }}" x-model="forfaits"
                                           class="h-4 w-4 rounded border-beige-300 text-bordeaux-700 focus:ring-bordeaux-500">
                                    <span class="flex-1">
                                        {{ $forfait->nom }}
                                        <span class="block text-xs text-stone-500">{{ $forfait->services->pluck('nom')->join(', ') }}</span>
                                    </span>
                                    <span class="text-xs text-stone-500">{{ $forfait->dureeFormatee() }} · {{ $forfait->prixFormate() }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif

                <p class="mt-4 text-sm text-red-700" x-show="doublons.length > 0" x-cloak
                   x-text="'Prestation comptée deux fois : ' + doublons.join(', ')"></p>
            </section>

            {{-- Personne, date, heure --}}
            <section class="card space-y-5">
                <h2 class="text-lg font-semibold">Personne, date et heure</h2>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="employe_id" class="label">Personne</label>
                        <select id="employe_id" name="employe_id" class="input" required x-model="employe">
                            <option value="">Choisir…</option>
                            @foreach ($employes as $employeChoix)
                                <option value="{{ $employeChoix->id }}" :disabled="! possible({{ $employeChoix->id }})">{{ $employeChoix->nomComplet() }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1.5 text-xs text-red-700" x-show="employeInvalide" x-cloak>
                            Cette personne ne propose pas toutes les prestations choisies.
                        </p>
                        <p class="mt-1.5 text-xs text-stone-500">Les personnes grisées ne proposent pas toutes les prestations choisies.</p>
                    </div>

                    <div>
                        <label for="date" class="label">Date</label>
                        <input id="date" name="date" type="date" class="input" required x-model="date"
                               @unless ($peutPasse) min="{{ now()->toDateString() }}" @endunless>
                    </div>
                </div>

                <div>
                    <p class="label">Créneaux libres</p>
                    <p class="text-sm text-stone-500" x-show="chargement" x-cloak>Chargement…</p>
                    <p class="text-sm text-stone-500" x-show="! chargement && messageCreneaux !== ''" x-text="messageCreneaux" x-cloak></p>
                    <div class="flex flex-wrap gap-2" x-show="! chargement && creneaux.length > 0" x-cloak>
                        <template x-for="h in creneaux" :key="h">
                            <button type="button" class="rounded-lg border px-3 py-1.5 text-sm"
                                    :class="heure === h ? 'border-bordeaux-700 bg-bordeaux-700 text-white' : 'border-beige-300 bg-white text-stone-700 hover:bg-beige-50'"
                                    @click="heure = h" x-text="h"></button>
                        </template>
                    </div>
                </div>

                <div class="max-w-xs">
                    <label for="heure_debut" class="label">Heure de début</label>
                    <input id="heure_debut" name="heure_debut" type="time" step="300" class="input" required x-model="heure">
                    <p class="mt-1.5 text-xs text-stone-500">Cliquez sur un créneau ci-dessus ou saisissez l'heure.</p>
                </div>
            </section>

            {{-- Détails --}}
            <section class="card space-y-5">
                <h2 class="text-lg font-semibold">Détails</h2>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="zone" class="label">Zone du salon</label>
                        <select id="zone" name="zone" class="input" required x-model="zone">
                            <option value="mixte" :disabled="exigeFemmes">Zone mixte</option>
                            <option value="femmes">Espace femmes</option>
                        </select>
                        <p class="mt-1.5 text-xs text-stone-500" x-show="exigeFemmes" x-cloak>Une prestation choisie est réservée à l'espace femmes.</p>
                    </div>

                    @unless ($editing)
                        <div>
                            <label for="statut" class="label">Statut initial</label>
                            <select id="statut" name="statut" class="input">
                                <option value="confirme" @selected(old('statut', 'confirme') === 'confirme')>Confirmé</option>
                                <option value="en_attente" @selected(old('statut') === 'en_attente')>En attente de confirmation</option>
                            </select>
                        </div>
                    @endunless
                </div>

                <div>
                    <label for="commentaire" class="label">Commentaire (facultatif)</label>
                    <textarea id="commentaire" name="commentaire" rows="2" maxlength="1000" class="input">{{ old('commentaire', $rdv?->commentaire) }}</textarea>
                </div>
            </section>
        </div>

        <aside class="card h-fit space-y-4 lg:sticky lg:top-6">
            <h2 class="text-lg font-semibold">Résumé</h2>
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between"><dt class="text-stone-500">Durée</dt><dd class="font-medium" x-text="duree > 0 ? fmtDuree(duree) : '—'"></dd></div>
                <div class="flex justify-between"><dt class="text-stone-500">Heure de fin</dt><dd class="font-medium" x-text="fin !== '' ? fin : '—'"></dd></div>
                <div class="flex justify-between border-t border-beige-100 pt-3"><dt class="text-stone-500">Prix prévu</dt><dd class="font-semibold" x-text="fmt(prix)"></dd></div>
            </dl>
            <p class="text-xs text-stone-500">Le prix et la durée sont figés à l'enregistrement : une modification ultérieure du catalogue ne change pas ce rendez-vous.</p>

            <div class="flex flex-col gap-3 border-t border-beige-100 pt-4">
                <button type="submit" class="btn-primary" :disabled="loading || ! client">
                    <span x-show="! loading">Enregistrer</span>
                    <span x-show="loading" x-cloak>Enregistrement…</span>
                </button>
                <p class="text-xs text-stone-500" x-show="! client" x-cloak>Choisissez d'abord un client.</p>
                <a href="{{ $editing ? route('rendez-vous.show', $rdv) : route('rendez-vous.index') }}" class="btn-secondary">Annuler</a>
            </div>
        </aside>
    </form>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('rdvForm', (cfg) => ({
                loading: false,
                idRdv: cfg.idRdv,
                urls: cfg.urls,
                catalogue: cfg.catalogue,
                forfaitsInfo: cfg.forfaitsInfo,
                employes: cfg.employes,
                client: cfg.client,
                recherche: '',
                resultats: [],
                recherchant: false,
                employe: cfg.employe,
                date: cfg.date,
                heure: cfg.heure,
                zone: cfg.zone,
                services: cfg.services,
                forfaits: cfg.forfaits,
                creneaux: [],
                chargement: false,
                messageCreneaux: '',
                requete: 0,

                init() {
                    this.$watch('employe', () => this.chargerCreneaux());
                    this.$watch('date', () => this.chargerCreneaux());
                    this.$watch('services', () => this.chargerCreneaux());
                    this.$watch('forfaits', () => this.chargerCreneaux());
                    this.$watch('exigeFemmes', (valeur) => { if (valeur) { this.zone = 'femmes'; } });

                    if (this.exigeFemmes) { this.zone = 'femmes'; }
                    this.chargerCreneaux();
                },

                get demande() { return this.services.length + this.forfaits.length; },

                get exigeFemmes() {
                    return this.services.some((id) => this.catalogue[id] && this.catalogue[id].zone === 'femmes')
                        || this.forfaits.some((id) => this.forfaitsInfo[id] && this.forfaitsInfo[id].zone === 'femmes');
                },

                idsRequis() {
                    const ids = new Set(this.services.map(String));
                    this.forfaits.forEach((f) => {
                        const info = this.forfaitsInfo[f];
                        (info ? info.services : []).forEach((s) => ids.add(String(s)));
                    });
                    return Array.from(ids);
                },

                possible(employeId) {
                    const e = this.employes[employeId];
                    if (! e) { return false; }
                    const propose = e.services.map(String);
                    return this.idsRequis().every((id) => propose.includes(id));
                },

                get employeInvalide() {
                    return this.employe !== '' && ! this.possible(this.employe);
                },

                get doublons() {
                    const compte = {};
                    this.services.forEach((id) => { compte[id] = (compte[id] || 0) + 1; });
                    this.forfaits.forEach((f) => {
                        const info = this.forfaitsInfo[f];
                        (info ? info.services : []).forEach((s) => { compte[s] = (compte[s] || 0) + 1; });
                    });
                    return Object.keys(compte)
                        .filter((id) => compte[id] > 1)
                        .map((id) => (this.catalogue[id] ? this.catalogue[id].nom : 'prestation ' + id));
                },

                get duree() {
                    let total = 0;
                    this.services.forEach((id) => { total += this.catalogue[id] ? this.catalogue[id].duree : 0; });
                    this.forfaits.forEach((id) => { total += this.forfaitsInfo[id] ? this.forfaitsInfo[id].duree : 0; });
                    return total;
                },

                get prix() {
                    let total = 0;
                    this.services.forEach((id) => { total += this.catalogue[id] ? this.catalogue[id].prix : 0; });
                    this.forfaits.forEach((id) => { total += this.forfaitsInfo[id] ? this.forfaitsInfo[id].prix : 0; });
                    return total;
                },

                get fin() {
                    if (! this.heure || this.duree === 0) { return ''; }
                    const morceaux = this.heure.split(':').map(Number);
                    const total = morceaux[0] * 60 + morceaux[1] + this.duree;
                    return String(Math.floor(total / 60)).padStart(2, '0') + ':' + String(total % 60).padStart(2, '0');
                },

                fmt(n) { return new Intl.NumberFormat('fr-FR').format(n) + ' FCFA'; },

                fmtDuree(m) {
                    const h = Math.floor(m / 60);
                    const r = m % 60;
                    if (h === 0) { return m + ' min'; }
                    return r === 0 ? h + ' h' : h + ' h ' + String(r).padStart(2, '0');
                },

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

                async chargerCreneaux() {
                    this.creneaux = [];
                    this.messageCreneaux = '';
                    const numero = ++this.requete;

                    if (this.employe === '' || this.date === '' || this.demande === 0) {
                        this.chargement = false;
                        this.messageCreneaux = 'Choisissez des prestations, une personne et une date pour voir les créneaux libres.';
                        return;
                    }

                    this.chargement = true;

                    const params = new URLSearchParams({ employe: this.employe, date: this.date });
                    this.services.forEach((id) => params.append('services[]', id));
                    this.forfaits.forEach((id) => params.append('forfaits[]', id));
                    if (this.idRdv) { params.set('ignore', this.idRdv); }

                    try {
                        const reponse = await fetch(this.urls.creneaux + '?' + params.toString(), {
                            headers: { 'Accept': 'application/json' },
                            credentials: 'same-origin',
                        });

                        if (numero !== this.requete) { return; }
                        if (! reponse.ok) { throw new Error('Réponse invalide'); }

                        const donnees = await reponse.json();
                        this.creneaux = donnees.creneaux || [];

                        if (this.creneaux.length === 0) {
                            this.messageCreneaux = 'Aucun créneau libre ce jour-là pour cette personne.';
                        }
                    } catch (e) {
                        if (numero === this.requete) {
                            this.messageCreneaux = "Impossible de charger les créneaux. Vous pouvez saisir l'heure à la main.";
                        }
                    } finally {
                        if (numero === this.requete) { this.chargement = false; }
                    }
                },
            }));
        });
    </script>
</x-layouts.app>