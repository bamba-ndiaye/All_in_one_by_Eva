<?php

namespace App\Models;

use App\Enums\TypeCommission;
use App\Support\Fcfa;
use Database\Factories\EmployeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

#[Fillable([
    'user_id', 'nom', 'prenom', 'telephone', 'fonction', 'specialites',
    'commission_type', 'commission_valeur', 'is_active',
])]
class Employe extends Model
{
    /** @use HasFactory<EmployeFactory> */
    use HasFactory;

    protected $table = 'employes';

    protected function casts(): array
    {
        return [
            'commission_type' => TypeCommission::class,
            'commission_valeur' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'employe_service');
    }

    public function disponibilites(): HasMany
    {
        return $this->hasMany(Disponibilite::class)
            ->orderBy('jour_semaine')
            ->orderBy('heure_debut');
    }

    public function scopeActifs(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function nomComplet(): string
    {
        return trim($this->prenom.' '.$this->nom);
    }

    public function commissionLibelle(): string
    {
        return match ($this->commission_type) {
            TypeCommission::Pourcentage => $this->commission_valeur.' %',
            TypeCommission::Fixe => Fcfa::format($this->commission_valeur).' par prestation',
            default => 'Aucune',
        };
    }

    /**
     * Plages horaires par jour (clé = numéro du jour), à partir de la relation chargée.
     *
     * @return Collection<int, Collection<int, string>>
     */
    public function horairesParJour(): Collection
    {
        return $this->disponibilites
            ->groupBy(fn (Disponibilite $d) => $d->jour_semaine->value)
            ->map(fn (Collection $plages) => $plages
                ->map(fn (Disponibilite $d) => $d->debut().' – '.$d->fin())
                ->values());
    }
}