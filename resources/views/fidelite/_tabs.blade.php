<nav class="mb-6 flex gap-2 border-b border-beige-200 text-sm" aria-label="Fidélité">
    @foreach ([['fidelite.index', 'Clientes et points', ['fidelite.index', 'fidelite.client']], ['fidelite.recompenses.index', 'Récompenses', ['fidelite.recompenses.*']]] as [$route, $libelle, $motifs])
        @php $actif = request()->routeIs(...$motifs); @endphp
        <a href="{{ route($route) }}" @class([
            '-mb-px border-b-2 px-4 py-2.5 font-medium transition',
            'border-bordeaux-700 text-bordeaux-800' => $actif,
            'border-transparent text-stone-500 hover:text-bordeaux-800' => ! $actif,
        ]) @if ($actif) aria-current="page" @endif>{{ $libelle }}</a>
    @endforeach
</nav>
