<?php

namespace App\Http\Controllers;

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

        if ($user->can('finance.view_revenue')) {
            $finances = $rapports->finances($debutMois, $aujourdhui);
            $serie = $this->serie30Jours($aujourdhui);
        }

        return view('dashboard', [
            'user' => $user,
            'aujourdhui' => $aujourdhui,
            'rendezVous' => $rendezVous,
            'voitTousLesRdv' => $voitTout,
            'voitSiens' => $voitSiens,
            'encaisseJour' => $voitCaisse ? $caisse->totalEncaisse($aujourdhui, $aujourdhui) : null,
            'impayes' => $voitCaisse ? RendezVous::impayes()->get(['id', 'prix_prevu']) : null,
            'anniversaires' => $user->can('birthdays.view') ? $anniversaires->prochains(7) : null,
            'stockBas' => ($user->can('stock.view') || $user->can('stock.manage'))
                ? Produit::enAlerte()->orderBy('quantite')->limit(5)->get()
                : null,
            'finances' => $finances,
            'serie' => $serie,
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
}
