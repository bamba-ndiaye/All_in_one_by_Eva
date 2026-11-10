<?php

namespace App\Http\Controllers;

use App\Enums\CanalCommunication;
use App\Http\Requests\ClientRequest;
use App\Models\Client;
use App\Services\ActivityLogger;
use App\Services\ClientService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Client::class);

        $filters = $this->filters($request);

        $clients = Client::query()
            ->filtrer($filters)
            ->orderBy('nom')
            ->orderBy('prenom')
            ->paginate(20)
            ->withQueryString();

        return view('clients.index', compact('clients', 'filters'));
    }

    public function create(): View
    {
        Gate::authorize('create', Client::class);

        return view('clients.form', [
            'client' => new Client(['canal_prefere' => CanalCommunication::Whatsapp->value]),
            'canaux' => CanalCommunication::cases(),
        ]);
    }

    public function store(ClientRequest $request, ClientService $clients): RedirectResponse
    {
        $client = $clients->create($request->payload());

        return redirect()->route('clients.show', $client)
            ->with('success', "La fiche de {$client->nomComplet()} a été créée.");
    }

    public function show(Client $client): View
    {
        Gate::authorize('view', $client);

        return view('clients.show', compact('client'));
    }

    public function edit(Client $client): View
    {
        Gate::authorize('update', $client);

        return view('clients.form', [
            'client' => $client,
            'canaux' => CanalCommunication::cases(),
        ]);
    }

    public function update(ClientRequest $request, Client $client, ClientService $clients): RedirectResponse
    {
        $clients->update($client, $request->payload());

        return redirect()->route('clients.show', $client)
            ->with('success', "La fiche de {$client->nomComplet()} a été mise à jour.");
    }

    public function toggle(Client $client, ClientService $clients): RedirectResponse
    {
        Gate::authorize('archive', $client);

        $clients->setActive($client, ! $client->is_active);

        return back()->with('success', $client->is_active
            ? "La fiche de {$client->nomComplet()} est réactivée."
            : "La fiche de {$client->nomComplet()} est archivée.");
    }

    public function export(Request $request, ActivityLogger $logger): StreamedResponse
    {
        Gate::authorize('export', Client::class);

        $filters = $this->filters($request);
        $total = Client::query()->filtrer($filters)->count();

        $logger->log('client.export', null, "Export CSV de {$total} client(s)");

        return response()->streamDownload(function () use ($filters) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM : Excel lit correctement les accents

            fputcsv($out, ['Prénom', 'Nom', 'Téléphone', 'WhatsApp', 'E-mail', 'Date de naissance', 'Consentement promo', 'Statut', 'Inscrit le'], ';');

            foreach (Client::query()->filtrer($filters)->lazyById(500) as $client) {
                fputcsv($out, array_map([$this, 'cell'], [
                    $client->prenom,
                    $client->nom,
                    $client->telephoneAffiche(),
                    $client->whatsappAffiche(),
                    $client->email ?? '',
                    $client->date_naissance?->format('d/m/Y') ?? '',
                    $client->consentement_promo ? 'Oui' : 'Non',
                    $client->is_active ? 'Actif' : 'Archivé',
                    $client->created_at->format('d/m/Y'),
                ]), ';');
            }

            fclose($out);
        }, 'clients-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Neutralise les cellules que Excel interpréterait comme une formule. */
    private function cell(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        $text = fn (string $key): string => is_string($request->query($key)) ? trim($request->query($key)) : '';

        return [
            'q' => $text('q'),
            'statut' => in_array($text('statut'), ['actifs', 'archives', 'tous'], true) ? $text('statut') : 'actifs',
            'consentement' => in_array($text('consentement'), ['oui', 'non'], true) ? $text('consentement') : '',
            'naissance' => $text('naissance') === 'manquante' ? 'manquante' : '',
            'canal' => CanalCommunication::tryFrom($text('canal'))?->value,
        ];
    }
}