{{-- Recherche rapide de clientes, présente en haut de chaque page. Raccourci : touche « / ». --}}
<form method="GET" action="{{ route('clients.index') }}" role="search" class="relative w-full"
      x-data="{
          q: '',
          resultats: [],
          ouvert: false,
          actif: -1,
          chargement: false,
          async chercher() {
              const q = this.q.trim();
              if (q.length < 2) { this.resultats = []; this.ouvert = false; return; }
              this.chargement = true;
              try {
                  const r = await fetch(@js(route('recherche.clients')) + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } });
                  this.resultats = r.ok ? (await r.json()).clients : [];
              } catch (e) { this.resultats = []; }
              this.chargement = false;
              this.actif = -1;
              this.ouvert = true;
          },
          deplacer(pas) {
              if (! this.resultats.length) return;
              this.actif = (this.actif + pas + this.resultats.length) % this.resultats.length;
          },
          valider(e) {
              if (this.actif >= 0 && this.resultats[this.actif]) { e.preventDefault(); window.location = this.resultats[this.actif].url; }
          },
      }"
      @keydown.window.slash="if (! ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) { $event.preventDefault(); $refs.champ.focus(); }"
      @click.outside="ouvert = false">
    <label for="recherche-globale" class="sr-only">Rechercher une cliente</label>
    <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-stone-400" />
    <input id="recherche-globale" x-ref="champ" name="q" type="search" autocomplete="off"
           class="input !py-2 pl-9 pr-10" placeholder="Rechercher une cliente (nom ou numéro)…"
           x-model="q" @input.debounce.250ms="chercher()" @focus="if (resultats.length) ouvert = true"
           @keydown.arrow-down.prevent="deplacer(1)" @keydown.arrow-up.prevent="deplacer(-1)"
           @keydown.enter="valider($event)" @keydown.escape="ouvert = false; $refs.champ.blur()"
           role="combobox" aria-autocomplete="list" aria-controls="recherche-resultats" :aria-expanded="ouvert.toString()">
    <kbd class="pointer-events-none absolute right-3 top-1/2 hidden -translate-y-1/2 rounded border border-beige-200 px-1.5 text-[11px] text-stone-400 sm:block">/</kbd>

    <div x-show="ouvert" x-cloak id="recherche-resultats" role="listbox"
         class="absolute left-0 right-0 top-full z-30 mt-2 overflow-hidden rounded-xl border border-beige-200 bg-white shadow-lg">
        <template x-for="(c, i) in resultats" :key="c.url">
            <a :href="c.url" role="option" :aria-selected="(i === actif).toString()"
               class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm"
               :class="i === actif ? 'bg-beige-100' : 'hover:bg-beige-50'" @mouseenter="actif = i">
                <span class="min-w-0">
                    <span class="block truncate font-medium text-stone-800" x-text="c.nom"></span>
                    <span class="block text-xs text-stone-500" x-text="c.telephone"></span>
                </span>
                <span x-show="c.archive" class="badge shrink-0 bg-stone-100 text-stone-500">Archivée</span>
            </a>
        </template>
        <p x-show="! chargement && resultats.length === 0" class="px-4 py-3 text-sm text-stone-500">Aucune cliente trouvée.</p>
        <button type="submit" class="block w-full border-t border-beige-100 px-4 py-2.5 text-left text-sm font-medium text-bordeaux-700 hover:bg-beige-50">
            Voir tous les résultats pour « <span x-text="q"></span> »
        </button>
    </div>
</form>
