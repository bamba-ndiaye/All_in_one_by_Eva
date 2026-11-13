<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nom', 'description', 'points', 'is_active'])]
class Recompense extends Model
{
    protected $table = 'recompenses';

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActives(Builder $query): void
    {
        $query->where('recompenses.is_active', true);
    }
}
