@extends('layouts.app')

@section('title', 'Instagram Settings')
@section('header', 'Instagram Settings')

@section('content')
<div x-data="instagramSettingsPage()" class="max-w-6xl space-y-6">

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Instagram Settings</h1>
                <p class="mt-2 text-sm text-gray-500 max-w-2xl">This page posts through a real Instagram browser session. Attach an account on the Accounts page, then save defaults and run a connection test here.</p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route('instagram.accounts') }}" class="inline-flex items-center px-4 py-2 bg-emerald-600 text-white text-sm font-medium rounded-lg hover:bg-emerald-700">Open Accounts</a>
                <a href="{{ route('instagram.raw') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-50">Open Raw Workspace</a>
                <a href="{{ route('settings.browser-worker') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-50">Open Browser Worker</a>
            </div>
        </div>
    </div>

    <div class="bg-blue-50 border border-blue-200 rounded-xl p-5 text-sm text-blue-900 space-y-2">
        <div class="font-semibold">Main flow</div>
        <ol class="list-decimal list-inside space-y-1">
            <li>Save Instagram accounts and store each password on the <a href="{{ route('instagram.accounts') }}" class="font-medium underline">Accounts page</a>.</li>
            <li>Use <span class="font-medium">Log in with saved credentials</span> on the account card you want to attach.</li>
            <li>Come back here, pick the saved account, run the connection test, and save defaults.</li>
        </ol>
        <a href="https://www.instagram.com/" target="_blank" rel="noopener" class="inline-block font-medium underline">Open Instagram in a new tab</a>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <div class="text-xs uppercase tracking-wide text-gray-500">Active profile</div>
            <div class="mt-2 text-base font-semibold text-gray-900">{{ $status['active_profile'] ?: '-' }}</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <div class="text-xs uppercase tracking-wide text-gray-500">Attached username</div>
            <div class="mt-2 text-base font-semibold text-gray-900">{{ $status['active_account']['instagram_username'] ?? '-' }}</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <div class="text-xs uppercase tracking-wide text-gray-500">Saved password</div>
            <div class="mt-2 text-sm font-semibold {{ !empty($status['active_account']['password_configured']) ? 'text-emerald-700' : 'text-red-600' }}">
                {{ !empty($status['active_account']['password_configured']) ? ($status['active_account']['password_masked'] ?: 'Configured') : 'Missing' }}
            </div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <div class="text-xs uppercase tracking-wide text-gray-500">Saved accounts</div>
            <div class="mt-2 text-2xl font-semibold text-gray-900">{{ count($status['accounts']) }}</div>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-5">
        <div class="flex items-start justify-between gap-3 flex-wrap">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Connection Test</h2>
                <p class="mt-1 text-sm text-gray-500">Pick a saved account, verify the attached browser session, reconnect it if needed, then run the deeper content probe.</p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <button type="button" @click="checkStatus()" :disabled="sessionBusy() || !testProfile"
                        class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-50 disabled:opacity-50">
                    <span x-text="statusActionLabel()"></span>
                </button>
                <button type="button" @click="reconnect()" :disabled="sessionBusy() || !testProfile"
                        class="inline-flex items-center px-4 py-2 bg-emerald-600 text-white text-sm font-medium rounded-lg hover:bg-emerald-700 disabled:opacity-50">
                    <span x-text="reconnectActionLabel()"></span>
                </button>
                <button type="button" @click="runTest()" :disabled="testing || sessionBusy() || !testProfile"
                        class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50">
                    <span x-show="!testing">Run Full Test</span>
                    <span x-show="testing" x-cloak>Testing…</span>
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_auto] gap-3 items-end">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">Saved account to test</label>
                <select x-model="testProfile" @change="clearSessionResult()" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm bg-white">
                    <option value="">Select a saved Instagram account</option>
                    @foreach($status['accounts'] as $account)
                        <option value="{{ $account['profile'] }}">{{ $account['label'] }} — {{ $account['instagram_username'] ?: $account['profile'] }}</option>
                    @endforeach
                </select>
            </div>
            <a href="{{ route('instagram.accounts') }}" class="inline-flex items-center justify-center px-4 py-2 bg-white border border-gray-300 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-50">Manage accounts</a>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 space-y-3">
                <div class="flex items-center justify-between gap-2 flex-wrap">
                    <div class="text-xs uppercase tracking-wide text-gray-500">Current Result</div>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium" :class="testBadgeClass()" x-text="testBadge()"></span>
                </div>
                <div class="text-sm font-semibold" :class="testSummaryClass()" x-text="testSummary()"></div>
                <div class="text-sm text-gray-700" x-text="testDetail()"></div>

                <template x-if="testResult?.data?.instagram_status?.data">
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between gap-2 border-t border-gray-200 pt-2">
                            <span class="text-gray-500">Account</span>
                            <span class="text-gray-900 font-medium text-right" x-text="testAccountLabel()"></span>
                        </div>
                        <div class="flex justify-between gap-2">
                            <span class="text-gray-500">Profile</span>
                            <span class="text-gray-900 font-mono text-right" x-text="testProfile || '-'"></span>
                        </div>
                        <div class="flex justify-between gap-2">
                            <span class="text-gray-500">Current URL</span>
                            <span class="text-gray-900 text-right break-all max-w-[60%]" x-text="testCurrentUrl()"></span>
                        </div>
                        <div class="flex justify-between gap-2">
                            <span class="text-gray-500">Worker state</span>
                            <span class="text-gray-900 text-right" x-text="testWorkerState()"></span>
                        </div>
                    </div>
                </template>


                <div class="rounded-lg border p-4 space-y-3" :class="isSessionConnected() ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50'" x-show="testResult || testProfile" x-cloak>
                    <div class="flex items-start justify-between gap-3 flex-wrap">
                        <div>
                            <div class="text-xs uppercase tracking-wide text-gray-500">Next step</div>
                            <div class="mt-1 text-sm font-semibold" :class="isSessionConnected() ? 'text-emerald-800' : 'text-amber-900'" x-text="recoveryTitle()"></div>
                            <p class="mt-1 text-sm" :class="isSessionConnected() ? 'text-emerald-800' : 'text-amber-800'" x-text="recoveryDetail()"></p>
                        </div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <button type="button" @click="checkStatus()" :disabled="sessionBusy() || !testProfile" class="inline-flex items-center px-3 py-2 bg-white border border-gray-300 text-xs font-medium rounded-lg text-gray-700 hover:bg-gray-50 disabled:opacity-50" x-text="statusActionLabel()"></button>
                            <button type="button" @click="reconnect()" :disabled="sessionBusy() || !testProfile" class="inline-flex items-center px-3 py-2 bg-emerald-600 text-white text-xs font-medium rounded-lg hover:bg-emerald-700 disabled:opacity-50" x-text="reconnectActionLabel()"></button>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-[minmax(0,1fr)_auto] gap-2 items-end" x-show="needsVerificationCode()" x-cloak>
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wide text-amber-700 mb-1" x-text="verificationLabel()"></label>
                            <input type="text" inputmode="numeric" x-model="verificationCode" class="w-full border border-amber-300 rounded-lg px-3 py-2 text-sm bg-white" placeholder="Enter the code from Instagram">
                        </div>
                        <button type="button" @click="submitVerificationCode()" :disabled="sessionBusy() || !(verificationCode || '').trim()" class="inline-flex items-center justify-center px-4 py-2 bg-amber-600 text-white text-sm font-medium rounded-lg hover:bg-amber-700 disabled:opacity-50" x-text="submitCodeActionLabel()"></button>
                    </div>
                </div>

                <details class="rounded-lg border border-gray-200 bg-white" x-show="testResult?.data?.following_sample" x-cloak>
                    <summary class="cursor-pointer px-3 py-2 text-sm font-medium text-gray-800">Following / posts / story sample</summary>
                    <div class="border-t border-gray-200 px-3 py-3 space-y-3 text-sm">
                        <div>
                            <div class="text-xs uppercase tracking-wide text-gray-500">Following graph</div>
                            <div class="mt-1 text-gray-900 font-medium" x-text="followingSample()?.source_username || '-'"></div>
                            <div class="text-gray-700" x-text="followingSample()?.detail || ''"></div>
                            <div class="text-xs text-gray-500 mt-1" x-text="(followingSample()?.count || 0) + ' followed accounts'"></div>
                            <div class="flex flex-wrap gap-1 mt-2" x-show="followingUsernames().length">
                                <template x-for="username in followingUsernames()" :key="username">
                                    <span class="inline-flex items-center rounded bg-gray-100 px-2 py-0.5 text-[11px] text-gray-700" x-text="'@' + username"></span>
                                </template>
                            </div>
                        </div>

                        <div class="border-t border-gray-100 pt-3">
                            <div class="text-xs uppercase tracking-wide text-gray-500">Random recent post</div>
                            <div class="mt-1 text-gray-900 font-medium" x-text="randomFollowingPost()?.instagram_username ? '@' + randomFollowingPost().instagram_username : '-'"></div>
                            <div class="text-gray-700" x-text="randomFollowingPost()?.detail || ''"></div>
                            <div class="space-y-1 mt-2" x-show="Array.isArray(randomFollowingPost()?.recent_post_links) && randomFollowingPost().recent_post_links.length">
                                <template x-for="link in (randomFollowingPost()?.recent_post_links || [])" :key="link">
                                    <a :href="link" target="_blank" rel="noopener" class="block break-all text-xs font-medium text-blue-600 underline" x-text="link"></a>
                                </template>
                            </div>
                        </div>

                        <div class="border-t border-gray-100 pt-3">
                            <div class="text-xs uppercase tracking-wide text-gray-500">Random active story</div>
                            <div class="mt-1 text-gray-900 font-medium" x-text="randomFollowingStory()?.instagram_username ? '@' + randomFollowingStory().instagram_username : '-'"></div>
                            <div class="text-xs text-gray-500" x-text="storySourceLabel()"></div>
                            <div class="text-gray-700 mt-1" x-text="randomFollowingStory()?.detail || ''"></div>
                            <div class="space-y-1 mt-2" x-show="randomStoryMedia().length">
                                <template x-for="m in randomStoryMedia()" :key="m.type + '-' + m.url">
                                    <a :href="m.url" target="_blank" rel="noopener" class="block break-all text-xs font-medium text-blue-600 underline"><span x-text="m.type"></span>: <span x-text="m.url"></span></a>
                                </template>
                            </div>
                        </div>
                    </div>
                </details>
            </div>

            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 space-y-2">
                <div class="text-xs uppercase tracking-wide text-gray-500">Raw payload</div>
                <template x-if="!testResult">
                    <p class="text-sm text-gray-500">Run the connection test to see the raw response payload here.</p>
                </template>
                <template x-if="testResult">
                    <pre class="text-[11px] font-mono whitespace-pre-wrap break-words text-gray-700 max-h-96 overflow-y-auto" x-text="pretty(testResult)"></pre>
                </template>
            </div>
        </div>

        <x-hexa-log-viewer
            title="Instagram Connection Test Log"
            log-var="testLog"
            slug="instagram-settings-test"
            theme="dark"
            :persist="false" />
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-4">
        <div>
            <h2 class="text-lg font-semibold text-gray-900">Default Test Targets</h2>
            <p class="mt-1 text-sm text-gray-500">Defaults that prefill the Raw Workspace and the public import checks.</p>
        </div>
        <form @submit.prevent="save()" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Default profile username</label>
                <input type="text" x-model="form.default_profile_username" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="jpnmiami">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Default story username</label>
                <input type="text" x-model="form.default_story_username" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="jpnmiami">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Default post URL</label>
                <input type="url" x-model="form.default_post_url" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="https://www.instagram.com/p/.../">
            </div>
            <div class="md:col-span-3 flex justify-end">
                <button type="submit" :disabled="saving" class="inline-flex items-center px-4 py-2 bg-emerald-600 text-white text-sm font-medium rounded-lg hover:bg-emerald-700 disabled:opacity-50">
                    <span x-show="!saving">Save Settings</span>
                    <span x-show="saving" x-cloak>Saving…</span>
                </button>
            </div>
        </form>
    </div>

    <details class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
        <summary class="cursor-pointer text-lg font-semibold text-gray-900">Optional Meta oEmbed Token</summary>
        <div class="mt-4 space-y-3">
            <p class="text-sm text-gray-500">Optional. The Instagram package can scrape many public posts without it. Save this only if you want the official Meta oEmbed path before falling back to public scraping. Last updated: <span class="font-mono">{{ $status['credential_updated_at'] ?: 'unknown' }}</span></p>
            <x-hexa-credential-field
                slug="instagram"
                key-name="meta_access_token"
                label="Instagram / Meta Access Token"
                :test-url="route('settings.instagram.test-meta-token')"
                help="Optional Meta oEmbed token. Public scrape fallback remains available without it."
            />
        </div>
    </details>

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
        sessionAction: '',
        verificationCode: '',
        testProfile: @json($status['active_profile']),
        testResult: null,
        testLog: [],
        toast: { show: false, message: '', type: 'success' },
        toastTimer: null,

        init() {
            this.log('info', 'Instagram settings page loaded. Pick a saved account to verify that exact attached session, then sample followed posts and stories.');
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
            try { return JSON.stringify(payload, null, 2); } catch (error) { return String(payload); }
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

        async request(url, options = {}) {
            const init = { ...options };
            const headers = { "Accept": "application/json", ...(init.headers || {}) };
            const method = String(init.method || "GET").toUpperCase();
            if (method !== "GET") headers["X-CSRF-TOKEN"] = this.csrfToken();
            if (init.body && !headers["Content-Type"]) headers["Content-Type"] = "application/json";
            init.headers = headers;

            const response = await fetch(url, init);
            const raw = await response.text();
            let data;
            try { data = raw ? JSON.parse(raw) : {}; } catch (error) { data = { success: false, message: "Request did not return JSON.", detail: raw.slice(0, 1000) }; }
            return { response, data };
        },



        sessionBusy() {
            return this.sessionAction !== '' || this.testing;
        },

        statusActionLabel() {
            return this.sessionAction === 'status' ? 'Checking status...' : 'Check status';
        },

        reconnectActionLabel() {
            return this.sessionAction === 'reconnect' ? 'Reconnecting...' : 'Reconnect with saved credentials';
        },

        submitCodeActionLabel() {
            return this.sessionAction === 'submitCode' ? 'Submitting code...' : 'Submit verification code';
        },

        clearSessionResult() {
            this.testResult = null;
            this.verificationCode = '';
        },

        sessionStatusData() {
            return this.testResult?.data?.instagram_status?.data || {};
        },

        isSessionConnected() {
            return Boolean(this.sessionStatusData().connected);
        },

        needsVerificationCode() {
            return Boolean(this.sessionStatusData().verification_required);
        },

        verificationLabel() {
            const channel = this.sessionStatusData().verification_channel || 'code';
            if (channel === 'email') return 'Instagram email code';
            if (channel === 'whatsapp') return 'Instagram WhatsApp code';
            if (channel === 'sms') return 'Instagram SMS code';
            return 'Instagram verification code';
        },

        recoveryTitle() {
            const state = this.sessionStatusData();
            if (state.connected) return 'Connected. You can run the full content test.';
            if (state.verification_required) return 'Verification code required.';
            if (state.challenge) return 'Instagram challenge/checkpoint is blocking this session.';
            if (state.login_form) return 'Browser is at the Instagram login screen.';
            if (this.testResult) return 'Reconnect this account from here.';
            return 'Start by checking status or reconnecting.';
        },

        recoveryDetail() {
            const state = this.sessionStatusData();
            if (state.connected) return 'The attached browser session is authenticated. Use Run Full Test only when you need the deeper following/posts/stories sample.';
            if (state.verification_required) return 'Enter the code Instagram sent, then submit it here. Do not leave this page to guess the next step.';
            if (state.challenge) return 'Finish the challenge in the attached browser/Instagram, then click Check status again.';
            if (state.login_form) return 'Click Reconnect with saved credentials to submit the saved username and password into this browser profile.';
            return 'Click Reconnect with saved credentials. This uses the saved account password and updates the result/log on this page.';
        },

        wrapSessionPayload(data) {
            const account = this.selectedAccount();
            return {
                success: Boolean(data?.success && data?.data?.connected),
                message: data?.message || 'Instagram session action completed.',
                detail: data?.detail || '',
                status_code: data?.status_code || 0,
                data: {
                    instagram_status: data,
                    selected_account: account,
                },
            };
        },

        async sessionRequest(action, label, url, options = {}) {
            if (!this.testProfile) {
                this.showToast('Select a saved Instagram account first.', 'error');
                return;
            }
            this.sessionAction = action;
            this.log('info', label + ' for ' + this.testProfile + '.');
            try {
                const { response, data } = await this.request(url, options);
                this.testResult = this.wrapSessionPayload(data);
                this.log(response.ok && data.success ? 'success' : 'error', label + ' finished for ' + this.testProfile + '.', {
                    message: data.message || '',
                    detail: data.detail || '',
                });
                this.showToast(data.message || (response.ok ? label + ' finished.' : label + ' failed.'), response.ok && data.success ? 'success' : 'error');
            } catch (error) {
                this.testResult = { success: false, message: label + ' failed.', detail: error?.message || String(error), data: { selected_account: this.selectedAccount(), instagram_status: { data: {} } } };
                this.log('error', label + ' request failed for ' + this.testProfile + '.', error?.message || String(error));
                this.showToast(label + ' failed.', 'error');
            } finally {
                this.sessionAction = '';
            }
        },

        async checkStatus() {
            const url = new URL('{{ route("instagram.accounts.status") }}', window.location.origin);
            url.searchParams.set('profile', this.testProfile || '');
            await this.sessionRequest('status', 'Instagram status check', url.toString());
        },

        async reconnect() {
            await this.sessionRequest('reconnect', 'Instagram reconnect', '{{ route("instagram.accounts.login") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ profile: this.testProfile }),
            });
        },

        async submitVerificationCode() {
            await this.sessionRequest('submitCode', 'Instagram verification code submit', '{{ route("instagram.accounts.submit-code") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ profile: this.testProfile, code: this.verificationCode || '' }),
            });
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
                    this.log('success', 'Instagram connection test passed for ' + this.testProfile + '.', { message: data.message || '', detail: data.detail || '' });
                    this.showToast(data.message || 'Instagram connection is healthy.');
                } else {
                    this.log('error', 'Instagram connection test failed for ' + this.testProfile + '.', { message: data.message || '', detail: data.detail || '' });
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

        followingSample() { return this.testResult?.data?.following_sample || null; },
        followingUsernames() { return this.followingSample()?.usernames || []; },
        activeStoryCandidates() { return this.testResult?.data?.active_story_candidates?.usernames || []; },
        randomFollowingPost() { return this.testResult?.data?.random_following_post || null; },
        randomFollowingStory() { return this.testResult?.data?.random_following_story || null; },
        checkedUsernames(sample) { return Array.isArray(sample?.checked_usernames) ? sample.checked_usernames : []; },
        randomStoryMedia() {
            const sample = this.randomFollowingStory();
            const media = [];
            const images = Array.isArray(sample?.image_urls) ? sample.image_urls : [];
            const videos = Array.isArray(sample?.video_urls) ? sample.video_urls : [];
            images.forEach((url) => media.push({ type: 'Image', url }));
            videos.forEach((url) => media.push({ type: 'Video', url }));
            return media;
        },
        storySourceLabel() {
            const source = this.randomFollowingStory()?.source || '';
            if (source === 'following_fallback') return 'Following fallback';
            if (source === 'active_story_candidates') return 'Home story tray';
            return 'No story source yet';
        },
    };
}
</script>
@endpush
@endsection
