<?php

namespace App\Http\Requests;

use App\Enums\TypeCommission;
use App\Models\Employe;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class EmployeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $employe = $this->route('employe');

        return $employe
            ? $this->user()->can('update', $employe)
            : $this->user()->can('create', Employe::class);
    }

    private function canEditCommission(): bool
    {
        return $this->user()->can('staff.view_earnings');
    }

    public function rules(): array
    {
        $rules = [
            'nom' => ['required', 'string', 'max:100'],
            'prenom' => ['required', 'string', 'max:100'],
            'telephone' => ['nullable', 'string', 'regex:/^[0-9+()\s.\-]{6,30}$/'],
            'fonction' => ['required', 'string', 'max:100'],
            'specialites' => ['nullable', 'string', 'max:1000'],
            'user_id' => [
                'nullable', 'integer', 'exists:users,id',
                Rule::unique('employes', 'user_id')->ignore($this->route('employe')),
            ],
            'is_active' => ['sometimes', 'boolean'],
            'services' => ['nullable', 'array'],
            'services.*' => ['integer', 'distinct', 'exists:services,id'],
        ];

        // Sans la permission « staff.view_earnings », la commission n'est ni validée ni enregistrée.
        if ($this->canEditCommission()) {
            $rules['commission_type'] = ['nullable', Rule::enum(TypeCommission::class)];
            $rules['commission_valeur'] = ['nullable', 'integer', 'min:0', 'max:1000000', 'required_with:commission_type'];
        }

        return $rules;
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty() || ! $this->canEditCommission()) {
                    return;
                }

                if ($this->input('commission_type') === 'pourcentage' && (int) $this->input('commission_valeur') > 100) {
                    $validator->errors()->add('commission_valeur', 'Un pourcentage ne peut pas dépasser 100 %.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'telephone.regex' => 'Le numéro de téléphone ne peut contenir que des chiffres, des espaces et les signes + ( ) - .',
            'user_id.unique' => 'Ce compte est déjà lié à un autre employé.',
            'commission_valeur.required_with' => 'Indiquez la valeur de la commission.',
        ];
    }

    public function attributes(): array
    {
        return [
            'specialites' => 'spécialités',
            'user_id' => 'compte utilisateur',
            'services' => 'prestations',
            'services.*' => 'prestation',
            'commission_type' => 'type de commission',
            'commission_valeur' => 'valeur de la commission',
        ];
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        $data = $this->validated();

        $payload = [
            'nom' => trim($data['nom']),
            'prenom' => trim($data['prenom']),
            'telephone' => isset($data['telephone']) ? trim($data['telephone']) : null,
            'fonction' => trim($data['fonction']),
            'specialites' => $data['specialites'] ?? null,
            'user_id' => isset($data['user_id']) ? (int) $data['user_id'] : null,
            'is_active' => $this->boolean('is_active'),
        ];

        if ($this->canEditCommission()) {
            $type = $data['commission_type'] ?? null;
            $payload['commission_type'] = $type;
            $payload['commission_valeur'] = $type ? (int) ($data['commission_valeur'] ?? 0) : 0;
        }

        return $payload;
    }

    /** @return list<int> */
    public function serviceIds(): array
    {
        return array_map('intval', $this->validated()['services'] ?? []);
    }
}