@php
    $rdv = $paiement->rendezVous;
    $estAnnulation = $paiement->type === \App\Enums\TypePaiement::Annulation;
    $adresse = config('salon.recu.adresse');
    $telephone = config('salon.recu.telephone');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reçu {{ $paiement->numero() }} — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
    <style>@page { margin: 12mm; }</style>
</head>
<body class="bg-white">
<div class="mx-auto max-w-md px-6 py-8 text-sm text-stone-800">
    <div class="mb-6 flex gap-3 print:hidden">
        <button type="button" onclick="window.print()" class="btn-primary">Imprimer</button>
        <a href="{{ route('paiements.show', $paiement) }}" class="btn-secondary">Retour</a>
    </div>

    <header class="border-b border-stone-300 pb-4 text-center">
        <p class="font-serif text-2xl font-semibold text-bordeaux-900">{{ config('app.name') }}</p>
        @if ($adresse)
            <p class="mt-1 text-xs text-stone-600">{{ $adresse }}</p>
        @endif
        @if ($telephone)
            <p class="text-xs text-stone-600">{{ $telephone }}</p>
        @endif
    </header>

    <section class="mt-4 text-center">
        <h1 class="font-serif text-lg font-semibold">{{ $estAnnulation ? 'Annulation de paiement' : 'Reçu de paiement' }}</h1>
        <p class="mt-1 font-medium">{{ $paiement->numero() }}</p>
        <p class="text-xs text-stone-600">{{ $paiement->date->format('d/m/Y') }} à {{ $paiement->created_at->format('H:i') }}</p>
    </section>

    @if ($estAnnulation)
        <p class="mt-4 rounded border border-stone-400 px-3 py-2 text-center text-xs font-semibold uppercase tracking-wide">
            ANNULATION @if ($paiement->origine) du paiement {{ $paiement->origine->numero() }} @endif
        </p>
    @elseif ($paiement->estAnnule())
        <p class="mt-4 rounded border border-stone-400 px-3 py-2 text-center text-xs font-semibold">
            Ce paiement a été annulé.
        </p>
    @endif

    <dl class="mt-4 space-y-1">
        <div class="flex justify-between gap-4"><dt class="text-stone-600">Client</dt><dd class="font-medium">{{ $paiement->client->nomComplet() }}</dd></div>
        <div class="flex justify-between gap-4"><dt class="text-stone-600">Mode de paiement</dt><dd>{{ $paiement->mode->nom }}</dd></div>
        @if ($paiement->reference)
            <div class="flex justify-between gap-4"><dt class="text-stone-600">Référence</dt><dd>{{ $paiement->reference }}</dd></div>
        @endif
        @if ($paiement->user)
            <div class="flex justify-between gap-4"><dt class="text-stone-600">Encaissé par</dt><dd>{{ $paiement->user->name }}</dd></div>
        @endif
    </dl>

    <section class="mt-4 border-t border-stone-300 pt-3">
        @if ($rdv)
            <table class="w-full">
                <tbody>
                    @foreach ($rdv->lignes as $ligne)
                        <tr>
                            <td class="py-0.5">{{ $ligne->nom }}</td>
                            <td class="py-0.5 text-right">{{ \App\Support\Fcfa::format($ligne->prix_applique) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @elseif ($paiement->libelle)
            <p>{{ $paiement->libelle }}</p>
        @endif
    </section>

    <section class="mt-3 space-y-1 border-t border-stone-300 pt-3">
        <div class="flex justify-between gap-4"><span>Total à payer</span><span>{{ \App\Support\Fcfa::format($paiement->montant_total) }}</span></div>
        <div class="flex justify-between gap-4 text-base font-semibold">
            <span>{{ $estAnnulation ? 'Montant annulé' : 'Montant payé' }}</span>
            <span>{{ $paiement->montantFormate() }}</span>
        </div>
        <div class="flex justify-between gap-4"><span>Reste à payer</span><span>{{ \App\Support\Fcfa::format($paiement->reste_apres) }}</span></div>
    </section>

    @if ($paiement->motif)
        <p class="mt-3 text-xs text-stone-600">Motif : {{ $paiement->motif }}</p>
    @endif

    <footer class="mt-6 border-t border-stone-300 pt-3 text-center text-xs text-stone-500">
        <p>Reçu de paiement : document non fiscal.</p>
        <p>Merci de votre visite !</p>
    </footer>
</div>
</body>
</html>