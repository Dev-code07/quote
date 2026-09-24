<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ isset($title) ? $title . ' — ' : '' }}{{ config('app.name', 'QuoteFlow') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-app-bg text-app-text antialiased">
        <div
            x-data="{ open: false }"
            @keydown.escape.window="open = false"
            class="min-h-screen"
        >
            {{-- Mobile scrim --}}
            <div
                x-show="open"
                x-transition:enter="transition-opacity ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition-opacity ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                x-on:click="open = false"
                class="fixed inset-0 z-40 hidden bg-app-text/35 max-[900px]:block"
                aria-hidden="true"
            ></div>

            {{-- Sidebar: 232px fixed on desktop, off-canvas drawer below 900px (design.md 2.1) --}}
            <aside
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="-translate-x-full"
                x-transition:enter-end="translate-x-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="translate-x-0"
                x-transition:leave-end="-translate-x-full"
                class="fixed inset-y-0 left-0 z-50 flex w-[232px] flex-col border-r border-app-border bg-app-surface shadow-app-card transition-transform duration-200 min-[901px]:shadow-none"
                :class="open ? 'translate-x-0' : '-translate-x-full min-[901px]:translate-x-0'"
                :aria-hidden="open ? null : (window.matchMedia('(min-width: 901px)').matches ? null : 'true')"
            >
                <div class="flex h-16 items-center gap-2.5 border-b border-app-border px-5">
                    <span class="flex size-8 items-center justify-center rounded-[8px] bg-app-accent text-sm font-bold text-white" aria-hidden="true">Q</span>
                    <span class="text-[15px] font-bold tracking-tight">{{ config('app.name', 'QuoteFlow') }}</span>
                </div>

                <nav class="flex-1 space-y-1 overflow-y-auto p-3" aria-label="Main navigation">
                    @php
                        $items = [
                            ['route' => 'dashboard', 'label' => 'Dashboard', 'pattern' => 'dashboard', 'icon' => '<path d="M3 3h7v7H3z"/><path d="M14 3h7v7h-7z"/><path d="M14 14h7v7h-7z"/><path d="M3 14h7v7H3z"/>'],
                            ['route' => 'clients.index', 'label' => 'Clients', 'pattern' => 'clients.*', 'icon' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/>'],
                            ['route' => 'templates.index', 'label' => 'Templates', 'pattern' => 'templates.*', 'icon' => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/>'],
                            ['route' => 'quotes.index', 'label' => 'Quotes', 'pattern' => 'quotes.*', 'icon' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="M8 13h8M8 17h5"/>'],
                        ];
                    @endphp

                    @foreach ($items as $item)
                        @php $active = request()->routeIs($item['pattern']); @endphp
                        <a
                            href="{{ route($item['route']) }}"
                            @if ($active) aria-current="page" @endif
                            class="flex items-center gap-2.5 rounded-[8px] px-3 py-2 text-[13.5px] font-medium transition-colors {{ $active ? 'bg-app-accent-soft text-app-accent' : 'text-app-muted hover:bg-app-neutral-soft hover:text-app-text' }}"
                        >
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $item['icon'] !!}</svg>
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </nav>

                <div class="border-t border-app-border p-3">
                    <div class="flex items-center gap-2.5 rounded-[8px] px-2 py-2">
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-app-neutral-soft text-xs font-bold text-app-muted" aria-hidden="true">
                            {{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[13px] font-semibold">{{ auth()->user()->name }}</p>
                            <p class="truncate text-xs text-app-faint">Administrator</p>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="rounded-[6px] p-1.5 text-app-faint transition-colors hover:bg-app-neutral-soft hover:text-app-danger" title="Log out" aria-label="Log out">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                            </button>
                        </form>
                    </div>
                </div>
            </aside>

            {{-- Main column --}}
            <div class="min-h-screen min-[901px]:pl-[232px]">
                <header class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-app-border bg-app-surface px-5">
                    <button
                        type="button"
                        x-on:click="open = true"
                        class="-ml-1 rounded-[6px] p-2 text-app-muted hover:bg-app-neutral-soft max-[900px]:inline-flex hidden"
                        aria-label="Open navigation menu"
                    >
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                    </button>

                    <h1 class="text-[15px] font-bold">{{ $title ?? config('app.name', 'QuoteFlow') }}</h1>

                    <div class="ml-auto flex items-center gap-2">
                        {{ $headerActions ?? '' }}
                    </div>
                </header>

                <main class="p-5">
                    {{ $slot }}
                </main>

                <x-page-footer label="QuoteFlow — Quotation &amp; Proforma Invoice Management System" />
            </div>

            <x-toast />
        </div>
    </body>
</html>
