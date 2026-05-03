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
        <div class="font-semibold">What this page is for</div>
        <ol class="list-decimal list-inside space-y-1">
            <li>Attach or switch Instagram accounts on the <a href="{{ route('instagram.accounts') }}" class="font-medium underline">Accounts page</a>.</li>
            <li>Use this page to confirm the active account is really connected.</li>
            <li>Open <a href="{{ route('settings.instagram') }}" class="font-medium underline">Settings</a> only when you need default test values or the optional Meta token.</li>
            <li>Use <a href="{{ route('instagram.raw') }}" class="font-medium underline">Raw Workspace</a> only for deep debugging and scan tools.</li>
        </ol>
    </div>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-5">
            <div>
                <div class="text-xs uppercase tracking-wide text-gray-500">Current active account</div>
                <div class="mt-3 text-2xl font-semibold text-gray-900">{{ $status['active_account']['label'] ?? 'No active Instagram account selected' }}</div>
                <div class="mt-1 text-sm text-gray-500 font-mono">{{ $status['active_profile'] }}</div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                    <div class="text-xs uppercase tracking-wide text-gray-500">Instagram username</div>
                    <div class="mt-1 text-base font-semibold text-gray-900">{{ $status['active_account']['instagram_username'] ?? 'Not saved yet' }}</div>
                </div>
                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                    <div class="text-xs uppercase tracking-wide text-gray-500">Saved password</div>
                    <div class="mt-1 text-base font-semibold {{ !empty($status['active_account']['password_configured']) ? 'text-emerald-700' : 'text-red-600' }}">
                        {{ !empty($status['active_account']['password_configured']) ? ($status['active_account']['password_masked'] ?: 'Configured') : 'Missing' }}
                    </div>
                </div>
                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                    <div class="text-xs uppercase tracking-wide text-gray-500">Default quick profile</div>
                    <div class="mt-1 text-base font-semibold text-gray-900">{{ $status['default_profile_username'] ?: 'Not set' }}</div>
                    <div class="mt-1 text-xs text-gray-500">Story target: {{ $status['default_story_username'] ?: 'Not set' }}</div>
                </div>
                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                    <div class="text-xs uppercase tracking-wide text-gray-500">Meta token</div>
                    <div class="mt-1 text-base font-semibold {{ $status['has_meta_token'] ? 'text-emerald-700' : 'text-gray-500' }}">{{ $status['has_meta_token'] ? 'Configured' : 'Not set' }}</div>
                    <div class="mt-1 text-xs text-gray-500">{{ $status['meta_token_masked'] ?: 'Public scrape fallback only' }}</div>
                </div>
            </div>

            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route('instagram.accounts') }}" class="inline-flex items-center px-4 py-2 bg-emerald-600 text-white text-sm font-medium rounded-lg hover:bg-emerald-700">Manage accounts</a>
                <a href="{{ route('settings.instagram') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-50">Open settings</a>
                <a href="{{ route('instagram.raw') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-50">Open raw workspace</a>
            </div>
        </div>

            <div>
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-5">
                    <div class="flex items-start justify-between gap-3 flex-wrap">
                        <div>
                            <div class="text-xs uppercase tracking-wide text-gray-500">Current connection state</div>
                            <div class="mt-3 flex items-center gap-3 flex-wrap">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold"
                                      :class="stateBadgeClass()"
                                      x-text="stateBadge()"></span>
                                <span class="text-sm text-gray-500" x-text="stateHeadline()"></span>
                            </div>
                        </div>
                        <button type="button" @click="runIntegrity()" :disabled="loading" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50">
                            <span x-text="loading ? 'Checking…' : 'Check live status'"></span>
                        </button>
                    </div>

                    <div class="rounded-xl border px-4 py-4"
                         :class="statePanelClass()">
                        <div class="text-base font-semibold" x-text="integrity.message"></div>
                        <div class="mt-2 text-sm" x-text="integrity.detail || 'Click the button to inspect the live browser session.'"></div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                            <div class="text-xs uppercase tracking-wide text-gray-500">Current browser URL</div>
                            <template x-if="currentUrl()">
                                <a :href="currentUrl()" target="_blank" rel="noopener" class="mt-1 block text-sm font-medium text-blue-700 underline break-all" x-text="currentUrl()"></a>
                            </template>
                            <template x-if="!currentUrl()">
                                <div class="mt-1 text-sm text-gray-500">No URL loaded yet.</div>
                            </template>
                        </div>
                        <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                            <div class="text-xs uppercase tracking-wide text-gray-500">What to do next</div>
                            <div class="mt-1 text-sm font-medium text-gray-900" x-text="nextStep()"></div>
                        </div>
                    </div>

                    <details class="rounded-xl border border-gray-200 bg-white">
                        <summary class="cursor-pointer px-4 py-3 text-sm font-medium text-gray-800">Show technical proof</summary>
                        <div class="border-t border-gray-200 p-4 space-y-4">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                                    <div class="text-xs uppercase tracking-wide text-gray-500">Worker state</div>
                                    <div class="mt-1 text-sm font-medium text-gray-900" x-text="workerState()"></div>
                                </div>
                                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                                    <div class="text-xs uppercase tracking-wide text-gray-500">Detected page state</div>
                                    <div class="mt-1 text-sm font-medium text-gray-900" x-text="pageState()"></div>
                                </div>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-slate-950 text-slate-100 p-4 text-xs font-mono min-h-[12rem] whitespace-pre-wrap" x-text="pretty(integrity)"></div>
                        </div>
                    </details>
                </div>
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

        statusData() {
            return this.integrity?.data?.instagram_status?.data || {};
        },

        currentUrl() {
            return this.statusData()?.probe?.url || this.statusData()?.worker?.current_url || '';
        },

        workerState() {
            const worker = this.statusData()?.worker || {};
            if (!Object.keys(worker).length) return 'No worker details loaded yet.';
            return worker.last_event
                ? `${worker.last_event}${worker.last_title ? ' · ' + worker.last_title : ''}`
                : (worker.last_title || 'Worker responded without an event label.');
        },

        pageState() {
            const data = this.statusData();
            if (data.connected) return 'Authenticated Instagram session';
            if (data.verification_required) return `Verification code required${data.verification_channel ? ' · ' + data.verification_channel : ''}`;
            if (data.challenge) return 'Instagram challenge / checkpoint';
            if (data.login_form) return 'Login form visible';
            return 'Unknown or empty page state';
        },

        stateBadge() {
            const data = this.statusData();
            if (!this.loading && !Object.keys(data).length && !this.integrity.success && !this.integrity.detail) return 'Not checked yet';
            if (data.connected) return 'Connected';
            if (data.verification_required) return 'Needs code';
            if (data.challenge) return 'Challenge';
            if (data.login_form) return 'Needs login';
            return this.integrity.success ? 'Worker healthy' : 'Check required';
        },

        stateBadgeClass() {
            const data = this.statusData();
            if (data.connected) return 'bg-emerald-100 text-emerald-800';
            if (data.verification_required) return 'bg-amber-100 text-amber-800';
            if (data.challenge || data.login_form) return 'bg-red-100 text-red-800';
            return this.integrity.success ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-700';
        },

        stateHeadline() {
            const data = this.statusData();
            if (data.connected) return 'The active browser profile is authenticated and usable.';
            if (data.verification_required) return 'Instagram is asking for a verification code before this profile can be used.';
            if (data.challenge) return 'Instagram is blocking the session behind a challenge or checkpoint.';
            if (data.login_form) return 'Instagram is still showing the login form for this profile.';
            return 'Run the live check to confirm what the browser session is actually doing.';
        },

        statePanelClass() {
            const data = this.statusData();
            if (data.connected) return 'border-emerald-200 bg-emerald-50 text-emerald-900';
            if (data.verification_required) return 'border-amber-200 bg-amber-50 text-amber-900';
            if (data.challenge || data.login_form) return 'border-red-200 bg-red-50 text-red-900';
            return 'border-blue-200 bg-blue-50 text-blue-900';
        },

        nextStep() {
            const data = this.statusData();
            if (data.connected) return 'Open Raw Workspace to run profile scans, story pulls, or post checks.';
            if (data.verification_required) return 'Go to Accounts, submit the fresh verification code for this browser profile, then check status again.';
            if (data.challenge) return 'Resolve the Instagram checkpoint/challenge first, then run the live check again.';
            if (data.login_form) return 'Go to Accounts and use Log in with saved credentials for the active profile.';
            return 'Attach an account on the Accounts page, then run the live check again.';
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
