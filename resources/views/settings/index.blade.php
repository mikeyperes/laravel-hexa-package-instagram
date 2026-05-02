@extends('layouts.app')

@section('title', 'Instagram Settings')
@section('header', 'Instagram Settings')

@section('content')
<div x-data="instagramSettingsPage()" class="max-w-5xl space-y-6">
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Instagram Settings</h1>
                <p class="mt-2 text-sm text-gray-500">Attach browser accounts first, then use this page to save default test targets and run account-specific connection checks.</p>
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
            <li>Go to the <a href="{{ route('instagram.accounts') }}" class="font-medium underline">Accounts page</a>, save one or more Instagram accounts, and store each password in its Hexa Core credential field.</li>
            <li>Use <span class="font-medium">Log in with saved credentials</span> on the account card you want to attach.</li>
            <li>Come back here, pick which saved account to test, and run the full connection check.</li>
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
                    <p class="mt-1 text-sm text-gray-500">These defaults prefill the Raw Workspace and the public import checks.</p>
                </div>

                <form @submit.prevent="save()" class="space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Default profile username</label>
                        <input type="text" x-model="form.default_profile_username" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="jpnmiami">
                        <p class="mt-1 text-xs text-gray-500">Used for quick attached-account profile scans.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Default story username</label>
                        <input type="text" x-model="form.default_story_username" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="jpnmiami">
                        <p class="mt-1 text-xs text-gray-500">Used for quick attached-account story pulls.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Default post URL</label>
                        <input type="url" x-model="form.default_post_url" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="https://www.instagram.com/p/.../">
                        <p class="mt-1 text-xs text-gray-500">Used for the public post-import check.</p>
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <button type="submit" :disabled="saving" class="inline-flex items-center px-5 py-2.5 bg-emerald-600 text-white text-sm font-medium rounded-lg hover:bg-emerald-700 disabled:opacity-50">
                            <span x-text="saving ? 'Saving…' : 'Save Settings'"></span>
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
                        <p class="mt-1 text-sm text-gray-500">Pick a saved account and verify that browser-worker can prove a real authenticated Instagram session.</p>
                    </div>
                    <button type="button" @click="runTest()" :disabled="testing || !testProfile" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50">
                        <span x-text="testing ? 'Testing…' : 'Run Connection Test'"></span>
                    </button>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Saved account to test</label>
                    <select x-model="testProfile" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                        <option value="">Select a saved Instagram account</option>
                        @foreach($status['accounts'] as $account)
                            <option value="{{ $account['profile'] }}">{{ $account['label'] }} — {{ $account['instagram_username'] ?: $account['profile'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 space-y-3">
                    <div class="flex items-center justify-between gap-3 flex-wrap">
                        <div>
                            <div class="text-xs uppercase tracking-wide text-gray-500">Current result</div>
                            <div class="mt-1 text-sm font-semibold" :class="testSummaryClass()" x-text="testSummary()"></div>
                        </div>
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium"
                              :class="testBadgeClass()"
                              x-text="testBadge()"></span>
                    </div>
                    <div class="text-sm text-gray-700" x-text="testDetail()"></div>
                    <template x-if="testResult?.data?.instagram_status?.data">
                        <div class="grid gap-3 text-sm">
                            <div class="rounded-lg border border-gray-200 bg-white px-4 py-3">
                                <div class="text-xs uppercase tracking-wide text-gray-500">Account</div>
                                <div class="mt-1 font-medium text-gray-900" x-text="testAccountLabel()"></div>
                                <div class="mt-1 text-xs text-gray-500 font-mono" x-text="testProfile || 'No account selected'"></div>
                            </div>
                            <div class="rounded-lg border border-gray-200 bg-white px-4 py-3">
                                <div class="text-xs uppercase tracking-wide text-gray-500">Current URL</div>
                                <div class="mt-1 text-gray-900 break-all" x-text="testCurrentUrl()"></div>
                            </div>
                            <div class="rounded-lg border border-gray-200 bg-white px-4 py-3">
                                <div class="text-xs uppercase tracking-wide text-gray-500">Worker state</div>
                                <div class="mt-1 text-gray-900" x-text="testWorkerState()"></div>
                            </div>
                        </div>
                    </template>
                </div>

                <x-hexa-log-viewer
                    title="Instagram Connection Test Log"
                    log-var="testLog"
                    slug="instagram-settings-test"
                    theme="dark"
                    :persist="false" />

                <details class="rounded-lg border border-gray-200 bg-white" x-show="testResult" x-cloak>
                    <summary class="cursor-pointer px-4 py-3 text-sm font-medium text-gray-800">Show raw test payload</summary>
                    <div class="border-t border-gray-200 px-4 py-3">
                        <pre class="text-xs font-mono whitespace-pre-wrap break-words text-gray-700" x-text="pretty(testResult)"></pre>
                    </div>
                </details>
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
                        <div class="text-xs uppercase tracking-wide text-gray-500">Saved accounts</div>
                        <div class="mt-1 font-medium text-gray-900">{{ count($status['accounts']) }}</div>
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
        accounts: @json($status['accounts']),
        form: {
            default_profile_username: @json($settings['default_profile_username']),
            default_story_username: @json($settings['default_story_username']),
            default_post_url: @json($settings['default_post_url']),
        },
        saving: false,
        testing: false,
        testProfile: @json($status['active_profile']),
        testResult: null,
        testLog: [],
        toast: { show: false, message: '', type: 'success' },
        toastTimer: null,

        init() {
            this.log('info', 'Instagram settings page loaded. Pick a saved account and run the connection test when ready.');
        },

        csrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.content || '';
        },

        now() {
            return new Date().toLocaleTimeString();
        },

        log(type, message, detail = null) {
            this.testLog.push({ type, message, detail, time: this.now() });
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
                    this.log('success', 'Saved Instagram settings.', this.form);
                    this.showToast(data.message || 'Instagram settings saved.');
                } else {
                    this.log('error', data.message || 'Failed to save Instagram settings.', data.errors || null);
                    this.showToast(data.message || 'Failed to save Instagram settings.', 'error');
                }
            } catch (error) {
                this.log('error', 'Failed to save Instagram settings.', error?.message || String(error));
                this.showToast('Failed to save Instagram settings.', 'error');
            } finally {
                this.saving = false;
            }
        },

        async runTest() {
            if (!this.testProfile) {
                this.showToast('Select a saved Instagram account first.', 'error');
                return;
            }

            this.testing = true;
            this.log('info', 'Running Instagram connection test for ' + this.testProfile + '.');

            try {
                const response = await fetch('{{ route('settings.instagram.test') }}', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken(),
                    },
                    body: JSON.stringify({ profile: this.testProfile }),
                });

                const data = await response.json();
                this.testResult = data;

                if (response.ok && data.success) {
                    this.log('success', 'Instagram connection test passed for ' + this.testProfile + '.', {
                        message: data.message || '',
                        detail: data.detail || '',
                    });
                    this.showToast(data.message || 'Instagram connection is healthy.');
                } else {
                    this.log('error', 'Instagram connection test failed for ' + this.testProfile + '.', {
                        message: data.message || '',
                        detail: data.detail || '',
                    });
                    this.showToast(data.message || 'Instagram connection test failed.', 'error');
                }
            } catch (error) {
                this.testResult = { success: false, message: 'Instagram connection test failed.', detail: error?.message || String(error) };
                this.log('error', 'Instagram connection test request failed for ' + this.testProfile + '.', error?.message || String(error));
                this.showToast('Instagram connection test failed.', 'error');
            } finally {
                this.testing = false;
            }
        },

        selectedAccount() {
            return this.accounts.find((account) => account.profile === this.testProfile) || null;
        },

        testSummary() {
            if (!this.testResult) return 'No connection test run yet.';
            return this.testResult.message || 'Instagram connection test completed.';
        },

        testSummaryClass() {
            if (!this.testResult) return 'text-slate-700';
            return this.testResult.success ? 'text-emerald-700' : 'text-amber-700';
        },

        testBadge() {
            if (!this.testResult) return 'Idle';
            const status = this.testResult?.data?.instagram_status?.data || {};
            if (status.connected) return 'Connected';
            if (status.challenge) return 'Challenge';
            if (status.login_form) return 'Needs login';
            return this.testResult.success ? 'Healthy' : 'Not ready';
        },

        testBadgeClass() {
            if (!this.testResult) return 'bg-slate-100 text-slate-700';
            const status = this.testResult?.data?.instagram_status?.data || {};
            if (status.connected) return 'bg-emerald-100 text-emerald-700';
            if (status.challenge) return 'bg-amber-100 text-amber-700';
            if (status.login_form) return 'bg-rose-100 text-rose-700';
            return this.testResult.success ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-700';
        },

        testDetail() {
            if (!this.testResult) return 'Select a saved account and run the live test.';
            return this.testResult.detail || 'No additional detail returned.';
        },

        testCurrentUrl() {
            const worker = this.testResult?.data?.instagram_status?.data?.worker || {};
            const probe = this.testResult?.data?.instagram_status?.data?.probe || {};
            return worker.current_url || worker.final?.final_url || probe.url || 'No URL captured.';
        },

        testWorkerState() {
            const worker = this.testResult?.data?.instagram_status?.data?.worker || {};
            const probe = this.testResult?.data?.instagram_status?.data?.probe || {};
            return [worker.last_event, worker.last_error, probe.title].filter(Boolean).join(' · ') || 'No worker state captured.';
        },

        testAccountLabel() {
            const account = this.selectedAccount();
            if (!account) return 'No saved account selected';
            return account.label + (account.instagram_username ? ' · ' + account.instagram_username : '');
        },
    };
}
</script>
@endpush
@endsection
