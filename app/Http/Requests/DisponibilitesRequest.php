<?php

namespace App\Http\Requests;

use App\Enums\JourSemaine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class DisponibilitesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('employe'));
    }

    public function rules(): array
    {
        return [
            'plages' => ['nullable', 'array', 'max:40'],
            'plages.*.jour' => ['required', 'integer', 'between:1,7'],
            'plages.*.debut' => ['required', 'date_format:H:i'],
            'plages.*.fin' => ['required', 'date_format:H:i'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $plages = $this->plages();

                foreach ($plages as $index => $plage) {
                    if ($plage['fin'] <= $plage['debut']) {
                        $validator->errors()->add(
                            "plages.{$index}.fin",
                            "L'heure de fin doit être postérieure à l'heure de début.",
                        );

                        return;
                    }
                }

                foreach (collect($plages)->groupBy('jour') as $jour => $rows) {
                    $triees = $rows->sortBy('debut')->values();

                    for ($i = 1; $i < $triees->count(); $i++) {
                        if ($triees[$i]['debut'] < $triees[$i - 1]['fin']) {
                            $validator->errors()->add(
                                'plages',
                                'Les plages du '.mb_strtolower(JourSemaine::from((int) $jour)->label()).' se chevauchent.',
                            );

                            return;
                        }
                    }
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'plages.*.jour' => 'jour',
            'plages.*.debut' => 'heure de début',
            'plages.*.fin' => 'heure de fin',
        ];
    }

    /** @return list<array{jour: int, debut: string, fin: string}> */
    public function plages(): array
    {
        return collect($this->input('plages', []))
            ->map(fn ($p) => [
                'jour' => (int) ($p['jour'] ?? 0),
                'debut' => (string) ($p['debut'] ?? ''),
                'fin' => (string) ($p['fin'] ?? ''),
            ])
            ->values()
            ->all();
    }
}