<?php

namespace App\Http\Controllers;

use App\Enums\JourSemaine;
use App\Enums\StatutRendezVous;
use App\Models\Depense;
use App\Models\Paiement;
use App\Models\Produit;
use App\Models\RendezVous;
use App\Services\AnniversaireService;
use App\Services\CaisseService;
use App\Services\RapportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Tableau de bord : chaque bloc n'apparaît que si la personne a le droit correspondant.
 */
class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        CaisseService $caisse,
        RapportService $rapports,
        AnniversaireService $anniversaires,
    ): View {
        $user = $request->user();
        $aujourdhui = CarbonImmutable::today();
        $debutMois = $aujourdhui->startOfMonth();

        // --- Rendez-vous du jour (tous, ou seulement les siens) ----------------------
        $voitTout = $user->can('appointments.view');
        $voitSiens = $user->can('appointments.view_own');
        $monEmploye = $voitTout ? null : $user->employe;

        $rendezVous = ($voitTout || ($voitSiens && $monEmploye))
            ? RendezVous::query()
                ->with(['client', 'employe'])
                ->withSum('paiements', 'montant')
                ->whereDate('date', $aujourdhui->toDateString())
                ->whereNotIn('statut', [StatutRendezVous::Annule->value])
                ->when($monEmploye, fn ($q) => $q->where('employe_id', $monEmploye->id))
                ->orderBy('heure_debut')
                ->get()
            : null;

        // --- Caisse du jour et impayés ----------------------------------------------
        $voitCaisse = $user->can('viewAny', Paiement::class);

        // --- Finances (administratrice) -------------------------------------------
        $finances = null;
        $serie = null;
        $serieMois = null;

        if ($user->can('finance.view_revenue')) {
            $finances = $rapports->finances($debutMois, $aujourdhui);
            $serie = $this->serie30Jours($aujourdhui);
            $serieMois = $this->serie12Mois($aujourdhui);
        }

        return view('dashboard', [
            'user' => $user,
            'aujourdhui' => $aujourdhui,
            'rendezVous' => $rendezVous,
            'voitTousLesRdv' => $voitTout,
            'voitSiens' => $voitSiens,
            'peutEncaisser' => $user->can('payments.record'),
            'encaisseJour' => $voitCaisse ? $caisse->totalEncaisse($aujourdhui, $aujourdhui) : null,
            'impayes' => $voitCaisse ? RendezVous::impayes()->get(['id', 'prix_prevu']) : null,
            'anniversaires' => $user->can('birthdays.view') ? $anniversaires->prochains(7) : null,
            'stockBas' => ($user->can('stock.view') || $user->can('stock.manage'))
                ? Produit::enAlerte()->orderBy('quantite')->limit(5)->get()
                : null,
            'finances' => $finances,
            'serie' => $serie,
            'serieMois' => $serieMois,
            'affluence' => $voitTout ? $this->affluence($aujourdhui) : null,
            'prestations' => $finances ? $rapports->prestations($debutMois, $aujourdhui, 5) : null,
        ]);
    }

    /**
     * Encaissé et dépensé par jour sur les 30 derniers jours (aujourd'hui compris).
     *
     * @return list<array{date: string, libelle: string, encaisse: int, depenses: int}>
     */
    private function serie30Jours(CarbonImmutable $aujourdhui): array
    {
        $debut = $aujourdhui->subDays(29);

        $encaisse = Paiement::query()
            ->entre($debut, $aujourdhui)
            ->selectRaw('DATE(date) as jour, SUM(montant) as total')
            ->groupBy('jour')
            ->pluck('total', 'jour');

        $depenses = Depense::query()
            ->valides()
            ->entre($debut, $aujourdhui)
            ->selectRaw('DATE(date) as jour, SUM(montant) as total')
            ->groupBy('jour')
            ->pluck('total', 'jour');

        $serie = [];

        for ($jour = $debut; $jour->lte($aujourdhui); $jour = $jour->addDay()) {
            $cle = $jour->toDateString();
            $serie[] = [
                'date' => $cle,
                'libelle' => $jour->translatedFormat('j M'),
                'encaisse' => (int) ($encaisse[$cle] ?? 0),
                'depenses' => (int) ($depenses[$cle] ?? 0),
            ];
        }

        return $serie;
    }

    /**
     * Encaissé et dépensé par mois sur les 12 derniers mois (mois en cours compris, partiel).
     *
     * @return list<array{date: string, libelle: string, titre: string, encaisse: int, depenses: int, en_cours: bool}>
     */
    private function serie12Mois(CarbonImmutable $aujourdhui): array
    {
        $debut = $aujourdhui->startOfMonth()->subMonthsNoOverflow(11);

        // Totaux par jour (DATE() marche partout), regroupés par mois en PHP.
        $parMois = fn ($query) => $query
            ->entre($debut, $aujourdhui)
            ->selectRaw('DATE(date) as jour, SUM(montant) as total')
            ->groupBy('jour')
            ->pluck('total', 'jour')
            ->groupBy(fn ($total, $jour) => substr((string) $jour, 0, 7), preserveKeys: true)
            ->map(fn ($jours) => (int) $jours->sum());

        $encaisse = $parMois(Paiement::query());
        $depenses = $parMois(Depense::query()->valides());

        $serie = [];

        for ($mois = $debut; $mois->lte($aujourdhui); $mois = $mois->addMonthNoOverflow()) {
            $cle = $mois->format('Y-m');
            $serie[] = [
                'date' => $cle,
                'libelle' => $mois->translatedFormat('M y'),
                'titre' => ucfirst($mois->translatedFormat('F Y')),
                'encaisse' => $encaisse[$cle] ?? 0,
                'depenses' => $depenses[$cle] ?? 0,
                'en_cours' => $mois->isSameMonth($aujourdhui),
            ];
        }

        return $serie;
    }

    /**
     * Heures de pointe : rendez-vous (hors annulés) des 8 dernières semaines, par jour et par heure de début.
     *
     * @return array{heures: list<int>, jours: list<array{jour: JourSemaine, cases: list<int>}>,
     *               max: int, total: int, pic: ?array{jour: JourSemaine, heure: int, nombre: int}}
     */
    private function affluence(CarbonImmutable $aujourdhui): array
    {
        $creneaux = RendezVous::query()
            ->whereDate('date', '>=', $aujourdhui->subWeeks(8)->addDay()->toDateString())
            ->whereDate('date', '<=', $aujourdhui->toDateString())
            ->where('statut', '!=', StatutRendezVous::Annule->value)
            ->get(['date', 'heure_debut']);

        $comptes = [];

        foreach ($creneaux as $rdv) {
            $jour = CarbonImmutable::parse($rdv->date)->dayOfWeekIso;
            $heure = (int) substr((string) $rdv->heure_debut, 0, 2);
            $comptes[$jour][$heure] = ($comptes[$jour][$heure] ?? 0) + 1;
        }

        // Plage d'heures : au moins 9 h – 19 h, élargie si des rendez-vous débordent.
        $heuresVues = collect($comptes)->flatMap(fn ($h) => array_keys($h));
        $heures = range(min(9, $heuresVues->min() ?? 9), max(19, $heuresVues->max() ?? 19));

        $pic = null;
        $jours = [];

        foreach (JourSemaine::cases() as $jour) {
            $cases = [];

            foreach ($heures as $heure) {
                $nombre = $comptes[$jour->value][$heure] ?? 0;
                $cases[] = $nombre;

                if ($nombre > ($pic['nombre'] ?? 0)) {
                    $pic = ['jour' => $jour, 'heure' => $heure, 'nombre' => $nombre];
                }
            }

            $jours[] = ['jour' => $jour, 'cases' => $cases];
        }

        return [
            'heures' => $heures,
            'jours' => $jours,
            'max' => $pic['nombre'] ?? 0,
            'total' => $creneaux->count(),
            'pic' => $pic,
        ];
    }
}
