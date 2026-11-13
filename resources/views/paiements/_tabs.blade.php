@php $surImpayes = request()->routeIs('paiements.impayes'); @endphp

<div class="mb-6 flex gap-2 border-b border-beige-200">
    <a href="{{ route('paiements.index') }}" @class([
        '-mb-px border-b-2 px-4 py-2 text-sm font-medium',
        'border-bordeaux-700 text-bordeaux-800' => ! $surImpayes,
        'border-transparent text-stone-500 hover:text-bordeaux-800' => $surImpayes,
    ])>Paiements</a>
    <a href="{{ route('paiements.impayes') }}" @class([
        '-mb-px border-b-2 px-4 py-2 text-sm font-medium',
        'border-bordeaux-700 text-bordeaux-800' => $surImpayes,
        'border-transparent text-stone-500 hover:text-bordeaux-800' => ! $surImpayes,
    ])>Impayés</a>
</div>