<?php

namespace App\Http\Controllers;

use App\Enums\TypeCommission;
use App\Http\Requests\EmployeRequest;
use App\Models\CategorieService;
use App\Models\Employe;
use App\Models\User;
use App\Services\EmployeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class EmployeController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Employe::class);

        $statut = $request->query('statut');

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'statut' => in_array($statut, ['actifs', 'inactifs', 'tous'], true) ? $statut : 'tous',
        ];

        $like = '%'.addcslashes($filters['q'], '%_\\').'%';

        $employes = Employe::query()
            ->withCount('services')
            ->with('disponibilites')
            ->when($filters['q'] !== '', fn ($q) => $q->where(
                fn ($w) => $w->where('nom', 'like', $like)->orWhere('prenom', 'like', $like),
            ))
            ->when($filters['statut'] === 'actifs', fn ($q) => $q->where('is_active', true))
            ->when($filters['statut'] === 'inactifs', fn ($q) => $q->where('is_active', false))
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get();

        return view('employes.index', compact('employes', 'filters'));
    }

    public function create(): View
    {
        Gate::authorize('create', Employe::class);

        return view('employes.form', $this->formData(new Employe([
            'is_active' => true,
            'commission_valeur' => 0,
        ])));
    }

    public function store(EmployeRequest $request, EmployeService $employes): RedirectResponse
    {
        $employe = $employes->create($request->payload(), $request->serviceIds());

        return redirect()->route('employes.show', $employe)
            ->with('success', "La fiche de {$employe->nomComplet()} a été créée. Pensez à renseigner ses horaires.");
    }

    public function show(Employe $employe): View
    {
        Gate::authorize('view', $employe);

        $employe->load(['services.categorie', 'disponibilites', 'user']);

        return view('employes.show', [
            'employe' => $employe,
            'servicesParCategorie' => $employe->services
                ->sortBy('nom')
                ->groupBy(fn ($service) => $service->categorie->nom),
        ]);
    }

    public function edit(Employe $employe): View
    {
        Gate::authorize('update', $employe);

        return view('employes.form', $this->formData($employe));
    }

    public function update(EmployeRequest $request, Employe $employe, EmployeService $employes): RedirectResponse
    {
        $employes->update($employe, $request->payload(), $request->serviceIds());

        return redirect()->route('employes.show', $employe)
            ->with('success', "La fiche de {$employe->nomComplet()} a été mise à jour.");
    }

    public function toggle(Employe $employe, EmployeService $employes): RedirectResponse
    {
        Gate::authorize('update', $employe);

        $employes->setActive($employe, ! $employe->is_active);

        return back()->with('success', $employe->is_active
            ? "{$employe->nomComplet()} est de nouveau active."
            : "{$employe->nomComplet()} est désactivée.");
    }

    /** @return array<string, mixed> */
    private function formData(Employe $employe): array
    {
        $selected = array_map('intval', (array) old(
            'services',
            $employe->exists ? $employe->services()->pluck('services.id')->all() : [],
        ));

        // Prestations actives, plus celles déjà proposées par cet employé.
        $categories = CategorieService::orderBy('ordre')
            ->with(['services' => fn ($query) => $query
                ->where(fn ($w) => $w->where('is_active', true)->orWhereIn('id', $selected))
                ->orderBy('nom')])
            ->get()
            ->filter(fn ($categorie) => $categorie->services->isNotEmpty());

        // Comptes actifs pas encore liés à un employé (plus celui de cette fiche).
        $comptes = User::where('is_active', true)
            ->where(fn ($q) => $q->whereDoesntHave('employe')->orWhere('id', $employe->user_id))
            ->orderBy('name')
            ->get();

        return [
            'employe' => $employe,
            'categories' => $categories,
            'selected' => $selected,
            'comptes' => $comptes,
            'types' => TypeCommission::cases(),
        ];
    }
}