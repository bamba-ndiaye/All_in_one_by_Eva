<?php

namespace App\Http\Requests;

use App\Enums\CanalCommunication;
use App\Models\Client;
use App\Services\PhoneNormalizer;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        $client = $this->route('client');

        return $client
            ? $this->user()->can('update', $client)
            : $this->user()->can('create', Client::class);
    }

    public function rules(): array
    {
        $numeroValide = function (string $attribute, mixed $value, Closure $fail) {
            if (app(PhoneNormalizer::class)->normalize((string) $value) === null) {
                $fail('Ce numéro n\'est pas valide. Exemples : 77 123 45 67 ou +221 77 123 45 67.');
            }
        };

        return [
            'prenom' => ['required', 'string', 'max:100'],
            'nom' => ['required', 'string', 'max:100'],
            'telephone' => ['required', 'string', 'max:30', $numeroValide],
            'whatsapp' => ['nullable', 'string', 'max:30', $numeroValide],
            'email' => ['nullable', 'email', 'max:150'],
            'date_naissance' => ['nullable', 'date', 'before_or_equal:today', 'after:1900-01-01'],
            'canal_prefere' => ['nullable', Rule::enum(CanalCommunication::class)],
            'consentement_promo' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->has('telephone')) {
                    return;
                }

                $telephone = app(PhoneNormalizer::class)->normalize($this->input('telephone'));

                $existant = Client::where('telephone', $telephone)
                    ->when($this->route('client'), fn ($query, $client) => $query->whereKeyNot($client->getKey()))
                    ->first();

                if ($existant) {
                    $validator->errors()->add('telephone', $existant->is_active
                        ? "Ce numéro est déjà enregistré pour {$existant->nomComplet()}."
                        : "Ce numéro appartient à une fiche archivée ({$existant->nomComplet()}) : réactivez-la plutôt que d'en créer une nouvelle.");
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'telephone' => 'téléphone',
            'whatsapp' => 'numéro WhatsApp',
            'date_naissance' => 'date de naissance',
            'canal_prefere' => 'canal préféré',
            'consentement_promo' => 'consentement',
        ];
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        $data = $this->validated();
        $normalizer = app(PhoneNormalizer::class);

        $telephone = $normalizer->normalize($data['telephone']);
        $whatsapp = isset($data['whatsapp']) ? $normalizer->normalize($data['whatsapp']) : null;

        // Un numéro WhatsApp identique au téléphone n'est pas stocké deux fois.
        if ($whatsapp === $telephone) {
            $whatsapp = null;
        }

        return [
            'prenom' => trim($data['prenom']),
            'nom' => trim($data['nom']),
            'telephone' => $telephone,
            'whatsapp' => $whatsapp,
            'email' => isset($data['email']) ? Str::lower(trim($data['email'])) : null,
            'date_naissance' => $data['date_naissance'] ?? null,
            'canal_prefere' => $data['canal_prefere'] ?? null,
            'consentement_promo' => $this->boolean('consentement_promo'),
            'notes' => $data['notes'] ?? null,
        ];
    }
}