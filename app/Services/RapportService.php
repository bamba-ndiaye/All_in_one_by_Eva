<?php

namespace App\Services;

use App\Enums\StatutRendezVous;
use App\Enums\TypeCommission;
use App\Enums\TypePaiement;
use App\Models\Client;
use App\Models\Employe;
use App\Models\Paiement;
use App\Models\RendezVous;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Indicateurs sur une période [début, fin], bornes comprises.
 */
class RapportService
{
    public function __construct(
        private readonly CaisseService $caisse,
        private readonly DepenseService $depenses,
    ) {}

    /**
     * @return array{encaisse: int, depenses: int, resultat: int, nb_paiements: int, panier_moyen: int,
     *               encaisse_precedent: int, evolution: ?int, par_mode: Collection, par_categorie: Collection}
     */
    public function finances(CarbonImmutable $debut, CarbonImmutable $fin): array
    {
        $encaisse = $this->caisse->totalEncaisse($debut, $fin);
        $depenses = $this->depenses->total($debut, $fin);

        // Paiements réellement conservés : on exclut ceux qui ont été annulés.
        $nbPaiements = Paiement::query()
            ->entre($debut, $fin)
            ->where('type', TypePaiement::Paiement->value)
            ->whereDoesntHave('annulation')
            ->count();

        [$debutPrec, $finPrec] = $this->periodePrecedente($debut, $fin);
        $precedent = $this->caisse->totalEncaisse($debutPrec, $finPrec);

        return [
            'encaisse' => $encaisse,
            'depenses' => $depenses,
            'resultat' => $encaisse - $depenses,
            'nb_paiements' => $nbPaiements,
            'panier_moyen' => $nbPaiements > 0 ? intdiv($encaisse, $nbPaiements) : 0,
            'encaisse_precedent' => $precedent,
            'evolution' => $precedent > 0 ? (int) round(($encaisse - $precedent) * 100 / $precedent) : null,
            'par_mode' => $this->caisse->parMode($debut, $fin),
            'par_categorie' => $this->depenses->parCategorie($debut, $fin),
        ];
    }

    /**
     * @return array{total: int, par_statut: Collection<int, array{statut: StatutRendezVous, nombre: int}>,
     *               termines: int, taux_annulation: ?int, taux_absence: ?int, nouvelles_clientes: int}
     */
    public function activite(CarbonImmutable $debut, CarbonImmutable $fin): array
    {
        $comptes = RendezVous::query()
            ->whereDate('date', '>=', $debut->toDateString())
            ->whereDate('date', '<=', $fin->toDateString())
            ->groupBy('statut')
            ->selectRaw('statut, COUNT(*) as nombre')
            ->pluck('nombre', 'statut')
            ->map(fn ($n) => (int) $n);

        $total = (int) $comptes->sum();
        $taux = fn (StatutRendezVous $s) => $total > 0 ? (int) round(($comptes[$s->value] ?? 0) * 100 / $total) : null;

        return [
            'total' => $total,
            'par_statut' => collect(StatutRendezVous::cases())
                ->map(fn ($s) => ['statut' => $s, 'nombre' => $comptes[$s->value] ?? 0])
                ->filter(fn ($l) => $l['nombre'] > 0)
                ->values(),
            'termines' => $comptes[StatutRendezVous::Termine->value] ?? 0,
            'taux_annulation' => $taux(StatutRendezVous::Annule),
            'taux_absence' => $taux(StatutRendezVous::ClientAbsent),
            'nouvelles_clientes' => Client::query()
                ->where('created_at', '>=', $debut->startOfDay())
                ->where('created_at', '<=', $fin->endOfDay())
                ->count(),
        ];
    }

    /**
     * Prestations réalisées (rendez-vous terminés), par nombre puis par montant.
     *
     * @return Collection<int, array{nom: string, nombre: int, montant: int}>
     */
    public function prestations(CarbonImmutable $debut, CarbonImmutable $fin, int $limite = 10): Collection
    {
        return DB::table('rendez_vous_service as l')
            ->join('rendez_vous as r', 'r.id', '=', 'l.rendez_vous_id')
            ->where('r.statut', StatutRendezVous::Termine->value)
            ->whereDate('r.date', '>=', $debut->toDateString())
            ->whereDate('r.date', '<=', $fin->toDateString())
            ->groupBy('l.service_id', 'l.nom')
            ->selectRaw('l.nom as nom, COUNT(*) as nombre, SUM(l.prix_applique) as montant')
            ->orderByDesc('nombre')
            ->orderByDesc('montant')
            ->limit($limite)
            ->get()
            ->map(fn ($l) => ['nom' => $l->nom, 'nombre' => (int) $l->nombre, 'montant' => (int) $l->montant]);
    }

    /**
     * Par employé : prestations réalisées, montant facturé et commission estimée.
     * La commission est calculée sur les prix figés des prestations terminées.
     *
     * @return Collection<int, array{employe: Employe, rdv: int, prestations: int, montant: int, commission: int}>
     */
    public function equipe(CarbonImmutable $debut, CarbonImmutable $fin): Collection
    {
        $stats = DB::table('rendez_vous_service as l')
            ->join('rendez_vous as r', 'r.id', '=', 'l.rendez_vous_id')
            ->where('r.statut', StatutRendezVous::Termine->value)
            ->whereDate('r.date', '>=', $debut->toDateString())
            ->whereDate('r.date', '<=', $fin->toDateString())
            ->groupBy('r.employe_id')
            ->selectRaw('r.employe_id, COUNT(DISTINCT r.id) as rdv, COUNT(*) as prestations, SUM(l.prix_applique) as montant')
            ->get()
            ->keyBy('employe_id');

        return Employe::query()
            ->whereIn('id', $stats->keys())
            ->get()
            ->map(function (Employe $employe) use ($stats) {
                $s = $stats[$employe->id];
                $montant = (int) $s->montant;
                $prestations = (int) $s->prestations;

                return [
                    'employe' => $employe,
                    'rdv' => (int) $s->rdv,
                    'prestations' => $prestations,
                    'montant' => $montant,
                    'commission' => match ($employe->commission_type) {
                        TypeCommission::Pourcentage => intdiv($montant * $employe->commission_valeur, 100),
                        TypeCommission::Fixe => $prestations * $employe->commission_valeur,
                        default => 0,
                    },
                ];
            })
            ->sortByDesc('montant')
            ->values();
    }

    /**
     * Période de comparaison :
     * - commence un 1er et tient dans un mois → mêmes jours du mois précédent (1–13 nov. → 1–13 oct.) ;
     * - sinon → même durée, juste avant.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function periodePrecedente(CarbonImmutable $debut, CarbonImmutable $fin): array
    {
        if ($debut->day === 1 && $debut->isSameMonth($fin)) {
            $debutPrec = $debut->subMonthNoOverflow();
            $finPrec = $fin->subMonthNoOverflow();

            return [$debutPrec, $finPrec->gt($debutPrec->endOfMonth()) ? $debutPrec->endOfMonth()->startOfDay() : $finPrec];
        }

        $jours = (int) $debut->diffInDays($fin) + 1;

        return [$debut->subDays($jours), $debut->subDay()];
    }
}
