<?php

namespace App\Models;

use App\Enums\ZoneSalon;
use App\Support\Fcfa;
use Database\Factories\ForfaitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['nom', 'description', 'prix', 'is_active'])]
class Forfait extends Model
{
    /** @use HasFactory<ForfaitFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'prix' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'forfait_service');
    }

    public function scopeActifs(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** Durée totale = somme des durées des services du forfait. */
    public function dureeTotale(): int
    {
        return (int) $this->services->sum('duree_minutes');
    }

    /** Prix qu'auraient les services pris séparément. */
    public function prixSepare(): int
    {
        return (int) $this->services->sum('prix');
    }

    /** Économie par rapport aux prestations séparées (négative si le forfait coûte plus cher). */
    public function economie(): int
    {
        return $this->prixSepare() - $this->prix;
    }

    /** Un forfait est réservé aux femmes dès qu'un de ses services l'est. */
    public function zone(): ZoneSalon
    {
        return $this->services->contains(fn (Service $service) => $service->zone === ZoneSalon::Femmes)
            ? ZoneSalon::Femmes
            : ZoneSalon::Mixte;
    }

    public function prixFormate(): string
    {
        return Fcfa::format($this->prix);
    }

    public function dureeFormatee(): string
    {
        return Service::formatDuree($this->dureeTotale());
    }
}