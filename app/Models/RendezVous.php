<?php

namespace App\Models;

use App\Enums\StatutRendezVous;
use App\Enums\ZoneSalon;
use App\Support\Fcfa;
use App\Support\Heure;
use Carbon\Carbon;
use Database\Factories\RendezVousFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'client_id', 'employe_id', 'date', 'heure_debut', 'heure_fin', 'zone', 'statut',
    'prix_prevu', 'commentaire', 'motif_annulation', 'created_by',
])]
class RendezVous extends Model
{
    /** @use HasFactory<RendezVousFactory> */
    use HasFactory;

    protected $table = 'rendez_vous';

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'zone' => ZoneSalon::class,
            'statut' => StatutRendezVous::class,
            'prix_prevu' => 'integer',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class);
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(LigneRendezVous::class, 'rendez_vous_id');
    }

    /** Rendez-vous qui occupent le créneau de leur employé. */
    public function scopeBloquants(Builder $query): void
    {
        $query->whereIn('statut', StatutRendezVous::bloquantsValeurs());
    }

    public function debut(): string
    {
        return substr($this->heure_debut, 0, 5);
    }

    public function fin(): string
    {
        return substr($this->heure_fin, 0, 5);
    }

    public function dureeMinutes(): int
    {
        return Heure::toMinutes($this->heure_fin) - Heure::toMinutes($this->heure_debut);
    }

    public function debutAt(): Carbon
    {
        return $this->date->copy()->startOfDay()->addMinutes(Heure::toMinutes($this->heure_debut));
    }

    public function prixFormate(): string
    {
        return Fcfa::format($this->prix_prevu);
    }
}