<?php

namespace App\Http\Controllers;

use App\Enums\TypePaiement;
use App\Http\Requests\PaiementRequest;
use App\Models\Client;
use App\Models\ModePaiement;
use App\Models\Paiement;
use App\Models\RendezVous;
use App\Services\CaisseService;
use App\Support\AgendaVue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PaiementController extends Controller
{
    public function index(Request $request, CaisseService $caisse): View
    {
        Gate::authorize('viewAny', Paiement::class);

        $voitRevenu = $request->user()->can('finance.view_revenue');
        $aujourdhui = now()->startOfDay();

        // Sans « finance.view_revenue », on ne voit que la feuille de caisse du jour.
        if ($voitRevenu) {
            $au = AgendaVue::lireDate($this->texte($request, 'au')) ?? $aujourdhui->copy();
            $du = AgendaVue::lireDate($this->texte($request, 'du')) ?? $aujourdhui->copy()->startOfMonth();
        } else {
            $du = $aujourdhui->copy();
            $au = $aujourdhui->copy();
        }

        if ($du->gt($au)) {
            [$du, $au] = [$au, $du];
        }

        if ($du->diffInDays($au) > 366) {
            $du = $au->copy()->subDays(366);
        }

        $mode = $this->entier($request->query('mode'));
        $type = TypePaiement::tryFrom($this->texte($request, 'type'))?->value;

        $paiements = Paiement::query()
            ->entre($du, $au)
            ->with(['client', 'mode', 'user', 'annulation', 'rendezVous.lignes'])
            ->when($mode, fn ($q, $id) => $q->where('mode_paiement_id', $id))
            ->when($type, fn ($q, $valeur) => $q->where('type', $valeur))
            ->orderByDesc('paiements.date')
            ->orderByDesc('paiements.id')
            ->paginate(25)
            ->withQueryString();

        return view('paiements.index', [
            'paiements' => $paiements,
            'du' => $du,
            'au' => $au,
            'mode' => $mode,
            'type' => $type,
            'modes' => ModePaiement::orderBy('ordre')->orderBy('id')->get(),
            'voitRevenu' => $voitRevenu,
            'total' => $voitRevenu ? $caisse->totalEncaisse($du, $au) : null,
            'parMode' => $voitRevenu ? $caisse->parMode($du, $au) : collect(),
        ]);
    }

    public function impayes(Request $request): View
    {
        Gate::authorize('viewAny', Paiement::class);

        $rendezVous = RendezVous::impayes()
            ->with(['client', 'employe', 'lignes'])
            ->withSum('paiements as montant_paye', 'montant')
            ->orderBy('date')
            ->orderBy('heure_debut')
            ->paginate(20)
            ->withQueryString();

        $voitRevenu = $request->user()->can('finance.view_revenue');
        $totalImpaye = null;

        if ($voitRevenu) {
            $du = (int) RendezVous::impayes()->sum('prix_prevu');
            $paye = (int) Paiement::whereIn('rendez_vous_id', RendezVous::impayes()->select('rendez_vous.id'))->sum('montant');
            $totalImpaye = $du - $paye;
        }

        return view('paiements.impayes', compact('rendezVous', 'voitRevenu', 'totalImpaye'));
    }

    public function create(Request $request, CaisseService $caisse): View|RedirectResponse
    {
        Gate::authorize('create', Paiement::class);

        $rdv = null;
        $id = $this->entier($request->query('rendez_vous'));

        if ($id) {
            $rdv = RendezVous::with(['client', 'lignes'])->findOrFail($id);

            if (! $caisse->estEncaissable($rdv)) {
                return redirect()->route('rendez-vous.show', $rdv)
                    ->with('error', "Ce rendez-vous « {$rdv->statut->label()} » ne peut pas être encaissé.");
            }

            if ((int) $rdv->prix_prevu <= 0) {
                return redirect()->route('rendez-vous.show', $rdv)
                    ->with('error', "Ce rendez-vous n'a aucun montant à encaisser.");
            }

            if ($rdv->resteAPayer() <= 0) {
                return redirect()->route('rendez-vous.show', $rdv)
                    ->with('error', 'Ce rendez-vous est déjà entièrement payé.');
            }
        }

        $reste = $rdv?->resteAPayer();

        $clientId = $rdv ? null : ($this->entier(old('client_id')) ?? $this->entier($request->query('client')));
        $client = $clientId ? Client::actifs()->find($clientId) : null;

        return view('paiements.form', [
            'rdv' => $rdv,
            'modes' => ModePaiement::actifs()->orderBy('ordre')->orderBy('id')->get(),
            'peutChoisirDate' => $request->user()->can('payments.correct'),
            'etat' => [
                'enRdv' => $rdv !== null,
                'urls' => ['clients' => route('paiements.clients')],
                'du' => $rdv ? (int) $rdv->prix_prevu : 0,
                'paye' => $rdv ? $rdv->montantPaye() : 0,
                'reste' => $reste ?? 0,
                'montant' => (string) old('montant', $reste ?? ''),
                'client' => $client ? [
                    'id' => $client->id,
                    'nom' => $client->nomComplet(),
                    'telephone' => $client->telephoneAffiche(),
                ] : null,
            ],
        ]);
    }

    public function store(PaiementRequest $request, CaisseService $caisse): RedirectResponse
    {
        $paiement = $caisse->enregistrer($request->payload(), $request->user());

        return redirect()->route('paiements.show', $paiement)
            ->with('success', "Paiement {$paiement->numero()} enregistré.");
    }

    public function show(Paiement $paiement): View
    {
        Gate::authorize('view', $paiement);

        $paiement->load(['client', 'mode', 'user', 'origine', 'annulation.user', 'rendezVous.lignes']);

        return view('paiements.show', [
            'paiement' => $paiement,
            'modes' => ModePaiement::actifs()->orderBy('ordre')->orderBy('id')->get(),
        ]);
    }

    public function recu(Paiement $paiement): View
    {
        Gate::authorize('view', $paiement);

        $paiement->load(['client', 'mode', 'user', 'origine', 'annulation', 'rendezVous.lignes']);

        return view('paiements.recu', compact('paiement'));
    }

    public function annuler(Request $request, Paiement $paiement, CaisseService $caisse): RedirectResponse
    {
        Gate::authorize('annuler', $paiement);

        $data = $request->validate([
            'motif' => ['required', 'string', 'min:5', 'max:255'],
        ]);

        $caisse->annuler($paiement, $data['motif'], $request->user());

        return redirect()->route('paiements.show', $paiement)
            ->with('success', "Le paiement {$paiement->numero()} a été annulé.");
    }

    public function corriger(Request $request, Paiement $paiement, CaisseService $caisse): RedirectResponse
    {
        Gate::authorize('annuler', $paiement);

        $data = $request->validate([
            'montant' => ['required', 'integer', 'min:1', 'max:10000000'],
            'mode_paiement_id' => ['required', 'integer', 'exists:modes_paiement,id'],
            'reference' => ['nullable', 'string', 'max:100'],
            'motif' => ['required', 'string', 'min:5', 'max:255'],
        ]);

        $corrige = $caisse->corriger($paiement, $data, $data['motif'], $request->user());

        return redirect()->route('paiements.show', $corrige)
            ->with('success', "Paiement corrigé : {$corrige->numero()}.");
    }

    /** Recherche de clients actifs (JSON), pour une vente sans rendez-vous. */
    public function clients(Request $request): JsonResponse
    {
        Gate::authorize('create', Paiement::class);
        Gate::authorize('viewAny', Client::class);

        $terme = $this->texte($request, 'q');

        if (mb_strlen($terme) < 2) {
            return response()->json(['clients' => []]);
        }

        $clients = Client::actifs()
            ->recherche($terme)
            ->orderBy('nom')
            ->orderBy('prenom')
            ->limit(10)
            ->get()
            ->map(fn (Client $client) => [
                'id' => $client->id,
                'nom' => $client->nomComplet(),
                'telephone' => $client->telephoneAffiche(),
            ])
            ->values();

        return response()->json(['clients' => $clients]);
    }

    private function texte(Request $request, string $cle): string
    {
        $valeur = $request->query($cle);

        return is_string($valeur) ? trim($valeur) : '';
    }

    private function entier(mixed $valeur): ?int
    {
        return (is_string($valeur) || is_int($valeur)) && ctype_digit((string) $valeur) && (int) $valeur > 0
            ? (int) $valeur
            : null;
    }
}