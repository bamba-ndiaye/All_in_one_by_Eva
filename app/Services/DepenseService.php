<?php

namespace App\Services;

use App\Models\CategorieDepense;
use App\Models\Depense;
use App\Models\User;
use App\Support\AgendaVue;
use App\Support\Fcfa;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class DepenseService
{
    private const MONTANT_MAX = 100_000_000;

    /** Disque privé : jamais accessible directement depuis le web. */
    private const DISQUE = 'local';

    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly CaisseService $caisse,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data  categorie_depense_id, montant, date (AAAA-MM-JJ), description
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function creer(array $data, ?UploadedFile $justificatif, User $user): Depense
    {
        $this->autoriser($user);

        $attributs = $this->valider($data);
        $chemin = $this->stocker($justificatif);

        try {
            return DB::transaction(function () use ($attributs, $chemin, $user) {
                $depense = Depense::create($attributs + [
                    'justificatif' => $chemin,
                    'user_id' => $user->id,
                ]);

                // Le journal ne contient jamais le texte de la description (un salaire nominatif pourrait y figurer).
                $this->logger->log(
                    'depense.creee',
                    $depense,
                    "Dépense n° {$depense->id} enregistrée : ".Fcfa::format($depense->montant),
                    null,
                    [
                        'montant' => $depense->montant,
                        'categorie_depense_id' => $depense->categorie_depense_id,
                        'date' => $depense->date->toDateString(),
                        'justificatif' => $chemin !== null,
                    ],
                    $user,
                );

                return $depense;
            });
        } catch (Throwable $e) {
            $this->supprimerFichier($chemin);

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function modifier(Depense $depense, array $data, ?UploadedFile $justificatif, User $user): Depense
    {
        $this->autoriser($user);

        $chemin = null;

        try {
            return DB::transaction(function () use ($depense, $data, $justificatif, $user, &$chemin) {
                $depense = Depense::whereKey($depense->id)->lockForUpdate()->firstOrFail();

                if ($depense->estAnnulee()) {
                    throw $this->erreur('depense', 'Cette dépense est annulée : elle ne peut plus être modifiée.');
                }

                $attributs = $this->valider($data, $depense);

                if ($justificatif && $depense->aUnJustificatif()) {
                    throw $this->erreur('justificatif', 'Un justificatif est déjà enregistré : il ne peut pas être remplacé. Annulez la dépense et recréez-la si le fichier est erroné.');
                }

                $ancien = [];
                $nouveau = [];

                foreach (['categorie_depense_id', 'montant'] as $champ) {
                    if ((int) $depense->{$champ} !== $attributs[$champ]) {
                        $ancien[$champ] = (int) $depense->{$champ};
                        $nouveau[$champ] = $attributs[$champ];
                    }
                }

                if ($depense->date->toDateString() !== $attributs['date']) {
                    $ancien['date'] = $depense->date->toDateString();
                    $nouveau['date'] = $attributs['date'];
                }

                if ($depense->description !== $attributs['description']) {
                    $nouveau['description_modifiee'] = true;
                }

                if ($justificatif) {
                    $chemin = $this->stocker($justificatif);
                    $attributs['justificatif'] = $chemin;
                    $nouveau['justificatif_ajoute'] = true;
                }

                if ($nouveau === []) {
                    return $depense;
                }

                $depense->fill($attributs)->save();

                $this->logger->log(
                    'depense.modifiee',
                    $depense,
                    "Dépense n° {$depense->id} modifiée",
                    $ancien ?: null,
                    $nouveau,
                    $user,
                );

                return $depense;
            });
        } catch (Throwable $e) {
            $this->supprimerFichier($chemin);

            throw $e;
        }
    }

    /**
     * Annule une dépense : elle reste visible mais n'entre plus dans aucun total.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function annuler(Depense $depense, string $motif, User $user): Depense
    {
        $this->autoriser($user);

        return DB::transaction(function () use ($depense, $motif, $user) {
            $depense = Depense::whereKey($depense->id)->lockForUpdate()->firstOrFail();

            if ($depense->estAnnulee()) {
                throw $this->erreur('depense', 'Cette dépense est déjà annulée.');
            }

            $motif = trim($motif);

            if (mb_strlen($motif) < 5) {
                throw $this->erreur('motif', "Indiquez le motif de l'annulation (5 caractères minimum).");
            }

            if (mb_strlen($motif) > 255) {
                throw $this->erreur('motif', 'Le motif ne doit pas dépasser 255 caractères.');
            }

            $depense->forceFill([
                'annulee_at' => now(),
                'annulee_par' => $user->id,
                'motif_annulation' => $motif,
            ])->save();

            $this->logger->log(
                'depense.annulee',
                $depense,
                "Dépense n° {$depense->id} annulée : ".Fcfa::format($depense->montant),
                null,
                ['montant' => $depense->montant],
                $user,
            );

            return $depense;
        });
    }

    /** Total des dépenses valides sur la période. */
    public function total(CarbonInterface|string $debut, CarbonInterface|string $fin): int
    {
        return (int) Depense::query()->valides()->entre($debut, $fin)->sum('montant');
    }

    /**
     * Total par catégorie sur la période, annulations exclues.
     *
     * @return Collection<int, array{categorie: string, total: int}>
     */
    public function parCategorie(CarbonInterface|string $debut, CarbonInterface|string $fin): Collection
    {
        return Depense::query()
            ->valides()
            ->entre($debut, $fin)
            ->join('categories_depenses', 'categories_depenses.id', '=', 'depenses.categorie_depense_id')
            ->groupBy('categories_depenses.id', 'categories_depenses.nom', 'categories_depenses.ordre')
            ->orderBy('categories_depenses.ordre')
            ->orderBy('categories_depenses.id')
            ->selectRaw('categories_depenses.nom as categorie, SUM(depenses.montant) as total')
            ->get()
            ->map(fn ($ligne) => ['categorie' => $ligne->categorie, 'total' => (int) $ligne->total]);
    }

    /**
     * Indicateur de gestion : recettes encaissées moins dépenses enregistrées.
     * Ce n'est pas un bénéfice comptable.
     *
     * @return array{encaisse: int, depenses: int, resultat: int}
     */
    public function resultatEstime(CarbonInterface|string $debut, CarbonInterface|string $fin): array
    {
        $encaisse = $this->caisse->totalEncaisse($debut, $fin);
        $depenses = $this->total($debut, $fin);

        return [
            'encaisse' => $encaisse,
            'depenses' => $depenses,
            'resultat' => $encaisse - $depenses,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{categorie_depense_id: int, montant: int, date: string, description: string}
     *
     * @throws ValidationException
     */
    private function valider(array $data, ?Depense $actuelle = null): array
    {
        $categorie = CategorieDepense::find((int) ($data['categorie_depense_id'] ?? 0));
        $memeCategorie = $actuelle !== null
            && $categorie !== null
            && (int) $actuelle->categorie_depense_id === $categorie->id;

        if (! $categorie || (! $categorie->is_active && ! $memeCategorie)) {
            throw $this->erreur('categorie_depense_id', 'Choisissez une catégorie active.');
        }

        $montant = $this->entier($data['montant'] ?? null);

        if ($montant === null || $montant < 1) {
            throw $this->erreur('montant', 'Le montant doit être un nombre entier de FCFA supérieur à 0.');
        }

        if ($montant > self::MONTANT_MAX) {
            throw $this->erreur('montant', 'Ce montant est trop élevé.');
        }

        $date = AgendaVue::lireDate($data['date'] ?? null);

        if (! $date) {
            throw $this->erreur('date', 'Date invalide.');
        }

        if ($date->gt(now()->startOfDay())) {
            throw $this->erreur('date', 'Une dépense ne peut pas être datée dans le futur.');
        }

        if ($date->lt(Carbon::create(2000, 1, 1))) {
            throw $this->erreur('date', 'Cette date est trop ancienne.');
        }

        $description = trim((string) ($data['description'] ?? ''));

        if ($description === '') {
            throw $this->erreur('description', 'Indiquez à quoi correspond cette dépense.');
        }

        if (mb_strlen($description) > 255) {
            throw $this->erreur('description', 'La description ne doit pas dépasser 255 caractères.');
        }

        return [
            'categorie_depense_id' => $categorie->id,
            'montant' => $montant,
            'date' => $date->toDateString(),
            'description' => $description,
        ];
    }

    /** @throws AuthorizationException */
    private function autoriser(User $user): void
    {
        if (! $user->hasPermission('expenses.manage')) {
            throw new AuthorizationException("Vous n'avez pas le droit de gérer les dépenses.");
        }
    }

    /** @throws ValidationException */
    private function stocker(?UploadedFile $fichier): ?string
    {
        if ($fichier === null) {
            return null;
        }

        $chemin = $fichier->store('justificatifs', self::DISQUE);

        if ($chemin === false) {
            throw $this->erreur('justificatif', "Le justificatif n'a pas pu être enregistré.");
        }

        return $chemin;
    }

    private function supprimerFichier(?string $chemin): void
    {
        if ($chemin !== null && $chemin !== '') {
            Storage::disk(self::DISQUE)->delete($chemin);
        }
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