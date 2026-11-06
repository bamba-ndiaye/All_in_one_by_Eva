<?php

namespace App\Http\Controllers;

use App\Http\Requests\DisponibilitesRequest;
use App\Models\Employe;
use App\Services\EmployeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DisponibiliteController extends Controller
{
    public function edit(Employe $employe): View
    {
        Gate::authorize('update', $employe);

        $rows = $employe->disponibilites()->get()
            ->map(fn ($d) => ['jour' => $d->jour_semaine->value, 'debut' => $d->debut(), 'fin' => $d->fin()])
            ->values()
            ->all();

        return view('employes.disponibilites', [
            'employe' => $employe,
            'rows' => old('plages', $rows),
        ]);
    }

    public function update(DisponibilitesRequest $request, Employe $employe, EmployeService $employes): RedirectResponse
    {
        $employes->saveDisponibilites($employe, $request->plages());

        return redirect()->route('employes.show', $employe)
            ->with('success', "Les horaires de {$employe->nomComplet()} ont été enregistrés.");
    }
}