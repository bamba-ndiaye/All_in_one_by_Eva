<?php

namespace App\Models;

use App\Enums\CibleCampagne;
use App\Enums\StatutCampagne;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['titre', 'message', 'cible', 'jours'])]
class Campagne extends Model
{
    protected $table = 'campagnes';

    protected function casts(): array
    {
        return [
            'cible' => CibleCampagne::class,
            'statut' => StatutCampagne::class,
            'jours' => 'integer',
            'lancee_at' => 'datetime',
        ];
    }

    public function destinataires(): HasMany
    {
        return $this->hasMany(CampagneDestinataire::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function estBrouillon(): bool
    {
        return $this->statut === StatutCampagne::Brouillon;
    }

    public function cibleLibelle(): string
    {
        return match ($this->cible) {
            CibleCampagne::Absentes => "Absentes depuis {$this->jours} jours",
            CibleCampagne::Nouvelles => "Inscrites depuis moins de {$this->jours} jours",
            default => $this->cible->label(),
        };
    }

    /** Message personnalisé pour une cliente. */
    public function messagePour(Client $client): string
    {
        return strtr($this->message, ['{prenom}' => $client->prenom]);
    }
}
