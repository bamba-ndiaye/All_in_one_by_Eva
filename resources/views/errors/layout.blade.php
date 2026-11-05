<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body>
<div class="flex min-h-screen items-center justify-center bg-gradient-to-br from-beige-100 via-creme to-bordeaux-50 px-4 py-12">
    <div class="w-full max-w-md text-center">
        <p class="font-serif text-6xl font-semibold text-bordeaux-700">@yield('code')</p>
        <h1 class="mt-4 text-2xl font-semibold">@yield('title')</h1>
        <p class="mt-3 text-sm text-stone-600">@yield('message')</p>
        <a href="{{ url('/') }}" class="btn-primary mt-8">Retour à l'accueil</a>
    </div>
</div>
</body>
</html>