<?php

namespace App\Models;

use Database\Factories\CategorieDepenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nom', 'ordre', 'is_active'])]
class CategorieDepense extends Model
{
    /** @use HasFactory<CategorieDepenseFactory> */
    use HasFactory;

    protected $table = 'categories_depenses';

    protected function casts(): array
    {
        return [
            'ordre' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function depenses(): HasMany
    {
        return $this->hasMany(Depense::class, 'categorie_depense_id');
    }

    public function scopeActives(Builder $query): void
    {
        $query->where('is_active', true);
    }
}