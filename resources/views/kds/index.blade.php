@extends('layouts.app')

@section('title', 'Kitchen Display System (KDS) - Qurais Kitchen')

@section('content')
<div id="kds-root" 
     data-user="{{ json_encode(Auth::user()->load('roles')) }}"
     data-settings="{{ json_encode($printerSettings) }}"
     class="flex-1 flex flex-col h-[calc(100vh-4rem)] overflow-hidden">
    <!-- React KdsApp will mount here -->
    <div class="flex-1 flex items-center justify-center">
        <div class="flex items-center gap-3 text-slate-400">
            <svg class="animate-spin h-6 w-6 text-amber-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
            <span>Menghubungkan ke Kitchen Display System (KDS) Realtime...</span>
        </div>
    </div>
</div>
@endsection
