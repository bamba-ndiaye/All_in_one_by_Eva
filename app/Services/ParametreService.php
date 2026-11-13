<?php

namespace App\Services;

use App\Models\Parametre;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Réglages modifiables depuis l'écran « Paramètres ».
 * Chaque réglage enregistré remplace la valeur de config (issue du .env) au démarrage.
 */
class ParametreService
{
    private const CACHE = 'parametres.valeurs';

    /** Clé du formulaire => clé de config, règles de validation, type. */
    public const DEFINITIONS = [
        'salon_nom' => ['config' => 'app.name', 'regles' => ['required', 'string', 'max:80'], 'type' => 'texte'],
        'salon_adresse' => ['config' => 'salon.recu.adresse', 'regles' => ['nullable', 'string', 'max:150'], 'type' => 'texte'],
        'salon_telephone' => ['config' => 'salon.recu.telephone', 'regles' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\s.\-]*$/'], 'type' => 'texte'],
        'rdv_pause_minutes' => ['config' => 'salon.rendez_vous.pause_minutes', 'regles' => ['required', 'integer', 'min:0', 'max:120'], 'type' => 'entier'],
        'fidelite_fcfa_par_point' => ['config' => 'salon.fidelite.fcfa_par_point', 'regles' => ['required', 'integer', 'min:0', 'max:1000000'], 'type' => 'entier'],
        'anniversaire_message' => ['config' => 'salon.anniversaires.message', 'regles' => ['required', 'string', 'min:10', 'max:700'], 'type' => 'texte'],
    ];

    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * Applique les réglages enregistrés à la configuration.
     * Silencieux si la base n'est pas prête (installation, migrations en cours).
     */
    public function appliquer(): void
    {
        try {
            $valeurs = Cache::rememberForever(self::CACHE, fn () => Parametre::query()->pluck('valeur', 'cle')->all());
        } catch (Throwable) {
            return;
        }

        foreach ($valeurs as $cle => $valeur) {
            if (isset(self::DEFINITIONS[$cle])) {
                config([self::DEFINITIONS[$cle]['config'] => $this->convertir($cle, $valeur)]);
            }
        }
    }

    /**
     * Valeurs actuelles (réglage enregistré, sinon valeur du .env).
     *
     * @return array<string, mixed>
     */
    public function valeurs(): array
    {
        return collect(self::DEFINITIONS)->map(fn ($def) => config($def['config']))->all();
    }

    /**
     * @param  array<string, mixed>  $donnees
     * @return list<string> clés modifiées
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function enregistrer(array $donnees, User $user): array
    {
        if (! $user->hasPermission('settings.manage')) {
            throw new AuthorizationException("Vous n'avez pas le droit de modifier les paramètres.");
        }

        $valides = Validator::make(
            $donnees,
            collect(self::DEFINITIONS)->map(fn ($def) => $def['regles'])->all(),
            ['salon_telephone.regex' => 'Le téléphone ne peut contenir que des chiffres, des espaces et les signes + ( ) - .'],
            [
                'salon_nom' => 'nom du salon',
                'salon_adresse' => 'adresse',
                'salon_telephone' => 'téléphone',
                'rdv_pause_minutes' => 'pause entre deux rendez-vous',
                'fidelite_fcfa_par_point' => 'montant pour 1 point',
                'anniversaire_message' => "message d'anniversaire",
            ],
        )->validate();

        $actuelles = $this->valeurs();
        $modifiees = [];

        DB::transaction(function () use ($valides, $actuelles, &$modifiees) {
            foreach (self::DEFINITIONS as $cle => $def) {
                $nouvelle = $this->convertir($cle, $valides[$cle] ?? null);

                if ($nouvelle === $this->convertir($cle, $actuelles[$cle])) {
                    continue;
                }

                Parametre::updateOrCreate(['cle' => $cle], ['valeur' => $nouvelle === null ? null : (string) $nouvelle]);
                config([$def['config'] => $nouvelle]);
                $modifiees[] = $cle;
            }
        });

        Cache::forget(self::CACHE);

        if ($modifiees !== []) {
            $this->logger->log('parametres.modifies', null, 'Paramètres modifiés : '.implode(', ', $modifiees), null, ['cles' => $modifiees], $user);
        }

        return $modifiees;
    }

    private function convertir(string $cle, mixed $valeur): mixed
    {
        if (self::DEFINITIONS[$cle]['type'] === 'entier') {
            return $valeur === null || $valeur === '' ? 0 : (int) $valeur;
        }

        $texte = trim((string) $valeur);

        return $texte === '' ? null : $texte;
    }
}
