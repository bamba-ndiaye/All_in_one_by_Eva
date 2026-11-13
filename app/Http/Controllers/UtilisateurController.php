<?php

namespace App\Http\Controllers;

use App\Enums\RoleUtilisateur;
use App\Http\Requests\UtilisateurRequest;
use App\Models\HistoriqueAction;
use App\Models\User;
use App\Services\UtilisateurService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class UtilisateurController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);

        $texte = fn (string $cle): string => is_string($request->query($cle)) ? trim($request->query($cle)) : '';

        $filtres = [
            'q' => $texte('q'),
            'role' => RoleUtilisateur::tryFrom($texte('role'))?->value ?? '',
            'statut' => in_array($texte('statut'), ['actifs', 'inactifs', 'tous'], true) ? $texte('statut') : 'tous',
        ];

        $like = '%'.addcslashes($filtres['q'], '%_\\').'%';

        $utilisateurs = User::query()
            ->with('employe')
            ->withCount('extraPermissions')
            ->when($filtres['q'] !== '', fn ($q) => $q->where(
                fn ($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like),
            ))
            ->when($filtres['role'] !== '', fn ($q) => $q->where('role', $filtres['role']))
            ->when($filtres['statut'] === 'actifs', fn ($q) => $q->where('is_active', true))
            ->when($filtres['statut'] === 'inactifs', fn ($q) => $q->where('is_active', false))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('utilisateurs.index', [
            'utilisateurs' => $utilisateurs,
            'filtres' => $filtres,
            'roles' => RoleUtilisateur::cases(),
        ]);
    }

    public function create(UtilisateurService $service, Request $request): View
    {
        Gate::authorize('create', User::class);

        $compte = new User;
        $compte->forceFill(['role' => RoleUtilisateur::Receptionniste, 'is_active' => true]);

        return view('utilisateurs.form', $this->formData($compte, $service, $request->user()));
    }

    public function store(UtilisateurRequest $request, UtilisateurService $service): RedirectResponse
    {
        $compte = $service->creer($request->payload(), $request->permissions(), $request->user());

        return redirect()->route('utilisateurs.show', $compte)
            ->with('success', "Le compte de {$compte->name} a été créé. Communiquez-lui son mot de passe de vive voix.");
    }

    public function show(User $utilisateur): View
    {
        Gate::authorize('view', $utilisateur);

        $utilisateur->load(['employe', 'extraPermissions']);

        $catalogue = config('permissions.catalogue');
        $roleDroits = $utilisateur->role->permissions();
        $extras = $utilisateur->extraPermissions->pluck('permission')->all();

        return view('utilisateurs.show', [
            'compte' => $utilisateur,
            'groupes' => $this->groupes(),
            'catalogue' => $catalogue,
            'roleDroits' => $roleDroits,
            'extras' => $extras,
            'activite' => HistoriqueAction::query()
                ->where('user_id', $utilisateur->id)
                ->latest('id')
                ->limit(15)
                ->get(),
        ]);
    }

    public function edit(User $utilisateur, UtilisateurService $service, Request $request): View
    {
        Gate::authorize('update', $utilisateur);

        return view('utilisateurs.form', $this->formData($utilisateur, $service, $request->user()));
    }

    public function update(UtilisateurRequest $request, User $utilisateur, UtilisateurService $service): RedirectResponse
    {
        $service->modifier($utilisateur, $request->payload(), $request->permissions(), $request->user());

        return redirect()->route('utilisateurs.show', $utilisateur)
            ->with('success', "Le compte de {$utilisateur->name} a été mis à jour.");
    }

    public function toggle(Request $request, User $utilisateur, UtilisateurService $service): RedirectResponse
    {
        Gate::authorize('toggle', $utilisateur);

        $service->definirActif($utilisateur, ! $utilisateur->is_active, $request->user());

        return back()->with('success', $utilisateur->is_active
            ? "Le compte de {$utilisateur->name} est de nouveau actif."
            : "Le compte de {$utilisateur->name} est désactivé : la personne ne peut plus se connecter.");
    }

    /** @return array<string, mixed> */
    private function formData(User $compte, UtilisateurService $service, User $acteur): array
    {
        $actuelles = $compte->exists ? $compte->extraPermissions()->pluck('permission')->all() : [];

        // Droits par défaut de chaque rôle, pour griser les cases déjà incluses dans le rôle choisi.
        $droitsParRole = collect(RoleUtilisateur::cases())
            ->mapWithKeys(fn (RoleUtilisateur $role) => [$role->value => $role->permissions()])
            ->all();

        return [
            'compte' => $compte,
            'roles' => $service->rolesAttribuables($acteur),
            'groupes' => $this->groupes(),
            'catalogue' => config('permissions.catalogue'),
            'attribuables' => $service->permissionsAttribuables($acteur),
            'selection' => array_values((array) old('permissions', $actuelles)),
            'droitsParRole' => $droitsParRole,
            'soiMeme' => $compte->exists && $compte->is($acteur),
        ];
    }

    /**
     * Permissions du catalogue regroupées par thème, pour l'affichage.
     *
     * @return array<string, list<string>>
     */
    private function groupes(): array
    {
        $libelles = config('permissions.groupes');
        $groupes = [];

        foreach (array_keys(config('permissions.catalogue')) as $permission) {
            $prefixe = strtok($permission, '.');
            $groupes[$libelles[$prefixe] ?? 'Autres'][] = $permission;
        }

        return $groupes;
    }
}
