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
                class="fixed inset-0 z-[55] hidden bg-app-text/35 max-[900px]:block"
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
                <div class="flex items-center gap-2.5 border-b border-app-border py-[18px] pl-5 pr-5">
                    <span class="flex size-[30px] shrink-0 items-center justify-center rounded-[7px] bg-app-accent text-white" aria-hidden="true">
                        <x-icon name="file-text" size="16" />
                    </span>
                    <span class="text-[16px] font-bold tracking-[-0.2px] text-app-text">
                        {{ \Illuminate\Support\Str::before(config('app.name', 'QuoteFlow'), 'Flow') }}<span class="text-app-accent">Flow</span>
                    </span>
                </div>

                <nav class="flex-1 overflow-y-auto py-2" aria-label="Main navigation">
                    <div class="px-[10px] pb-1.5 pt-2 text-[11px] uppercase tracking-[0.06em] text-app-faint">Main</div>

                    @php
                        $items = [
                            ['route' => 'dashboard', 'label' => 'Dashboard', 'pattern' => 'dashboard', 'icon' => 'layout'],
                            ['route' => 'clients.index', 'label' => 'Clients', 'pattern' => 'clients.*', 'icon' => 'users'],
                            ['route' => 'templates.index', 'label' => 'Templates', 'pattern' => 'templates.*', 'icon' => 'table'],
                            ['route' => 'quotes.index', 'label' => 'Quotes', 'pattern' => 'quotes.*', 'icon' => 'file-text', 'badge' => $quoteCount ?? null],
                        ];
                    @endphp

                    @foreach ($items as $item)
                        @php $active = request()->routeIs($item['pattern']); @endphp
                        <a
                            href="{{ route($item['route']) }}"
                            @if ($active) aria-current="page" @endif
                            class="mb-0.5 flex items-center gap-2.5 rounded-[6px] py-[9px] pl-[10px] pr-[10px] text-sm font-medium transition-colors {{ $active ? 'bg-app-accent-soft font-semibold text-app-accent' : 'text-app-muted hover:bg-app-neutral-soft hover:text-app-text' }}"
                        >
                            <x-icon :name="$item['icon']" size="17" class="shrink-0" :stroke-width="1.8" />
                            {{ $item['label'] }}
                            @if (! empty($item['badge']))
                                <span class="ml-auto rounded-full bg-app-accent-soft px-[7px] py-px text-[11px] font-semibold text-app-accent">{{ $item['badge'] }}</span>
                            @endif
                        </a>
                    @endforeach
                </nav>

                <div class="border-t border-app-border pb-3.5 pl-3 pr-3 pt-2.5">
                    <a
                        href="{{ route('profile.edit') }}"
                        class="mb-0.5 flex items-center gap-2.5 rounded-[6px] py-[9px] pl-[10px] pr-[10px] text-sm font-medium text-app-muted transition-colors hover:bg-app-neutral-soft hover:text-app-text"
                    >
                        <x-icon name="settings" size="17" class="shrink-0" :stroke-width="1.8" />
                        Settings
                    </a>

                    <div class="mt-1 flex items-center gap-2.5 rounded-[6px] py-2 pl-[10px] pr-2.5">
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-[#1e40af] text-xs font-semibold text-white" aria-hidden="true">
                            {{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[13px] font-semibold">{{ auth()->user()->name }}</p>
                            <p class="truncate text-[11px] text-app-faint">Administrator</p>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="rounded-[6px] p-1.5 text-app-faint transition-colors hover:bg-app-neutral-soft hover:text-app-danger" title="Log out" aria-label="Log out">
                                <x-icon name="log-out" size="16" :stroke-width="1.8" />
                            </button>
                        </form>
                    </div>
                </div>
            </aside>

            {{-- Main column --}}
            <div class="min-h-screen min-[901px]:pl-[232px]">
                <header class="sticky top-0 z-50 flex h-[60px] items-center gap-3.5 border-b border-app-border bg-app-surface px-7">
                    <button
                        type="button"
                        x-on:click="open = true"
                        class="hidden size-9 shrink-0 items-center justify-center rounded-[6px] border border-app-border text-app-muted transition-colors hover:bg-app-neutral-soft hover:text-app-text max-[900px]:inline-flex"
                        aria-label="Open navigation menu"
                    >
                        <x-icon name="menu" size="18" />
                    </button>

                    {{-- Global search (prototype .topbar-search: flex 0 1 340px) --}}
                    <form method="GET" action="{{ route('search') }}" role="search" class="relative hidden w-full max-w-[340px] text-app-faint max-[900px]:block">
                        <x-icon name="search" size="15" class="pointer-events-none absolute left-[11px] top-1/2 -translate-y-1/2" />
                        <input
                            type="search"
                            name="q"
                            value="{{ request('q') }}"
                            placeholder="Search quotes, clients..."
                            aria-label="Search quotes and clients"
                            class="w-full rounded-[6px] border border-app-border bg-app-bg py-2 pl-[34px] pr-3 text-[13px] text-app-text outline-none transition-colors placeholder:text-app-faint focus:border-app-accent focus:bg-white focus:outline-2 focus:outline-offset-0 focus:outline-[rgba(37,99,235,0.25)]"
                        />
                    </form>

                    <div class="ml-auto flex items-center gap-2.5">
                        {{-- Notifications --}}
                        <button
                            type="button"
                            class="relative flex size-9 items-center justify-center rounded-[6px] border border-app-border bg-white text-app-muted transition-colors hover:bg-app-neutral-soft hover:text-app-text"
                            title="Notifications"
                            aria-label="Notifications"
                        >
                            <x-icon name="bell" size="17" :stroke-width="1.8" />
                            <span class="absolute right-2 top-[7px] size-[7px] rounded-full border-[1.5px] border-white bg-app-danger"></span>
                        </button>

                        {{-- Info / help --}}
                        <button
                            type="button"
                            class="flex size-9 items-center justify-center rounded-[6px] border border-app-border bg-white text-app-muted transition-colors hover:bg-app-neutral-soft hover:text-app-text"
                            title="Help"
                            aria-label="Help"
                        >
                            <x-icon name="help-circle" size="17" :stroke-width="1.8" />
                        </button>
                    </div>
                </header>

                <main class="p-7">
                    {{ $slot }}
                </main>

                <x-page-footer label="QuoteFlow — Quotation &amp; Proforma Invoice Management System" />
            </div>

            <x-toast />
        </div>
    </body>
</html>
