@extends('layouts.app')

@section('title', 'Superadmin Dashboard Login - Qurais Kitchen')

@section('content')
<div class="flex-1 flex items-center justify-center p-4 sm:p-6 lg:p-8 relative overflow-hidden bg-radial from-slate-900 via-slate-950 to-slate-950">
    <!-- Ambient glow highlights (Indigo / Cyan theme for Superadmin Dashboard) -->
    <div class="absolute -top-40 -left-40 w-96 h-96 bg-indigo-600/15 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-40 -right-40 w-96 h-96 bg-purple-600/15 rounded-full blur-3xl pointer-events-none"></div>

    <div class="w-full max-w-md relative z-10">
        <!-- Logo & Title -->
        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-tr from-indigo-600 via-purple-500 to-pink-500 p-0.5 shadow-xl shadow-indigo-950/60 mb-3">
                <div class="w-full h-full bg-slate-950 rounded-[14px] flex items-center justify-center">
                    <span class="text-indigo-400 font-extrabold text-3xl">Q</span>
                </div>
            </div>
            <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-indigo-500/10 border border-indigo-500/30 text-indigo-300 text-[11px] font-bold tracking-wider uppercase mb-1">
                <span class="w-1.5 h-1.5 rounded-full bg-indigo-400 animate-ping"></span>
                Superadmin Control Panel
            </div>
            <h1 class="text-2xl font-black tracking-tight text-white">QURAIS KITCHEN DASHBOARD</h1>
            <p class="text-xs text-slate-400 mt-1">Kelola Menu, Kategori & Analitik Penjualan POS</p>
        </div>

        <!-- Main Card -->
        <div class="bg-slate-900/85 backdrop-blur-xl border border-indigo-950/80 rounded-2xl shadow-2xl p-6 sm:p-8 relative">
            <div class="absolute inset-x-0 -top-px h-px bg-gradient-to-r from-transparent via-indigo-500/50 to-transparent"></div>

            <!-- Role Badge Alert Banner -->
            <div class="mb-5 p-3 rounded-xl border border-indigo-500/30 bg-indigo-500/10 text-indigo-200 text-xs flex items-start gap-2.5">
                <svg class="w-4 h-4 shrink-0 mt-0.5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                <div>
                    <strong class="text-white">Portal Khusus Superadmin</strong>
                    <p class="opacity-90 mt-0.5 text-[11px]">
                        Akses eksklusif untuk manajemen katalog menu, struktur kategori, dan pemantauan analitik transaksi.
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
            <form method="POST" action="{{ route('cms.login.submit') }}" id="cms-login-form" class="space-y-4">
                @csrf

                <div>
                    <label for="username" class="block text-xs font-semibold text-slate-300 mb-1.5">Username / Email Superadmin</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500">
                            <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        </span>
                        <input type="text" 
                               name="username" 
                               id="username" 
                               value="{{ old('username', 'adnan@superadmin.com') }}" 
                               required
                               placeholder="adnan@superadmin.com" 
                               class="w-full pl-9 pr-4 py-2.5 bg-slate-950/80 border border-slate-700 focus:border-indigo-500 rounded-xl text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 transition-all font-mono">
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="password" class="block text-xs font-semibold text-slate-300">Password</label>
                        <span class="text-[10px] text-indigo-400 font-mono">Blessings777!</span>
                    </div>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500">
                            <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        </span>
                        <input type="password" 
                               name="password" 
                               id="password" 
                               value="Blessings777!"
                               required
                               placeholder="Password" 
                               class="w-full pl-9 pr-10 py-2.5 bg-slate-950/80 border border-slate-700 focus:border-indigo-500 rounded-xl text-sm text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 transition-all font-mono">
                        <button type="button" 
                                onclick="togglePasswordVisibility()" 
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-200">
                            <svg id="eye-icon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-between text-xs text-slate-400 pt-1">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="remember" checked class="rounded border-slate-700 bg-slate-950 text-indigo-600 focus:ring-indigo-500">
                        <span>Ingat Sesi Superadmin</span>
                    </label>
                </div>

                <!-- Submit Button -->
                <button type="submit" 
                        id="submit-btn" 
                        class="w-full py-3 px-4 rounded-xl font-bold text-sm text-white bg-indigo-600 hover:bg-indigo-500 shadow-lg shadow-indigo-950/50 transition-all flex items-center justify-center gap-2">
                    <span>Masuk ke Qurais Kitchen Dashboard</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                </button>
            </form>

            <!-- Quick Autofill Credentials & Back to POS -->
            <div class="mt-6 pt-5 border-t border-slate-800">
                <button type="button" 
                        onclick="fillCmsCredentials('adnan@superadmin.com', 'Blessings777!')"
                        class="w-full p-2.5 rounded-xl bg-slate-950/70 border border-indigo-500/30 hover:border-indigo-500/60 text-left transition-all group mb-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-1.5 text-indigo-400 font-bold text-xs">
                            <span class="w-2 h-2 rounded-full bg-indigo-400"></span>
                            Klik Untuk Isi Kredensial Superadmin:
                        </div>
                        <span class="text-[10px] text-indigo-300 font-semibold uppercase">Autofill</span>
                    </div>
                    <div class="text-xs text-slate-300 font-mono mt-1">adnan@superadmin.com</div>
                    <div class="text-[10px] text-slate-500 font-mono">Password: Blessings777!</div>
                </button>

                <div class="text-center">
                    <a href="{{ route('login') }}" class="text-xs text-slate-400 hover:text-slate-200 transition-colors inline-flex items-center gap-1">
                        &larr; Kembali ke Portal Kasir & Dapur (POS / KDS)
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function fillCmsCredentials(username, pass) {
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
