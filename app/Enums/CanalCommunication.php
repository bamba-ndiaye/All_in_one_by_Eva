<?php

namespace App\Enums;

enum CanalCommunication: string
{
    case Whatsapp = 'whatsapp';
    case Appel = 'appel';
    case Sms = 'sms';
    case Email = 'email';

    public function label(): string
    {
        return match ($this) {
            self::Whatsapp => 'WhatsApp',
            self::Appel => 'Appel téléphonique',
            self::Sms => 'SMS',
            self::Email => 'E-mail',
        };
    }
}