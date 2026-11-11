<?php

namespace App\Http\Controllers;

use App\Enums\ZoneSalon;
use App\Http\Requests\ServiceRequest;
use App\Models\CategorieService;
use App\Models\Service;
use App\Services\CatalogueService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Service::class);

        // $statut = $request->query('statut');

        // $filters = [
        //     'q' => trim((string) $request->query('q', '')),
        //     'categorie' => $request->integer('categorie') ?: null,
        //     'zone' => ZoneSalon::tryFrom((string) $request->query('zone'))?->value,
        //     'statut' => in_array($statut, ['actifs', 'inactifs', 'tous'], true) ? $statut : 'tous',
        // ];

        $text = fn (string $key): string => is_string($request->query($key)) ? trim($request->query($key)) : '';

        $filters = [
            'q' => $text('q'),
            'categorie' => $request->integer('categorie') ?: null,
            'zone' => ZoneSalon::tryFrom($text('zone'))?->value,
            'statut' => in_array($text('statut'), ['actifs', 'inactifs', 'tous'], true) ? $text('statut') : 'tous',
        ];

        $services = Service::query()
            ->when($filters['q'] !== '', fn ($q) => $q->where('nom', 'like', '%'.addcslashes($filters['q'], '%_\\').'%'))
            ->when($filters['categorie'], fn ($q, $id) => $q->where('categorie_service_id', $id))
            ->when($filters['zone'], fn ($q, $zone) => $q->where('zone', $zone))
            ->when($filters['statut'] === 'actifs', fn ($q) => $q->where('is_active', true))
            ->when($filters['statut'] === 'inactifs', fn ($q) => $q->where('is_active', false))
            ->orderBy('nom')
            ->get();

        return view('services.index', [
            'categories' => CategorieService::orderBy('ordre')->get(),
            'services' => $services->groupBy('categorie_service_id'),
            'total' => $services->count(),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Service::class);

        return view('services.form', [
            'service' => new Service(['zone' => ZoneSalon::Mixte->value, 'is_active' => true]),
            'categories' => CategorieService::orderBy('ordre')->get(),
        ]);
    }

    public function store(ServiceRequest $request, CatalogueService $catalogue): RedirectResponse
    {
        $service = $catalogue->createService($request->payload());

        return redirect()->route('services.index')
            ->with('success', "La prestation « {$service->nom} » a été créée.");
    }

    public function edit(Service $service): View
    {
        Gate::authorize('update', $service);

        return view('services.form', [
            'service' => $service,
            'categories' => CategorieService::orderBy('ordre')->get(),
        ]);
    }

    public function update(ServiceRequest $request, Service $service, CatalogueService $catalogue): RedirectResponse
    {
        $catalogue->updateService($service, $request->payload());

        return redirect()->route('services.index')
            ->with('success', "La prestation « {$service->nom} » a été mise à jour.");
    }

    public function toggle(Service $service, CatalogueService $catalogue): RedirectResponse
    {
        Gate::authorize('update', $service);

        $catalogue->setServiceActive($service, ! $service->is_active);

        return back()->with('success', $service->is_active
            ? "« {$service->nom} » est de nouveau proposée."
            : "« {$service->nom} » est désactivée.");
    }
}