<?php

namespace App\Http\Controllers;

use App\Enums\TypeMouvementStock;
use App\Http\Requests\ProduitRequest;
use App\Models\Produit;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProduitController extends Controller
{
    public function index(Request $request, StockService $stock): View
    {
        Gate::authorize('viewAny', Produit::class);

        $texte = fn (string $cle): string => is_string($request->query($cle)) ? trim($request->query($cle)) : '';

        $filtres = [
            'q' => $texte('q'),
            'etat' => in_array($texte('etat'), ['tous', 'alerte', 'inactifs'], true) ? $texte('etat') : 'tous',
        ];

        $like = '%'.addcslashes($filtres['q'], '%_\\').'%';

        $produits = Produit::query()
            ->when($filtres['q'] !== '', fn ($q) => $q->where(
                fn ($w) => $w->where('nom', 'like', $like)->orWhere('reference', 'like', $like),
            ))
            ->when($filtres['etat'] === 'tous', fn ($q) => $q->actifs())
            ->when($filtres['etat'] === 'alerte', fn ($q) => $q->enAlerte())
            ->when($filtres['etat'] === 'inactifs', fn ($q) => $q->where('is_active', false))
            ->orderBy('nom')
            ->paginate(30)
            ->withQueryString();

        $voitCouts = $request->user()->can('voirCouts', Produit::class);

        return view('produits.index', [
            'produits' => $produits,
            'filtres' => $filtres,
            'voitCouts' => $voitCouts,
            'nbActifs' => Produit::actifs()->count(),
            'nbAlerte' => Produit::enAlerte()->count(),
            'valeur' => $voitCouts ? $stock->valeurTotale() : null,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Produit::class);

        return view('produits.form', ['produit' => new Produit(['unite' => 'pièce', 'seuil_alerte' => 2])]);
    }

    public function store(ProduitRequest $request, StockService $stock): RedirectResponse
    {
        $produit = $stock->creer($request->payload(), $request->quantiteInitiale(), $request->user());

        return redirect()->route('produits.show', $produit)
            ->with('success', "Le produit « {$produit->nom} » a été ajouté au stock.");
    }

    public function show(Request $request, Produit $produit): View
    {
        Gate::authorize('view', $produit);

        return view('produits.show', [
            'produit' => $produit,
            'mouvements' => $produit->mouvements()->with('user')->latest('id')->paginate(20),
            'types' => TypeMouvementStock::cases(),
            'voitCouts' => $request->user()->can('voirCouts', Produit::class),
        ]);
    }

    public function edit(Produit $produit): View
    {
        Gate::authorize('update', $produit);

        return view('produits.form', ['produit' => $produit]);
    }

    public function update(ProduitRequest $request, Produit $produit, StockService $stock): RedirectResponse
    {
        $stock->modifier($produit, $request->payload(), $request->user());

        return redirect()->route('produits.show', $produit)
            ->with('success', "Le produit « {$produit->nom} » a été mis à jour.");
    }

    public function toggle(Request $request, Produit $produit, StockService $stock): RedirectResponse
    {
        Gate::authorize('update', $produit);

        $stock->definirActif($produit, ! $produit->is_active, $request->user());

        return back()->with('success', $produit->is_active
            ? "« {$produit->nom} » est de nouveau actif."
            : "« {$produit->nom} » est désactivé : il n'apparaît plus dans la liste.");
    }

    public function mouvement(Request $request, Produit $produit, StockService $stock): RedirectResponse
    {
        Gate::authorize('mouvement', $produit);

        $data = $request->validate([
            'type' => ['required', Rule::enum(TypeMouvementStock::class)],
            'quantite' => ['required'],
            'motif' => ['nullable', 'string', 'max:255'],
        ], [], ['quantite' => 'quantité']);

        $type = TypeMouvementStock::from($data['type']);
        $mouvement = $stock->mouvement($produit, $type, $data['quantite'], $data['motif'] ?? null, $request->user());

        return redirect()->route('produits.show', $produit)
            ->with('success', "Mouvement enregistré ({$type->label()}). Il reste {$produit->refresh()->quantiteFormatee($mouvement->quantite_apres)}.");
    }
}
