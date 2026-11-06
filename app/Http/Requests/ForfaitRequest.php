<?php

namespace App\Http\Requests;

use App\Models\Forfait;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ForfaitRequest extends FormRequest
{
    public function authorize(): bool
    {
        $forfait = $this->route('forfait');

        return $forfait
            ? $this->user()->can('update', $forfait)
            : $this->user()->can('create', Forfait::class);
    }

    public function rules(): array
    {
        return [
            'nom' => [
                'required', 'string', 'max:150',
                Rule::unique('forfaits', 'nom')->ignore($this->route('forfait')),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'prix' => ['required', 'integer', 'min:0', 'max:10000000'],
            'is_active' => ['sometimes', 'boolean'],
            'services' => ['required', 'array', 'min:2'],
            'services.*' => ['integer', 'distinct', 'exists:services,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'nom.unique' => 'Un forfait de ce nom existe déjà.',
            'prix.integer' => 'Le prix doit être un nombre entier en FCFA, sans virgule.',
            'services.required' => 'Un forfait doit regrouper au moins 2 prestations.',
            'services.min' => 'Un forfait doit regrouper au moins 2 prestations.',
        ];
    }

    public function attributes(): array
    {
        return [
            'services' => 'prestations',
            'services.*' => 'prestation',
        ];
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        $data = $this->validated();

        return [
            'nom' => $data['nom'],
            'description' => $data['description'] ?? null,
            'prix' => (int) $data['prix'],
            'is_active' => $this->boolean('is_active'),
        ];
    }

    /** @return list<int> */
    public function serviceIds(): array
    {
        return array_map('intval', $this->validated()['services']);
    }
}