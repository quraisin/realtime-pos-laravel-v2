@extends('layouts.app')

@section('title', 'Analitik Transaksi & Penjualan - Qurais Kitchen')

@section('content')
<div id="analytics-root" 
     data-user="{{ json_encode(Auth::user()->load('roles')) }}"
     class="flex-1 flex flex-col p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto w-full">
    <!-- React AnalyticsApp will mount here -->
    <div class="flex-1 flex items-center justify-center">
        <div class="flex items-center gap-3 text-slate-400">
            <svg class="animate-spin h-6 w-6 text-rose-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
            <span>Memuat Data Analitik...</span>
        </div>
    </div>
</div>
@endsection
