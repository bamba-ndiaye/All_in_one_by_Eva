<?php

namespace App\Http\Controllers;

use App\Enums\TypeMouvementFidelite;
use App\Models\Client;
use App\Models\MouvementFidelite;
use App\Models\Recompense;
use App\Services\FideliteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class FideliteController extends Controller
{
    public function index(Request $request, FideliteService $fidelite): View
    {
        Gate::authorize('loyalty.view');

        $q = is_string($request->query('q')) ? trim($request->query('q')) : '';

        // Sans recherche : les clientes qui ont des points. Avec recherche : toutes, pour pouvoir ouvrir leur fiche.
        $clients = Client::query()
            ->withSum('mouvementsFidelite as points', 'points')
            ->when($q === '', fn ($query) => $query->whereHas('mouvementsFidelite'))
            ->recherche($q)
            ->orderByDesc('points')
            ->orderBy('prenom')
            ->paginate(25)
            ->withQueryString();

        $debutMois = now()->startOfMonth();

        return view('fidelite.index', [
            'clients' => $clients,
            'q' => $q,
            'recompenses' => Recompense::actives()->orderBy('points')->get(),
            'fcfaParPoint' => $fidelite->fcfaParPoint(),
            'pointsEnCours' => (int) MouvementFidelite::sum('points'),
            'nbClientes' => Client::whereHas('mouvementsFidelite')->count(),
            'utilisationsMois' => MouvementFidelite::where('type', TypeMouvementFidelite::Utilisation->value)
                ->where('created_at', '>=', $debutMois)
                ->count(),
        ]);
    }

    public function client(Request $request, Client $client, FideliteService $fidelite): View
    {
        Gate::authorize('loyalty.view');

        $solde = $client->pointsFidelite();

        return view('fidelite.client', [
            'client' => $client,
            'solde' => $solde,
            'mouvements' => $client->mouvementsFidelite()->with(['user', 'paiement', 'recompense'])->latest('id')->paginate(20),
            'recompenses' => Recompense::actives()->orderBy('points')->get(),
            'peutUtiliser' => $fidelite->peutUtiliser($request->user()),
            'peutCorriger' => $request->user()->can('loyalty.manage'),
            'fcfaParPoint' => $fidelite->fcfaParPoint(),
        ]);
    }

    public function utiliser(Request $request, Client $client, FideliteService $fidelite): RedirectResponse
    {
        $data = $request->validate(
            ['recompense_id' => ['required', 'integer', 'exists:recompenses,id']],
            [],
            ['recompense_id' => 'récompense'],
        );

        $mouvement = $fidelite->utiliser($client, Recompense::findOrFail($data['recompense_id']), $request->user());

        return redirect()->route('fidelite.client', $client)
            ->with('success', "« {$mouvement->motif} » accordé à {$client->nomComplet()} : ".abs($mouvement->points).' points déduits.');
    }

    public function corriger(Request $request, Client $client, FideliteService $fidelite): RedirectResponse
    {
        Gate::authorize('loyalty.manage');

        $data = $request->validate([
            'points' => ['required'],
            'motif' => ['required', 'string', 'max:255'],
        ]);

        $fidelite->corriger($client, $data['points'], $data['motif'], $request->user());

        return redirect()->route('fidelite.client', $client)
            ->with('success', "Les points de {$client->nomComplet()} ont été corrigés.");
    }
}
