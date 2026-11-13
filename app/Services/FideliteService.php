<?php

namespace App\Services;

use App\Enums\TypeMouvementFidelite;
use App\Enums\TypePaiement;
use App\Models\Client;
use App\Models\MouvementFidelite;
use App\Models\Paiement;
use App\Models\Recompense;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FideliteService
{
    private const POINTS_MAX = 100_000;

    public function __construct(private readonly ActivityLogger $logger) {}

    public function fcfaParPoint(): int
    {
        return max(0, (int) config('salon.fidelite.fcfa_par_point', 1000));
    }

    public function estActive(): bool
    {
        return $this->fcfaParPoint() > 0;
    }

    /** Points rapportés par un montant encaissé : 1 point par tranche complète. */
    public function pointsPour(int $montant): int
    {
        return $this->estActive() && $montant > 0 ? intdiv($montant, $this->fcfaParPoint()) : 0;
    }

    /**
     * Crédite les points d'un paiement. Appelé par la caisse, dans sa transaction.
     */
    public function crediterPaiement(Paiement $paiement, User $user): ?MouvementFidelite
    {
        if ($paiement->type !== TypePaiement::Paiement || ! $paiement->client_id) {
            return null;
        }

        $points = $this->pointsPour((int) $paiement->montant);

        if ($points === 0) {
            return null;
        }

        return MouvementFidelite::create([
            'client_id' => $paiement->client_id,
            'type' => TypeMouvementFidelite::Gain,
            'points' => $points,
            'paiement_id' => $paiement->id,
            'user_id' => $user->id,
        ]);
    }

    /**
     * Retire les points gagnés par un paiement qui vient d'être annulé.
     * On retire exactement ce qui avait été crédité, même si le taux a changé depuis.
     */
    public function retirerPaiement(Paiement $origine, User $user): ?MouvementFidelite
    {
        $gain = MouvementFidelite::where('paiement_id', $origine->id)
            ->where('type', TypeMouvementFidelite::Gain->value)
            ->first();

        if (! $gain) {
            return null;
        }

        return MouvementFidelite::firstOrCreate(
            ['paiement_id' => $origine->id, 'type' => TypeMouvementFidelite::Retrait->value],
            ['client_id' => $gain->client_id, 'points' => -$gain->points, 'user_id' => $user->id],
        );
    }

    /**
     * La cliente utilise une récompense : ses points sont déduits.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function utiliser(Client $client, Recompense $recompense, User $user): MouvementFidelite
    {
        if (! $this->peutUtiliser($user)) {
            throw new AuthorizationException("Vous n'avez pas le droit d'utiliser une récompense.");
        }

        return DB::transaction(function () use ($client, $recompense, $user) {
            // Le verrou empêche d'utiliser deux fois les mêmes points en même temps.
            $client = Client::whereKey($client->id)->lockForUpdate()->firstOrFail();
            $recompense = Recompense::whereKey($recompense->id)->firstOrFail();

            if (! $recompense->is_active) {
                throw $this->erreur('recompense_id', "Cette récompense n'est plus proposée.");
            }

            $solde = $client->pointsFidelite();

            if ($solde < $recompense->points) {
                throw $this->erreur('recompense_id', "Points insuffisants : {$client->prenom} a {$solde} points, il en faut {$recompense->points}.");
            }

            $mouvement = MouvementFidelite::create([
                'client_id' => $client->id,
                'type' => TypeMouvementFidelite::Utilisation,
                'points' => -$recompense->points,
                'recompense_id' => $recompense->id,
                'motif' => $recompense->nom,
                'user_id' => $user->id,
            ]);

            $this->logger->log(
                'fidelite.utilisation',
                $client,
                "Récompense « {$recompense->nom} » utilisée (-{$recompense->points} points)",
                null,
                ['mouvement_id' => $mouvement->id, 'recompense_id' => $recompense->id, 'points' => -$recompense->points],
                $user,
            );

            return $mouvement;
        });
    }

    /**
     * Ajoute ou retire des points à la main, avec un motif.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function corriger(Client $client, mixed $points, string $motif, User $user): MouvementFidelite
    {
        if (! $user->hasPermission('loyalty.manage')) {
            throw new AuthorizationException("Vous n'avez pas le droit de corriger les points.");
        }

        $points = is_int($points) ? $points : (is_string($points) && preg_match('/^[+-]?\d{1,6}$/', trim($points)) ? (int) trim($points) : null);
        $motif = trim($motif);

        if ($points === null || $points === 0 || abs($points) > self::POINTS_MAX) {
            throw $this->erreur('points', 'Indiquez un nombre de points entier, différent de 0 (par exemple 20 ou -20).');
        }

        if (mb_strlen($motif) < 5 || mb_strlen($motif) > 255) {
            throw $this->erreur('motif', 'Indiquez le motif de la correction (5 à 255 caractères).');
        }

        return DB::transaction(function () use ($client, $points, $motif, $user) {
            $client = Client::whereKey($client->id)->lockForUpdate()->firstOrFail();
            $solde = $client->pointsFidelite();

            if ($solde + $points < 0) {
                throw $this->erreur('points', "Impossible de retirer plus de points que le solde ({$solde} points).");
            }

            $mouvement = MouvementFidelite::create([
                'client_id' => $client->id,
                'type' => TypeMouvementFidelite::Correction,
                'points' => $points,
                'motif' => $motif,
                'user_id' => $user->id,
            ]);

            // Le motif reste dans l'historique de fidélité : le journal ne garde que les chiffres.
            $this->logger->log(
                'fidelite.correction',
                $client,
                'Correction de points : '.($points > 0 ? '+' : '').$points,
                ['solde' => $solde],
                ['mouvement_id' => $mouvement->id, 'points' => $points, 'solde' => $solde + $points],
                $user,
            );

            return $mouvement;
        });
    }

    /**
     * @param  array{nom: string, description: ?string, points: int}  $data
     *
     * @throws AuthorizationException
     */
    public function enregistrerRecompense(?Recompense $recompense, array $data, User $user): Recompense
    {
        $this->autoriserGestion($user);

        return DB::transaction(function () use ($recompense, $data, $user) {
            $nouvelle = $recompense === null;
            $recompense ??= new Recompense(['is_active' => true]);
            $recompense->fill($data);

            if ($recompense->isDirty()) {
                $nouveau = $recompense->getDirty();
                $ancien = $nouvelle ? null : array_intersect_key($recompense->getOriginal(), $nouveau);
                $recompense->save();

                $this->logger->log(
                    $nouvelle ? 'recompense.creee' : 'recompense.modifiee',
                    $recompense,
                    ($nouvelle ? 'Récompense créée : ' : 'Récompense modifiée : ').$recompense->nom,
                    $ancien,
                    $nouveau,
                    $user,
                );
            }

            return $recompense;
        });
    }

    /** @throws AuthorizationException */
    public function definirRecompenseActive(Recompense $recompense, bool $active, User $user): void
    {
        $this->autoriserGestion($user);

        if ($recompense->is_active === $active) {
            return;
        }

        $recompense->forceFill(['is_active' => $active])->save();

        $this->logger->log(
            $active ? 'recompense.activee' : 'recompense.desactivee',
            $recompense,
            ($active ? 'Récompense réactivée : ' : 'Récompense désactivée : ').$recompense->nom,
            null,
            null,
            $user,
        );
    }

    /** Personne à l'accueil (encaissement) ou gestionnaire de la fidélité. */
    public function peutUtiliser(User $user): bool
    {
        return $user->hasPermission('loyalty.manage')
            || ($user->hasPermission('loyalty.view') && $user->hasPermission('payments.record'));
    }

    /** @throws AuthorizationException */
    private function autoriserGestion(User $user): void
    {
        if (! $user->hasPermission('loyalty.manage')) {
            throw new AuthorizationException("Vous n'avez pas le droit de gérer les récompenses.");
        }
    }

    private function erreur(string $champ, string $message): ValidationException
    {
        return ValidationException::withMessages([$champ => $message]);
    }
}
