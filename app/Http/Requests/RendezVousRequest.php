<?php

namespace App\Http\Requests;

use App\Models\RendezVous;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RendezVousRequest extends FormRequest
{
    public function authorize(): bool
    {
        $rendezVous = $this->route('rendezVous');

        return $rendezVous
            ? $this->user()->can('update', $rendezVous)
            : $this->user()->can('create', RendezVous::class);
    }

    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'employe_id' => ['required', 'integer', 'exists:employes,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'heure_debut' => ['required', 'date_format:H:i'],
            'zone' => ['required', Rule::in(['mixte', 'femmes'])],
            'services' => ['nullable', 'array'],
            'services.*' => ['integer', 'distinct', 'exists:services,id'],
            'forfaits' => ['nullable', 'array'],
            'forfaits.*' => ['integer', 'distinct', 'exists:forfaits,id'],
            'statut' => ['sometimes', Rule::in(['en_attente', 'confirme'])],
            'commentaire' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'client_id' => 'client',
            'employe_id' => 'personne',
            'heure_debut' => 'heure de début',
            'services.*' => 'prestation',
            'forfaits.*' => 'forfait',
        ];
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        $data = $this->validated();

        return [
            'client_id' => (int) $data['client_id'],
            'employe_id' => (int) $data['employe_id'],
            'date' => $data['date'],
            'heure_debut' => $data['heure_debut'],
            'zone' => $data['zone'],
            'services' => array_map('intval', $data['services'] ?? []),
            'forfaits' => array_map('intval', $data['forfaits'] ?? []),
            'statut' => $data['statut'] ?? 'confirme',
            'commentaire' => $data['commentaire'] ?? null,
        ];
    }
}