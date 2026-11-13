<?php

namespace App\Models;

use App\Support\Fcfa;
use Carbon\CarbonInterface;
use Database\Factories\DepenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'categorie_depense_id', 'montant', 'date', 'description', 'justificatif',
    'user_id', 'annulee_at', 'annulee_par', 'motif_annulation',
])]
class Depense extends Model
{
    /** @use HasFactory<DepenseFactory> */
    use HasFactory;

    protected $table = 'depenses';

    protected function casts(): array
    {
        return [
            'montant' => 'integer',
            'date' => 'date',
            'annulee_at' => 'datetime',
        ];
    }

    public function categorie(): BelongsTo
    {
        return $this->belongsTo(CategorieDepense::class, 'categorie_depense_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function annuleePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'annulee_par');
    }

    /** Dépenses non annulées : seules elles comptent dans les totaux. */
    public function scopeValides(Builder $query): void
    {
        $query->whereNull('depenses.annulee_at');
    }

    public function scopeEntre(Builder $query, CarbonInterface|string $debut, CarbonInterface|string $fin): void
    {
        // whereDate compare uniquement le jour, quelle que soit la façon dont la date est stockée.
        $query
            ->whereDate('depenses.date', '>=', $debut instanceof CarbonInterface ? $debut->toDateString() : $debut)
            ->whereDate('depenses.date', '<=', $fin instanceof CarbonInterface ? $fin->toDateString() : $fin);
    }

    public function estAnnulee(): bool
    {
        return $this->annulee_at !== null;
    }

    public function aUnJustificatif(): bool
    {
        return $this->justificatif !== null && $this->justificatif !== '';
    }

    public function extensionJustificatif(): string
    {
        return $this->aUnJustificatif() ? strtolower(pathinfo($this->justificatif, PATHINFO_EXTENSION)) : '';
    }

    public function montantFormate(): string
    {
        return Fcfa::format($this->montant);
    }
}