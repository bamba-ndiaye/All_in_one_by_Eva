<?php

namespace App\Http\Controllers;

use App\Http\Requests\DepenseRequest;
use App\Models\CategorieDepense;
use App\Models\Depense;
use App\Services\DepenseService;
use App\Support\AgendaVue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DepenseController extends Controller
{
    public function index(Request $request, DepenseService $service): View
    {
        Gate::authorize('viewAny', Depense::class);

        $aujourdhui = now()->startOfDay();

        $au = AgendaVue::lireDate($this->texte($request, 'au')) ?? $aujourdhui->copy();
        $du = AgendaVue::lireDate($this->texte($request, 'du')) ?? $aujourdhui->copy()->startOfMonth();

        if ($du->gt($au)) {
            [$du, $au] = [$au, $du];
        }

        if ($du->diffInDays($au) > 366) {
            $du = $au->copy()->subDays(366);
        }

        $categorie = $this->entier($request->query('categorie'));
        $statut = $this->texte($request, 'statut');
        $statut = in_array($statut, ['valides', 'annulees', 'toutes'], true) ? $statut : 'toutes';

        $depenses = Depense::query()
            ->entre($du, $au)
            ->with(['categorie', 'user'])
            ->when($categorie, fn ($q, $id) => $q->where('categorie_depense_id', $id))
            ->when($statut === 'valides', fn ($q) => $q->whereNull('depenses.annulee_at'))
            ->when($statut === 'annulees', fn ($q) => $q->whereNotNull('depenses.annulee_at'))
            ->orderByDesc('depenses.date')
            ->orderByDesc('depenses.id')
            ->paginate(25)
            ->withQueryString();

        $voitResultat = $request->user()->can('finance.view_revenue');

        return view('depenses.index', [
            'depenses' => $depenses,
            'du' => $du,
            'au' => $au,
            'categorie' => $categorie,
            'statut' => $statut,
            'categories' => CategorieDepense::orderBy('ordre')->orderBy('id')->get(),
            'totalAujourdhui' => $service->total($aujourdhui, $aujourdhui),
            'totalMois' => $service->total($aujourdhui->copy()->startOfMonth(), $aujourdhui->copy()->endOfMonth()),
            'totalPeriode' => $service->total($du, $au),
            'parCategorie' => $service->parCategorie($du, $au),
            'voitResultat' => $voitResultat,
            'resultat' => $voitResultat ? $service->resultatEstime($du, $au) : null,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Depense::class);

        return view('depenses.form', $this->formData(new Depense(['date' => now()->toDateString()])));
    }

    public function store(DepenseRequest $request, DepenseService $service): RedirectResponse
    {
        $depense = $service->creer($request->payload(), $request->file('justificatif'), $request->user());

        return redirect()->route('depenses.show', $depense)
            ->with('success', "La dépense n° {$depense->id} a été enregistrée.");
    }

    public function show(Depense $depense): View
    {
        Gate::authorize('view', $depense);

        $depense->load(['categorie', 'user', 'annuleePar']);

        return view('depenses.show', compact('depense'));
    }

    public function edit(Depense $depense): View|RedirectResponse
    {
        Gate::authorize('update', $depense);

        if ($depense->estAnnulee()) {
            return redirect()->route('depenses.show', $depense)
                ->with('error', 'Cette dépense est annulée : elle ne peut plus être modifiée.');
        }

        return view('depenses.form', $this->formData($depense));
    }

    public function update(DepenseRequest $request, Depense $depense, DepenseService $service): RedirectResponse
    {
        $service->modifier($depense, $request->payload(), $request->file('justificatif'), $request->user());

        return redirect()->route('depenses.show', $depense)
            ->with('success', "La dépense n° {$depense->id} a été mise à jour.");
    }

    public function annuler(Request $request, Depense $depense, DepenseService $service): RedirectResponse
    {
        Gate::authorize('annuler', $depense);

        $data = $request->validate([
            'motif' => ['required', 'string', 'min:5', 'max:255'],
        ]);

        $service->annuler($depense, $data['motif'], $request->user());

        return redirect()->route('depenses.show', $depense)
            ->with('success', "La dépense n° {$depense->id} a été annulée.");
    }

    /** Sert le justificatif depuis le disque privé, uniquement à une personne autorisée. */
    public function justificatif(Depense $depense): StreamedResponse
    {
        Gate::authorize('view', $depense);

        $disque = Storage::disk('local');

        abort_unless($depense->aUnJustificatif() && $disque->exists($depense->justificatif), 404);

        return $disque->response(
            $depense->justificatif,
            'justificatif-'.$depense->id.'.'.$depense->extensionJustificatif(),
            ['X-Content-Type-Options' => 'nosniff'],
        );
    }

    /** @return array<string, mixed> */
    private function formData(Depense $depense): array
    {
        // Catégories actives, plus celle de cette dépense si elle a été désactivée depuis.
        $categories = CategorieDepense::query()
            ->where(fn ($q) => $q->where('is_active', true)
                ->when($depense->exists, fn ($w) => $w->orWhere('id', $depense->categorie_depense_id)))
            ->orderBy('ordre')
            ->orderBy('id')
            ->get();

        return ['depense' => $depense, 'categories' => $categories];
    }

    private function texte(Request $request, string $cle): string
    {
        $valeur = $request->query($cle);

        return is_string($valeur) ? trim($valeur) : '';
    }

    private function entier(mixed $valeur): ?int
    {
        return (is_string($valeur) || is_int($valeur)) && ctype_digit((string) $valeur) && (int) $valeur > 0
            ? (int) $valeur
            : null;
    }
}