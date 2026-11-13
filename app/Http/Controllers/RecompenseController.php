<?php

namespace App\Http\Controllers;

use App\Models\Recompense;
use App\Services\FideliteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class RecompenseController extends Controller
{
    public function index(): View
    {
        Gate::authorize('loyalty.view');

        return view('fidelite.recompenses', [
            'recompenses' => Recompense::orderByDesc('is_active')->orderBy('points')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('loyalty.manage');

        return view('fidelite.recompense-form', ['recompense' => new Recompense(['points' => 50])]);
    }

    public function store(Request $request, FideliteService $fidelite): RedirectResponse
    {
        Gate::authorize('loyalty.manage');

        $recompense = $fidelite->enregistrerRecompense(null, $this->valider($request), $request->user());

        return redirect()->route('fidelite.recompenses.index')
            ->with('success', "La récompense « {$recompense->nom} » a été ajoutée.");
    }

    public function edit(Recompense $recompense): View
    {
        Gate::authorize('loyalty.manage');

        return view('fidelite.recompense-form', ['recompense' => $recompense]);
    }

    public function update(Request $request, Recompense $recompense, FideliteService $fidelite): RedirectResponse
    {
        Gate::authorize('loyalty.manage');

        $fidelite->enregistrerRecompense($recompense, $this->valider($request), $request->user());

        return redirect()->route('fidelite.recompenses.index')
            ->with('success', "La récompense « {$recompense->nom} » a été mise à jour.");
    }

    public function toggle(Request $request, Recompense $recompense, FideliteService $fidelite): RedirectResponse
    {
        Gate::authorize('loyalty.manage');

        $fidelite->definirRecompenseActive($recompense, ! $recompense->is_active, $request->user());

        return back()->with('success', $recompense->is_active
            ? "« {$recompense->nom} » est de nouveau proposée."
            : "« {$recompense->nom} » n'est plus proposée.");
    }

    /** @return array{nom: string, description: ?string, points: int} */
    private function valider(Request $request): array
    {
        $data = $request->validate([
            'nom' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'points' => ['required', 'integer', 'min:1', 'max:100000'],
        ]);

        return [
            'nom' => trim($data['nom']),
            'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
            'points' => (int) $data['points'],
        ];
    }
}
