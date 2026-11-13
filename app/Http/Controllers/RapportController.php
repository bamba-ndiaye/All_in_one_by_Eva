<?php

namespace App\Http\Controllers;

use App\Services\RapportService;
use App\Support\AgendaVue;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RapportController extends Controller
{
    public function index(Request $request, RapportService $rapports): View
    {
        Gate::authorize('reports.view');

        [$debut, $fin, $periode] = $this->periode($request);
        $user = $request->user();

        $voitFinances = $user->can('finance.view_revenue');
        $voitEquipe = $user->can('staff.view_earnings');

        return view('rapports.index', [
            'debut' => $debut,
            'fin' => $fin,
            'periode' => $periode,
            'precedente' => $rapports->periodePrecedente($debut, $fin),
            'finances' => $voitFinances ? $rapports->finances($debut, $fin) : null,
            'activite' => $rapports->activite($debut, $fin),
            'prestations' => $rapports->prestations($debut, $fin),
            'equipe' => $voitEquipe ? $rapports->equipe($debut, $fin) : null,
            'peutExporter' => $user->can('reports.export'),
        ]);
    }

    public function export(Request $request, RapportService $rapports): StreamedResponse
    {
        Gate::authorize('reports.export');

        [$debut, $fin] = $this->periode($request);
        $user = $request->user();

        $finances = $user->can('finance.view_revenue') ? $rapports->finances($debut, $fin) : null;
        $activite = $rapports->activite($debut, $fin);
        $prestations = $rapports->prestations($debut, $fin, 50);
        $equipe = $user->can('staff.view_earnings') ? $rapports->equipe($debut, $fin) : null;

        $nom = 'rapport-'.$debut->toDateString().'-au-'.$fin->toDateString().'.csv';

        return response()->streamDownload(function () use ($debut, $fin, $finances, $activite, $prestations, $equipe) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM : accents corrects dans Excel
            $ligne = fn (array $l) => fputcsv($out, $l, ';');

            $ligne(['Rapport All in One by Eva', 'du '.$debut->format('d/m/Y').' au '.$fin->format('d/m/Y')]);
            $ligne([]);

            if ($finances) {
                $ligne(['FINANCES (FCFA)']);
                $ligne(['Encaissé', $finances['encaisse']]);
                $ligne(['Dépenses', $finances['depenses']]);
                $ligne(['Résultat estimé', $finances['resultat']]);
                $ligne(['Nombre de paiements', $finances['nb_paiements']]);
                $ligne(['Panier moyen', $finances['panier_moyen']]);
                $ligne(['Encaissé période précédente', $finances['encaisse_precedent']]);
                $ligne([]);
                $ligne(['Mode de paiement', 'Montant']);
                foreach ($finances['par_mode'] as $m) {
                    $ligne([$m['mode'], $m['total']]);
                }
                $ligne([]);
                $ligne(['Catégorie de dépense', 'Montant']);
                foreach ($finances['par_categorie'] as $c) {
                    $ligne([$c['categorie'], $c['total']]);
                }
                $ligne([]);
            }

            $ligne(['ACTIVITÉ']);
            $ligne(['Rendez-vous', $activite['total']]);
            foreach ($activite['par_statut'] as $s) {
                $ligne(['  '.$s['statut']->label(), $s['nombre']]);
            }
            $ligne(['Taux d\'annulation (%)', $activite['taux_annulation'] ?? '']);
            $ligne(['Taux d\'absence (%)', $activite['taux_absence'] ?? '']);
            $ligne(['Nouvelles clientes', $activite['nouvelles_clientes']]);
            $ligne([]);

            $ligne(['Prestation', 'Nombre', 'Montant (FCFA)']);
            foreach ($prestations as $p) {
                $ligne([$p['nom'], $p['nombre'], $p['montant']]);
            }

            if ($equipe) {
                $ligne([]);
                $ligne(['Employé', 'Rendez-vous', 'Prestations', 'Montant (FCFA)', 'Commission estimée (FCFA)']);
                foreach ($equipe as $e) {
                    $ligne([$e['employe']->nomComplet(), $e['rdv'], $e['prestations'], $e['montant'], $e['commission']]);
                }
            }

            fclose($out);
        }, $nom, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Période choisie : raccourci (mois, mois-dernier, 30-jours, annee) ou dates libres (366 jours maximum).
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string}
     */
    private function periode(Request $request): array
    {
        $aujourdhui = CarbonImmutable::today();
        $choix = is_string($request->query('periode')) ? $request->query('periode') : 'mois';

        [$debut, $fin] = match ($choix) {
            'mois-dernier' => [$aujourdhui->subMonthNoOverflow()->startOfMonth(), $aujourdhui->subMonthNoOverflow()->endOfMonth()->startOfDay()],
            '30-jours' => [$aujourdhui->subDays(29), $aujourdhui],
            'annee' => [$aujourdhui->startOfYear(), $aujourdhui],
            'libre' => [null, null],
            default => [$aujourdhui->startOfMonth(), $aujourdhui],
        };

        if ($choix === 'libre') {
            $du = AgendaVue::lireDate(is_string($request->query('du')) ? $request->query('du') : null);
            $au = AgendaVue::lireDate(is_string($request->query('au')) ? $request->query('au') : null);
            $debut = $du ? CarbonImmutable::parse($du->toDateString()) : $aujourdhui->startOfMonth();
            $fin = $au ? CarbonImmutable::parse($au->toDateString()) : $aujourdhui;

            if ($debut->gt($fin)) {
                [$debut, $fin] = [$fin, $debut];
            }

            if ($debut->diffInDays($fin) > 366) {
                $debut = $fin->subDays(366);
            }
        } elseif (! in_array($choix, ['mois-dernier', '30-jours', 'annee'], true)) {
            $choix = 'mois';
        }

        return [$debut, $fin, $choix];
    }
}
