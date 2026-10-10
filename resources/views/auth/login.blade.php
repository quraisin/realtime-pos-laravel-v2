@extends('layouts.app')

@section('title', 'Login Portal - Qurais Kitchen POS & KDS')

@section('content')
<div class="flex-1 flex items-center justify-center p-4 sm:p-6 lg:p-8 relative overflow-hidden bg-radial from-slate-900 via-slate-950 to-slate-950">
    <!-- Background subtle ambient glow -->
    <div class="absolute -top-40 -left-40 w-96 h-96 bg-rose-600/10 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-40 -right-40 w-96 h-96 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>

    <div class="w-full max-w-md relative z-10">
        <!-- Logo & Title -->
        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-tr from-rose-600 via-orange-500 to-amber-400 p-0.5 shadow-xl shadow-rose-950/60 mb-3">
                <div class="w-full h-full bg-slate-950 rounded-[14px] flex items-center justify-center">
                    <span class="text-rose-500 font-extrabold text-3xl">Q</span>
                </div>
            </div>
            <h1 class="text-2xl font-black tracking-tight text-white">QURAIS KITCHEN</h1>
            <p class="text-sm text-slate-400 mt-1">Realtime POS, Thermal Printer & Kitchen Display System</p>
        </div>

        <!-- Main Card -->
        <div class="bg-slate-900/80 backdrop-blur-xl border border-slate-800 rounded-2xl shadow-2xl p-6 sm:p-8">
            <!-- Role Selector Tabs (2 Login Systems) -->
            <div class="flex rounded-xl bg-slate-950/60 p-1 border border-slate-800/80 mb-6" id="login-tabs">
                <button type="button" 
                        id="tab-cashier" 
                        onclick="switchLoginRole('cashier')"
                        class="flex-1 py-2.5 px-3 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-2 {{ ($defaultRole ?? 'cashier') === 'cashier' ? 'bg-rose-600 text-white shadow-lg shadow-rose-950' : 'text-slate-400 hover:text-white' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                    Portal Kasir
                </button>
                <button type="button" 
                        id="tab-kitchen" 
                        onclick="switchLoginRole('kitchen')"
                        class="flex-1 py-2.5 px-3 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-2 {{ ($defaultRole ?? 'cashier') === 'kitchen' ? 'bg-amber-600 text-white shadow-lg shadow-amber-950' : 'text-slate-400 hover:text-white' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                    Portal Dapur (KDS)
                </button>
            </div>

            <!-- Role Badge Alert Banner -->
            <div id="role-banner" class="mb-5 p-3 rounded-xl border text-xs flex items-start gap-2.5 transition-colors {{ ($defaultRole ?? 'cashier') === 'kitchen' ? 'bg-amber-500/10 border-amber-500/30 text-amber-300' : 'bg-rose-500/10 border-rose-500/30 text-rose-300' }}">
                <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <div>
                    <strong id="banner-role-title">{{ ($defaultRole ?? 'cashier') === 'kitchen' ? 'Mode Login: Kitchen Display (Chef)' : 'Mode Login: Kasir & Transaksi POS' }}</strong>
                    <p id="banner-role-desc" class="opacity-90 mt-0.5">
                        {{ ($defaultRole ?? 'cashier') === 'kitchen' ? 'Akses antrian pesanan realtime & status dapur.' : 'Akses kasir POS, katalog produk, dan transaksi.' }}
                    </p>
                </div>
            </div>

            @if($errors->any())
                <div class="mb-5 p-3 rounded-xl bg-red-500/20 border border-red-500/40 text-red-200 text-xs">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Login Form -->
            <form method="POST" action="{{ route('login.submit') }}" id="login-form" class="space-y-4">
                @csrf
                <input type="hidden" name="expected_role" id="expected_role" value="{{ $defaultRole ?? 'cashier' }}">

                <div>
                    <label for="username" class="block text-xs font-semibold text-slate-300 mb-1.5">Username / Email</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        </span>
                        <input type="text" 
                               name="username" 
                               id="username" 
                               value="{{ old('username', ($defaultRole ?? 'cashier') === 'kitchen' ? 'chef@admin' : 'cashier@admin') }}" 
                               required
                               placeholder="e.g. cashier@admin" 
                               class="w-full pl-9 pr-4 py-2.5 bg-slate-950/80 border border-slate-700 rounded-xl text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:border-rose-500 transition-all">
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="password" class="block text-xs font-semibold text-slate-300">Password</label>
                        <span class="text-[10px] text-slate-500">Default: behemoth</span>
                    </div>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        </span>
                        <input type="password" 
                               name="password" 
                               id="password" 
                               value="behemoth"
                               required
                               placeholder="Password" 
                               class="w-full pl-9 pr-10 py-2.5 bg-slate-950/80 border border-slate-700 rounded-xl text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:border-rose-500 transition-all">
                        <button type="button" 
                                onclick="togglePasswordVisibility()" 
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-200">
                            <svg id="eye-icon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-between text-xs text-slate-400 pt-1">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="remember" checked class="rounded border-slate-700 bg-slate-950 text-rose-600 focus:ring-rose-500">
                        <span>Ingat Sesi Login</span>
                    </label>
                </div>

                <!-- Submit Button -->
                <button type="submit" 
                        id="submit-btn" 
                        class="w-full py-3 px-4 rounded-xl font-bold text-sm text-white transition-all shadow-lg flex items-center justify-center gap-2 {{ ($defaultRole ?? 'cashier') === 'kitchen' ? 'bg-amber-600 hover:bg-amber-500 shadow-amber-950/50' : 'bg-rose-600 hover:bg-rose-500 shadow-rose-950/50' }}">
                    <span id="btn-text">{{ ($defaultRole ?? 'cashier') === 'kitchen' ? 'Masuk ke Dashboard Dapur (KDS)' : 'Masuk ke Sistem Kasir POS' }}</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                </button>
            </form>

            <!-- Quick Auto-Fill Credentials Buttons -->
            <div class="mt-6 pt-5 border-t border-slate-800">
                <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-2.5 text-center">
                    Klik Cepat Kredensial Pengujian:
                </p>
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <button type="button" 
                            onclick="fillCredentials('cashier@admin', 'behemoth', 'cashier')"
                            class="p-2.5 rounded-lg bg-slate-950/70 border border-rose-500/20 hover:border-rose-500/50 text-left transition-all group">
                        <div class="flex items-center gap-1.5 text-rose-400 font-bold text-[11px]">
                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                            Akun Kasir
                        </div>
                        <div class="text-[11px] text-slate-300 font-mono mt-0.5 truncate">cashier@admin</div>
                        <div class="text-[10px] text-slate-500">pass: behemoth</div>
                    </button>

                    <button type="button" 
                            onclick="fillCredentials('chef@admin', 'behemoth', 'kitchen')"
                            class="p-2.5 rounded-lg bg-slate-950/70 border border-amber-500/20 hover:border-amber-500/50 text-left transition-all group">
                        <div class="flex items-center gap-1.5 text-amber-400 font-bold text-[11px]">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                            Akun KDS (Chef)
                        </div>
                        <div class="text-[11px] text-slate-300 font-mono mt-0.5 truncate">chef@admin</div>
                        <div class="text-[10px] text-slate-500">pass: behemoth</div>
                    </button>
                </div>

                <!-- Superadmin Dedicated Portal Link -->
                <div class="mt-4 pt-3 border-t border-slate-800/80 text-center">
                    <p class="text-[11px] text-slate-500">
                        Khusus Pengelola Restoran? 
                        <a href="{{ route('cms.login') }}" class="text-indigo-400 hover:text-indigo-300 font-semibold hover:underline">
                            Login Superadmin Dashboard &rarr;
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function switchLoginRole(role) {
    const isKitchen = role === 'kitchen';
    document.getElementById('expected_role').value = role;
    
    // Tab styling
    const tabCashier = document.getElementById('tab-cashier');
    const tabKitchen = document.getElementById('tab-kitchen');
    const roleBanner = document.getElementById('role-banner');
    const bannerTitle = document.getElementById('banner-role-title');
    const bannerDesc = document.getElementById('banner-role-desc');
    const submitBtn = document.getElementById('submit-btn');
    const btnText = document.getElementById('btn-text');
    const usernameInput = document.getElementById('username');

    if (isKitchen) {
        tabKitchen.className = 'flex-1 py-2.5 px-3 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-2 bg-amber-600 text-white shadow-lg shadow-amber-950';
        tabCashier.className = 'flex-1 py-2.5 px-3 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-2 text-slate-400 hover:text-white';
        
        roleBanner.className = 'mb-5 p-3 rounded-xl border text-xs flex items-start gap-2.5 transition-colors bg-amber-500/10 border-amber-500/30 text-amber-300';
        bannerTitle.innerText = 'Mode Login: Kitchen Display (Chef)';
        bannerDesc.innerText = 'Akses antrian pesanan realtime & status dapur.';

        submitBtn.className = 'w-full py-3 px-4 rounded-xl font-bold text-sm text-white transition-all shadow-lg flex items-center justify-center gap-2 bg-amber-600 hover:bg-amber-500 shadow-amber-950/50';
        btnText.innerText = 'Masuk ke Dashboard Dapur (KDS)';

        if (usernameInput.value === 'cashier@admin' || !usernameInput.value) {
            usernameInput.value = 'chef@admin';
        }
    } else {
        tabCashier.className = 'flex-1 py-2.5 px-3 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-2 bg-rose-600 text-white shadow-lg shadow-rose-950';
        tabKitchen.className = 'flex-1 py-2.5 px-3 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-2 text-slate-400 hover:text-white';

        roleBanner.className = 'mb-5 p-3 rounded-xl border text-xs flex items-start gap-2.5 transition-colors bg-rose-500/10 border-rose-500/30 text-rose-300';
        bannerTitle.innerText = 'Mode Login: Kasir & Transaksi POS';
        bannerDesc.innerText = 'Akses kasir POS, katalog produk, dan transaksi.';

        submitBtn.className = 'w-full py-3 px-4 rounded-xl font-bold text-sm text-white transition-all shadow-lg flex items-center justify-center gap-2 bg-rose-600 hover:bg-rose-500 shadow-rose-950/50';
        btnText.innerText = 'Masuk ke Sistem Kasir POS';

        if (usernameInput.value === 'chef@admin' || !usernameInput.value) {
            usernameInput.value = 'cashier@admin';
        }
    }
}

function fillCredentials(username, pass, role) {
    switchLoginRole(role);
    document.getElementById('username').value = username;
    document.getElementById('password').value = pass;
}

function togglePasswordVisibility() {
    const input = document.getElementById('password');
    if (input.type === 'password') {
        input.type = 'text';
    } else {
        input.type = 'password';
    }
}
</script>
@endsection
