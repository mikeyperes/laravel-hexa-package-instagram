@extends('layouts.app')

@section('title', 'Instagram')
@section('header', 'Instagram')

@section('content')
<div x-data="instagramHomePage()" class="max-w-5xl space-y-6">
    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Instagram</h1>
                <p class="mt-2 text-sm text-gray-500">Account bindings and one Browser Worker connection check for Instagram collection.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('instagram.accounts') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Account bindings</a>
                <a href="{{ route('settings.instagram') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Settings</a>
                <a href="{{ route('instagram.raw') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Scan diagnostics</a>
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-blue-200 bg-blue-50 p-5 text-sm text-blue-900">
        Browser Console owns the browser session, human login, and direct or proxy route. Instagram stores only the profile-to-account binding and reads verified Worker evidence.
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="space-y-5 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <div>
                <div class="text-xs uppercase tracking-wide text-gray-500">Selected binding</div>
                <div class="mt-2 text-2xl font-semibold text-gray-900">{{ $status['active_account']['label'] ?? 'No account binding' }}</div>
                <div class="mt-1 font-mono text-sm text-gray-500">{{ $status['active_profile'] }}</div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                    <div class="text-xs uppercase tracking-wide text-gray-500">Expected account</div>
                    <div class="mt-1 text-base font-semibold text-gray-900">{{ !empty($status['active_account']['instagram_username']) ? '@'.$status['active_account']['instagram_username'] : 'Not bound' }}</div>
                </div>
                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                    <div class="text-xs uppercase tracking-wide text-gray-500">Optional Meta token</div>
                    <div class="mt-1 text-base font-semibold {{ $status['has_meta_token'] ? 'text-emerald-700' : 'text-gray-500' }}">{{ $status['has_meta_token'] ? 'Configured' : 'Not set' }}</div>
                    <div class="mt-1 text-xs text-gray-500">Used only by public post import.</div>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ $status['active_account']['console_url'] ?? route('browser-console.sessions', ['profile' => $status['active_profile']]) }}" class="inline-flex items-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">Open Browser Console</a>
                <a href="{{ route('instagram.accounts') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Edit binding</a>
            </div>
        </div>

        <div class="space-y-5 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div class="text-xs uppercase tracking-wide text-gray-500">Verified connection</div>
                    <div class="mt-2 flex flex-wrap items-center gap-3">
                        <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-semibold" :class="badgeClass()" x-text="badge()"></span>
                        <span class="text-sm text-gray-500" x-text="connection.detail || 'Checking Worker evidence…'"></span>
                    </div>
                </div>
                <button type="button" @click="checkStatus()" :disabled="loading" class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50" x-text="loading ? 'Checking…' : 'Check status'"></button>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                    <div class="text-xs uppercase tracking-wide text-gray-500">Actual route</div>
                    <div class="mt-1 text-sm font-semibold text-gray-900" x-text="data().transport || 'Unknown'"></div>
                </div>
                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                    <div class="text-xs uppercase tracking-wide text-gray-500">Account identity</div>
                    <div class="mt-1 text-sm font-semibold text-gray-900" x-text="data().account_verified ? 'Verified' : 'Not verified'"></div>
                </div>
                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                    <div class="text-xs uppercase tracking-wide text-gray-500">Authentication</div>
                    <div class="mt-1 text-sm font-semibold text-gray-900" x-text="data().authenticated ? 'Verified' : 'Not verified'"></div>
                </div>
                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                    <div class="text-xs uppercase tracking-wide text-gray-500">Worker runtime</div>
                    <div class="mt-1 text-sm font-semibold text-gray-900" x-text="data().runtime_ready ? 'Ready' : 'Not ready'"></div>
                </div>
            </div>

            <template x-if="!loading && !data().connected">
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    Open this profile in Browser Console, restore the Instagram session if needed, then check status again.
                </div>
            </template>

            <details class="rounded-xl border border-gray-200 bg-white">
                <summary class="cursor-pointer px-4 py-3 text-sm font-medium text-gray-800">Technical evidence</summary>
                <pre class="overflow-auto border-t border-gray-200 p-4 text-xs" x-text="pretty(connection)"></pre>
            </details>
        </div>
    </div>
</div>

@push('scripts')
<script>
function instagramHomePage() {
    return {
        profile: @json($status['active_profile']),
        connection: { success: false, detail: '', data: {} },
        loading: false,

        init() {
            this.checkStatus();
        },

        data() {
            return this.connection?.data || {};
        },

        badge() {
            if (this.loading) return 'Checking';
            return this.data().connected ? 'Connected' : 'Needs attention';
        },

        badgeClass() {
            return this.data().connected
                ? 'bg-emerald-100 text-emerald-800'
                : 'bg-amber-100 text-amber-800';
        },

        pretty(value) {
            try {
                return JSON.stringify(value, null, 2);
            } catch (error) {
                return String(value);
            }
        },

        async checkStatus() {
            this.loading = true;
            try {
                const url = new URL(@json(route('instagram.status')), window.location.origin);
                url.searchParams.set('profile', this.profile);
                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                this.connection = await response.json();
            } catch (error) {
                this.connection = {
                    success: false,
                    detail: error?.message || 'The connection check failed.',
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
