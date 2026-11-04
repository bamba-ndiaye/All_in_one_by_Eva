<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'user_id', 'action', 'sujet_type', 'sujet_id', 'description',
    'anciennes_valeurs', 'nouvelles_valeurs', 'adresse_ip',
])]
class HistoriqueAction extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'historique_actions';

    protected function casts(): array
    {
        return [
            'anciennes_valeurs' => 'array',
            'nouvelles_valeurs' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // Le journal ne se modifie pas et ne se supprime pas.
        static::updating(fn () => false);
        static::deleting(fn () => false);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sujet(): MorphTo
    {
        return $this->morphTo('sujet');
    }
}