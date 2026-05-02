@extends('layouts.app')

@section('title', 'Instagram Accounts')
@section('header', 'Instagram Accounts')

@section('content')
<div x-data="instagramAccountsPage()" class="max-w-6xl space-y-6">
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Attach Instagram Accounts</h1>
                <p class="mt-2 text-sm text-gray-500">Save one or more Instagram browser profiles here, store the login password in Hexa credentials, then run the login and status checks against each attached account.</p>
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
            <li>Save the account label, browser profile id, Instagram username, and password below.</li>
            <li>Click <span class="font-medium">Log in with saved credentials</span> on the account card you want to attach.</li>
            <li>Use <span class="font-medium">Check status</span> to confirm whether the active browser profile is really authenticated or stuck at login / challenge.</li>
            <li>After an account is working, set it active if needed and then use <a href="{{ route('settings.instagram') }}" class="font-medium underline">Settings</a> and <a href="{{ route('instagram.raw') }}" class="font-medium underline">Raw Workspace</a> for the rest of the testing flow.</li>
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
            <p class="mt-1 text-sm text-gray-500">Saving the same browser profile again updates the label, Instagram username, and password.</p>
        </div>

        <form @submit.prevent="addAccount()" class="grid gap-4 lg:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Account label</label>
                <input type="text" x-model="newAccount.label" @input="syncProfileFromLabel()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="JPN Miami Main">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Browser profile</label>
                <input type="text" x-model="newAccount.profile" @input="profileTouched = true" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono" placeholder="jpn-miami-main">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Instagram username</label>
                <input type="text" x-model="newAccount.instagram_username" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="jpnmiami">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Instagram password</label>
                <input type="password" x-model="newAccount.password" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Save a new password or leave blank to keep the existing one">
            </div>

            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" x-model="newAccount.set_active" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                Set active now
            </label>

            <div class="flex items-center justify-end gap-3">
                <button type="button" @click="clearForm()" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-50">Clear</button>
                <button type="submit" :disabled="saving" class="inline-flex items-center px-5 py-2.5 bg-emerald-600 text-white text-sm font-medium rounded-lg hover:bg-emerald-700 disabled:opacity-50">
                    <span x-text="saving ? 'Saving...' : 'Save Instagram account'"></span>
                </button>
            </div>
        </form>
    </div>

    <template x-if="!accounts.length">
        <div class="bg-white rounded-xl border border-dashed border-gray-300 shadow-sm p-10 text-center text-sm text-gray-500">
            No Instagram account profiles saved yet.
        </div>
    </template>

    <div class="grid gap-6 xl:grid-cols-2" x-show="accounts.length">
        <template x-for="account in accounts" :key="account.profile">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-4">
                <div class="flex items-start justify-between gap-4 flex-wrap">
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <h2 class="text-lg font-semibold text-gray-900" x-text="account.label"></h2>
                            <span x-show="account.profile === activeProfile" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700">Active</span>
                        </div>
                        <div class="mt-1 text-xs text-gray-500 font-mono" x-text="account.profile"></div>
                    </div>
                    <div class="text-right text-sm">
                        <div class="font-medium" :class="statusTone(account.profile)" x-text="statusLabel(account.profile)"></div>
                        <div class="mt-1 text-xs text-gray-500" x-text="statusDetail(account.profile)"></div>
                    </div>
                </div>

                <div class="grid gap-3 md:grid-cols-2 text-sm">
                    <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                        <div class="text-xs uppercase tracking-wide text-gray-500">Instagram username</div>
                        <div class="mt-1 font-medium text-gray-900" x-text="account.instagram_username || 'Not saved yet'"></div>
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                        <div class="text-xs uppercase tracking-wide text-gray-500">Saved password</div>
                        <div class="mt-1 font-medium" :class="account.password_configured ? 'text-emerald-700' : 'text-red-600'" x-text="account.password_configured ? (account.password_masked || 'Configured') : 'Missing'"></div>
                    </div>
                </div>

                <div class="flex items-center gap-2 flex-wrap">
                    <button type="button" @click="loadStatus(account.profile)" :disabled="busy(account.profile)" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-50 disabled:opacity-50">Check status</button>
                    <button type="button" @click="login(account.profile)" :disabled="busy(account.profile)" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50">Log in with saved credentials</button>
                    <button type="button" @click="setActive(account.profile)" :disabled="busy(account.profile) || account.profile === activeProfile" class="inline-flex items-center px-4 py-2 bg-emerald-600 text-white text-sm font-medium rounded-lg hover:bg-emerald-700 disabled:opacity-50">Use as active</button>
                    <button type="button" @click="logout(account.profile)" :disabled="busy(account.profile)" class="inline-flex items-center px-4 py-2 bg-amber-600 text-white text-sm font-medium rounded-lg hover:bg-amber-700 disabled:opacity-50">Log out</button>
                    <button type="button" @click="removeAccount(account.profile)" :disabled="busy(account.profile)" class="inline-flex items-center px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 disabled:opacity-50">Remove</button>
                </div>

                <template x-if="screenshotFor(account.profile)">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 space-y-3">
                        <div class="text-sm font-medium text-slate-900">Latest browser proof</div>
                        <img :src="screenshotFor(account.profile)" alt="Instagram browser proof" class="w-full rounded-lg border border-slate-200 bg-white">
                    </div>
                </template>

                <div class="rounded-xl border border-slate-200 bg-slate-950 text-slate-100 p-4 text-xs font-mono min-h-[12rem] whitespace-pre-wrap" x-text="pretty(payloadFor(account.profile))"></div>
            </div>
        </template>
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
function instagramAccountsPage() {
    return {
        accounts: @json($status['accounts']),
        activeProfile: @json($status['active_profile']),
        newAccount: { label: '', profile: '', instagram_username: '', password: '', set_active: true },
        profileTouched: false,
        saving: false,
        loadingMap: {},
        accountStates: {},
        payloads: {},
        toast: { show: false, message: '', type: 'success' },
        toastTimer: null,

        init() {
            if (this.activeProfile) {
                this.loadStatus(this.activeProfile);
            }
        },

        csrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.content || '';
        },

        busy(profile) {
            return Boolean(this.loadingMap[profile]);
        },

        showToast(message, type = 'success') {
            if (this.toastTimer) clearTimeout(this.toastTimer);
            this.toast = { show: true, message, type };
            this.toastTimer = setTimeout(() => { this.toast.show = false; }, 3500);
        },

        pretty(payload) {
            try {
                return JSON.stringify(payload || { message: 'No browser proof loaded yet.' }, null, 2);
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
            this.newAccount = { label: '', profile: '', instagram_username: '', password: '', set_active: true };
            this.profileTouched = false;
        },

        replaceStatus(status) {
            this.accounts = status.accounts || [];
            this.activeProfile = status.active_profile || this.activeProfile;
        },

        stateFor(profile) {
            return this.accountStates[profile] || {};
        },

        payloadFor(profile) {
            return this.payloads[profile] || { message: 'No browser proof loaded yet.' };
        },

        screenshotFor(profile) {
            return this.stateFor(profile)?.worker?.final?.screenshot_data_url || null;
        },

        statusLabel(profile) {
            const state = this.stateFor(profile);
            if (state.connected) return 'Connected';
            if (state.challenge) return 'Challenge required';
            if (state.login_form) return 'Login form visible';
            if (state.probe) return 'Not connected';
            return 'No status loaded';
        },

        statusTone(profile) {
            const state = this.stateFor(profile);
            if (state.connected) return 'text-emerald-700';
            if (state.challenge) return 'text-amber-700';
            if (state.login_form) return 'text-blue-700';
            return 'text-gray-500';
        },

        statusDetail(profile) {
            const state = this.stateFor(profile);
            return state.detail || state.probe?.title || 'Run a status check to load the browser result.';
        },

        async request(url, options = {}) {
            const response = await fetch(url, {
                ...options,
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken(),
                    ...(options.headers || {}),
                },
            });
            const data = await response.json();
            return { response, data };
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
                    this.replaceStatus(data.status || {});
                    this.showToast(data.message || 'Instagram account profile saved.');
                    const profile = this.slugify(this.newAccount.profile || this.newAccount.label);
                    this.clearForm();
                    await this.loadStatus(profile);
                } else {
                    this.showToast(data.message || 'Failed to save the Instagram account profile.', 'error');
                }
            } catch (error) {
                this.showToast('Failed to save the Instagram account profile.', 'error');
            } finally {
                this.saving = false;
            }
        },

        async loadStatus(profile) {
            const normalized = this.slugify(profile);
            this.loadingMap[normalized] = true;
            try {
                const url = new URL('{{ route('instagram.accounts.status') }}', window.location.origin);
                url.searchParams.set('profile', normalized);
                const { data } = await this.request(url);
                this.accountStates[normalized] = data.data || {};
                this.accountStates[normalized].detail = data.detail || '';
                this.payloads[normalized] = data;
                if (data.success) {
                    this.showToast(data.message || 'Instagram status loaded.');
                } else {
                    this.showToast(data.message || 'Instagram status check failed.', 'error');
                }
            } catch (error) {
                this.payloads[normalized] = { success: false, message: error?.message || 'Status request failed.' };
                this.showToast('Instagram status check failed.', 'error');
            } finally {
                this.loadingMap[normalized] = false;
            }
        },

        async login(profile) {
            const normalized = this.slugify(profile);
            this.loadingMap[normalized] = true;
            try {
                const { data } = await this.request('{{ route('instagram.accounts.login') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ profile: normalized }),
                });
                this.accountStates[normalized] = data.data || {};
                this.accountStates[normalized].detail = data.detail || '';
                this.payloads[normalized] = data;
                this.showToast(data.message || 'Instagram login flow finished.', data.success ? 'success' : 'error');
            } catch (error) {
                this.payloads[normalized] = { success: false, message: error?.message || 'Instagram login failed.' };
                this.showToast('Instagram login failed.', 'error');
            } finally {
                this.loadingMap[normalized] = false;
            }
        },

        async setActive(profile) {
            const normalized = this.slugify(profile);
            this.loadingMap[normalized] = true;
            try {
                const { response, data } = await this.request('{{ route('instagram.accounts.activate') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ profile: normalized }),
                });
                if (response.ok && data.success) {
                    this.replaceStatus(data.status || {});
                    this.showToast(data.message || 'Active Instagram account updated.');
                    await this.loadStatus(normalized);
                } else {
                    this.showToast(data.message || 'Failed to update the active Instagram account.', 'error');
                }
            } catch (error) {
                this.showToast('Failed to update the active Instagram account.', 'error');
            } finally {
                this.loadingMap[normalized] = false;
            }
        },

        async logout(profile) {
            const normalized = this.slugify(profile);
            this.loadingMap[normalized] = true;
            try {
                const { data } = await this.request('{{ route('instagram.accounts.logout') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ profile: normalized }),
                });
                this.accountStates[normalized] = data.data || {};
                this.accountStates[normalized].detail = data.detail || '';
                this.payloads[normalized] = data;
                this.showToast(data.message || 'Instagram browser profile logged out.', data.success ? 'success' : 'error');
            } catch (error) {
                this.showToast('Failed to log out the Instagram browser profile.', 'error');
            } finally {
                this.loadingMap[normalized] = false;
            }
        },

        async removeAccount(profile) {
            const normalized = this.slugify(profile);
            if (!confirm(`Remove the Instagram account profile "${normalized}"?`)) return;

            this.loadingMap[normalized] = true;
            try {
                const { response, data } = await this.request('{{ route('instagram.accounts.destroy') }}', {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ profile: normalized }),
                });
                if (response.ok && data.success) {
                    this.replaceStatus(data.status || {});
                    delete this.accountStates[normalized];
                    delete this.payloads[normalized];
                    this.showToast(data.message || 'Instagram account profile removed.');
                } else {
                    this.showToast(data.message || 'Failed to remove the Instagram account profile.', 'error');
                }
            } catch (error) {
                this.showToast('Failed to remove the Instagram account profile.', 'error');
            } finally {
                delete this.loadingMap[normalized];
            }
        },
    };
}
</script>
@endpush
@endsection
