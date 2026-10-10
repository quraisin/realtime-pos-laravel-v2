@extends('layouts.app')

@section('title', 'Sistem Kasir POS - Qurais Kitchen')

@section('content')
<div id="pos-root" 
     data-user="{{ json_encode(Auth::user()->load('roles')) }}"
     data-settings="{{ json_encode($printerSettings) }}"
     class="flex-1 flex flex-col h-[calc(100vh-4rem)] overflow-hidden">
    <!-- React PosApp will mount here -->
    <div class="flex-1 flex items-center justify-center">
        <div class="flex items-center gap-3 text-slate-400">
            <svg class="animate-spin h-6 w-6 text-rose-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
            <span>Memuat Sistem Kasir POS...</span>
        </div>
    </div>
</div>
@endsection
