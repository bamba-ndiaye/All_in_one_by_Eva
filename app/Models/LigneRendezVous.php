<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['service_id', 'forfait_id', 'nom', 'prix_applique', 'duree_appliquee'])]
class LigneRendezVous extends Model
{
    public $timestamps = false;

    protected $table = 'rendez_vous_service';

    protected function casts(): array
    {
        return [
            'prix_applique' => 'integer',
            'duree_appliquee' => 'integer',
        ];
    }

    public function rendezVous(): BelongsTo
    {
        return $this->belongsTo(RendezVous::class, 'rendez_vous_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function forfait(): BelongsTo
    {
        return $this->belongsTo(Forfait::class);
    }
}