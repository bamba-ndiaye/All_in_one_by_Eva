<?php

namespace App\Models;

use App\Enums\TypeMouvementStock;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['produit_id', 'type', 'quantite', 'quantite_apres', 'motif', 'user_id'])]
class MouvementStock extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'mouvements_stock';

    protected function casts(): array
    {
        return [
            'type' => TypeMouvementStock::class,
            'quantite' => 'integer',
            'quantite_apres' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Le registre de stock ne se modifie pas et ne se supprime pas : on corrige par un inventaire.
        static::updating(fn () => false);
        static::deleting(fn () => false);
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
