<?php

namespace Database\Seeders;

use App\Enums\CanalCommunication;
use App\Models\Client;
use Illuminate\Database\Seeder;

class DemoClientsSeeder extends Seeder
{
    public function run(): void
    {
        // [prénom, nom, téléphone, consentement, date de naissance, canal, actif]
        $clients = [
            ['Awa', '[Démo] Diallo', '+221770000101', true, '1990-05-12', CanalCommunication::Whatsapp, true],
            ['Mariama', '[Démo] Sow', '+221770000102', true, '1985-11-03', CanalCommunication::Whatsapp, true],
            ['Fatou', '[Démo] Ba', '+221760000103', false, '1994-02-28', CanalCommunication::Appel, true],
            ['Khady', '[Démo] Seck', '+221780000104', true, null, CanalCommunication::Sms, true],
            ['Aminata', '[Démo] Gueye', '+221750000105', false, '2001-08-19', CanalCommunication::Whatsapp, true],
            ['Ibrahima', '[Démo] Thiam', '+221770000106', false, '1988-12-25', null, true],
            ['Cheikh', '[Démo] Mbaye', '+221770000107', true, '2015-07-07', CanalCommunication::Whatsapp, true],
            ['Rokhaya', '[Démo] Cissé', '+221770000108', false, '1979-01-15', null, false],
        ];

        foreach ($clients as [$prenom, $nom, $telephone, $consentement, $naissance, $canal, $actif]) {
            Client::updateOrCreate(
                ['telephone' => $telephone],
                [
                    'prenom' => $prenom,
                    'nom' => $nom,
                    'date_naissance' => $naissance,
                    'canal_prefere' => $canal,
                    'consentement_promo' => $consentement,
                    'date_consentement' => $consentement ? now() : null,
                    'is_active' => $actif,
                ],
            );
        }
    }
}