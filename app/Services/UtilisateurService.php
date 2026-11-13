<?php

namespace App\Services;

use App\Enums\RoleUtilisateur;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UtilisateurService
{
    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * @param  array{name: string, email: string, role: RoleUtilisateur, password: string, is_active?: bool}  $data
     * @param  list<string>  $permissions  droits individuels demandés
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function creer(array $data, array $permissions, User $acteur): User
    {
        $this->autoriser($acteur);
        $this->verifierRoleAttribuable($data['role'], $acteur);

        return DB::transaction(function () use ($data, $permissions, $acteur) {
            $user = new User([
                'name' => trim($data['name']),
                'email' => Str::lower(trim($data['email'])),
                'password' => $data['password'],
            ]);

            $user->forceFill([
                'role' => $data['role'],
                'is_active' => $data['is_active'] ?? true,
            ])->save();

            $extras = $this->permissionsFinales($user, $data['role'], $permissions, $acteur);
            $this->synchroniserPermissions($user, $extras);

            // Jamais de mot de passe dans le journal.
            $this->logger->log(
                'utilisateur.cree',
                $user,
                "Compte créé : {$user->name} ({$user->role->label()})",
                null,
                [
                    'role' => $user->role->value,
                    'is_active' => $user->is_active,
                    'permissions' => $extras,
                ],
                $acteur,
            );

            return $user;
        });
    }

    /**
     * @param  array{name: string, email: string, role: RoleUtilisateur, password?: ?string}  $data
     * @param  list<string>  $permissions
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function modifier(User $user, array $data, array $permissions, User $acteur): User
    {
        $this->autoriser($acteur);
        $this->verifierCompteModifiable($user, $acteur);

        $role = $data['role'];

        if ($user->is($acteur) && $role !== $user->role) {
            throw $this->erreur('role', 'Vous ne pouvez pas changer votre propre rôle.');
        }

        $this->verifierRoleAttribuable($role, $acteur);

        return DB::transaction(function () use ($user, $data, $permissions, $acteur, $role) {
            $ancien = [];
            $nouveau = [];

            $user->fill([
                'name' => trim($data['name']),
                'email' => Str::lower(trim($data['email'])),
            ]);

            foreach (['name', 'email'] as $champ) {
                if ($user->isDirty($champ)) {
                    $ancien[$champ] = $user->getOriginal($champ);
                    $nouveau[$champ] = $user->{$champ};
                }
            }

            if ($role !== $user->role) {
                $ancien['role'] = $user->role->value;
                $nouveau['role'] = $role->value;
                $user->forceFill(['role' => $role]);
            }

            $motDePasseChange = filled($data['password'] ?? null);

            if ($motDePasseChange) {
                $user->forceFill([
                    'password' => $data['password'],
                    'remember_token' => Str::random(60),
                ]);
                $nouveau['mot_de_passe_modifie'] = true;
            }

            $user->save();

            $avant = $user->extraPermissions()->pluck('permission')->sort()->values()->all();
            $apres = $this->permissionsFinales($user, $role, $permissions, $acteur);

            if ($avant !== $apres) {
                $this->synchroniserPermissions($user, $apres);
                $ancien['permissions'] = $avant;
                $nouveau['permissions'] = $apres;
            }

            // Un nouveau mot de passe déconnecte la personne partout, sauf si elle le change elle-même.
            if ($motDePasseChange && ! $user->is($acteur)) {
                $this->fermerSessions($user);
            }

            if ($nouveau !== []) {
                $this->logger->log(
                    'utilisateur.modifie',
                    $user,
                    "Compte modifié : {$user->name}",
                    $ancien ?: null,
                    $nouveau,
                    $acteur,
                );
            }

            return $user;
        });
    }

    /**
     * Active ou désactive un compte. Un compte désactivé est déconnecté immédiatement.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function definirActif(User $user, bool $actif, User $acteur): void
    {
        $this->autoriser($acteur);
        $this->verifierCompteModifiable($user, $acteur);

        if ($user->is($acteur)) {
            throw $this->erreur('compte', 'Vous ne pouvez pas désactiver votre propre compte.');
        }

        if ($user->is_active === $actif) {
            return;
        }

        DB::transaction(function () use ($user, $actif, $acteur) {
            $user->forceFill([
                'is_active' => $actif,
                'remember_token' => $actif ? $user->remember_token : Str::random(60),
            ])->save();

            if (! $actif) {
                $this->fermerSessions($user);
            }

            $this->logger->log(
                $actif ? 'utilisateur.active' : 'utilisateur.desactive',
                $user,
                ($actif ? 'Compte réactivé : ' : 'Compte désactivé : ').$user->name,
                null,
                null,
                $acteur,
            );
        });
    }

    /**
     * Droits individuels que l'acteur peut accorder ou retirer.
     * L'administratrice peut tout accorder ; les autres seulement ce qu'ils possèdent eux-mêmes.
     *
     * @return list<string>
     */
    public function permissionsAttribuables(User $acteur): array
    {
        $catalogue = array_keys(config('permissions.catalogue'));

        if ($acteur->isAdmin()) {
            return $catalogue;
        }

        return array_values(array_filter($catalogue, fn (string $p) => $acteur->hasPermission($p)));
    }

    /**
     * Rôles que l'acteur peut attribuer.
     *
     * @return list<RoleUtilisateur>
     */
    public function rolesAttribuables(User $acteur): array
    {
        return array_values(array_filter(
            RoleUtilisateur::cases(),
            fn (RoleUtilisateur $role) => $role !== RoleUtilisateur::Administratrice || $acteur->isAdmin(),
        ));
    }

    /**
     * Calcule les droits individuels à enregistrer :
     * - seulement des permissions connues et absentes des droits du rôle ;
     * - les droits que l'acteur ne peut pas accorder restent tels qu'ils étaient.
     *
     * @param  list<string>  $demandees
     * @return list<string>
     */
    private function permissionsFinales(User $user, RoleUtilisateur $role, array $demandees, User $acteur): array
    {
        if ($role === RoleUtilisateur::Administratrice) {
            return [];
        }

        $catalogue = array_keys(config('permissions.catalogue'));
        $attribuables = $this->permissionsAttribuables($acteur);
        $actuelles = $user->exists ? $user->extraPermissions()->pluck('permission')->all() : [];

        $inconnues = array_diff($demandees, $catalogue);

        if ($inconnues !== []) {
            throw $this->erreur('permissions', 'Permission inconnue : '.implode(', ', $inconnues).'.');
        }

        $interdites = array_diff($demandees, $attribuables, $actuelles);

        if ($interdites !== []) {
            throw new AuthorizationException("Vous ne pouvez pas accorder un droit que vous n'avez pas.");
        }

        $conservees = array_diff($actuelles, $attribuables);
        $choisies = array_intersect($demandees, $attribuables);

        $finales = array_diff(
            array_unique(array_merge($conservees, $choisies)),
            $role->permissions(),
        );

        sort($finales);

        return array_values($finales);
    }

    /** @param  list<string>  $permissions */
    private function synchroniserPermissions(User $user, array $permissions): void
    {
        $user->extraPermissions()->whereNotIn('permission', $permissions)->delete();

        foreach ($permissions as $permission) {
            $user->extraPermissions()->firstOrCreate(['permission' => $permission]);
        }

        $user->forgetPermissionCache();
    }

    private function fermerSessions(User $user): void
    {
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
    }

    /** @throws AuthorizationException */
    private function autoriser(User $acteur): void
    {
        if (! $acteur->hasPermission('users.manage')) {
            throw new AuthorizationException("Vous n'avez pas le droit de gérer les utilisateurs.");
        }
    }

    /** @throws AuthorizationException */
    private function verifierCompteModifiable(User $user, User $acteur): void
    {
        if ($user->isAdmin() && ! $acteur->isAdmin()) {
            throw new AuthorizationException("Seule une administratrice peut modifier le compte d'une administratrice.");
        }
    }

    /** @throws AuthorizationException */
    private function verifierRoleAttribuable(RoleUtilisateur $role, User $acteur): void
    {
        if (! in_array($role, $this->rolesAttribuables($acteur), true)) {
            throw new AuthorizationException('Seule une administratrice peut attribuer le rôle administratrice.');
        }
    }

    private function erreur(string $champ, string $message): ValidationException
    {
        return ValidationException::withMessages([$champ => $message]);
    }
}
