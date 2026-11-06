<?php

namespace App\Http\Requests;

use App\Enums\ZoneSalon;
use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $service = $this->route('service');

        return $service
            ? $this->user()->can('update', $service)
            : $this->user()->can('create', Service::class);
    }

    public function rules(): array
    {
        return [
            'categorie_service_id' => ['required', 'integer', 'exists:categories_services,id'],
            'nom' => [
                'required', 'string', 'max:150',
                Rule::unique('services', 'nom')
                    ->where('categorie_service_id', $this->input('categorie_service_id'))
                    ->ignore($this->route('service')),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'prix' => ['required', 'integer', 'min:0', 'max:10000000'],
            'duree_minutes' => ['required', 'integer', 'min:5', 'max:720'],
            'zone' => ['required', Rule::enum(ZoneSalon::class)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nom.unique' => 'Une prestation de ce nom existe déjà dans cette catégorie.',
            'prix.integer' => 'Le prix doit être un nombre entier en FCFA, sans virgule.',
        ];
    }

    public function attributes(): array
    {
        return [
            'categorie_service_id' => 'catégorie',
            'duree_minutes' => 'durée',
        ];
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        $data = $this->validated();

        return [
            'categorie_service_id' => (int) $data['categorie_service_id'],
            'nom' => $data['nom'],
            'description' => $data['description'] ?? null,
            'prix' => (int) $data['prix'],
            'duree_minutes' => (int) $data['duree_minutes'],
            'zone' => $data['zone'],
            'is_active' => $this->boolean('is_active'),
        ];
    }
}