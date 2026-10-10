@extends('layouts.app')

@section('title', 'Superadmin Dashboard - Qurais Kitchen')

@section('content')
<div class="flex-1 max-w-7xl w-full mx-auto p-4 sm:p-6 lg:p-8">
    <div id="cms-root"
         data-user="{{ json_encode($user) }}"
         data-settings="{{ json_encode($printerSettings) }}"
         data-initial-tab="{{ $initialTab ?? 'dashboard' }}">
        <!-- Initial loading state while React initializes -->
        <div class="h-96 flex flex-col items-center justify-center text-slate-500 text-sm space-y-3">
            <svg class="animate-spin h-8 w-8 text-indigo-500" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
            </svg>
            <span class="font-medium text-slate-400">Memuat Qurais Kitchen Dashboard...</span>
        </div>
    </div>
</div>
@endsection
