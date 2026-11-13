<?php

namespace App\Http\Controllers;

use App\Services\ParametreService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ParametreController extends Controller
{
    public function edit(ParametreService $parametres): View
    {
        Gate::authorize('settings.manage');

        return view('parametres.edit', ['valeurs' => $parametres->valeurs()]);
    }

    public function update(Request $request, ParametreService $parametres): RedirectResponse
    {
        Gate::authorize('settings.manage');

        $modifiees = $parametres->enregistrer(
            $request->only(array_keys(ParametreService::DEFINITIONS)),
            $request->user(),
        );

        return redirect()->route('parametres.index')->with('success', $modifiees === []
            ? 'Aucun changement à enregistrer.'
            : 'Paramètres enregistrés. Ils s\'appliquent immédiatement.');
    }
}
