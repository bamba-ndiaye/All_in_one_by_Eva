<?php

namespace App\Models;

use App\Enums\RoleUtilisateur;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use InvalidArgumentException;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** @var list<string>|null */
    private ?array $permissionCache = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => RoleUtilisateur::class,
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function extraPermissions(): HasMany
    {
        return $this->hasMany(UserPermission::class);
    }
        public function employe(): HasOne
    {
        return $this->hasOne(Employe::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === RoleUtilisateur::Administratrice;
    }

    /**
     * Permissions du rôle + permissions accordées individuellement.
     *
     * @return list<string>
     */
    public function allPermissions(): array
    {
        return $this->permissionCache ??= array_values(array_unique(array_merge(
            $this->role->permissions(),
            $this->extraPermissions()->pluck('permission')->all(),
        )));
    }

    public function hasPermission(string $permission): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return $this->isAdmin() || in_array($permission, $this->allPermissions(), true);
    }

    public function grantPermission(string $permission): void
    {
        if (! array_key_exists($permission, config('permissions.catalogue'))) {
            throw new InvalidArgumentException("Permission inconnue : {$permission}");
        }

        $this->extraPermissions()->firstOrCreate(['permission' => $permission]);
        $this->forgetPermissionCache();
    }

    public function revokePermission(string $permission): void
    {
        $this->extraPermissions()->where('permission', $permission)->delete();
        $this->forgetPermissionCache();
    }

    public function forgetPermissionCache(): void
    {
        $this->permissionCache = null;
    }
}