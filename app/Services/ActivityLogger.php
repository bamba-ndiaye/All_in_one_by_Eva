<?php

namespace App\Services;

use App\Models\HistoriqueAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ActivityLogger
{
    /** Champs jamais écrits dans le journal. */
    private const SENSITIVE = ['password', 'remember_token'];

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public function log(
        string $action,
        ?Model $subject = null,
        ?string $description = null,
        ?array $old = null,
        ?array $new = null,
        ?User $user = null,
    ): HistoriqueAction {
        $user ??= auth()->user();

        return HistoriqueAction::create([
            'user_id' => $user?->getKey(),
            'action' => $action,
            'sujet_type' => $subject?->getMorphClass(),
            'sujet_id' => $subject?->getKey(),
            'description' => $description,
            'anciennes_valeurs' => $this->redact($old),
            'nouvelles_valeurs' => $this->redact($new),
            'adresse_ip' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    private function redact(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        foreach (self::SENSITIVE as $key) {
            if (array_key_exists($key, $values)) {
                $values[$key] = '[masqué]';
            }
        }

        return $values;
    }
}