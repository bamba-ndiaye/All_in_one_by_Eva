<?php

namespace App\Models;

use Database\Factories\CategorieServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nom', 'ordre'])]
class CategorieService extends Model
{
    /** @use HasFactory<CategorieServiceFactory> */
    use HasFactory;

    protected $table = 'categories_services';

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }
}