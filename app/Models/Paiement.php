<?php

namespace App\Models;

use App\Enums\TypePaiement;
use App\Support\Fcfa;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'client_id', 'rendez_vous_id', 'type', 'paiement_origine_id', 'mode_paiement_id',
    'montant', 'montant_total', 'reste_apres', 'date', 'reference', 'libelle', 'motif', 'user_id',
])]
class Paiement extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'paiements';

    protected function casts(): array
    {
        return [
            'type' => TypePaiement::class,
            'montant' => 'integer',
            'montant_total' => 'integer',
            'reste_apres' => 'integer',
            'date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        // Le registre de caisse ne se modifie pas et ne se supprime pas : on annule, on ne réécrit pas.
        static::updating(fn () => false);
        static::deleting(fn () => false);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function rendezVous(): BelongsTo
    {
        return $this->belongsTo(RendezVous::class, 'rendez_vous_id');
    }

    public function mode(): BelongsTo
    {
        return $this->belongsTo(ModePaiement::class, 'mode_paiement_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Pour une annulation : le paiement annulé. */
    public function origine(): BelongsTo
    {
        return $this->belongsTo(self::class, 'paiement_origine_id');
    }

    /** Pour un paiement : l'annulation qui l'a annulé, s'il y en a une. */
    public function annulation(): HasOne
    {
        return $this->hasOne(self::class, 'paiement_origine_id');
    }

    /** Numéro de reçu : R-2026-000012. */
    public function numero(): string
    {
        $annee = ($this->created_at ?? now())->format('Y');

        return 'R-'.$annee.'-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    public function estAnnule(): bool
    {
        return $this->type === TypePaiement::Paiement && $this->annulation !== null;
    }

    public function montantFormate(): string
    {
        return Fcfa::format($this->montant);
    }

    public function scopeEntre(Builder $query, CarbonInterface|string $debut, CarbonInterface|string $fin): void
    {
        // whereDate compare uniquement le jour, quelle que soit la façon dont la date est stockée.
        $query
            ->whereDate('paiements.date', '>=', $debut instanceof CarbonInterface ? $debut->toDateString() : $debut)
            ->whereDate('paiements.date', '<=', $fin instanceof CarbonInterface ? $fin->toDateString() : $fin);
    }
}