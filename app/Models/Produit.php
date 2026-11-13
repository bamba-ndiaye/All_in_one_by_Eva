<?php

namespace App\Models;

use App\Support\Fcfa;
use Database\Factories\ProduitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** La quantité n'est pas « fillable » : elle ne change que par un mouvement de stock. */
#[Fillable(['nom', 'reference', 'unite', 'prix_achat', 'prix_vente', 'seuil_alerte', 'is_active'])]
class Produit extends Model
{
    /** @use HasFactory<ProduitFactory> */
    use HasFactory;

    protected $table = 'produits';

    protected function casts(): array
    {
        return [
            'prix_achat' => 'integer',
            'prix_vente' => 'integer',
            'seuil_alerte' => 'integer',
            'quantite' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function mouvements(): HasMany
    {
        return $this->hasMany(MouvementStock::class);
    }

    public function scopeActifs(Builder $query): void
    {
        $query->where('produits.is_active', true);
    }

    /** Produits actifs dont la quantité est au seuil d'alerte ou en dessous. */
    public function scopeEnAlerte(Builder $query): void
    {
        $query->where('produits.is_active', true)->whereColumn('produits.quantite', '<=', 'produits.seuil_alerte');
    }

    public function estEnAlerte(): bool
    {
        return $this->is_active && $this->quantite <= $this->seuil_alerte;
    }

    public function estEnRupture(): bool
    {
        return $this->quantite <= 0;
    }

    public function quantiteFormatee(?int $quantite = null): string
    {
        $quantite ??= $this->quantite;

        // « 3 flacons », mais « 3 kg », « 3 ml » : les abréviations ne prennent pas de s.
        $pluriel = abs($quantite) > 1
            && mb_strlen($this->unite) > 2
            && ! preg_match('/[sx]$/u', $this->unite);

        return $quantite.' '.$this->unite.($pluriel ? 's' : '');
    }

    public function prixFormate(?int $prix): string
    {
        return $prix === null ? '—' : Fcfa::format($prix);
    }

    /** Valeur du stock au prix d'achat. */
    public function valeur(): int
    {
        return max(0, $this->quantite) * (int) $this->prix_achat;
    }
}
