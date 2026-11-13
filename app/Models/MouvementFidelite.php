<?php

namespace App\Models;

use App\Enums\TypeMouvementFidelite;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['client_id', 'type', 'points', 'paiement_id', 'recompense_id', 'motif', 'user_id'])]
class MouvementFidelite extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'mouvements_fidelite';

    protected function casts(): array
    {
        return [
            'type' => TypeMouvementFidelite::class,
            'points' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // L'historique des points ne se modifie pas et ne se supprime pas : on corrige par un nouveau mouvement.
        static::updating(fn () => false);
        static::deleting(fn () => false);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function paiement(): BelongsTo
    {
        return $this->belongsTo(Paiement::class);
    }

    public function recompense(): BelongsTo
    {
        return $this->belongsTo(Recompense::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
