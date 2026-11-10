<?php

namespace App\Services;

use App\Enums\JourSemaine;
use App\Enums\ZoneSalon;
use App\Models\Client;
use App\Models\Employe;
use App\Models\Forfait;
use App\Models\RendezVous;
use App\Models\Service;
use App\Models\User;
use App\Support\Heure;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class AppointmentScheduler
{
    /** Pause minimale entre deux rendez-vous d'une même personne (minutes). */
    public function pause(): int
    {
        return max(0, (int) config('salon.rendez_vous.pause_minutes', 0));
    }

    /**
     * Déplie prestations et forfaits en lignes, avec prix et durées figés.
     * Le prix d'un forfait est réparti proportionnellement sur ses prestations.
     *
     * @param  list<int>  $serviceIds
     * @param  list<int>  $forfaitIds
     * @return array{
     *     lignes: list<array{service_id: int, forfait_id: ?int, nom: string, prix_applique: int, duree_appliquee: int}>,
     *     duree: int,
     *     prix: int,
     *     services: Collection<int, Service>,
     *     forfaits: Collection<int, Forfait>,
     *     doublons: list<string>
     * }
     */
    public function composer(array $serviceIds, array $forfaitIds): array
    {
        $serviceIds = array_values(array_unique(array_map('intval', $serviceIds)));
        $forfaitIds = array_values(array_unique(array_map('intval', $forfaitIds)));

        $services = Service::whereIn('id', $serviceIds)->get()->keyBy('id');
        $forfaits = Forfait::with('services')->whereIn('id', $forfaitIds)->get()->keyBy('id');

        $lignes = [];
        $concernes = collect();
        $origines = [];
        $doublons = [];

        $ajouter = function (Service $service, ?int $forfaitId, int $prix, string $origine) use (&$lignes, &$concernes, &$origines, &$doublons) {
            if (isset($origines[$service->id])) {
                $doublons[] = "« {$service->nom} » est déjà prévue ({$origines[$service->id]}).";

                return;
            }

            $origines[$service->id] = $origine;
            $concernes->put($service->id, $service);
            $lignes[] = [
                'service_id' => $service->id,
                'forfait_id' => $forfaitId,
                'nom' => $service->nom,
                'prix_applique' => $prix,
                'duree_appliquee' => $service->duree_minutes,
            ];
        };

        foreach ($serviceIds as $id) {
            if ($service = $services->get($id)) {
                $ajouter($service, null, $service->prix, 'prestation seule');
            }
        }

        foreach ($forfaitIds as $id) {
            if (! $forfait = $forfaits->get($id)) {
                continue;
            }

            $membres = $forfait->services->sortBy('id')->values();
            $parts = $this->repartir($forfait->prix, $membres);

            foreach ($membres as $service) {
                $ajouter($service, $forfait->id, $parts[$service->id], "forfait « {$forfait->nom} »");
            }
        }

        return [
            'lignes' => $lignes,
            'duree' => (int) array_sum(array_column($lignes, 'duree_appliquee')),
            'prix' => (int) array_sum(array_column($lignes, 'prix_applique')),
            'services' => $concernes,
            'forfaits' => $forfaits,
            'doublons' => array_values(array_unique($doublons)),
        ];
    }

    /**
     * Vérifie toutes les règles d'un rendez-vous et renvoie les erreurs par champ.
     * Un tableau vide signifie que la réservation est possible.
     *
     * @param  array<string, mixed>  $in  client_id, employe_id, date (Y-m-d), heure_debut (H:i), zone, services[], forfaits[]
     * @return array<string, string>
     */
    public function erreurs(array $in, User $user, ?RendezVous $rdv = null): array
    {
        $rdv?->loadMissing('lignes');

        $client = Client::find($in['client_id'] ?? 0);
        $employe = Employe::with(['disponibilites', 'services'])->find($in['employe_id'] ?? 0);

        $errors = [];

        if (! $client) {
            $errors['client_id'] = 'Client introuvable.';
        }

        if (! $employe) {
            $errors['employe_id'] = 'Employé introuvable.';
        }

        if ($errors !== []) {
            return $errors;
        }

        if (! $client->is_active && ! ($rdv && $rdv->client_id === $client->id)) {
            $errors['client_id'] = 'Cette fiche client est archivée : réactivez-la avant de prendre un rendez-vous.';
        }

        if (! $employe->is_active && ! ($rdv && $rdv->employe_id === $employe->id)) {
            $errors['employe_id'] = "{$employe->nomComplet()} est désactivée et ne peut plus recevoir de rendez-vous.";
        }

        // --- Prestations -------------------------------------------------------------
        $compo = $this->composer($in['services'] ?? [], $in['forfaits'] ?? []);

        if ($compo['lignes'] === []) {
            $errors['services'] = 'Choisissez au moins une prestation ou un forfait.';

            return $errors;
        }

        if ($compo['doublons'] !== []) {
            $errors['services'] = implode(' ', $compo['doublons']);
        }

        $anciensServices = $rdv ? $rdv->lignes->pluck('service_id')->map(fn ($id) => (int) $id)->all() : [];
        $anciensForfaits = $rdv ? $rdv->lignes->pluck('forfait_id')->filter()->map(fn ($id) => (int) $id)->all() : [];

        $inactifs = $compo['services']
            ->filter(fn (Service $s) => ! $s->is_active && ! in_array($s->id, $anciensServices, true))
            ->pluck('nom');

        if ($inactifs->isNotEmpty()) {
            $errors['services'] = 'Prestation désactivée : '.$inactifs->join(', ').'.';
        }

        $forfaitsInactifs = $compo['forfaits']
            ->filter(fn (Forfait $f) => ! $f->is_active && ! in_array($f->id, $anciensForfaits, true))
            ->pluck('nom');

        if ($forfaitsInactifs->isNotEmpty()) {
            $errors['forfaits'] = 'Forfait désactivé : '.$forfaitsInactifs->join(', ').'.';
        }

        $proposees = $employe->services->pluck('id')->all();
        $manquantes = $compo['services']->filter(fn (Service $s) => ! in_array($s->id, $proposees, true))->pluck('nom');

        if ($manquantes->isNotEmpty()) {
            $errors['employe_id'] = "{$employe->nomComplet()} ne propose pas : ".$manquantes->join(', ').'.';
        }

        $zone = ZoneSalon::tryFrom((string) ($in['zone'] ?? '')) ?? ZoneSalon::Mixte;

        if ($compo['services']->contains(fn (Service $s) => $s->zone === ZoneSalon::Femmes) && $zone !== ZoneSalon::Femmes) {
            $errors['zone'] = "Une prestation réservée aux femmes doit se dérouler dans l'espace femmes.";
        }

        // --- Date et heure -----------------------------------------------------------
         $date = $this->parseDate((string) ($in['date'] ?? ''));

        if (! $date) {
            $errors['date'] = 'Date invalide.';

            return $errors;
        }

        if (! preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', (string) ($in['heure_debut'] ?? ''))) {
            $errors['heure_debut'] = 'Heure invalide.';

            return $errors;
        }

        $debut = Heure::toMinutes($in['heure_debut']);
        $fin = $debut + $compo['duree'];

        // Rien à revérifier si le rendez-vous reste exactement où il est.
        $inchange = $rdv
            && $rdv->date->toDateString() === $date->toDateString()
            && Heure::toMinutes($rdv->heure_debut) === $debut
            && Heure::toMinutes($rdv->heure_fin) === $fin
            && $rdv->employe_id === $employe->id;

        if ($inchange) {
            return $errors;
        }

        if ($fin > 1440) {
            $errors['heure_debut'] = 'Le rendez-vous doit se terminer avant minuit.';

            return $errors;
        }

        $jour = $date->dayOfWeekIso;
        $plages = $employe->disponibilites->filter(fn ($d) => $d->jour_semaine->value === $jour);

        if ($plages->isEmpty()) {
            $errors['date'] = "{$employe->nomComplet()} ne travaille pas le ".mb_strtolower(JourSemaine::from($jour)->label()).'.';
        } elseif (! $plages->contains(
            fn ($d) => Heure::toMinutes($d->heure_debut) <= $debut && Heure::toMinutes($d->heure_fin) >= $fin,
        )) {
            $errors['heure_debut'] = 'Ce créneau ('.Heure::format($debut).' – '.Heure::format($fin)
                .') est en dehors des horaires de travail : '
                .$plages->map(fn ($d) => $d->debut().' – '.$d->fin())->join(', ').'.';
        }

        if ($date->copy()->addMinutes($debut)->lt(now()) && ! $user->hasPermission('appointments.create_past')) {
            $errors['date'] ??= 'Ce créneau est déjà passé : seule une personne autorisée peut enregistrer un rendez-vous dans le passé.';
        }

        $pause = $this->pause();

        $conflit = $this->rendezVousBloquants($employe, $date, $rdv)
            ->first(fn (RendezVous $autre) => $this->seChevauchent($debut, $fin, $autre, $pause));

        if ($conflit) {
            $errors['heure_debut'] = "{$employe->nomComplet()} a déjà un rendez-vous de {$conflit->debut()} à {$conflit->fin()}"
                .($pause > 0 ? " (pause de {$pause} min incluse)" : '').'.';
        }

        return $errors;
    }

    /**
     * Heures de début possibles ce jour-là pour une durée donnée.
     *
     * @return list<string> Heures au format « 09:30 »
     */
    public function creneauxLibres(
        Employe $employe,
        CarbonInterface $date,
        int $duree,
        ?RendezVous $ignore = null,
        int $pas = 15,
        bool $inclurePasse = false,
    ): array {
        $employe->loadMissing('disponibilites');

        $jour = $date->dayOfWeekIso;
        $pause = $this->pause();
        $pas = max(1, $pas);
        $maintenant = now();
        $occupes = $this->rendezVousBloquants($employe, $date, $ignore);
        $libres = [];

        foreach ($employe->disponibilites->filter(fn ($d) => $d->jour_semaine->value === $jour) as $plage) {
            $debutPlage = Heure::toMinutes($plage->heure_debut);
            $finPlage = Heure::toMinutes($plage->heure_fin);

            for ($debut = $debutPlage; $debut + $duree <= $finPlage; $debut += $pas) {
                $fin = $debut + $duree;

                if (! $inclurePasse && $date->copy()->startOfDay()->addMinutes($debut)->lt($maintenant)) {
                    continue;
                }

                if ($occupes->contains(fn (RendezVous $autre) => $this->seChevauchent($debut, $fin, $autre, $pause))) {
                    continue;
                }

                $libres[] = Heure::format($debut);
            }
        }

        return $libres;
    }

    /** @return Collection<int, RendezVous> */
    private function rendezVousBloquants(Employe $employe, CarbonInterface $date, ?RendezVous $ignore): Collection
    {
        return RendezVous::query()
            ->where('employe_id', $employe->id)
            ->whereDate('date', $date->toDateString())
            ->bloquants()
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->orderBy('heure_debut')
            ->get();
    }

        /** Date « AAAA-MM-JJ » réelle, ou null. « 2026-02-31 » est refusée au lieu d'être décalée en mars. */
    private function parseDate(string $value): ?Carbon
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('!Y-m-d', $value, config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }

        return ($date && $date->format('Y-m-d') === $value) ? $date : null;
    }

    /** Deux rendez-vous se gênent s'ils se chevauchent, pause comprise. */
    private function seChevauchent(int $debut, int $fin, RendezVous $autre, int $pause): bool
    {
        return $debut < Heure::toMinutes($autre->heure_fin) + $pause
            && $fin + $pause > Heure::toMinutes($autre->heure_debut);
    }

    /**
     * @param  Collection<int, Service>  $membres
     * @return array<int, int> identifiant du service => part du prix du forfait
     */
    private function repartir(int $prixForfait, Collection $membres): array
    {
        $parts = [];
        $reste = $prixForfait;
        $total = (int) $membres->sum('prix');
        $dernier = $membres->count() - 1;

        foreach ($membres->values() as $index => $service) {
            if ($index === $dernier) {
                $parts[$service->id] = $reste;
                break;
            }

            $part = $total > 0
                ? intdiv($prixForfait * $service->prix, $total)
                : intdiv($prixForfait, $membres->count());

            $parts[$service->id] = $part;
            $reste -= $part;
        }

        return $parts;
    }
}