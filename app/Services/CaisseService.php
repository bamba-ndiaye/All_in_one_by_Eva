<?php

namespace App\Services;

use App\Enums\StatutRendezVous;
use App\Enums\TypePaiement;
use App\Models\Client;
use App\Models\ModePaiement;
use App\Models\Paiement;
use App\Models\RendezVous;
use App\Models\User;
use App\Support\AgendaVue;
use App\Support\Fcfa;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CaisseService
{
    private const MONTANT_MAX = 10_000_000;

    /** Un rendez-vous ne peut être encaissé que dans ces statuts. */
    private const STATUTS_PAYABLES = [
        StatutRendezVous::Confirme,
        StatutRendezVous::ClientArrive,
        StatutRendezVous::EnCours,
        StatutRendezVous::Termine,
    ];

    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly FideliteService $fidelite,
    ) {}

    /**
     * Enregistre un paiement, complet ou partiel.
     *
     * @param  array<string, mixed>  $data  rendez_vous_id? | client_id + libelle, montant, mode_paiement_id, reference?, date?
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function enregistrer(array $data, User $user): Paiement
    {
        if (! ($user->hasPermission('payments.record') || $user->hasPermission('payments.correct'))) {
            throw new AuthorizationException("Vous n'avez pas le droit d'enregistrer un paiement.");
        }

        return DB::transaction(function () use ($data, $user) {
            $rdv = null;

            if (! empty($data['rendez_vous_id'])) {
                // Le verrou empêche deux encaissements simultanés de dépasser le prix.
                $rdv = RendezVous::whereKey((int) $data['rendez_vous_id'])->lockForUpdate()->first();

                if (! $rdv) {
                    throw $this->erreur('rendez_vous_id', 'Rendez-vous introuvable.');
                }

                if (! in_array($rdv->statut, self::STATUTS_PAYABLES, true)) {
                    throw $this->erreur('rendez_vous_id', "Un rendez-vous « {$rdv->statut->label()} » ne peut pas être encaissé.");
                }
            }

            $montant = $this->entier($data['montant'] ?? null);

            if ($montant === null || $montant < 1) {
                throw $this->erreur('montant', 'Le montant doit être un nombre entier de FCFA supérieur à 0.');
            }

            if ($montant > self::MONTANT_MAX) {
                throw $this->erreur('montant', 'Ce montant est trop élevé.');
            }

            $mode = ModePaiement::find((int) ($data['mode_paiement_id'] ?? 0));

            if (! $mode || ! $mode->is_active) {
                throw $this->erreur('mode_paiement_id', 'Choisissez un mode de paiement actif.');
            }

            $date = $this->dateOperation($data['date'] ?? null, $user);

            $reference = trim((string) ($data['reference'] ?? ''));

            if (mb_strlen($reference) > 100) {
                throw $this->erreur('reference', 'La référence ne doit pas dépasser 100 caractères.');
            }

            if ($rdv) {
                $total = (int) $rdv->prix_prevu;
                $reste = $total - $rdv->montantPaye();

                if ($total <= 0) {
                    throw $this->erreur('montant', "Ce rendez-vous n'a aucun montant à encaisser.");
                }

                if ($reste <= 0) {
                    throw $this->erreur('montant', 'Ce rendez-vous est déjà entièrement payé.');
                }

                if ($montant > $reste) {
                    throw $this->erreur('montant', 'Le montant dépasse le reste à payer ('.Fcfa::format($reste).').');
                }

                $clientId = $rdv->client_id;
                $libelle = null;
                $resteApres = $reste - $montant;
            } else {
                $client = Client::find((int) ($data['client_id'] ?? 0));

                if (! $client) {
                    throw $this->erreur('client_id', 'Choisissez un client.');
                }

                if (! $client->is_active) {
                    throw $this->erreur('client_id', 'Cette fiche client est archivée.');
                }

                $libelle = trim((string) ($data['libelle'] ?? ''));

                if ($libelle === '') {
                    throw $this->erreur('libelle', 'Indiquez ce qui est payé (par exemple : vente de produit).');
                }

                if (mb_strlen($libelle) > 150) {
                    throw $this->erreur('libelle', 'Le libellé ne doit pas dépasser 150 caractères.');
                }

                $clientId = $client->id;
                $total = $montant;
                $resteApres = 0;
            }

            $paiement = Paiement::create([
                'client_id' => $clientId,
                'rendez_vous_id' => $rdv?->id,
                'type' => TypePaiement::Paiement,
                'mode_paiement_id' => $mode->id,
                'montant' => $montant,
                'montant_total' => $total,
                'reste_apres' => $resteApres,
                'date' => $date,
                'reference' => $reference === '' ? null : $reference,
                'libelle' => $libelle,
                'user_id' => $user->id,
            ]);

            // Le journal ne contient aucune donnée personnelle : pas de nom de client.
            $this->logger->log(
                'paiement.enregistre',
                $paiement,
                "Paiement {$paiement->numero()} enregistré : ".Fcfa::format($montant),
                null,
                [
                    'montant' => $montant,
                    'mode_paiement_id' => $mode->id,
                    'rendez_vous_id' => $rdv?->id,
                    'reste_apres' => $resteApres,
                ],
                $user,
            );

            $this->fidelite->crediterPaiement($paiement, $user);

            return $paiement;
        });
    }

    /**
     * Annule un paiement par une ligne négative. Le paiement d'origine n'est jamais modifié.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function annuler(Paiement $paiement, string $motif, User $user): Paiement
    {
        if (! $user->hasPermission('payments.correct')) {
            throw new AuthorizationException("Vous n'avez pas le droit d'annuler un paiement.");
        }

        return DB::transaction(function () use ($paiement, $motif, $user) {
            $origine = Paiement::whereKey($paiement->id)->lockForUpdate()->firstOrFail();

            if ($origine->type !== TypePaiement::Paiement) {
                throw $this->erreur('paiement', "Seul un paiement peut être annulé : une annulation ne s'annule pas.");
            }

            if ($origine->annulation()->exists()) {
                throw $this->erreur('paiement', 'Ce paiement est déjà annulé.');
            }

            $motif = trim($motif);

            if (mb_strlen($motif) < 5) {
                throw $this->erreur('motif', "Indiquez le motif de l'annulation (5 caractères minimum).");
            }

            if (mb_strlen($motif) > 255) {
                throw $this->erreur('motif', 'Le motif ne doit pas dépasser 255 caractères.');
            }

            $resteApres = 0;

            if ($origine->rendez_vous_id) {
                $rdv = RendezVous::whereKey($origine->rendez_vous_id)->lockForUpdate()->firstOrFail();
                $resteApres = (int) $rdv->prix_prevu - ($rdv->montantPaye() - $origine->montant);
            }

            $annulation = Paiement::create([
                'client_id' => $origine->client_id,
                'rendez_vous_id' => $origine->rendez_vous_id,
                'type' => TypePaiement::Annulation,
                'paiement_origine_id' => $origine->id,
                'mode_paiement_id' => $origine->mode_paiement_id,
                'montant' => -$origine->montant,
                'montant_total' => $origine->montant_total,
                'reste_apres' => $resteApres,
                'date' => now()->startOfDay(),
                'libelle' => $origine->libelle,
                'motif' => $motif,
                'user_id' => $user->id,
            ]);

            // Le motif reste dans le registre : il peut contenir des informations personnelles.
            $this->logger->log(
                'paiement.annule',
                $origine,
                "Paiement {$origine->numero()} annulé : ".Fcfa::format($origine->montant),
                null,
                ['annulation_id' => $annulation->id, 'montant' => -$origine->montant, 'reste_apres' => $resteApres],
                $user,
            );

            $this->fidelite->retirerPaiement($origine, $user);

            return $annulation;
        });
    }

    /**
     * Corrige un paiement : annule l'ancien et en enregistre un nouveau, en une seule transaction.
     * Le rendez-vous, le client et le libellé ne changent pas.
     *
     * @param  array<string, mixed>  $nouveau  montant, mode_paiement_id, reference?, date?
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function corriger(Paiement $paiement, array $nouveau, string $motif, User $user): Paiement
    {
        return DB::transaction(function () use ($paiement, $nouveau, $motif, $user) {
            $this->annuler($paiement, $motif, $user);

            $corrige = $this->enregistrer([
                'rendez_vous_id' => $paiement->rendez_vous_id,
                'client_id' => $paiement->client_id,
                'libelle' => $paiement->libelle,
                'montant' => $nouveau['montant'] ?? null,
                'mode_paiement_id' => $nouveau['mode_paiement_id'] ?? null,
                'reference' => $nouveau['reference'] ?? null,
                'date' => $nouveau['date'] ?? null,
            ], $user);

            $this->logger->log(
                'paiement.corrige',
                $corrige,
                "Paiement {$paiement->numero()} corrigé en {$corrige->numero()}",
                ['paiement_id' => $paiement->id, 'montant' => $paiement->montant],
                ['paiement_id' => $corrige->id, 'montant' => $corrige->montant],
                $user,
            );

            return $corrige;
        });
    }

    /** Un rendez-vous ne peut être encaissé que dans certains statuts. */
    public function estEncaissable(RendezVous $rdv): bool
    {
        return in_array($rdv->statut, self::STATUTS_PAYABLES, true);
    }

    /** @return array{du: int, paye: int, reste: int} */
    public function solde(RendezVous $rdv): array
    {
        $paye = $rdv->montantPaye();

        return [
            'du' => (int) $rdv->prix_prevu,
            'paye' => $paye,
            'reste' => (int) $rdv->prix_prevu - $paye,
        ];
    }

    /** Total encaissé sur la période, annulations déduites : un paiement n'est jamais compté deux fois. */
    public function totalEncaisse(CarbonInterface|string $debut, CarbonInterface|string $fin): int
    {
        return (int) Paiement::query()->entre($debut, $fin)->sum('montant');
    }

    /**
     * Total par mode de paiement sur la période, annulations déduites.
     *
     * @return Collection<int, array{mode: string, total: int}>
     */
    public function parMode(CarbonInterface|string $debut, CarbonInterface|string $fin): Collection
    {
        return Paiement::query()
            ->entre($debut, $fin)
            ->join('modes_paiement', 'modes_paiement.id', '=', 'paiements.mode_paiement_id')
            ->groupBy('modes_paiement.id', 'modes_paiement.nom', 'modes_paiement.ordre')
            ->orderBy('modes_paiement.ordre')
            ->orderBy('modes_paiement.id')
            ->selectRaw('modes_paiement.nom as mode, SUM(paiements.montant) as total')
            ->get()
            ->map(fn ($ligne) => ['mode' => $ligne->mode, 'total' => (int) $ligne->total]);
    }

    private function dateOperation(mixed $value, User $user): CarbonInterface
    {
        $aujourdhui = now()->startOfDay();

        if ($value === null || $value === '') {
            return $aujourdhui;
        }

        $date = AgendaVue::lireDate($value);

        if (! $date) {
            throw $this->erreur('date', 'Date invalide.');
        }

        if ($date->gt($aujourdhui)) {
            throw $this->erreur('date', 'Un paiement ne peut pas être enregistré à une date future.');
        }

        if (! $date->isSameDay($aujourdhui) && ! $user->hasPermission('payments.correct')) {
            throw $this->erreur('date', 'Seules les personnes autorisées peuvent enregistrer un paiement à une autre date que celle du jour.');
        }

        return $date;
    }

    /** Entier strict : « 5000 » est accepté, « 5000.5 » ou « abc » non. */
    private function entier(mixed $valeur): ?int
    {
        if (is_int($valeur)) {
            return $valeur;
        }

        if (is_string($valeur) && preg_match('/^-?\d+$/', trim($valeur))) {
            return (int) trim($valeur);
        }

        return null;
    }

    private function erreur(string $champ, string $message): ValidationException
    {
        return ValidationException::withMessages([$champ => $message]);
    }
}
