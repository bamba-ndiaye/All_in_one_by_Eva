<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Services\AnniversaireService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AnniversaireController extends Controller
{
    public function index(Request $request, AnniversaireService $anniversaires): View
    {
        Gate::authorize('birthdays.view');

        $liste = $anniversaires->prochains(30);

        return view('anniversaires.index', [
            'aujourdhui' => $liste->where('dans', 0)->values(),
            'semaine' => $liste->filter(fn ($a) => $a['dans'] >= 1 && $a['dans'] <= 6)->values(),
            'mois' => $liste->filter(fn ($a) => $a['dans'] >= 7)->values(),
            'sansDate' => Client::actifs()->whereNull('date_naissance')->count(),
            'service' => $anniversaires,
            'user' => $request->user(),
        ]);
    }

    /**
     * Note les vœux comme envoyés puis ouvre WhatsApp avec le message prêt.
     */
    public function souhaiter(Request $request, Client $client, AnniversaireService $anniversaires): RedirectResponse
    {
        Gate::authorize('birthdays.view');

        abort_unless($anniversaires->peutSouhaiter($request->user(), $client), 403);

        $anniversaires->marquerSouhaite($client, $request->user());

        $date = $anniversaires->prochaineDate($client);

        return redirect()->away($anniversaires->lienWhatsapp($client, $date->year - $client->date_naissance->year));
    }
}
