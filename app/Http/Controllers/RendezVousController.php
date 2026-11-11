<?php

namespace App\Http\Controllers;

use App\Enums\StatutRendezVous;
use App\Http\Requests\RendezVousRequest;
use App\Models\CategorieService;
use App\Models\Client;
use App\Models\Employe;
use App\Models\Forfait;
use App\Models\HistoriqueAction;
use App\Models\RendezVous;
use App\Services\AppointmentScheduler;
use App\Services\RendezVousService;
use App\Support\AgendaVue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RendezVousController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', RendezVous::class);

        $user = $request->user();
        $periode = AgendaVue::depuisRequete($request->query('vue'), $request->query('date'));

        // Sans « appointments.view », on ne voit que les rendez-vous de sa propre fiche employé.
        $voitTout = $user->can('appointments.view');
        $monEmploye = $voitTout ? null : $user->employe;
        $filtreEmploye = $voitTout ? $this->entier($request->query('employe')) : null;

        $rendezVous = RendezVous::query()
            ->with(['client', 'employe', 'lignes'])
            ->where('date', '>=', $periode->debut()->toDateString())
            ->where('date', '<', $periode->fin()->copy()->addDay()->toDateString())
            ->when(! $voitTout, fn ($q) => $monEmploye ? $q->where('employe_id', $monEmploye->id) : $q->whereRaw('1 = 0'))
            ->when($filtreEmploye, fn ($q, $id) => $q->where('employe_id', $id))
            ->orderBy('date')
            ->orderBy('heure_debut')
            ->get();

        $parJour = $rendezVous->groupBy(fn (RendezVous $rdv) => $rdv->date->toDateString());

        $employes = collect();

        if ($periode->vue === AgendaVue::JOUR) {
            $jourIso = $periode->date->dayOfWeekIso;

            $employes = Employe::with('disponibilites')
                ->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $rendezVous->pluck('employe_id')))
                ->when(! $voitTout, fn ($q) => $monEmploye ? $q->whereKey($monEmploye->id) : $q->whereRaw('1 = 0'))
                ->when($filtreEmploye, fn ($q, $id) => $q->whereKey($id))
                ->orderBy('nom')
                ->orderBy('prenom')
                ->get()
                ->filter(fn (Employe $employe) => $rendezVous->contains('employe_id', $employe->id)
                    || $employe->disponibilites->contains(fn ($d) => $d->jour_semaine->value === $jourIso))
                ->values();
        }

        return view('rendez-vous.index', [
            'periode' => $periode,
            'rendezVous' => $rendezVous,
            'parJour' => $parJour,
            'employes' => $employes,
            'listeEmployes' => $voitTout ? Employe::actifs()->orderBy('nom')->orderBy('prenom')->get() : collect(),
            'filtreEmploye' => $filtreEmploye,
            'voitTout' => $voitTout,
            'monEmploye' => $monEmploye,
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', RendezVous::class);

        return view('rendez-vous.form', $this->formData(null, [
            'date' => AgendaVue::lireDate($request->query('date'))?->toDateString() ?? now()->toDateString(),
            'employe_id' => $this->entier($request->query('employe')),
            'client_id' => $this->entier($request->query('client')),
        ]));
    }

    public function store(RendezVousRequest $request, RendezVousService $rendezVous): RedirectResponse
    {
        $rdv = $rendezVous->creer($request->payload(), $request->user());

        return redirect()->route('rendez-vous.show', $rdv)
            ->with('success', 'Le rendez-vous a été enregistré.');
    }

    public function show(RendezVous $rendezVous): View
    {
        Gate::authorize('view', $rendezVous);

        $rendezVous->load(['client', 'employe', 'lignes.forfait', 'createur']);

        // L'historique des modifications n'est visible que pour le personnel qui gère les rendez-vous.
        $journal = auth()->user()->can('create', RendezVous::class)
            ? HistoriqueAction::with('user')
                ->where('sujet_type', $rendezVous->getMorphClass())
                ->where('sujet_id', $rendezVous->id)
                ->orderByDesc('id')
                ->limit(15)
                ->get()
            : collect();

        return view('rendez-vous.show', compact('rendezVous', 'journal'));
    }

    public function edit(RendezVous $rendezVous): View|RedirectResponse
    {
        Gate::authorize('update', $rendezVous);

        if (! $rendezVous->statut->estModifiable()) {
            return redirect()->route('rendez-vous.show', $rendezVous)
                ->with('error', "Ce rendez-vous est « {$rendezVous->statut->label()} » : il ne peut plus être modifié.");
        }

        $rendezVous->load(['client', 'lignes']);

        return view('rendez-vous.form', $this->formData($rendezVous));
    }

    public function update(RendezVousRequest $request, RendezVous $rendezVous, RendezVousService $service): RedirectResponse
    {
        $service->modifier($rendezVous, $request->payload(), $request->user());

        return redirect()->route('rendez-vous.show', $rendezVous)
            ->with('success', 'Le rendez-vous a été mis à jour.');
    }

    public function statut(Request $request, RendezVous $rendezVous, RendezVousService $service): RedirectResponse
    {
        $data = $request->validate([
            'statut' => ['required', Rule::enum(StatutRendezVous::class)],
            'motif' => ['nullable', 'string', 'max:255'],
        ]);

        $vers = StatutRendezVous::from($data['statut']);

        Gate::authorize('changeStatus', [$rendezVous, $vers]);

        $service->changerStatut($rendezVous, $vers, $request->user(), $data['motif'] ?? null);

        return back()->with('success', "Rendez-vous : {$vers->label()}.");
    }

    /** Heures de début possibles (JSON), pour le formulaire. */
    public function creneaux(Request $request, AppointmentScheduler $scheduler): JsonResponse
    {
        Gate::authorize('create', RendezVous::class);

        $data = $request->validate([
            'employe' => ['required', 'integer', 'exists:employes,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'services' => ['nullable', 'array'],
            'services.*' => ['integer'],
            'forfaits' => ['nullable', 'array'],
            'forfaits.*' => ['integer'],
            'ignore' => ['nullable', 'integer'],
        ]);

        $employe = Employe::with('disponibilites')->findOrFail($data['employe']);
        $date = AgendaVue::lireDate($data['date']);

        // La durée vient du serveur (prestations choisies), jamais du navigateur.
        $compo = $scheduler->composer($data['services'] ?? [], $data['forfaits'] ?? []);

        if ($date === null || $compo['duree'] === 0) {
            return response()->json(['creneaux' => [], 'duree' => 0]);
        }

        $ignore = isset($data['ignore']) ? RendezVous::find($data['ignore']) : null;

        return response()->json([
            'creneaux' => $scheduler->creneauxLibres(
                $employe,
                $date,
                $compo['duree'],
                $ignore,
                15,
                $request->user()->hasPermission('appointments.create_past'),
            ),
            'duree' => $compo['duree'],
        ]);
    }

    /** Recherche de clients actifs (JSON), pour le formulaire. */
    public function clients(Request $request): JsonResponse
    {
        Gate::authorize('create', RendezVous::class);
        Gate::authorize('viewAny', Client::class);

        $terme = is_string($request->query('q')) ? trim($request->query('q')) : '';

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

    /**
     * @param  array<string, mixed>  $defauts
     * @return array<string, mixed>
     */
    private function formData(?RendezVous $rdv, array $defauts = []): array
    {
        $lignes = $rdv ? $rdv->lignes : collect();

        $servicesSeuls = $lignes->whereNull('forfait_id')->pluck('service_id')->map(fn ($id) => (string) $id)->values()->all();
        $forfaitsChoisis = $lignes->pluck('forfait_id')->filter()->unique()->map(fn ($id) => (string) $id)->values()->all();
        $dejaServices = $lignes->pluck('service_id')->all();

        // Prestations et forfaits actifs, plus ceux déjà présents dans ce rendez-vous.
        $categories = CategorieService::orderBy('ordre')
            ->with(['services' => fn ($q) => $q
                ->where(fn ($w) => $w->where('is_active', true)->orWhereIn('id', $dejaServices))
                ->orderBy('nom')])
            ->get()
            ->filter(fn ($categorie) => $categorie->services->isNotEmpty());

        $forfaits = Forfait::with('services')
            ->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $forfaitsChoisis))
            ->orderBy('nom')
            ->get();

        $employes = Employe::with('services')
            ->where(fn ($q) => $q->where('is_active', true)
                ->when($rdv, fn ($w) => $w->orWhere('id', $rdv->employe_id)))
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get();

        $clientId = old('client_id', $defauts['client_id'] ?? $rdv?->client_id);
        $client = $clientId ? Client::find((int) $clientId) : null;

        $etat = [
            'idRdv' => $rdv?->id,
            'urls' => [
                'creneaux' => route('rendez-vous.creneaux'),
                'clients' => route('rendez-vous.clients'),
            ],
            'client' => $client ? [
                'id' => $client->id,
                'nom' => $client->nomComplet(),
                'telephone' => $client->telephoneAffiche(),
            ] : null,
            'employe' => (string) old('employe_id', $defauts['employe_id'] ?? $rdv?->employe_id ?? ''),
            'date' => (string) old('date', $rdv?->date->toDateString() ?? $defauts['date'] ?? now()->toDateString()),
            'heure' => (string) old('heure_debut', $rdv?->debut() ?? ''),
            'zone' => (string) old('zone', $rdv?->zone->value ?? 'mixte'),
            'services' => array_map('strval', (array) old('services', $servicesSeuls)),
            'forfaits' => array_map('strval', (array) old('forfaits', $forfaitsChoisis)),
            'catalogue' => (object) $categories
                ->flatMap(fn ($categorie) => $categorie->services)
                ->mapWithKeys(fn ($service) => [$service->id => [
                    'nom' => $service->nom,
                    'prix' => $service->prix,
                    'duree' => $service->duree_minutes,
                    'zone' => $service->zone->value,
                ]])
                ->all(),
            'forfaitsInfo' => (object) $forfaits
                ->mapWithKeys(fn ($forfait) => [$forfait->id => [
                    'nom' => $forfait->nom,
                    'prix' => $forfait->prix,
                    'duree' => $forfait->dureeTotale(),
                    'zone' => $forfait->zone()->value,
                    'services' => $forfait->services->pluck('id')->all(),
                ]])
                ->all(),
            'employes' => (object) $employes
                ->mapWithKeys(fn ($employe) => [$employe->id => [
                    'nom' => $employe->nomComplet(),
                    'services' => $employe->services->pluck('id')->all(),
                ]])
                ->all(),
        ];

        return [
            'rdv' => $rdv,
            'etat' => $etat,
            'categories' => $categories,
            'forfaits' => $forfaits,
            'employes' => $employes,
            'peutPasse' => auth()->user()->hasPermission('appointments.create_past'),
        ];
    }

    private function entier(mixed $valeur): ?int
    {
        return (is_string($valeur) || is_int($valeur)) && ctype_digit((string) $valeur) && (int) $valeur > 0
            ? (int) $valeur
            : null;
    }
}