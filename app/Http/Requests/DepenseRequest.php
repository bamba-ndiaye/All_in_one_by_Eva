<?php

namespace App\Http\Requests;

use App\Models\Depense;
use Illuminate\Foundation\Http\FormRequest;

class DepenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $depense = $this->route('depense');

        return $depense
            ? $this->user()->can('update', $depense)
            : $this->user()->can('create', Depense::class);
    }

    public function rules(): array
    {
        return [
            'categorie_depense_id' => ['required', 'integer'],
            'montant' => ['required', 'integer', 'min:1', 'max:100000000'],
            'date' => ['required', 'date_format:Y-m-d'],
            'description' => ['required', 'string', 'max:255'],
            'justificatif' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'montant.integer' => 'Le montant doit être un nombre entier de FCFA, sans virgule.',
            'justificatif.mimes' => 'Le justificatif doit être une image (JPG ou PNG) ou un PDF.',
            'justificatif.max' => 'Le justificatif ne doit pas dépasser 2 Mo.',
        ];
    }

    public function attributes(): array
    {
        return [
            'categorie_depense_id' => 'catégorie',
        ];
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        $data = $this->validated();

        return [
            'categorie_depense_id' => $data['categorie_depense_id'],
            'montant' => $data['montant'],
            'date' => $data['date'],
            'description' => $data['description'],
        ];
    }
}