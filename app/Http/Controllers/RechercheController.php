<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RechercheController extends Controller
{
    /** Recherche rapide de clientes (barre du haut) : nom, prénom ou numéro. */
    public function clients(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Client::class);

        $q = is_string($request->query('q')) ? trim($request->query('q')) : '';

        if (mb_strlen($q) < 2) {
            return response()->json(['clients' => []]);
        }

        $clients = Client::query()
            ->recherche($q)
            ->orderByDesc('is_active')
            ->orderBy('prenom')
            ->orderBy('nom')
            ->limit(8)
            ->get()
            ->map(fn (Client $c) => [
                'nom' => $c->nomComplet(),
                'telephone' => $c->telephoneAffiche(),
                'archive' => ! $c->is_active,
                'url' => route('clients.show', $c),
            ]);

        return response()->json(['clients' => $clients]);
    }
}
