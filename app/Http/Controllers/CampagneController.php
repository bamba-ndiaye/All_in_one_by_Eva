<?php

namespace App\Http\Controllers;

use App\Enums\CibleCampagne;
use App\Models\Campagne;
use App\Models\CampagneDestinataire;
use App\Services\CampagneService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CampagneController extends Controller
{
    public function index(): View
    {
        Gate::authorize('campaigns.manage');

        return view('campagnes.index', [
            'campagnes' => Campagne::query()
                ->withCount(['destinataires', 'destinataires as envoyes_count' => fn ($q) => $q->whereNotNull('envoye_at')])
                ->latest('id')
                ->paginate(20),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('campaigns.manage');

        $campagne = new Campagne([
            'message' => 'Bonjour {prenom} ! ',
            'cible' => CibleCampagne::Toutes,
            'jours' => 60,
        ]);

        return view('campagnes.form', ['campagne' => $campagne, 'cibles' => CibleCampagne::cases()]);
    }

    public function store(Request $request, CampagneService $service): RedirectResponse
    {
        Gate::authorize('campaigns.manage');

        $campagne = $service->enregistrer(null, $this->valider($request), $request->user());

        return redirect()->route('campagnes.show', $campagne)
            ->with('success', 'Campagne enregistrée en brouillon. Vérifiez l\'aperçu puis lancez-la.');
    }

    public function show(Campagne $campagne, CampagneService $service): View
    {
        Gate::authorize('campaigns.manage');

        $destinataires = $campagne->estBrouillon()
            ? null
            : $campagne->destinataires()->with(['client', 'envoyePar'])->orderByRaw('envoye_at is not null')->orderBy('id')->paginate(50);

        $exemple = $campagne->estBrouillon()
            ? $service->requeteCible($campagne->cible, $campagne->jours)->orderBy('prenom')->first()
            : $destinataires?->first()?->client;

        return view('campagnes.show', [
            'campagne' => $campagne,
            'destinataires' => $destinataires,
            'nbCible' => $campagne->estBrouillon() ? $service->compterCible($campagne->cible, $campagne->jours) : null,
            'total' => $campagne->destinataires()->count(),
            'envoyes' => $campagne->destinataires()->whereNotNull('envoye_at')->count(),
            'exemple' => $exemple,
        ]);
    }

    public function edit(Campagne $campagne): View|RedirectResponse
    {
        Gate::authorize('campaigns.manage');

        if (! $campagne->estBrouillon()) {
            return redirect()->route('campagnes.show', $campagne)->with('error', 'Une campagne lancée ne peut plus être modifiée.');
        }

        return view('campagnes.form', ['campagne' => $campagne, 'cibles' => CibleCampagne::cases()]);
    }

    public function update(Request $request, Campagne $campagne, CampagneService $service): RedirectResponse
    {
        Gate::authorize('campaigns.manage');

        $service->enregistrer($campagne, $this->valider($request), $request->user());

        return redirect()->route('campagnes.show', $campagne)->with('success', 'Campagne mise à jour.');
    }

    public function lancer(Request $request, Campagne $campagne, CampagneService $service): RedirectResponse
    {
        Gate::authorize('campaigns.manage');

        $service->lancer($campagne, $request->user());

        return redirect()->route('campagnes.show', $campagne)
            ->with('success', 'Campagne lancée : cliquez sur « Envoyer » pour chaque cliente.');
    }

    public function envoyer(Request $request, Campagne $campagne, CampagneDestinataire $destinataire, CampagneService $service): RedirectResponse
    {
        Gate::authorize('campaigns.manage');
        abort_unless($destinataire->campagne_id === $campagne->id, 404);

        return redirect()->away($service->envoyer($destinataire, $request->user()));
    }

    public function cloturer(Request $request, Campagne $campagne, CampagneService $service): RedirectResponse
    {
        Gate::authorize('campaigns.manage');

        $service->cloturer($campagne, $request->user());

        return redirect()->route('campagnes.show', $campagne)->with('success', 'Campagne clôturée.');
    }

    /** Nombre de clientes visées, pour l'aperçu en direct du formulaire. */
    public function apercu(Request $request, CampagneService $service): JsonResponse
    {
        Gate::authorize('campaigns.manage');

        $cible = CibleCampagne::tryFrom((string) $request->query('cible')) ?? CibleCampagne::Toutes;
        $jours = min(3650, max(1, (int) $request->query('jours', 60)));

        return response()->json(['nombre' => $service->compterCible($cible, $jours)]);
    }

    /** @return array{titre: string, message: string, cible: CibleCampagne, jours: ?int} */
    private function valider(Request $request): array
    {
        $data = $request->validate([
            'titre' => ['required', 'string', 'max:100'],
            'message' => ['required', 'string', 'min:10', 'max:1000'],
            'cible' => ['required', Rule::enum(CibleCampagne::class)],
            'jours' => ['nullable', 'required_unless:cible,toutes', 'integer', 'min:1', 'max:3650'],
        ], [
            'jours.required_unless' => 'Indiquez le nombre de jours.',
        ], [
            'cible' => 'public',
            'jours' => 'nombre de jours',
        ]);

        return [
            'titre' => trim($data['titre']),
            'message' => trim($data['message']),
            'cible' => CibleCampagne::from($data['cible']),
            'jours' => isset($data['jours']) ? (int) $data['jours'] : null,
        ];
    }
}
