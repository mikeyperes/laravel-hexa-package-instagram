@extends('layouts.app')

@section('title', 'Instagram Settings')
@section('header', 'Instagram Settings')

@section('content')
<div x-data="instagramSettingsPage()" class="max-w-5xl space-y-6">
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Instagram Settings</h1>
                <p class="mt-2 text-sm text-gray-500">Attach browser accounts first, then use this page to save the default test targets and run the full connection check.</p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route('instagram.accounts') }}" class="inline-flex items-center px-4 py-2 bg-emerald-600 text-white text-sm font-medium rounded-lg hover:bg-emerald-700">Open Accounts</a>
                <a href="{{ route('instagram.raw') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-50">Open Raw Workspace</a>
            </div>
        </div>
    </div>

    <div class="bg-blue-50 border border-blue-200 rounded-xl p-5 text-sm text-blue-900 space-y-3">
        <div class="font-semibold">Instructions</div>
        <ol class="list-decimal list-inside space-y-1 text-blue-900">
            <li>Go to the <a href="{{ route('instagram.accounts') }}" class="font-medium underline">Accounts page</a>, save one or more Instagram account profiles, and store the login password there.</li>
            <li>Click <span class="font-medium">Log in with saved credentials</span> on the account card you want to use.</li>
            <li>Come back here, save the default usernames / sample post URL, and run the full connection test.</li>
            <li>Use <a href="{{ route('instagram.raw') }}" class="font-medium underline">Raw Workspace</a> for live profile scans, story pulls, and post-import debugging.</li>
        </ol>
        <div class="flex items-center gap-3 flex-wrap text-sm">
            <a href="{{ route('instagram.accounts') }}" class="font-medium underline">Open Accounts page</a>
            <a href="{{ route('instagram.raw') }}" class="font-medium underline">Open Raw Workspace</a>
            <a href="{{ route('settings.browser-worker') }}" class="font-medium underline">Open Browser Worker settings</a>
            <a href="https://www.instagram.com/" target="_blank" rel="noopener" class="font-medium underline">Open Instagram in a new tab</a>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
        <div class="space-y-6">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-5">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Default Test Targets</h2>
                    <p class="mt-1 text-sm text-gray-500">These defaults prefill the Raw Workspace and the full connection test.</p>
                </div>

                <form @submit.prevent="save()" class="space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Default profile username</label>
                        <input type="text" x-model="form.default_profile_username" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="jpnmiami">
                        <p class="mt-1 text-xs text-gray-500">Used for the quick profile-scan checks.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Default story username</label>
                        <input type="text" x-model="form.default_story_username" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="jpnmiami">
                        <p class="mt-1 text-xs text-gray-500">Used for the quick story-pull checks.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Default post URL</label>
                        <input type="url" x-model="form.default_post_url" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="https://www.instagram.com/p/.../">
                        <p class="mt-1 text-xs text-gray-500">Used for the public post-import check.</p>
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <button type="submit" :disabled="saving" class="inline-flex items-center px-5 py-2.5 bg-emerald-600 text-white text-sm font-medium rounded-lg hover:bg-emerald-700 disabled:opacity-50">
                            <span x-text="saving ? 'Saving...' : 'Save Settings'"></span>
                        </button>
                    </div>
                </form>
            </div>

            <details class="bg-white rounded-xl border border-gray-200 shadow-sm p-6" open>
                <summary class="cursor-pointer text-lg font-semibold text-gray-900">Optional Meta oEmbed Token</summary>
                <div class="mt-4 space-y-5">
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 space-y-2">
                        <div class="font-semibold">What this token is for</div>
                        <p>This is optional. The Instagram package can still scrape many public posts without it.</p>
                        <p>Save this only if you want the official Meta oEmbed path available before the public scrape fallback.</p>
                        <div class="text-xs text-amber-800">Last updated: {{ $status['credential_updated_at'] ?: 'unknown' }}</div>
                    </div>

                    <x-hexa-credential-field
                        slug="instagram"
                        key-name="meta_access_token"
                        label="Instagram / Meta Access Token"
                        :test-url="route('settings.instagram.test-meta-token')"
                        help="Optional Meta oEmbed token. Public scrape fallback remains available without it."
                    />
                </div>
            </details>
        </div>

        <div class="space-y-6">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-4">
                <div class="flex items-start justify-between gap-3 flex-wrap">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Full Connection Test</h2>
                        <p class="mt-1 text-sm text-gray-500">Checks browser-worker health and whether the active Instagram browser profile is really logged in.</p>
                    </div>
                    <button type="button" @click="runTest()" :disabled="testing" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50">
                        <span x-text="testing ? 'Testing...' : 'Run Connection Test'"></span>
                    </button>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-950 text-slate-100 p-4 text-xs font-mono min-h-[14rem] whitespace-pre-wrap" x-text="testOutput"></div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-4">
                <h2 class="text-lg font-semibold text-gray-900">Current Active Account</h2>
                <div class="space-y-3 text-sm">
                    <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                        <div class="text-xs uppercase tracking-wide text-gray-500">Active profile</div>
                        <div class="mt-1 font-medium text-gray-900">{{ $status['active_profile'] }}</div>
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                        <div class="text-xs uppercase tracking-wide text-gray-500">Attached Instagram username</div>
                        <div class="mt-1 font-medium text-gray-900">{{ $status['active_account']['instagram_username'] ?? 'No saved account selected yet' }}</div>
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                        <div class="text-xs uppercase tracking-wide text-gray-500">Saved password</div>
                        <div class="mt-1 font-medium {{ !empty($status['active_account']['password_configured']) ? 'text-emerald-700' : 'text-red-600' }}">
                            {{ !empty($status['active_account']['password_configured']) ? ($status['active_account']['password_masked'] ?: 'Configured') : 'Missing' }}
                        </div>
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                        <div class="text-xs uppercase tracking-wide text-gray-500">Meta token</div>
                        <div class="mt-1 font-medium {{ $status['has_meta_token'] ? 'text-emerald-700' : 'text-gray-500' }}">
                            {{ $status['has_meta_token'] ? ($status['meta_token_masked'] ?: 'Configured') : 'Not set' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <template x-if="toast.show">
        <div class="fixed bottom-6 right-6 z-50 max-w-sm w-full rounded-xl border px-5 py-4 shadow-lg"
             :class="toast.type === 'success' ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800'">
            <div class="text-sm font-medium" x-text="toast.message"></div>
        </div>
    </template>
</div>

@push('scripts')
<script>
function instagramSettingsPage() {
    return {
        form: {
            default_profile_username: @json($settings['default_profile_username']),
            default_story_username: @json($settings['default_story_username']),
            default_post_url: @json($settings['default_post_url']),
        },
        saving: false,
        testing: false,
        testOutput: 'Run the full connection test to verify browser-worker health and the active Instagram browser session.',
        toast: { show: false, message: '', type: 'success' },
        toastTimer: null,

        csrfToken() {
            return document.querySelector('meta[name=\"csrf-token\"]')?.content || '';
        },

        showToast(message, type = 'success') {
            if (this.toastTimer) clearTimeout(this.toastTimer);
            this.toast = { show: true, message, type };
            this.toastTimer = setTimeout(() => { this.toast.show = false; }, 3500);
        },

        pretty(payload) {
            try {
                return JSON.stringify(payload, null, 2);
            } catch (error) {
                return String(payload);
            }
        },

        async save() {
            this.saving = true;

            try {
                const response = await fetch('{{ route('settings.instagram.update') }}', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken(),
                    },
                    body: JSON.stringify(this.form),
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    this.showToast(data.message || 'Instagram settings saved.');
                } else {
                    this.showToast(data.message || 'Failed to save Instagram settings.', 'error');
                }
            } catch (error) {
                this.showToast('Failed to save Instagram settings.', 'error');
            } finally {
                this.saving = false;
            }
        },

        async runTest() {
            this.testing = true;
            this.testOutput = 'Running Instagram connection test...';

            try {
                const response = await fetch('{{ route('settings.instagram.test') }}', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken(),
                    },
                });

                const data = await response.json();
                this.testOutput = this.pretty(data);

                if (response.ok && data.success) {
                    this.showToast(data.message || 'Instagram connection is healthy.');
                } else {
                    this.showToast(data.message || 'Instagram connection test failed.', 'error');
                }
            } catch (error) {
                this.testOutput = error?.message || String(error);
                this.showToast('Instagram connection test failed.', 'error');
            } finally {
                this.testing = false;
            }
        },
    };
}
</script>
@endpush
@endsection
