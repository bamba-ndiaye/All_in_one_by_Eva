<?php

namespace App\Http\Requests;

use App\Models\Paiement;
use Illuminate\Foundation\Http\FormRequest;

class PaiementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Paiement::class);
    }

    public function rules(): array
    {
        return [
            'rendez_vous_id' => ['nullable', 'integer', 'exists:rendez_vous,id'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'libelle' => ['nullable', 'string', 'max:150'],
            'montant' => ['required', 'integer', 'min:1', 'max:10000000'],
            'mode_paiement_id' => ['required', 'integer', 'exists:modes_paiement,id'],
            'reference' => ['nullable', 'string', 'max:100'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function attributes(): array
    {
        return [
            'rendez_vous_id' => 'rendez-vous',
            'client_id' => 'client',
            'libelle' => 'libellé',
            'mode_paiement_id' => 'mode de paiement',
            'reference' => 'référence',
        ];
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        $data = $this->validated();

        return [
            'rendez_vous_id' => $data['rendez_vous_id'] ?? null,
            'client_id' => $data['client_id'] ?? null,
            'libelle' => $data['libelle'] ?? null,
            'montant' => $data['montant'],
            'mode_paiement_id' => $data['mode_paiement_id'],
            'reference' => $data['reference'] ?? null,
            'date' => $data['date'] ?? null,
        ];
    }
}