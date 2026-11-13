<?php

namespace App\Http\Requests;

use App\Enums\RoleUtilisateur;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UtilisateurRequest extends FormRequest
{
    public function authorize(): bool
    {
        $compte = $this->route('utilisateur');

        return $compte
            ? $this->user()->can('update', $compte)
            : $this->user()->can('create', User::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        $creation = $this->route('utilisateur') === null;

        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => [
                'required', 'string', 'email', 'max:150',
                Rule::unique('users', 'email')->ignore($this->route('utilisateur')),
            ],
            'role' => ['required', Rule::enum(RoleUtilisateur::class)],
            'password' => [$creation ? 'required' : 'nullable', 'string', 'confirmed', 'max:100', Password::min(10)],
            'is_active' => ['sometimes', 'boolean'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::in(array_keys(config('permissions.catalogue')))],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Un compte utilise déjà cette adresse e-mail.',
            'password.confirmed' => 'Les deux mots de passe ne sont pas identiques.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nom',
            'email' => 'adresse e-mail',
            'role' => 'rôle',
            'password' => 'mot de passe',
            'permissions' => 'droits',
            'permissions.*' => 'droit',
        ];
    }

    /** @return array{name: string, email: string, role: RoleUtilisateur, password: ?string, is_active: bool} */
    public function payload(): array
    {
        $data = $this->validated();

        return [
            'name' => trim($data['name']),
            'email' => $data['email'],
            'role' => RoleUtilisateur::from($data['role']),
            'password' => filled($data['password'] ?? null) ? $data['password'] : null,
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
        ];
    }

    /** @return list<string> */
    public function permissions(): array
    {
        return array_values($this->validated()['permissions'] ?? []);
    }
}
