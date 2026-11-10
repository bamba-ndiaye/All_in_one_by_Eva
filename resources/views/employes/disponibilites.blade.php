<x-layouts.app title="Horaires">
    <x-page-header :title="'Horaires de '.$employe->nomComplet()"
                   subtitle="Ajoutez une plage par période de travail. Pour une pause déjeuner, créez deux plages le même jour." />

    <form method="POST" action="{{ route('employes.disponibilites.update', $employe) }}"
          class="card max-w-3xl space-y-6"
          x-data="{
              loading: false,
              rows: @js($rows),
              add(jour = 1) { this.rows.push({ jour: jour, debut: '09:00', fin: '18:00' }) },
              addWeek() { [1, 2, 3, 4, 5].forEach(j => this.add(j)) },
              remove(i) { this.rows.splice(i, 1) }
          }"
          @submit="loading = true">
        @csrf
        @method('PUT')

        <div class="space-y-3">
            <template x-for="(row, i) in rows" :key="i">
                <div class="flex flex-wrap items-end gap-3 rounded-xl border border-beige-200 p-3">
                    <div class="min-w-36">
                        <label class="label">Jour</label>
                        <select class="input" :name="'plages[' + i + '][jour]'" x-model="row.jour">
                            @foreach (\App\Enums\JourSemaine::cases() as $jour)
                                <option value="{{ $jour->value }}">{{ $jour->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">De</label>
                        <input type="time" step="300" class="input" required :name="'plages[' + i + '][debut]'" x-model="row.debut">
                    </div>
                    <div>
                        <label class="label">À</label>
                        <input type="time" step="300" class="input" required :name="'plages[' + i + '][fin]'" x-model="row.fin">
                    </div>
                    <button type="button" class="btn-secondary" @click="remove(i)">Retirer</button>
                </div>
            </template>

            <p x-show="rows.length === 0" x-cloak class="rounded-xl bg-beige-50 p-4 text-sm text-stone-600">
                Aucune plage : cette personne ne pourra recevoir aucun rendez-vous.
            </p>
        </div>

        <div class="flex flex-wrap gap-3">
            <button type="button" class="btn-secondary" @click="add()">Ajouter une plage</button>
            <button type="button" class="btn-secondary" @click="addWeek()">Ajouter lundi à vendredi (09:00–18:00)</button>
        </div>

        <div class="flex flex-wrap gap-3 border-t border-beige-100 pt-6">
            <button type="submit" class="btn-primary" :disabled="loading">
                <span x-show="! loading">Enregistrer les horaires</span>
                <span x-show="loading" x-cloak>Enregistrement…</span>
            </button>
            <a href="{{ route('employes.show', $employe) }}" class="btn-secondary">Annuler</a>
        </div>
    </form>
</x-layouts.app>