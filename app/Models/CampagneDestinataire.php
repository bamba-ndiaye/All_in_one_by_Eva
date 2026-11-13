<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['campagne_id', 'client_id'])]
class CampagneDestinataire extends Model
{
    public $timestamps = false;

    protected $table = 'campagne_destinataires';

    protected function casts(): array
    {
        return ['envoye_at' => 'datetime'];
    }

    public function campagne(): BelongsTo
    {
        return $this->belongsTo(Campagne::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function envoyePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'envoye_par');
    }
}
