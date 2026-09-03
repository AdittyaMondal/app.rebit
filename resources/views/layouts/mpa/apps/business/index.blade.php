<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name') }}</title>

        @include('layouts.parts.meta')
        @include('layouts.parts.favicons')

        @vite([
            'resources/js/business/main.js',
            config('assets.fonts.sans'),
            config('assets.fonts.mono')
        ])

        @if(theme_name() == 'dark')
            <link rel="stylesheet" href="{{ asset('build/assets/business-main-dark.css') }}?v={{ $buildNumber }}">
        @else
            @vite('resources/css/business/main.css')
        @endif

        @stack('styles')

        @stack('scripts')

        @livewireStyles
    </head>

    <body @class(['pt-20', (theme_name() == 'dark' ? 'bg-black' : 'bg-pr')])
        x-data="{ sidebarOpen: false }"
        x-on:keydown.escape.window="sidebarOpen = false"
        :class="{ 'overflow-hidden': sidebarOpen }"
    >
        <x-main>
            {{-- Mobile sidebar backdrop --}}
            <div
                x-show="sidebarOpen"
                x-transition:enter="transition-opacity ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition-opacity ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                x-on:click="sidebarOpen = false"
                class="fixed inset-0 bg-black/50 z-40 lg:hidden"
                style="display: none;"
            ></div>

            @include('businessLayout::parts.sidebar')

            @include('businessLayout::parts.header')

            <x-container>
                <x-messages.primary></x-messages.primary>

                <div class="app-min-vh px-4 md:px-8">
                    @yield('pageContent')
                </div>
            </x-container>

            <x-modals.confirm.confirm></x-modals.confirm.confirm>

            @livewireScripts
        </x-main>
    </body>
</html>
