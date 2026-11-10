<?php

namespace App\Models;

use App\Enums\CanalCommunication;
use App\Services\PhoneNormalizer;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'prenom', 'nom', 'telephone', 'whatsapp', 'email', 'date_naissance', 'canal_prefere',
    'consentement_promo', 'date_consentement', 'date_retrait_consentement', 'notes', 'is_active',
])]
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'date_naissance' => 'date',
            'canal_prefere' => CanalCommunication::class,
            'consentement_promo' => 'boolean',
            'date_consentement' => 'datetime',
            'date_retrait_consentement' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function nomComplet(): string
    {
        return trim($this->prenom.' '.$this->nom);
    }

    public function telephoneAffiche(): string
    {
        return app(PhoneNormalizer::class)->format($this->telephone);
    }

    public function whatsappAffiche(): string
    {
        return app(PhoneNormalizer::class)->format($this->whatsapp);
    }

    /** Numéro à utiliser pour WhatsApp : le numéro dédié s'il existe, sinon le téléphone. */
    public function numeroWhatsapp(): string
    {
        return $this->whatsapp ?: $this->telephone;
    }

    public function scopeActifs(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Recherche par nom/prénom, ou par numéro si la saisie ressemble à un numéro.
     */
    public function scopeRecherche(Builder $query, string $term): void
    {
        $term = trim($term);

        if ($term === '') {
            return;
        }

        $digits = preg_replace('/\D+/', '', $term);

        // Saisie de type numéro (« 77 123 », « +221 77… ») : on cherche dans les numéros.
        if (preg_match('/^[+\d\s().\-]+$/', $term) && strlen($digits) >= 3) {
            $query->where(function (Builder $q) use ($digits) {
                $q->where('telephone', 'like', '%'.$digits.'%')
                    ->orWhere('whatsapp', 'like', '%'.$digits.'%');
            });

            return;
        }

        // Sinon chaque mot doit se retrouver dans le prénom ou le nom.
        foreach (preg_split('/\s+/', $term, -1, PREG_SPLIT_NO_EMPTY) as $mot) {
            $like = '%'.addcslashes($mot, '%_\\').'%';

            $query->where(fn (Builder $q) => $q->where('prenom', 'like', $like)->orWhere('nom', 'like', $like));
        }
    }

    /** @param array<string, mixed> $filters */
    public function scopeFiltrer(Builder $query, array $filters): void
    {
        $statut = $filters['statut'] ?? 'actifs';
        $consentement = $filters['consentement'] ?? '';

        $query
            ->recherche((string) ($filters['q'] ?? ''))
            ->when($statut === 'actifs', fn (Builder $q) => $q->where('is_active', true))
            ->when($statut === 'archives', fn (Builder $q) => $q->where('is_active', false))
            ->when($consentement === 'oui', fn (Builder $q) => $q->where('consentement_promo', true))
            ->when($consentement === 'non', fn (Builder $q) => $q->where('consentement_promo', false))
            ->when(($filters['naissance'] ?? '') === 'manquante', fn (Builder $q) => $q->whereNull('date_naissance'))
            ->when($filters['canal'] ?? null, fn (Builder $q, string $canal) => $q->where('canal_prefere', $canal));
    }
}