@extends('layouts.app')

@section('title', 'Instagram Accounts')
@section('header', 'Instagram Accounts')

@section('content')
<div x-data="instagramAccountsPage()" class="max-w-6xl space-y-6">
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Attach Instagram Accounts</h1>
                <p class="mt-2 text-sm text-gray-500">Save one or more Instagram browser profiles here, then store each password in Hexa Core credentials and run the live login/status checks against that saved account.</p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route('settings.instagram') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-50">Open Settings</a>
                <a href="{{ route('instagram.raw') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-50">Open Raw Workspace</a>
            </div>
        </div>
    </div>

    <div class="bg-blue-50 border border-blue-200 rounded-xl p-5 text-sm text-blue-900 space-y-3">
        <div class="font-semibold">Instructions</div>
        <ol class="list-decimal list-inside space-y-1">
            <li>Save the account label, browser profile id, and Instagram username below.</li>
            <li>On the saved account card, store the Instagram password in the Hexa Core credential field.</li>
            <li>Click <span class="font-medium">Log in with saved credentials</span> to attach that account to the browser profile.</li>
            <li>Use <span class="font-medium">Check status</span> to confirm whether the browser profile is authenticated, still at login, or blocked by a challenge.</li>
        </ol>
        <div class="flex items-center gap-3 flex-wrap">
            <a href="https://www.instagram.com/accounts/login/" target="_blank" rel="noopener" class="font-medium underline">Open Instagram login in a new tab</a>
            <a href="{{ route('settings.browser-worker') }}" class="font-medium underline">Open Browser Worker settings</a>
            <a href="{{ route('instagram.raw') }}" class="font-medium underline">Open Raw Workspace</a>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-4">
        <div>
            <h2 class="text-lg font-semibold text-gray-900">Save Instagram account profile</h2>
            <p class="mt-1 text-sm text-gray-500">Passwords are managed per saved account below with the standard Hexa Core credential flow.</p>
        </div>

        <form @submit.prevent="addAccount()" class="grid gap-4 lg:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Account label</label>
                <input type="text" x-model="newAccount.label" @input="syncProfileFromLabel()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="JPN Miami">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Browser profile</label>
                <input type="text" x-model="newAccount.profile" @input="profileTouched = true" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono" placeholder="jpn-miami">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Instagram username</label>
                <input type="text" x-model="newAccount.instagram_username" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="miamijpn">
            </div>

            <div class="flex items-end">
                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" x-model="newAccount.set_active" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                    Set active now
                </label>
            </div>

            <div class="lg:col-span-2 flex items-center justify-end gap-3">
                <button type="button" @click="clearForm()" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-50">Clear</button>
                <button type="submit" :disabled="saving" class="inline-flex items-center px-5 py-2.5 bg-emerald-600 text-white text-sm font-medium rounded-lg hover:bg-emerald-700 disabled:opacity-50">
                    <span x-text="saving ? 'Saving account…' : 'Save Instagram account'"></span>
                </button>
            </div>
        </form>
    </div>

    @if(empty($status['accounts']))
        <div class="bg-white rounded-xl border border-dashed border-gray-300 shadow-sm p-10 text-center text-sm text-gray-500">
            No Instagram account profiles saved yet.
        </div>
    @else
        <div class="grid gap-6 xl:grid-cols-2">
            @foreach($status['accounts'] as $account)
                @php
                    $profile = (string) ($account['profile'] ?? '');
                    $username = (string) ($account['instagram_username'] ?? '');
                    $passwordKey = 'account_password_' . $profile;
                @endphp
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-4">
                    <div class="flex items-start justify-between gap-4 flex-wrap">
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <h2 class="text-lg font-semibold text-gray-900">{{ $account['label'] }}</h2>
                                <span x-show="activeProfile === @js($profile)" x-cloak class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700">Active</span>
                            </div>
                            <div class="mt-1 text-xs text-gray-500 font-mono">{{ $profile }}</div>
                        </div>
                        <div class="text-right text-sm">
                            <div class="font-medium" :class="statusTone(@js($profile))" x-text="statusLabel(@js($profile))"></div>
                            <div class="mt-1 text-xs text-gray-500" x-text="statusDetail(@js($profile))"></div>
                        </div>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2 text-sm">
                        <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                            <div class="text-xs uppercase tracking-wide text-gray-500">Instagram username</div>
                            <div class="mt-1 font-medium text-gray-900">{{ $username ?: 'Not saved yet' }}</div>
                        </div>
                        <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                            <div class="text-xs uppercase tracking-wide text-gray-500">Credential key</div>
                            <div class="mt-1 font-mono text-xs text-gray-700 break-all">{{ 'instagram.' . $passwordKey }}</div>
                        </div>
                    </div>

                    <x-hexa-credential-field
                        slug="instagram"
                        :key-name="$passwordKey"
                        label="Instagram password"
                        help="Stored in Hexa Core credentials for this browser profile. Save it here, then use “Log in with saved credentials” below."
                    />

                    <div class="flex items-center gap-2 flex-wrap">
                        <button type="button" @click="loadStatus(@js($profile))" :disabled="busy(@js($profile))" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-50 disabled:opacity-50">
                            <span x-text="statusButtonLabel(@js($profile))"></span>
                        </button>
                        <button type="button" @click="login(@js($profile))" :disabled="busy(@js($profile)) || {{ !empty($account['password_configured']) ? 'false' : 'true' }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50">
                            <span x-text="loginButtonLabel(@js($profile))"></span>
                        </button>
                        <button type="button" @click="setActive(@js($profile))" :disabled="busy(@js($profile)) || activeProfile === @js($profile)" class="inline-flex items-center px-4 py-2 bg-emerald-600 text-white text-sm font-medium rounded-lg hover:bg-emerald-700 disabled:opacity-50">
                            <span x-text="activateButtonLabel(@js($profile))"></span>
                        </button>
                        <button type="button" @click="logout(@js($profile))" :disabled="busy(@js($profile))" class="inline-flex items-center px-4 py-2 bg-amber-600 text-white text-sm font-medium rounded-lg hover:bg-amber-700 disabled:opacity-50">
                            <span x-text="logoutButtonLabel(@js($profile))"></span>
                        </button>
                        <button type="button" @click="removeAccount(@js($profile), @js($account['label']))" :disabled="busy(@js($profile))" class="inline-flex items-center px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 disabled:opacity-50">
                            <span x-text="removeButtonLabel(@js($profile))"></span>
                        </button>
                    </div>

                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 space-y-3">
                            <div class="flex items-center justify-between gap-3 flex-wrap">
                                <div>
                                    <div class="text-sm font-semibold text-slate-900">Latest browser proof</div>
                                    <div class="mt-1 text-xs text-slate-500" x-text="proofHeadline(@js($profile))"></div>
                            </div>
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium"
                                  :class="proofBadgeClass(@js($profile))"
                                  x-text="proofBadge(@js($profile))"></span>
                        </div>

                        <template x-if="proofLoaded(@js($profile))">
                            <div class="grid gap-3 md:grid-cols-2 text-sm">
                                <div class="rounded-lg border border-slate-200 bg-white px-4 py-3">
                                    <div class="text-xs uppercase tracking-wide text-slate-500">Current URL</div>
                                    <div class="mt-1">
                                        <a :href="proofUrl(@js($profile))" target="_blank" rel="noopener" class="text-blue-700 underline break-all" x-text="proofUrl(@js($profile))"></a>
                                    </div>
                                </div>
                                <div class="rounded-lg border border-slate-200 bg-white px-4 py-3">
                                    <div class="text-xs uppercase tracking-wide text-slate-500">Worker state</div>
                                    <div class="mt-1 text-slate-900" x-text="proofState(@js($profile))"></div>
                                </div>
                                <div class="rounded-lg border border-slate-200 bg-white px-4 py-3 md:col-span-2">
                                    <div class="text-xs uppercase tracking-wide text-slate-500">What happened</div>
                                    <div class="mt-1 font-medium text-slate-900" x-text="statusDetail(@js($profile))"></div>
                                    <div class="mt-2 text-sm text-slate-700" x-text="proofNextStep(@js($profile))"></div>
                                    <div class="mt-2 text-xs text-slate-500" x-text="proofSecondary(@js($profile))"></div>
                                </div>
                            </div>
                        </template>

                        <template x-if="needsVerificationCode(@js($profile))">
                            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-4 space-y-3">
                                <div class="text-sm font-semibold text-amber-900" x-text="verificationHeading(@js($profile))"></div>
                                <p class="text-sm text-amber-800">Saved credentials were accepted. Enter the verification code here to keep the browser session moving inside Hexa instead of getting stuck on Instagram.</p>
                                <div class="flex items-end gap-3 flex-wrap">
                                    <div class="min-w-[14rem] flex-1">
                                        <label class="block text-sm font-medium text-amber-900 mb-1">Verification code</label>
                                        <input type="text" inputmode="numeric" x-model="verificationCodes[@js($profile)]" class="w-full border border-amber-300 rounded-lg px-3 py-2 text-sm bg-white" placeholder="Enter the code from email or WhatsApp">
                                    </div>
                                    <button type="button" @click="submitCode(@js($profile))" :disabled="actionFor(@js($profile)) === 'submitCode' || !(verificationCodes[@js($profile)] || '').trim()" class="inline-flex items-center px-4 py-2 bg-amber-600 text-white text-sm font-medium rounded-lg hover:bg-amber-700 disabled:opacity-50">
                                        <span x-text="actionFor(@js($profile)) === 'submitCode' ? 'Submitting code…' : 'Submit verification code'"></span>
                                    </button>
                                </div>
                            </div>
                        </template>

                        <template x-if="!proofLoaded(@js($profile))">
                            <div class="rounded-lg border border-dashed border-slate-300 bg-white px-4 py-4 text-sm text-slate-500 text-center">
                                Run <span class="font-medium">Check status</span> or <span class="font-medium">Log in with saved credentials</span> to load live browser proof for this account.
                            </div>
                        </template>

                        <details x-show="payloadFor(@js($profile))" x-cloak class="rounded-lg border border-slate-200 bg-white">
                            <summary class="cursor-pointer px-4 py-3 text-sm font-medium text-slate-800">Show raw worker payload</summary>
                            <div class="border-t border-slate-200 px-4 py-3">
                                <pre class="text-xs font-mono whitespace-pre-wrap break-words text-slate-700" x-text="pretty(payloadFor(@js($profile)))"></pre>
                            </div>
                        </details>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <x-hexa-log-viewer
        title="Instagram Account Activity"
        log-var="accountLog"
        slug="instagram-accounts"
        theme="dark"
        :persist="false" />

    <template x-if="toast.show">
        <div class="fixed bottom-6 right-6 z-50 max-w-sm w-full rounded-xl border px-5 py-4 shadow-lg"
             :class="toast.type === 'success' ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800'">
            <div class="text-sm font-medium" x-text="toast.message"></div>
        </div>
    </template>
