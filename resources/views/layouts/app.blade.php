<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' — ' : '' }}{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div x-data="{ sidebarOpen: false }" class="min-h-screen lg:flex">

    {{-- Fond sombre derrière le menu mobile --}}
    <div x-show="sidebarOpen" x-cloak @click="sidebarOpen = false"
         x-transition.opacity
         class="fixed inset-0 z-30 bg-stone-900/50 lg:hidden"></div>

    {{-- Barre latérale --}}
    <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
           class="fixed inset-y-0 left-0 z-40 flex w-72 flex-col bg-bordeaux-900 text-bordeaux-50
                  transition-transform duration-200 lg:sticky lg:top-0 lg:h-screen lg:translate-x-0">

        <div class="flex items-center justify-between px-6 py-6">
            <x-brand :light="true" />
            <button type="button" @click="sidebarOpen = false" class="text-bordeaux-200 lg:hidden" aria-label="Fermer le menu">
                <x-icon name="x" />
            </button>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto px-4 pb-4" aria-label="Navigation principale">
            @foreach (config('navigation.items') as $item)
                {{-- @php
                    $enabled = Route::has($item['route']);
                    $active = $enabled && request()->routeIs($item['pattern']);
                @endphp --}}
                @php
                    $required = (array) ($item['permission'] ?? []);
                    $allowed = $required === []
                        || collect($required)->contains(fn ($permission) => auth()->user()->can($permission));
                    $enabled = Route::has($item['route']);
                    $active = $enabled && request()->routeIs($item['pattern']);
                @endphp

                @continue(! $allowed)



                @if ($enabled)
                    <a href="{{ route($item['route']) }}"
                       @class([
                           'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition',
                           'bg-white/10 text-white' => $active,
                           'text-bordeaux-100 hover:bg-white/5 hover:text-white' => ! $active,
                       ])
                       @if ($active) aria-current="page" @endif>
                        <x-icon :name="$item['icon']" class="h-5 w-5" />
                        {{ $item['label'] }}
                    </a>
                @else
                    <span class="flex cursor-not-allowed items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-bordeaux-300/60"
                          title="Module en cours de développement">
                        <x-icon :name="$item['icon']" class="h-5 w-5" />
                        {{ $item['label'] }}
                        <span class="ml-auto rounded-full bg-white/5 px-2 py-0.5 text-[10px] uppercase tracking-wide">Bientôt</span>
                    </span>
                @endif
            @endforeach
        </nav>

        <div class="border-t border-white/10 px-6 py-4">
            {{-- <p class="truncate text-sm font-medium text-white">{{ auth()->user()->name ?? 'Visiteur' }}</p> --}}
            <p class="truncate text-sm font-medium text-white">{{ auth()->user()->name }}</p>
            <p class="text-xs text-bordeaux-200">{{ auth()->user()->role->label() }}</p>
            @if (Route::has('logout'))
                <form method="POST" action="{{ route('logout') }}" class="mt-2">
                    @csrf
                    <button type="submit" class="flex items-center gap-2 text-sm text-bordeaux-200 hover:text-white">
                        <x-icon name="log-out" class="h-4 w-4" /> Se déconnecter
                    </button>
                </form>
            @endif
        </div>
    </aside>

    {{-- Contenu --}}
    <div class="flex min-w-0 flex-1 flex-col">
        <header class="sticky top-0 z-20 flex items-center gap-4 border-b border-beige-200 bg-creme/90 px-4 py-3 backdrop-blur lg:hidden">
            <button type="button" @click="sidebarOpen = true" class="text-bordeaux-800" aria-label="Ouvrir le menu">
                <x-icon name="menu" class="h-6 w-6" />
            </button>
            <x-brand />
        </header>

        <main class="flex-1 px-4 py-8 sm:px-8 lg:px-10">
            <div class="mx-auto w-full max-w-7xl">
                <x-flash />
                {{ $slot }}
            </div>
        </main>

        <footer class="px-8 py-4 text-center text-xs text-stone-400">
            ©️ {{ date('Y') }} {{ config('app.name') }}
        </footer>
    </div>
</div>
</body>
</html>