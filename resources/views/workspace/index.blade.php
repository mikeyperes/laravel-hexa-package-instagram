@extends('layouts.app')

@section('title', 'Instagram')
@section('header', 'Instagram')

@section('content')
<div x-data="instagramHomePage()" class="max-w-5xl space-y-6">
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Instagram</h1>
                <p class="mt-2 text-sm text-gray-500">Dedicated Instagram package for attached browser accounts, connection testing, profile/story scraping, and public post import.</p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route('instagram.accounts') }}" class="inline-flex items-center px-4 py-2 bg-emerald-600 text-white text-sm font-medium rounded-lg hover:bg-emerald-700">Attach accounts</a>
                <a href="{{ route('settings.instagram') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-50">Open Settings</a>
                <a href="{{ route('instagram.raw') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-50">Open Raw Workspace</a>
            </div>
        </div>
    </div>

    <div class="bg-blue-50 border border-blue-200 rounded-xl p-5 text-sm text-blue-900 space-y-3">
        <div class="font-semibold">Main flow</div>
        <ol class="list-decimal list-inside space-y-1">
            <li>Attach one or more Instagram accounts on the <a href="{{ route('instagram.accounts') }}" class="font-medium underline">Accounts page</a>.</li>
            <li>Store the optional Meta token in <a href="{{ route('settings.instagram') }}" class="font-medium underline">Settings</a> if you want the official oEmbed path available before the public scrape fallback.</li>
            <li>Run the full connection test to confirm the active browser profile is really authenticated.</li>
            <li>Use <a href="{{ route('instagram.raw') }}" class="font-medium underline">Raw Workspace</a> for profile scans, story pulls, post import checks, and worker logs.</li>
        </ol>
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <div class="text-xs uppercase tracking-wide text-gray-500">Active profile</div>
            <div class="mt-2 text-sm font-semibold text-gray-900">{{ $status['active_profile'] }}</div>
            <div class="mt-1 text-xs text-gray-500 font-mono">{{ $status['active_account']['label'] ?? 'No saved account selected' }}</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <div class="text-xs uppercase tracking-wide text-gray-500">Instagram username</div>
            <div class="mt-2 text-sm font-semibold text-gray-900">{{ $status['active_account']['instagram_username'] ?? 'Not saved' }}</div>
            <div class="mt-1 text-xs text-gray-500">{{ !empty($status['active_account']['password_configured']) ? ($status['active_account']['password_masked'] ?: 'Password configured') : 'Password missing' }}</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <div class="text-xs uppercase tracking-wide text-gray-500">Default profile scan username</div>
            <div class="mt-2 text-sm font-semibold text-gray-900">{{ $status['default_profile_username'] ?: 'Not set' }}</div>
            <div class="mt-1 text-xs text-gray-500">Story username: {{ $status['default_story_username'] ?: 'Not set' }}</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <div class="text-xs uppercase tracking-wide text-gray-500">Meta token</div>
            <div class="mt-2 text-sm font-semibold {{ $status['has_meta_token'] ? 'text-emerald-700' : 'text-gray-500' }}">{{ $status['has_meta_token'] ? 'Configured' : 'Not set' }}</div>
            <div class="mt-1 text-xs text-gray-500 font-mono">{{ $status['meta_token_masked'] ?: 'public scrape fallback only' }}</div>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-4">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Full Connection Test</h2>
                <p class="mt-1 text-sm text-gray-500">Proof that browser-worker is reachable and the active Instagram browser profile is actually authenticated.</p>
            </div>
            <button type="button" @click="runIntegrity()" :disabled="loading" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50">
                <span x-text="loading ? 'Testing...' : 'Run Connection Test'"></span>
            </button>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                <div class="text-xs uppercase tracking-wide text-gray-500">Current result</div>
                <div class="mt-2 text-sm font-semibold" :class="integrity.success ? 'text-emerald-700' : 'text-amber-700'" x-text="integrity.message"></div>
                <div class="mt-2 text-sm text-gray-600" x-text="integrity.detail || 'Run the test to load the live worker and Instagram state.'"></div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-950 text-slate-100 p-4 text-xs font-mono min-h-[12rem] whitespace-pre-wrap" x-text="pretty(integrity)"></div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function instagramHomePage() {
    return {
        profile: @json($status['active_profile']),
        integrity: {
            success: false,
            message: 'Run the connection test to load the live browser-worker and Instagram account state.',
            detail: '',
            data: {},
        },
        loading: false,

        init() {
            this.runIntegrity();
        },

        pretty(payload) {
            try {
                return JSON.stringify(payload, null, 2);
            } catch (error) {
                return String(payload);
            }
        },

        async runIntegrity() {
            this.loading = true;
            try {
                const url = new URL('{{ route('instagram.integrity') }}', window.location.origin);
                url.searchParams.set('profile', this.profile || 'instagram-main');
                const response = await fetch(url, {
                    headers: {
                        'Accept': 'application/json',
                    },
                });
                this.integrity = await response.json();
            } catch (error) {
                this.integrity = {
                    success: false,
                    message: 'Connection test failed.',
                    detail: error?.message || 'Unknown error.',
                    data: {},
                };
            } finally {
                this.loading = false;
            }
        },
    };
}
</script>
@endpush
@endsection
