<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Support\Facades\DB;

class ClientService
{
    public function __construct(private readonly ActivityLogger $logger)
    {
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): Client
    {
        return DB::transaction(function () use ($data) {
            if ($data['consentement_promo']) {
                $data['date_consentement'] = now();
            }

            $client = Client::create($data);

            // Le journal ne contient aucune donnée personnelle (ni numéro, ni date de naissance).
            $this->logger->log(
                'client.cree',
                $client,
                "Fiche client n° {$client->id} créée",
                null,
                ['consentement_promo' => (bool) $data['consentement_promo']],
            );

            return $client;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Client $client, array $data): Client
    {
        return DB::transaction(function () use ($client, $data) {
            $avant = (bool) $client->consentement_promo;
            $apres = (bool) $data['consentement_promo'];

            $client->fill($data);

            if ($apres && ! $avant) {
                $client->date_consentement = now();
                $client->date_retrait_consentement = null;
            } elseif (! $apres && $avant) {
                $client->date_retrait_consentement = now();
            }

            if ($client->isDirty()) {
                $champs = array_keys($client->getDirty());
                $client->save();

                $this->logger->log(
                    'client.modifie',
                    $client,
                    "Fiche client n° {$client->id} modifiée",
                    null,
                    ['champs' => $champs],
                );
            }

            if ($avant !== $apres) {
                $this->logger->log(
                    'client.consentement',
                    $client,
                    $apres ? 'Consentement aux messages promotionnels donné' : 'Consentement aux messages promotionnels retiré',
                    ['consentement_promo' => $avant],
                    ['consentement_promo' => $apres],
                );
            }

            return $client;
        });
    }

    public function setActive(Client $client, bool $active): void
    {
        if ($client->is_active === $active) {
            return;
        }

        DB::transaction(function () use ($client, $active) {
            $client->forceFill(['is_active' => $active])->save();

            $this->logger->log(
                $active ? 'client.reactive' : 'client.archive',
                $client,
                $active ? "Fiche client n° {$client->id} réactivée" : "Fiche client n° {$client->id} archivée",
            );
        });
    }
}