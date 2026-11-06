<?php

namespace App\Http\Controllers;

use App\Http\Requests\ForfaitRequest;
use App\Models\CategorieService;
use App\Models\Forfait;
use App\Models\Service;
use App\Services\CatalogueService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Gate;

class ForfaitController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Forfait::class);

        return view('forfaits.index', [
            'forfaits' => Forfait::with('services')->orderBy('nom')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Forfait::class);

        return view('forfaits.form', $this->formData(new Forfait(['is_active' => true])));
    }

    public function store(ForfaitRequest $request, CatalogueService $catalogue): RedirectResponse
    {
        $forfait = $catalogue->createForfait($request->payload(), $request->serviceIds());

        return redirect()->route('services.forfaits.index')
            ->with('success', "Le forfait « {$forfait->nom} » a été créé.");
    }

    public function edit(Forfait $forfait): View
    {
        Gate::authorize('update', $forfait);

        return view('forfaits.form', $this->formData($forfait));
    }

    public function update(ForfaitRequest $request, Forfait $forfait, CatalogueService $catalogue): RedirectResponse
    {
        $catalogue->updateForfait($forfait, $request->payload(), $request->serviceIds());

        return redirect()->route('services.forfaits.index')
            ->with('success', "Le forfait « {$forfait->nom} » a été mis à jour.");
    }

    public function toggle(Forfait $forfait, CatalogueService $catalogue): RedirectResponse
    {
        Gate::authorize('update', $forfait);

        $catalogue->setForfaitActive($forfait, ! $forfait->is_active);

        return back()->with('success', $forfait->is_active
            ? "Le forfait « {$forfait->nom} » est de nouveau proposé."
            : "Le forfait « {$forfait->nom} » est désactivé.");
    }

    /** @return array<string, mixed> */
    private function formData(Forfait $forfait): array
    {
        $selected = array_map('intval', (array) old(
            'services',
            $forfait->exists ? $forfait->services()->pluck('services.id')->all() : [],
        ));

        // Seules les prestations actives sont proposées, plus celles déjà dans le forfait.
        $categories = CategorieService::orderBy('ordre')
            ->with(['services' => fn ($query) => $query
                ->where(fn ($w) => $w->where('is_active', true)->orWhereIn('id', $selected))
                ->orderBy('nom')])
            ->get()
            ->filter(fn ($categorie) => $categorie->services->isNotEmpty());

        $map = $categories
            ->flatMap(fn ($categorie) => $categorie->services)
            ->mapWithKeys(fn (Service $service) => [
                $service->id => ['prix' => $service->prix, 'duree' => $service->duree_minutes],
            ]);

        return [
            'forfait' => $forfait,
            'categories' => $categories,
            'selected' => $selected,
            'map' => $map,
        ];
    }
}