</div>

@push('scripts')
<script>
function instagramAccountsPage() {
    return {
        accounts: @json($status['accounts']),
        activeProfile: @json($status['active_profile']),
        newAccount: { label: '', profile: '', instagram_username: '', set_active: true },
        profileTouched: false,
        saving: false,
        actionMap: {},
        accountStates: {},
        payloads: {},
        verificationCodes: {},
        accountLog: [],
        toast: { show: false, message: '', type: 'success' },
        toastTimer: null,

        init() {
            this.log('info', 'Instagram accounts page loaded. Save a profile, store the password in Hexa Core credentials, then attach it.');
            if (this.activeProfile) {
                this.loadStatus(this.activeProfile);
            }
        },

        csrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.content || '';
        },

        now() {
            return new Date().toLocaleTimeString();
        },

        log(type, message, detail = null) {
            this.accountLog.push({ type, message, detail, time: this.now() });
        },

        busy(profile) {
            return Boolean(this.actionMap[profile]);
        },

        actionFor(profile) {
            return this.actionMap[profile] || '';
        },

        statusButtonLabel(profile) {
            return this.actionFor(profile) === 'status' ? 'Checking status…' : 'Check status';
        },

        loginButtonLabel(profile) {
            return this.actionFor(profile) === 'login' ? 'Logging in…' : 'Log in with saved credentials';
        },

        activateButtonLabel(profile) {
            return this.actionFor(profile) === 'activate' ? 'Switching…' : 'Use as active';
        },

        logoutButtonLabel(profile) {
            return this.actionFor(profile) === 'logout' ? 'Logging out…' : 'Log out';
        },

        removeButtonLabel(profile) {
            return this.actionFor(profile) === 'remove' ? 'Removing…' : 'Remove';
        },

        showToast(message, type = 'success') {
            if (this.toastTimer) clearTimeout(this.toastTimer);
            this.toast = { show: true, message, type };
            this.toastTimer = setTimeout(() => { this.toast.show = false; }, 3500);
        },

        pretty(payload) {
            try {
                return JSON.stringify(payload || { message: 'No browser payload loaded yet.' }, null, 2);
            } catch (error) {
                return String(payload);
            }
        },

        slugify(value) {
            return String(value || '')
                .trim()
                .toLowerCase()
                .replace(/[^a-z0-9._-]+/g, '-')
                .replace(/^[._-]+|[._-]+$/g, '') || 'instagram-main';
        },

        syncProfileFromLabel() {
            if (this.profileTouched) return;
            this.newAccount.profile = this.slugify(this.newAccount.label);
        },

        clearForm() {
            this.newAccount = { label: '', profile: '', instagram_username: '', set_active: true };
            this.profileTouched = false;
        },

        replaceStatus(status) {
            this.accounts = status.accounts || this.accounts;
            this.activeProfile = status.active_profile || this.activeProfile;
        },

        request(url, options = {}) {
            return fetch(url, {
                ...options,
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken(),
                    ...(options.headers || {}),
                },
            }).then(async (response) => ({ response, data: await response.json() }));
        },

        rememberPayload(profile, payload) {
            this.payloads[profile] = payload;
            const data = payload?.data || {};
            const worker = data.worker || {};
            const probe = data.probe || {};
            this.accountStates[profile] = {
                connected: Boolean(data.connected),
                login_form: Boolean(data.login_form),
                verification_required: Boolean(data.verification_required),
                verification_channel: data.verification_channel || '',
                challenge: Boolean(data.challenge),
                detail: payload?.detail || '',
                message: payload?.message || '',
                current_url: worker.current_url || worker.final?.final_url || probe.url || '',
                current_title: worker.last_title || probe.title || '',
                last_event: worker.last_event || '',
                last_error: worker.last_error || '',
                strong_nav_count: probe.strong_nav_count || 0,
                login_copy_detected: Boolean(probe.login_copy_detected),
                visible_text_inputs: probe.visible_text_inputs || [],
                visible_password_inputs: probe.visible_password_inputs || 0,
            };
        },

        proofLoaded(profile) {
            return Boolean(this.payloads[profile]);
        },

        stateFor(profile) {
            return this.accountStates[profile] || {};
        },

        payloadFor(profile) {
            return this.payloads[profile] || null;
        },

        statusLabel(profile) {
            const state = this.stateFor(profile);
            if (state.connected) return 'Connected';
            if (state.verification_required) return this.verificationShortLabel(profile);
            if (state.challenge) return 'Challenge required';
            if (state.login_form) return 'Login form visible';
            if (state.current_url) return 'Not connected';
            return 'No status loaded';
        },

        statusTone(profile) {
            const state = this.stateFor(profile);
            if (state.connected) return 'text-emerald-700';
            if (state.verification_required) return 'text-amber-700';
            if (state.challenge) return 'text-amber-700';
            if (state.login_form) return 'text-rose-700';
            return 'text-gray-500';
        },

        statusDetail(profile) {
            const state = this.stateFor(profile);
            return state.detail || 'Run a live status check to load browser proof for this account.';
        },

        proofHeadline(profile) {
            const state = this.stateFor(profile);
            if (state.connected) return 'The browser session is authenticated and Instagram navigation markers were found.';
            if (state.verification_required) return this.verificationHeading(profile);
            if (state.challenge) return 'Instagram is asking for challenge/checkpoint verification on this browser profile.';
            if (state.login_form) return 'Instagram is still serving a login screen for this browser profile.';
            if (state.current_url) return 'Instagram loaded, but the session was not confirmed as authenticated.';
            return 'No browser proof loaded yet.';
        },

        proofBadge(profile) {
            const state = this.stateFor(profile);
            if (state.connected) return 'Connected';
            if (state.verification_required) return this.verificationShortLabel(profile);
            if (state.challenge) return 'Challenge';
            if (state.login_form) return 'Needs login';
            if (state.current_url) return 'Not confirmed';
            return 'No proof';
        },

        proofBadgeClass(profile) {
            const state = this.stateFor(profile);
            if (state.connected) return 'bg-emerald-100 text-emerald-700';
            if (state.verification_required) return 'bg-amber-100 text-amber-700';
            if (state.challenge) return 'bg-amber-100 text-amber-700';
            if (state.login_form) return 'bg-rose-100 text-rose-700';
            return 'bg-slate-100 text-slate-700';
        },

        proofUrl(profile) {
            return this.stateFor(profile).current_url || 'No URL captured yet.';
        },

        proofState(profile) {
            const state = this.stateFor(profile);
            return [state.last_event, state.current_title].filter(Boolean).join(' · ') || 'No worker state captured yet.';
        },

        proofSecondary(profile) {
            const state = this.stateFor(profile);
            const details = [];
            details.push('Saved password: ' + (this.accounts.find((account) => account.profile === profile)?.password_configured ? 'configured' : 'missing'));
            if (state.visible_text_inputs?.length) details.push('Visible text inputs: ' + state.visible_text_inputs.join(', '));
            if (state.visible_password_inputs) details.push('Visible password inputs: ' + state.visible_password_inputs);
            if (state.strong_nav_count) details.push('Strong nav markers: ' + state.strong_nav_count);
            if (state.last_error) details.push('Worker error: ' + state.last_error);
            return details.join(' • ');
        },

        needsVerificationCode(profile) {
            return Boolean(this.stateFor(profile).verification_required);
        },

        verificationShortLabel(profile) {
            const channel = this.stateFor(profile).verification_channel;
            if (channel === 'email') return 'Email code required';
            if (channel === 'whatsapp') return 'WhatsApp code required';
            if (channel === 'sms') return 'SMS code required';
            return 'Verification required';
        },

        verificationHeading(profile) {
            const channel = this.stateFor(profile).verification_channel;
            if (channel === 'email') return 'Instagram accepted the password and is waiting for the email code.';
            if (channel === 'whatsapp') return 'Instagram accepted the password and is waiting for the WhatsApp code.';
            if (channel === 'sms') return 'Instagram accepted the password and is waiting for the SMS code.';
            return 'Instagram accepted the password and is waiting for a verification code.';
        },

        proofNextStep(profile) {
            const state = this.stateFor(profile);
            if (state.connected) return 'Next step: the account is ready. Move to Settings or Raw Workspace and run live scans.';
            if (state.verification_required) return 'Next step: enter the verification code below and submit it here so the attached browser session can finish the login.';
            if (state.login_form) return 'Next step: click “Log in with saved credentials” to submit the stored username and password into the attached browser profile.';
            if (state.challenge) return 'Next step: Instagram is blocking the session behind a challenge. Finish the challenge, then run Check status again.';
            return 'Next step: run Check status again after the browser session changes.';
        },

        async addAccount() {
            this.saving = true;
            try {
                const { response, data } = await this.request('{{ route('instagram.accounts.store') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(this.newAccount),
                });

                if (response.ok && data.success) {
                    this.log('success', 'Saved Instagram account profile ' + (this.newAccount.label || this.newAccount.profile) + '.', {
                        profile: this.slugify(this.newAccount.profile || this.newAccount.label),
                        instagram_username: this.newAccount.instagram_username,
                    });
                    this.showToast(data.message || 'Instagram account saved.');
                    setTimeout(() => window.location.reload(), 300);
                } else {
                    this.log('error', data.message || 'Failed to save the Instagram account profile.', data.errors || null);
                    this.showToast(data.message || 'Failed to save the Instagram account profile.', 'error');
                }
            } catch (error) {
                this.log('error', 'Failed to save the Instagram account profile.', error?.message || String(error));
                this.showToast('Failed to save the Instagram account profile.', 'error');
            } finally {
                this.saving = false;
            }
        },

        async loadStatus(profile) {
            this.actionMap[profile] = 'status';
            try {
                const url = new URL('{{ route('instagram.accounts.status') }}', window.location.origin);
                url.searchParams.set('profile', profile);
                const { data } = await this.request(url.toString());
                this.rememberPayload(profile, data);
                this.log(data.success ? 'info' : 'error', 'Loaded Instagram status for ' + profile + '.', {
                    message: data.message || '',
                    detail: data.detail || '',
                });
            } catch (error) {
                this.log('error', 'Failed to load Instagram status for ' + profile + '.', error?.message || String(error));
                this.showToast('Failed to load Instagram status.', 'error');
            } finally {
                delete this.actionMap[profile];
            }
        },

        async login(profile) {
            this.actionMap[profile] = 'login';
            try {
                const { data } = await this.request('{{ route('instagram.accounts.login') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ profile }),
                });
                this.rememberPayload(profile, data);
                this.log(data.success ? 'success' : 'error', 'Ran Instagram login for ' + profile + '.', {
                    message: data.message || '',
                    detail: data.detail || '',
                });
                this.showToast(data.message || (data.success ? 'Instagram login finished.' : 'Instagram login failed.'), data.success ? 'success' : 'error');
            } catch (error) {
                this.log('error', 'Instagram login request failed for ' + profile + '.', error?.message || String(error));
                this.showToast('Instagram login request failed.', 'error');
            } finally {
                delete this.actionMap[profile];
            }
        },

        async submitCode(profile) {
            this.actionMap[profile] = 'submitCode';
            try {
                const { data } = await this.request('{{ route('instagram.accounts.submit-code') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ profile, code: this.verificationCodes[profile] || '' }),
                });
                this.rememberPayload(profile, data);
                this.log(data.success ? 'success' : 'error', 'Submitted Instagram verification code for ' + profile + '.', {
                    message: data.message || '',
                    detail: data.detail || '',
                });
                this.showToast(data.message || (data.success ? 'Verification code submitted.' : 'Verification code submission failed.'), data.success ? 'success' : 'error');
            } catch (error) {
                this.log('error', 'Instagram verification code submission failed for ' + profile + '.', error?.message || String(error));
                this.showToast('Instagram verification code submission failed.', 'error');
            } finally {
                delete this.actionMap[profile];
            }
        },

        async setActive(profile) {
            this.actionMap[profile] = 'activate';
            try {
                const { response, data } = await this.request('{{ route('instagram.accounts.activate') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ profile }),
                });
                if (response.ok && data.success) {
                    this.replaceStatus(data.status || {});
                    this.log('success', 'Set active Instagram profile to ' + profile + '.');
                    this.showToast(data.message || 'Active Instagram account updated.');
                    await this.loadStatus(profile);
                } else {
                    this.log('error', data.message || 'Failed to change the active Instagram account.', data.errors || null);
                    this.showToast(data.message || 'Failed to change the active Instagram account.', 'error');
                }
            } catch (error) {
                this.log('error', 'Failed to change the active Instagram account.', error?.message || String(error));
                this.showToast('Failed to change the active Instagram account.', 'error');
            } finally {
                delete this.actionMap[profile];
            }
        },

        async logout(profile) {
            this.actionMap[profile] = 'logout';
            try {
                const { data } = await this.request('{{ route('instagram.accounts.logout') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ profile }),
                });
                this.rememberPayload(profile, data);
                this.log(data.success ? 'warning' : 'error', 'Logged out Instagram browser profile ' + profile + '.', {
                    message: data.message || '',
                    detail: data.detail || '',
                });
                this.showToast(data.message || (data.success ? 'Instagram browser profile logged out.' : 'Instagram logout failed.'), data.success ? 'success' : 'error');
            } catch (error) {
                this.log('error', 'Instagram logout request failed for ' + profile + '.', error?.message || String(error));
                this.showToast('Instagram logout request failed.', 'error');
            } finally {
                delete this.actionMap[profile];
            }
        },

        async removeAccount(profile, label) {
            if (!confirm('Remove the Instagram account profile "' + label + '"?')) {
                return;
            }
            this.actionMap[profile] = 'remove';
            try {
                const { response, data } = await this.request('{{ route('instagram.accounts.destroy') }}', {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ profile }),
                });
                if (response.ok && data.success) {
                    this.log('warning', 'Removed Instagram account profile ' + profile + '.');
                    this.showToast(data.message || 'Instagram account profile removed.');
                    setTimeout(() => window.location.reload(), 300);
                } else {
                    this.log('error', data.message || 'Failed to remove the Instagram account profile.', data.errors || null);
                    this.showToast(data.message || 'Failed to remove the Instagram account profile.', 'error');
                }
            } catch (error) {
                this.log('error', 'Failed to remove the Instagram account profile.', error?.message || String(error));
                this.showToast('Failed to remove the Instagram account profile.', 'error');
            } finally {
                delete this.actionMap[profile];
            }
        },
    };
}
</script>
@endpush
@endsection
