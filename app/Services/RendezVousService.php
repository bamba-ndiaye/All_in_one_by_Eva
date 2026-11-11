<?php

namespace App\Services;

use App\Enums\StatutRendezVous;
use App\Models\Employe;
use App\Models\RendezVous;
use App\Models\User;
use App\Support\Heure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RendezVousService
{
    public function __construct(
        private readonly AppointmentScheduler $scheduler,
        private readonly ActivityLogger $logger,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data  client_id, employe_id, date, heure_debut, zone, services[], forfaits[], statut?, commentaire?
     *
     * @throws ValidationException
     */
    public function creer(array $data, User $user): RendezVous
    {
        return DB::transaction(function () use ($data, $user) {
            // Deux réservations simultanées pour la même personne passent l'une après l'autre.
            Employe::whereKey($data['employe_id'])->lockForUpdate()->first();

            $statut = StatutRendezVous::tryFrom((string) ($data['statut'] ?? 'confirme'));

            if (! $statut || ! in_array($statut, StatutRendezVous::creables(), true)) {
                throw ValidationException::withMessages([
                    'statut' => 'Un rendez-vous se crée « En attente » ou « Confirmé ».',
                ]);
            }

            $this->verifier($data, $user, null);

            $compo = $this->scheduler->composer($data['services'] ?? [], $data['forfaits'] ?? []);
            $debut = Heure::toMinutes($data['heure_debut']);

            $rdv = RendezVous::create([
                'client_id' => (int) $data['client_id'],
                'employe_id' => (int) $data['employe_id'],
                'date' => $data['date'],
                'heure_debut' => Heure::sql($debut),
                'heure_fin' => Heure::sql($debut + $compo['duree']),
                'zone' => $data['zone'],
                'statut' => $statut,
                'prix_prevu' => $compo['prix'],
                'commentaire' => $this->commentaire($data),
                'created_by' => $user->id,
            ]);

            $rdv->lignes()->createMany($compo['lignes']);

            // Le journal ne contient aucune donnée personnelle : pas de nom de client.
            $this->logger->log(
                'rendezvous.cree',
                $rdv,
                "Rendez-vous n° {$rdv->id} créé",
                null,
                $this->resume($rdv->load('lignes')),
                $user,
            );

            return $rdv;
        });
    }

    /**
     * Modifie ou déplace un rendez-vous encore modifiable.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function modifier(RendezVous $rdv, array $data, User $user): RendezVous
    {
        return DB::transaction(function () use ($rdv, $data, $user) {
            // Verrous dans l'ordre des identifiants : pas de blocage croisé entre deux déplacements.
            collect([$rdv->employe_id, (int) $data['employe_id']])
                ->unique()
                ->sort()
                ->each(fn ($id) => Employe::whereKey($id)->lockForUpdate()->first());

            $rdv = RendezVous::with('lignes')->whereKey($rdv->id)->lockForUpdate()->firstOrFail();

            if (! $rdv->statut->estModifiable()) {
                throw ValidationException::withMessages([
                    'statut' => "Ce rendez-vous est « {$rdv->statut->label()} » : il ne peut plus être modifié.",
                ]);
            }

            $this->verifier($data, $user, $rdv);

            $compo = $this->scheduler->composer($data['services'] ?? [], $data['forfaits'] ?? []);
            $debut = Heure::toMinutes($data['heure_debut']);

            $avant = $this->resume($rdv);
            $commentaireAvant = $rdv->commentaire;

            $rdv->fill([
                'client_id' => (int) $data['client_id'],
                'employe_id' => (int) $data['employe_id'],
                'date' => $data['date'],
                'heure_debut' => Heure::sql($debut),
                'heure_fin' => Heure::sql($debut + $compo['duree']),
                'zone' => $data['zone'],
                'prix_prevu' => $compo['prix'],
                'commentaire' => $this->commentaire($data),
            ]);
            $rdv->save();

            if ($this->signature($rdv->lignes) !== $this->signature($compo['lignes'])) {
                $rdv->lignes()->delete();
                $rdv->lignes()->createMany($compo['lignes']);
            }

            $rdv->load('lignes');
            $apres = $this->resume($rdv);

            $ancien = [];
            $nouveau = [];

            foreach ($apres as $cle => $valeur) {
                if ($avant[$cle] !== $valeur) {
                    $ancien[$cle] = $avant[$cle];
                    $nouveau[$cle] = $valeur;
                }
            }

            if ($commentaireAvant !== $rdv->commentaire) {
                $nouveau['commentaire_modifie'] = true;
            }

            if ($nouveau !== []) {
                $this->logger->log('rendezvous.modifie', $rdv, "Rendez-vous n° {$rdv->id} modifié", $ancien ?: null, $nouveau, $user);
            }

            return $rdv;
        });
    }

    /**
     * @throws ValidationException
     */
    public function changerStatut(RendezVous $rdv, StatutRendezVous $vers, User $user, ?string $motif = null): RendezVous
    {
        return DB::transaction(function () use ($rdv, $vers, $user, $motif) {
            $rdv = RendezVous::whereKey($rdv->id)->lockForUpdate()->firstOrFail();
            $ancien = $rdv->statut;

            if (! $ancien->peutPasserA($vers)) {
                throw ValidationException::withMessages([
                    'statut' => "Un rendez-vous « {$ancien->label()} » ne peut pas passer à « {$vers->label()} ».",
                ]);
            }

            if ($vers->estTerrain() && $rdv->date->toDateString() > now()->toDateString()) {
                throw ValidationException::withMessages([
                    'statut' => "Ce rendez-vous n'a pas encore eu lieu : il ne peut pas être marqué « {$vers->label()} ».",
                ]);
            }

            $rdv->statut = $vers;

            if ($vers === StatutRendezVous::Annule) {
                $motif = $motif === null ? '' : trim($motif);
                $rdv->motif_annulation = $motif === '' ? null : $motif;
            }

            $rdv->save();

            $this->logger->log(
                'rendezvous.statut',
                $rdv,
                "Rendez-vous n° {$rdv->id} : {$ancien->label()} → {$vers->label()}",
                ['statut' => $ancien->value],
                ['statut' => $vers->value],
                $user,
            );

            return $rdv;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    private function verifier(array $data, User $user, ?RendezVous $rdv): void
    {
        $erreurs = $this->scheduler->erreurs($data, $user, $rdv);

        if ($erreurs !== []) {
            throw ValidationException::withMessages($erreurs);
        }
    }

    /** @param array<string, mixed> $data */
    private function commentaire(array $data): ?string
    {
        $commentaire = isset($data['commentaire']) ? trim((string) $data['commentaire']) : '';

        return $commentaire === '' ? null : $commentaire;
    }

    /**
     * Données utiles du rendez-vous, pour le journal et la comparaison avant/après.
     *
     * @return array<string, mixed>
     */
    private function resume(RendezVous $rdv): array
    {
        return [
            'client_id' => (int) $rdv->client_id,
            'employe_id' => (int) $rdv->employe_id,
            'date' => $rdv->date->toDateString(),
            'heure_debut' => substr($rdv->heure_debut, 0, 5),
            'heure_fin' => substr($rdv->heure_fin, 0, 5),
            'zone' => $rdv->zone->value,
            'prix_prevu' => (int) $rdv->prix_prevu,
            'prestations' => $rdv->lignes
                ->map(fn ($ligne) => $ligne->service_id.($ligne->forfait_id ? '@'.$ligne->forfait_id : ''))
                ->sort()
                ->values()
                ->all(),
        ];
    }

    /**
     * Empreinte des lignes pour savoir si elles ont changé.
     *
     * @param  iterable<mixed>  $lignes
     * @return list<string>
     */
    private function signature(iterable $lignes): array
    {
        return collect($lignes)
            ->map(fn ($ligne) => implode('|', [
                data_get($ligne, 'service_id'),
                data_get($ligne, 'forfait_id') ?? 0,
                data_get($ligne, 'prix_applique'),
                data_get($ligne, 'duree_appliquee'),
            ]))
            ->sort()
            ->values()
            ->all();
    }
}