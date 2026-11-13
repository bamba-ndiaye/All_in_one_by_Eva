<?php

namespace App\Models;

use Database\Factories\ModePaiementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nom', 'ordre', 'is_active'])]
class ModePaiement extends Model
{
    /** @use HasFactory<ModePaiementFactory> */
    use HasFactory;

    protected $table = 'modes_paiement';

    protected function casts(): array
    {
        return [
            'ordre' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActifs(Builder $query): void
    {
        $query->where('is_active', true);
    }
}