<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['client_id', 'annee', 'user_id'])]
class VoeuAnniversaire extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'voeux_anniversaire';

    protected function casts(): array
    {
        return ['annee' => 'integer'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
