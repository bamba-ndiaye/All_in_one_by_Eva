<?php

namespace App\Models;

use App\Enums\JourSemaine;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['jour_semaine', 'heure_debut', 'heure_fin'])]
class Disponibilite extends Model
{
    protected $table = 'disponibilites';

    protected function casts(): array
    {
        return [
            'jour_semaine' => JourSemaine::class,
        ];
    }

    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class);
    }

    /** Heure de début au format HH:MM. */
    public function debut(): string
    {
        return substr($this->heure_debut, 0, 5);
    }

    /** Heure de fin au format HH:MM. */
    public function fin(): string
    {
        return substr($this->heure_fin, 0, 5);
    }
}