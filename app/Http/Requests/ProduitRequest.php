<?php

namespace App\Http\Requests;

use App\Models\Produit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProduitRequest extends FormRequest
{
    public function authorize(): bool
    {
        $produit = $this->route('produit');

        return $produit
            ? $this->user()->can('update', $produit)
            : $this->user()->can('create', Produit::class);
    }

    protected function prepareForValidation(): void
    {
        $reference = is_string($this->input('reference')) ? trim($this->input('reference')) : $this->input('reference');

        $this->merge(['reference' => $reference === '' ? null : $reference]);
    }

    public function rules(): array
    {
        $rules = [
            'nom' => ['required', 'string', 'max:100'],
            'reference' => ['nullable', 'string', 'max:50', Rule::unique('produits', 'reference')->ignore($this->route('produit'))],
            'unite' => ['required', 'string', 'max:20'],
            'prix_achat' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'prix_vente' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'seuil_alerte' => ['required', 'integer', 'min:0', 'max:100000'],
        ];

        // La quantité de départ ne se saisit qu'à la création ; ensuite, elle passe par les mouvements.
        if (! $this->route('produit')) {
            $rules['quantite_initiale'] = ['nullable', 'integer', 'min:0', 'max:100000'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'reference.unique' => 'Un autre produit utilise déjà cette référence.',
        ];
    }

    public function attributes(): array
    {
        return [
            'reference' => 'référence',
            'unite' => 'unité',
            'prix_achat' => "prix d'achat",
            'prix_vente' => 'prix de vente',
            'seuil_alerte' => "seuil d'alerte",
            'quantite_initiale' => 'quantité en stock',
        ];
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        $data = $this->validated();

        return [
            'nom' => trim($data['nom']),
            'reference' => $data['reference'] ?? null,
            'unite' => trim($data['unite']),
            'prix_achat' => isset($data['prix_achat']) ? (int) $data['prix_achat'] : null,
            'prix_vente' => isset($data['prix_vente']) ? (int) $data['prix_vente'] : null,
            'seuil_alerte' => (int) $data['seuil_alerte'],
        ];
    }

    public function quantiteInitiale(): int
    {
        return (int) ($this->validated()['quantite_initiale'] ?? 0);
    }
}
