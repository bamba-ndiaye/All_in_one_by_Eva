<x-layouts.app title="Paramètres">
    <x-page-header title="Paramètres" subtitle="Les réglages du salon. Ils s'appliquent dès l'enregistrement." />

    <form method="POST" action="{{ route('parametres.update') }}" class="max-w-3xl space-y-6"
          x-data="{ loading: false, message: @js(old('anniversaire_message', $valeurs['anniversaire_message'])), point: @js((int) old('fidelite_fcfa_par_point', $valeurs['fidelite_fcfa_par_point'])) }"
          @submit="loading = true">
        @csrf
        @method('PUT')

        <section class="card space-y-4">
            <div>
                <h2 class="text-lg font-semibold">Le salon</h2>
                <p class="mt-1 text-sm text-stone-500">Affiché en haut des reçus et dans le titre des pages.</p>
            </div>
            <div>
                <label for="salon_nom" class="label">Nom du salon</label>
                <input id="salon_nom" name="salon_nom" class="input" required maxlength="80" value="{{ old('salon_nom', $valeurs['salon_nom']) }}">
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="sm:col-span-2">
                    <label for="salon_adresse" class="label">Adresse (facultatif)</label>
                    <input id="salon_adresse" name="salon_adresse" class="input" maxlength="150" placeholder="Sacré-Cœur 3, Dakar"
                           value="{{ old('salon_adresse', $valeurs['salon_adresse']) }}">
                </div>
                <div>
                    <label for="salon_telephone" class="label">Téléphone (facultatif)</label>
                    <input id="salon_telephone" name="salon_telephone" type="tel" class="input" maxlength="30" placeholder="+221 77 000 00 00"
                           value="{{ old('salon_telephone', $valeurs['salon_telephone']) }}">
                </div>
            </div>
        </section>

        <section class="card space-y-4">
            <h2 class="text-lg font-semibold">Rendez-vous</h2>
            <div class="flex flex-wrap items-center gap-3 text-sm">
                <label for="rdv_pause_minutes">Pause minimale entre deux rendez-vous d'une même personne :</label>
                <input id="rdv_pause_minutes" name="rdv_pause_minutes" type="number" min="0" max="120" step="5" required class="input w-24"
                       value="{{ old('rdv_pause_minutes', $valeurs['rdv_pause_minutes']) }}">
                <span>minutes</span>
            </div>
            <p class="text-xs text-stone-500">0 = les rendez-vous peuvent s'enchaîner sans pause.</p>
        </section>

        <section class="card space-y-4">
            <h2 class="text-lg font-semibold">Fidélité</h2>
            <div class="flex flex-wrap items-center gap-3 text-sm">
                <span>1 point pour chaque tranche de</span>
                <input id="fidelite_fcfa_par_point" name="fidelite_fcfa_par_point" type="number" min="0" step="100" required class="input w-32"
                       x-model.number="point">
                <span>FCFA encaissés</span>
            </div>
            <p class="text-xs text-stone-500" x-show="point > 0">
                Exemple : un paiement de 25 000 FCFA rapporte <strong x-text="Math.floor(25000 / point)"></strong> points.
                Les points déjà gagnés ne changent pas.
            </p>
            <p class="text-xs font-medium text-amber-700" x-show="point <= 0" x-cloak>
                0 = programme de fidélité en pause : les nouveaux paiements ne rapportent plus de points.
            </p>
        </section>

        <section class="card space-y-4">
            <h2 class="text-lg font-semibold">Message d'anniversaire</h2>
            <div>
                <label for="anniversaire_message" class="label">Message envoyé sur WhatsApp</label>
                <textarea id="anniversaire_message" name="anniversaire_message" rows="4" required minlength="10" maxlength="700" class="input"
                          x-model="message"></textarea>
                <p class="mt-1 text-xs text-stone-500">
                    <strong>{prenom}</strong> et <strong>{age}</strong> sont remplacés automatiquement.
                </p>
            </div>
            <div class="rounded-xl border border-beige-200 bg-beige-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-stone-500">Aperçu pour Awa, 30 ans</p>
                <p class="mt-2 whitespace-pre-line text-sm text-stone-800" x-text="message.replaceAll('{prenom}', 'Awa').replaceAll('{age}', '30')"></p>
            </div>
        </section>

        <div class="flex gap-3">
            <button type="submit" class="btn-primary" :disabled="loading">
                <span x-show="! loading">Enregistrer les paramètres</span>
                <span x-show="loading" x-cloak>Enregistrement…</span>
            </button>
        </div>
    </form>
</x-layouts.app>
