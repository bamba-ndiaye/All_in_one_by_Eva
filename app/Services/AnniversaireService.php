<?php

namespace App\Services;

use App\Models\Client;
use App\Models\User;
use App\Models\VoeuAnniversaire;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AnniversaireService
{
    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * Anniversaires des clientes actives dans les $jours prochains jours (aujourd'hui compris),
     * du plus proche au plus lointain.
     *
     * @return Collection<int, array{client: Client, date: CarbonImmutable, dans: int, age: int, souhaite: bool}>
     */
    public function prochains(int $jours = 30): Collection
    {
        $aujourdhui = CarbonImmutable::today();
        $fin = $aujourdhui->addDays(max(0, $jours - 1));

        $clients = Client::query()->actifs()->whereNotNull('date_naissance')->get();

        $souhaites = VoeuAnniversaire::query()
            ->whereIn('client_id', $clients->pluck('id'))
            ->whereIn('annee', [$aujourdhui->year, $fin->year])
            ->get(['client_id', 'annee'])
            ->map(fn ($v) => $v->client_id.'-'.$v->annee)
            ->flip();

        return $clients
            ->map(function (Client $client) use ($aujourdhui, $souhaites) {
                $date = $this->prochaineDate($client, $aujourdhui);

                return [
                    'client' => $client,
                    'date' => $date,
                    'dans' => (int) $aujourdhui->diffInDays($date),
                    'age' => $date->year - $client->date_naissance->year,
                    'souhaite' => $souhaites->has($client->id.'-'.$date->year),
                ];
            })
            ->filter(fn (array $a) => $a['date']->lte($fin))
            ->sortBy([['dans', 'asc'], fn ($a, $b) => strcmp($a['client']->prenom, $b['client']->prenom)])
            ->values();
    }

    /**
     * Prochain anniversaire à partir d'aujourd'hui (aujourd'hui compris).
     * Une personne née un 29 février le fête le 28 février les années non bissextiles.
     */
    public function prochaineDate(Client $client, ?CarbonImmutable $aujourdhui = null): CarbonImmutable
    {
        $aujourdhui ??= CarbonImmutable::today();
        $naissance = $client->date_naissance;

        $date = $this->dansAnnee($naissance->month, $naissance->day, $aujourdhui->year);

        return $date->lt($aujourdhui)
            ? $this->dansAnnee($naissance->month, $naissance->day, $aujourdhui->year + 1)
            : $date;
    }

    public function message(Client $client, int $age): string
    {
        return strtr((string) config('salon.anniversaires.message'), [
            '{prenom}' => $client->prenom,
            '{age}' => (string) $age,
        ]);
    }

    /** Lien WhatsApp avec le message déjà écrit. */
    public function lienWhatsapp(Client $client, int $age): string
    {
        $numero = ltrim((string) $client->numeroWhatsapp(), '+');

        return 'https://wa.me/'.$numero.'?text='.rawurlencode($this->message($client, $age));
    }

    public function peutSouhaiter(User $user, Client $client): bool
    {
        return $user->hasPermission('messages.send')
            && $client->consentement_promo
            && $client->is_active
            && filled($client->numeroWhatsapp());
    }

    /**
     * Note que la cliente a été souhaitée cette année. Sans effet si c'est déjà fait.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function marquerSouhaite(Client $client, User $user): VoeuAnniversaire
    {
        if (! $user->hasPermission('messages.send')) {
            throw new AuthorizationException("Vous n'avez pas le droit d'envoyer des messages.");
        }

        if (! $client->consentement_promo) {
            throw ValidationException::withMessages([
                'client' => "{$client->prenom} n'a pas donné son accord pour recevoir des messages.",
            ]);
        }

        if (! $client->date_naissance) {
            throw ValidationException::withMessages(['client' => 'Cette fiche n\'a pas de date de naissance.']);
        }

        $annee = $this->prochaineDate($client)->year;

        $voeu = VoeuAnniversaire::firstOrCreate(
            ['client_id' => $client->id, 'annee' => $annee],
            ['user_id' => $user->id],
        );

        if ($voeu->wasRecentlyCreated) {
            // Pas de nom ni de numéro dans le journal.
            $this->logger->log('anniversaire.souhaite', $client, "Vœux d'anniversaire envoyés ({$annee})", null, ['annee' => $annee], $user);
        }

        return $voeu;
    }

    private function dansAnnee(int $mois, int $jour, int $annee): CarbonImmutable
    {
        if ($mois === 2 && $jour === 29 && ! checkdate(2, 29, $annee)) {
            $jour = 28;
        }

        return CarbonImmutable::create($annee, $mois, $jour)->startOfDay();
    }
}
