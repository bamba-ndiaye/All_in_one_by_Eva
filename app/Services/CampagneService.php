<?php

namespace App\Services;

use App\Enums\CibleCampagne;
use App\Enums\StatutCampagne;
use App\Models\Campagne;
use App\Models\CampagneDestinataire;
use App\Models\Client;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CampagneService
{
    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * Clientes visées : toujours actives, consentantes et joignables.
     *
     * @return Builder<Client>
     */
    public function requeteCible(CibleCampagne $cible, ?int $jours): Builder
    {
        $depuis = now()->subDays(max(1, (int) $jours))->startOfDay();

        return Client::query()
            ->actifs()
            ->where('consentement_promo', true)
            ->where(fn ($q) => $q->whereNotNull('whatsapp')->orWhereNotNull('telephone'))
            ->when($cible === CibleCampagne::Nouvelles, fn ($q) => $q->where('clients.created_at', '>=', $depuis))
            ->when($cible === CibleCampagne::Absentes, fn ($q) => $q
                // Une cliente inscrite récemment n'est pas « absente ».
                ->where('clients.created_at', '<', $depuis)
                ->whereNotExists(fn ($s) => $s->select(DB::raw(1))->from('rendez_vous')
                    ->whereColumn('rendez_vous.client_id', 'clients.id')
                    ->whereDate('rendez_vous.date', '>=', $depuis->toDateString()))
                ->whereNotExists(fn ($s) => $s->select(DB::raw(1))->from('paiements')
                    ->whereColumn('paiements.client_id', 'clients.id')
                    ->whereDate('paiements.date', '>=', $depuis->toDateString())));
    }

    public function compterCible(CibleCampagne $cible, ?int $jours): int
    {
        return $this->requeteCible($cible, $jours)->count();
    }

    /**
     * @param  array{titre: string, message: string, cible: CibleCampagne, jours: ?int}  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function enregistrer(?Campagne $campagne, array $data, User $user): Campagne
    {
        $this->autoriser($user);

        if ($campagne && ! $campagne->estBrouillon()) {
            throw $this->erreur('campagne', 'Une campagne lancée ne peut plus être modifiée.');
        }

        $nouvelle = $campagne === null;
        $campagne ??= new Campagne;
        $campagne->fill([
            'titre' => $data['titre'],
            'message' => $data['message'],
            'cible' => $data['cible'],
            'jours' => $data['cible']->utiliseJours() ? $data['jours'] : null,
        ]);

        if ($nouvelle) {
            $campagne->forceFill(['statut' => StatutCampagne::Brouillon, 'user_id' => $user->id]);
        }

        if ($campagne->isDirty()) {
            $campagne->save();
            // Le texte du message n'est pas recopié dans le journal.
            $this->logger->log(
                $nouvelle ? 'campagne.creee' : 'campagne.modifiee',
                $campagne,
                ($nouvelle ? 'Campagne créée : ' : 'Campagne modifiée : ').$campagne->titre,
                null,
                ['cible' => $campagne->cible->value, 'jours' => $campagne->jours],
                $user,
            );
        }

        return $campagne;
    }

    /**
     * Fige la liste des destinataires et ouvre l'envoi.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function lancer(Campagne $campagne, User $user): Campagne
    {
        $this->autoriser($user);

        return DB::transaction(function () use ($campagne, $user) {
            $campagne = Campagne::whereKey($campagne->id)->lockForUpdate()->firstOrFail();

            if (! $campagne->estBrouillon()) {
                throw $this->erreur('campagne', 'Cette campagne est déjà lancée.');
            }

            $ids = $this->requeteCible($campagne->cible, $campagne->jours)->orderBy('prenom')->pluck('id');

            if ($ids->isEmpty()) {
                throw $this->erreur('campagne', 'Aucune cliente ne correspond à ce public : rien à envoyer.');
            }

            foreach ($ids as $id) {
                CampagneDestinataire::create(['campagne_id' => $campagne->id, 'client_id' => $id]);
            }

            $campagne->forceFill(['statut' => StatutCampagne::EnCours, 'lancee_at' => now()])->save();

            $this->logger->log('campagne.lancee', $campagne, "Campagne lancée : {$campagne->titre}", null, ['destinataires' => $ids->count()], $user);

            return $campagne;
        });
    }

    /**
     * Note qu'un message est parti et renvoie le lien WhatsApp à ouvrir.
     * Le consentement est revérifié : il a pu être retiré depuis le lancement.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function envoyer(CampagneDestinataire $destinataire, User $user): string
    {
        $this->autoriser($user);

        return DB::transaction(function () use ($destinataire, $user) {
            $destinataire = CampagneDestinataire::with(['campagne', 'client'])->whereKey($destinataire->id)->lockForUpdate()->firstOrFail();
            $campagne = $destinataire->campagne;
            $client = $destinataire->client;

            if ($campagne->statut !== StatutCampagne::EnCours) {
                throw $this->erreur('campagne', "Cette campagne n'est pas en cours d'envoi.");
            }

            if (! $client->consentement_promo || ! $client->is_active) {
                throw $this->erreur('campagne', "{$client->prenom} ne souhaite plus recevoir de messages : envoi annulé.");
            }

            if ($destinataire->envoye_at === null) {
                $destinataire->forceFill(['envoye_at' => now(), 'envoye_par' => $user->id])->save();
            }

            if (! $campagne->destinataires()->whereNull('envoye_at')->exists()) {
                $campagne->forceFill(['statut' => StatutCampagne::Terminee])->save();
            }

            return 'https://wa.me/'.ltrim((string) $client->numeroWhatsapp(), '+').'?text='.rawurlencode($campagne->messagePour($client));
        });
    }

    /**
     * Arrête l'envoi : les messages non envoyés ne le seront plus.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function cloturer(Campagne $campagne, User $user): void
    {
        $this->autoriser($user);

        if ($campagne->statut !== StatutCampagne::EnCours) {
            throw $this->erreur('campagne', "Seule une campagne en cours d'envoi peut être clôturée.");
        }

        $campagne->forceFill(['statut' => StatutCampagne::Terminee])->save();

        $this->logger->log('campagne.cloturee', $campagne, "Campagne clôturée : {$campagne->titre}", null, [
            'envoyes' => $campagne->destinataires()->whereNotNull('envoye_at')->count(),
        ], $user);
    }

    /** @throws AuthorizationException */
    private function autoriser(User $user): void
    {
        if (! $user->hasPermission('campaigns.manage')) {
            throw new AuthorizationException("Vous n'avez pas le droit de gérer les campagnes.");
        }
    }

    private function erreur(string $champ, string $message): ValidationException
    {
        return ValidationException::withMessages([$champ => $message]);
    }
}
