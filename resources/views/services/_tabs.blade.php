@php $onForfaits = request()->routeIs('services.forfaits.*'); @endphp

<div class="mb-6 flex gap-2 border-b border-beige-200">
    <a href="{{ route('services.index') }}" @class([
        '-mb-px border-b-2 px-4 py-2 text-sm font-medium',
        'border-bordeaux-700 text-bordeaux-800' => ! $onForfaits,
        'border-transparent text-stone-500 hover:text-bordeaux-800' => $onForfaits,
    ])>Prestations</a>
    <a href="{{ route('services.forfaits.index') }}" @class([
        '-mb-px border-b-2 px-4 py-2 text-sm font-medium',
        'border-bordeaux-700 text-bordeaux-800' => $onForfaits,
        'border-transparent text-stone-500 hover:text-bordeaux-800' => ! $onForfaits,
    ])>Forfaits</a>
</div>