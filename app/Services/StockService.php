<?php

namespace App\Services;

use App\Enums\TypeMouvementStock;
use App\Models\MouvementStock;
use App\Models\Produit;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockService
{
    private const QUANTITE_MAX = 100_000;

    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * Crée un produit. Une quantité initiale est enregistrée comme une entrée « Stock initial ».
     *
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function creer(array $data, int $quantiteInitiale, User $user): Produit
    {
        $this->autoriser($user);

        if ($quantiteInitiale < 0 || $quantiteInitiale > self::QUANTITE_MAX) {
            throw $this->erreur('quantite_initiale', 'La quantité initiale doit être comprise entre 0 et '.self::QUANTITE_MAX.'.');
        }

        return DB::transaction(function () use ($data, $quantiteInitiale, $user) {
            $produit = Produit::create($data);

            $this->logger->log('produit.cree', $produit, "Produit créé : {$produit->nom}", null, $data, $user);

            if ($quantiteInitiale > 0) {
                $this->enregistrer($produit, TypeMouvementStock::Entree, $quantiteInitiale, 'Stock initial', $user);
            }

            return $produit->refresh();
        });
    }

    /**
     * Modifie la fiche. La quantité ne change jamais ici : elle passe par un mouvement.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     */
    public function modifier(Produit $produit, array $data, User $user): Produit
    {
        $this->autoriser($user);

        return DB::transaction(function () use ($produit, $data, $user) {
            $produit->fill($data);

            if ($produit->isDirty()) {
                $nouveau = $produit->getDirty();
                $ancien = array_intersect_key($produit->getOriginal(), $nouveau);
                $produit->save();

                $this->logger->log('produit.modifie', $produit, "Produit modifié : {$produit->nom}", $ancien, $nouveau, $user);
            }

            return $produit;
        });
    }

    /** @throws AuthorizationException */
    public function definirActif(Produit $produit, bool $actif, User $user): void
    {
        $this->autoriser($user);

        if ($produit->is_active === $actif) {
            return;
        }

        DB::transaction(function () use ($produit, $actif, $user) {
            $produit->forceFill(['is_active' => $actif])->save();

            $this->logger->log(
                $actif ? 'produit.active' : 'produit.desactive',
                $produit,
                ($actif ? 'Produit réactivé : ' : 'Produit désactivé : ').$produit->nom,
                null,
                null,
                $user,
            );
        });
    }

    /**
     * Enregistre un mouvement.
     * - Entrée, utilisation, vente, perte : $quantite = nombre d'unités (≥ 1).
     * - Inventaire : $quantite = quantité réellement comptée (≥ 0) ; l'écart est enregistré.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function mouvement(Produit $produit, TypeMouvementStock $type, mixed $quantite, ?string $motif, User $user): MouvementStock
    {
        $this->autoriser($user);

        $quantite = $this->entier($quantite);
        $motif = trim((string) $motif);

        if ($quantite === null || $quantite > self::QUANTITE_MAX) {
            throw $this->erreur('quantite', 'Indiquez une quantité en nombre entier.');
        }

        if ($type !== TypeMouvementStock::Inventaire && $quantite < 1) {
            throw $this->erreur('quantite', 'La quantité doit être d\'au moins 1.');
        }

        if ($quantite < 0) {
            throw $this->erreur('quantite', 'La quantité comptée ne peut pas être négative.');
        }

        if ($type->motifObligatoire() && mb_strlen($motif) < 3) {
            throw $this->erreur('motif', $type === TypeMouvementStock::Perte
                ? 'Indiquez la raison de la perte (3 caractères minimum).'
                : "Indiquez la raison de l'écart d'inventaire (3 caractères minimum).");
        }

        if (mb_strlen($motif) > 255) {
            throw $this->erreur('motif', 'Le motif ne doit pas dépasser 255 caractères.');
        }

        return DB::transaction(function () use ($produit, $type, $quantite, $motif, $user) {
            // Le verrou empêche deux sorties simultanées de rendre le stock négatif.
            $produit = Produit::whereKey($produit->id)->lockForUpdate()->firstOrFail();

            if (! $produit->is_active) {
                throw $this->erreur('produit', 'Ce produit est désactivé : réactivez-le avant de faire un mouvement.');
            }

            $delta = match (true) {
                $type === TypeMouvementStock::Inventaire => $quantite - $produit->quantite,
                $type->estSortie() => -$quantite,
                default => $quantite,
            };

            if ($type === TypeMouvementStock::Inventaire && $delta === 0) {
                throw $this->erreur('quantite', 'La quantité comptée est identique au stock enregistré : rien à corriger.');
            }

            if ($type->estSortie() && $quantite > $produit->quantite) {
                throw $this->erreur('quantite', 'Stock insuffisant : il reste '.$produit->quantiteFormatee().'.');
            }

            return $this->enregistrer($produit, $type, $delta, $motif === '' ? null : $motif, $user);
        });
    }

    /**
     * Valeur totale du stock au prix d'achat (produits actifs).
     */
    public function valeurTotale(): int
    {
        return (int) Produit::query()
            ->actifs()
            ->where('quantite', '>', 0)
            ->whereNotNull('prix_achat')
            ->sum(DB::raw('quantite * prix_achat'));
    }

    /** À appeler dans une transaction, produit verrouillé. */
    private function enregistrer(Produit $produit, TypeMouvementStock $type, int $delta, ?string $motif, User $user): MouvementStock
    {
        $apres = $produit->quantite + $delta;

        $produit->forceFill(['quantite' => $apres])->save();

        $mouvement = MouvementStock::create([
            'produit_id' => $produit->id,
            'type' => $type,
            'quantite' => $delta,
            'quantite_apres' => $apres,
            'motif' => $motif,
            'user_id' => $user->id,
        ]);

        $this->logger->log(
            'stock.'.$type->value,
            $produit,
            "{$type->label()} : {$produit->nom} (".($delta > 0 ? '+' : '').$delta.')',
            null,
            ['mouvement_id' => $mouvement->id, 'quantite' => $delta, 'quantite_apres' => $apres],
            $user,
        );

        return $mouvement;
    }

    /** @throws AuthorizationException */
    private function autoriser(User $user): void
    {
        if (! $user->hasPermission('stock.manage')) {
            throw new AuthorizationException("Vous n'avez pas le droit de gérer le stock.");
        }
    }

    /** Entier strict : « 5 » est accepté, « 5.5 » ou « abc » non. */
    private function entier(mixed $valeur): ?int
    {
        if (is_int($valeur)) {
            return $valeur;
        }

        if (is_string($valeur) && preg_match('/^-?\d{1,9}$/', trim($valeur))) {
            return (int) trim($valeur);
        }

        return null;
    }

    private function erreur(string $champ, string $message): ValidationException
    {
        return ValidationException::withMessages([$champ => $message]);
    }
}
