<?php

namespace App\Models;

use App\Enums\ZoneSalon;
use App\Support\Fcfa;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['categorie_service_id', 'nom', 'description', 'prix', 'duree_minutes', 'zone', 'is_active'])]
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'prix' => 'integer',
            'duree_minutes' => 'integer',
            'zone' => ZoneSalon::class,
            'is_active' => 'boolean',
        ];
    }

    public function categorie(): BelongsTo
    {
        return $this->belongsTo(CategorieService::class, 'categorie_service_id');
    }

    public function forfaits(): BelongsToMany
    {
        return $this->belongsToMany(Forfait::class, 'forfait_service');
    }

    public function scopeActifs(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function prixFormate(): string
    {
        return Fcfa::format($this->prix);
    }

    public function dureeFormatee(): string
    {
        return self::formatDuree($this->duree_minutes);
    }

    public static function formatDuree(int $minutes): string
    {
        if ($minutes < 60) {
            return "{$minutes} min";
        }

        $heures = intdiv($minutes, 60);
        $reste = $minutes % 60;

        return $reste === 0 ? "{$heures} h" : sprintf('%d h %02d', $heures, $reste);
    }
}