<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Qurais Kitchen POS') - Realtime POS & Thermal Printer</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col font-sans antialiased selection:bg-rose-500 selection:text-white">
    <!-- Top Navigation Bar -->
    <header class="sticky top-0 z-40 bg-slate-900/95 backdrop-blur-md border-b border-slate-800 shadow-md shadow-black/20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <!-- Brand Logo & Identity -->
            <div class="flex items-center gap-3 shrink-0">
                <a href="{{ url('/') }}" class="flex items-center gap-3 group">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-rose-600 via-orange-500 to-amber-400 p-0.5 shadow-md shadow-rose-950/40 group-hover:scale-105 transition-transform shrink-0">
                        <div class="w-full h-full bg-slate-950 rounded-[10px] flex items-center justify-center">
                            <span class="text-rose-500 font-black text-xl tracking-tighter">Q</span>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-black tracking-tight text-white text-base">QURAIS KITCHEN</span>
                            @if(Auth::check())
                                @if(Auth::user()->hasRole('superadmin') || Auth::user()->hasPermissionTo('cms.access'))
                                    <span class="text-[9px] uppercase font-extrabold tracking-wider px-2 py-0.5 rounded-md bg-indigo-500/15 text-indigo-300 border border-indigo-500/30">SUPERADMIN</span>
                                @elseif(Auth::user()->hasRole('kitchen'))
                                    <span class="text-[9px] uppercase font-extrabold tracking-wider px-2 py-0.5 rounded-md bg-amber-500/15 text-amber-300 border border-amber-500/30">DAPUR</span>
                                @else
                                    <span class="text-[9px] uppercase font-extrabold tracking-wider px-2 py-0.5 rounded-md bg-rose-500/15 text-rose-300 border border-rose-500/30">KASIR</span>
                                @endif
                            @endif
                        </div>
                        <p class="text-[11px] text-slate-400 hidden sm:block leading-none mt-0.5">Realtime POS & Kitchen System</p>
                    </div>
                </a>
            </div>

            <!-- Unified Segmented Navigation Bar -->
            <nav class="hidden md:flex items-center gap-1.5 p-1 rounded-xl bg-slate-950/70 border border-slate-800/80 shadow-inner">
                @if(Auth::check())
                    @php
                        $isSuperadmin = Auth::user()->hasRole('superadmin') || Auth::user()->hasPermissionTo('cms.access');
                        $isKitchen = Auth::user()->hasRole('kitchen');
                    @endphp

                    {{-- 1. Dashboard (Superadmin Only - Cashier & Kitchen NEVER see this) --}}
                    @if($isSuperadmin)
                        <a href="{{ route('cms.index') }}" 
                           class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 {{ request()->is('cms*') ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/40 ring-1 ring-indigo-400/40' : 'text-slate-300 hover:text-white hover:bg-slate-800/80' }}">
                            <svg class="w-3.5 h-3.5 {{ request()->is('cms*') ? 'text-white' : 'text-indigo-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                            Dashboard
                        </a>
                    @endif

                    {{-- 2. Kasir POS (Accessible by Superadmin and Cashier - Kitchen cannot access) --}}
                    @if($isSuperadmin || Auth::user()->hasPermissionTo('pos.access'))
                        <a href="{{ route('pos.index') }}" 
                           class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 {{ request()->routeIs('pos.*') ? 'bg-rose-600 text-white shadow-md shadow-rose-600/40 ring-1 ring-rose-400/40' : 'text-slate-300 hover:text-white hover:bg-slate-800/80' }}">
                            <svg class="w-3.5 h-3.5 {{ request()->routeIs('pos.*') ? 'text-white' : 'text-rose-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                            Kasir POS
                        </a>
                    @endif

                    {{-- 3. Dapur KDS (Accessible by Superadmin and Kitchen - Cashier cannot access) --}}
                    @if($isSuperadmin || $isKitchen || Auth::user()->hasPermissionTo('kds.access'))
                        <a href="{{ route('kds.index') }}" 
                           class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 {{ request()->routeIs('kds.*') ? 'bg-amber-600 text-white shadow-md shadow-amber-600/40 ring-1 ring-amber-400/40' : 'text-slate-300 hover:text-white hover:bg-slate-800/80' }}">
                            <svg class="w-3.5 h-3.5 {{ request()->routeIs('kds.*') ? 'text-white' : 'text-amber-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                            Dapur KDS
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                        </a>
                    @endif

                    {{-- Divider & Management Tools (Superadmin and Cashier) --}}
                    @if($isSuperadmin || Auth::user()->hasPermissionTo('transactions.access'))
                        <div class="h-4 w-px bg-slate-800 mx-0.5"></div>

                        {{-- 4. Transaksi --}}
                        <a href="{{ route('transactions.index') }}" 
                           class="px-2.5 py-1.5 rounded-lg text-xs font-semibold transition-all flex items-center gap-1.5 {{ request()->routeIs('transactions.*') ? 'bg-slate-800 text-white ring-1 ring-slate-700' : 'text-slate-400 hover:text-white hover:bg-slate-900/60' }}">
                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                            Transaksi
                        </a>

                        {{-- 5. Analitik --}}
                        <a href="{{ route('analytics.index') }}" 
                           class="px-2.5 py-1.5 rounded-lg text-xs font-semibold transition-all flex items-center gap-1.5 {{ request()->routeIs('analytics.*') ? 'bg-slate-800 text-white ring-1 ring-slate-700' : 'text-slate-400 hover:text-white hover:bg-slate-900/60' }}">
                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                            Analitik
                        </a>

                        {{-- 6. Log Viewer --}}
                        <a href="{{ route('log-viewer.index') }}" 
                           class="px-2.5 py-1.5 rounded-lg text-xs font-semibold transition-all flex items-center gap-1.5 {{ (request()->routeIs('log-viewer.*') || request()->routeIs('logs.*')) ? 'bg-slate-800 text-white ring-1 ring-slate-700' : 'text-slate-400 hover:text-white hover:bg-slate-900/60' }}">
                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            Logs
                        </a>
                    @endif
                @endif
            </nav>

            <!-- User Status & Action Controls -->
            <div class="flex items-center gap-3">
                @if(Auth::check())
                    <!-- User Profile & Role Info -->
                    <div class="flex items-center gap-2.5">
                        <div class="text-right hidden sm:block">
                            <p class="text-xs font-bold text-white leading-tight">{{ Auth::user()->name }}</p>
                            <span class="text-[10px] font-mono {{ Auth::user()->hasRole('superadmin') ? 'text-indigo-400' : (Auth::user()->hasRole('kitchen') ? 'text-amber-400' : 'text-rose-400') }}">
                                {{ Auth::user()->username }}
                            </span>
                        </div>
                        <div class="w-8 h-8 rounded-full flex items-center justify-center font-black text-xs shadow-md {{ Auth::user()->hasRole('superadmin') ? 'bg-indigo-600 text-white ring-2 ring-indigo-500/30' : (Auth::user()->hasRole('kitchen') ? 'bg-amber-600 text-amber-100 ring-2 ring-amber-500/30' : 'bg-rose-600 text-rose-100 ring-2 ring-rose-500/30') }}">
                            {{ substr(Auth::user()->name, 0, 1) }}
                        </div>
                        
                        <!-- Logout Form -->
                        <form method="POST" action="{{ route('logout') }}" class="inline">
                            @csrf
                            <button type="submit" 
                                    title="Keluar / Logout"
                                    class="p-2 rounded-lg text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 transition-colors ml-0.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                            </button>
                        </form>
                    </div>

                    <!-- Mobile Hamburger Button -->
                    <button type="button" 
                            onclick="toggleMobileMenu()" 
                            aria-label="Toggle navigation menu"
                            class="md:hidden p-2 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"/></svg>
                    </button>
                @else
                    <a href="{{ route('login') }}" class="px-4 py-2 rounded-lg bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold transition-all shadow-md shadow-rose-600/30">
                        Masuk Sistem
                    </a>
                @endif
            </div>
        </div>

        <!-- Mobile Dropdown Menu -->
        @if(Auth::check())
        <div id="mobile-menu" class="hidden md:hidden border-t border-slate-800 bg-slate-900/98 px-4 py-3 space-y-1">
            @php
                $isSuperadmin = Auth::user()->hasRole('superadmin') || Auth::user()->hasPermissionTo('cms.access');
                $isKitchen = Auth::user()->hasRole('kitchen');
            @endphp

            @if($isSuperadmin)
                <a href="{{ route('cms.index') }}" class="block px-3 py-2 rounded-lg text-xs font-bold {{ request()->is('cms*') ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
                    Dashboard Superadmin
                </a>
            @endif

            @if($isSuperadmin || Auth::user()->hasPermissionTo('pos.access'))
                <a href="{{ route('pos.index') }}" class="block px-3 py-2 rounded-lg text-xs font-bold {{ request()->routeIs('pos.*') ? 'bg-rose-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
                    Kasir POS
                </a>
            @endif

            @if($isSuperadmin || $isKitchen || Auth::user()->hasPermissionTo('kds.access'))
                <a href="{{ route('kds.index') }}" class="block px-3 py-2 rounded-lg text-xs font-bold {{ request()->routeIs('kds.*') ? 'bg-amber-600 text-white' : 'text-slate-300 hover:bg-slate-800' }}">
                    Dapur KDS (Live)
                </a>
            @endif

            @if($isSuperadmin || Auth::user()->hasPermissionTo('transactions.access'))
                <div class="h-px bg-slate-800 my-1"></div>
                <a href="{{ route('transactions.index') }}" class="block px-3 py-2 rounded-lg text-xs font-medium {{ request()->routeIs('transactions.*') ? 'bg-slate-800 text-white' : 'text-slate-400 hover:bg-slate-800' }}">
                    Transaksi
                </a>
                <a href="{{ route('analytics.index') }}" class="block px-3 py-2 rounded-lg text-xs font-medium {{ request()->routeIs('analytics.*') ? 'bg-slate-800 text-white' : 'text-slate-400 hover:bg-slate-800' }}">
                    Analitik
                </a>
                <a href="{{ route('log-viewer.index') }}" class="block px-3 py-2 rounded-lg text-xs font-medium {{ (request()->routeIs('log-viewer.*') || request()->routeIs('logs.*')) ? 'bg-slate-800 text-white' : 'text-slate-400 hover:bg-slate-800' }}">
                    Log Viewer
                </a>
            @endif
        </div>
        <script>
        function toggleMobileMenu() {
            const menu = document.getElementById('mobile-menu');
            if (menu) menu.classList.toggle('hidden');
        }
        </script>
        @endif
    </header>

    <!-- Main Content Body -->
    <main class="flex-1 flex flex-col">
        @yield('content')
    </main>

    <!-- Global Printable Container (Used by window.print for Thermal Printers) -->
    <div id="print-section" class="hidden"></div>
</body>
</html>
