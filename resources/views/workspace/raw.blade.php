@extends('layouts.app')

@section('title', 'Instagram Raw Workspace')
@section('header', 'Instagram Raw Workspace')

@section('content')
<div x-data="instagramRawWorkspace()" class="max-w-6xl space-y-6">
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h1 class="text-2xl font-semibold text-gray-900">Instagram Raw Workspace</h1>
                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-[11px] font-semibold text-gray-600">v{{ $status['package_version'] ?? '1.0.0' }}</span>
                </div>
                <p class="mt-2 text-sm text-gray-500">Low-level browser-worker debugger for attached Instagram accounts, profile scans, story pulls, public post import, and worker logs.</p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route('instagram.accounts') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-50">Accounts</a>
                <a href="{{ route('settings.instagram') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-50">Settings</a>
                <a href="{{ route('instagram.index') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-50">Overview</a>
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-blue-200 bg-blue-50 p-5 text-sm text-blue-900 space-y-2">
        <div class="font-semibold">Use this page for raw Instagram debugging only.</div>
        <p>Attach the account on the Accounts page first. This page is where you prove the browser login is still alive, inspect worker logs, and test profile, story, and post-import behavior before wiring automation jobs.</p>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-5">
        <div class="grid gap-4 md:grid-cols-[18rem_auto] items-end">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Account profile</label>
                <select x-model="profile" @change="setActiveAccountFromProfile()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                    <template x-for="account in accounts" :key="account.profile">
                        <option :value="account.profile" x-text="`${account.label} (${account.profile})`"></option>
                    </template>
                </select>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <button type="button" @click="loadStatus()" :disabled="loading.status" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50">
                    <span x-text="loading.status ? 'Loading status...' : 'Refresh status'"></span>
                </button>
                <button type="button" @click="runIntegrity()" :disabled="loading.integrity" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                    <span x-text="loading.integrity ? 'Testing...' : 'Run integrity test'"></span>
                </button>
                <button type="button" @click="loadLogs()" :disabled="loading.logs" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-50 disabled:opacity-50">
                    <span x-text="loading.logs ? 'Loading logs...' : 'Refresh logs'"></span>
                </button>
                <a href='/instagram/accounts' target='_blank' rel='noopener' class='inline-flex items-center px-4 py-2 bg-emerald-600 text-white text-sm font-semibold rounded-lg hover:bg-emerald-700'>Connect / reconnect account &nearr;</a>
                <a href='/settings/instagram' target='_blank' rel='noopener' class='inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-50'>Connection settings &nearr;</a>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                <div class="text-xs uppercase tracking-wide text-gray-500">Connection</div>
                <div class="mt-1 text-sm font-semibold" :class="statusPayload.data?.connected ? 'text-emerald-700' : 'text-amber-700'" x-text="statusPayload.data?.connected ? 'Connected' : 'Not connected'"></div>
                <div class="mt-1 text-xs text-gray-500" x-text="statusPayload.message || 'Refresh the worker status to confirm the browser session.'"></div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                <div class="text-xs uppercase tracking-wide text-gray-500">Instagram username</div>
                <div class="mt-1 text-sm font-semibold text-gray-900" x-text="activeAccount?.instagram_username || 'Not saved'"></div>
                <div class="mt-1 text-xs text-gray-500" x-text="activeAccount?.password_configured ? (activeAccount.password_masked || 'Password configured') : 'Password missing'"></div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                <div class="text-xs uppercase tracking-wide text-gray-500">Challenge state</div>
                <div class="mt-1 text-sm font-semibold text-gray-900" x-text="statusPayload.data?.challenge ? 'Challenge / checkpoint' : 'None detected'"></div>
                <div class="mt-1 text-xs text-gray-500" x-text="statusPayload.data?.probe?.path || 'No path loaded yet.'"></div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                <div class="text-xs uppercase tracking-wide text-gray-500">Meta token</div>
                <div class="mt-1 text-sm font-semibold {{ $status['has_meta_token'] ? 'text-emerald-700' : 'text-gray-500' }}">{{ $status['has_meta_token'] ? 'Configured' : 'Not set' }}</div>
                <div class="mt-1 text-xs text-gray-500 font-mono">{{ $status['meta_token_masked'] ?: 'public scrape fallback only' }}</div>
            </div>
        </div>

        <template x-if='!statusPayload.data?.connected'>
            <div class='rounded-xl border border-amber-200 bg-amber-50 p-5 space-y-4'>
                <div class='flex items-start justify-between gap-4 flex-wrap'>
                    <div>
                        <div class='text-sm font-semibold text-amber-950'>Raw connection recovery</div>
                        <p class='mt-1 text-sm text-amber-900'>This account is not connected. Use the account recovery page to run the saved-credential login, submit any Instagram code, or clear the browser session. Return here and refresh status after the recovery step.</p>
                    </div>
                    <div class='flex items-center gap-2 flex-wrap'>
                        <a href='/instagram/accounts' target='_blank' rel='noopener' class='inline-flex items-center px-4 py-2 rounded-lg bg-emerald-600 text-sm font-semibold text-white hover:bg-emerald-700'>Connect / reconnect account &nearr;</a>
                        <a href='/settings/instagram' target='_blank' rel='noopener' class='inline-flex items-center px-4 py-2 rounded-lg border border-amber-300 bg-white text-sm font-semibold text-amber-900 hover:bg-amber-100'>Open connection settings &nearr;</a>
                        <a href='https://www.instagram.com/accounts/login/' target='_blank' rel='noopener' class='inline-flex items-center px-4 py-2 rounded-lg border border-amber-300 bg-white text-sm font-semibold text-amber-900 hover:bg-amber-100'>Open Instagram login &nearr;</a>
                    </div>
                </div>
                <div class='grid gap-3 md:grid-cols-3'>
                    <div class='rounded-lg border border-amber-200 bg-white px-4 py-3'>
                        <div class='text-xs uppercase tracking-wide text-gray-500'>Step 1</div>
                        <div class='mt-1 text-sm font-semibold text-gray-900'>Open Accounts recovery</div>
                        <div class='mt-1 text-xs text-gray-600'>Select this profile and run Log in with saved credentials.</div>
                    </div>
                    <div class='rounded-lg border border-amber-200 bg-white px-4 py-3'>
                        <div class='text-xs uppercase tracking-wide text-gray-500'>Step 2</div>
                        <div class='mt-1 text-sm font-semibold text-gray-900'>Handle code or challenge</div>
                        <div class='mt-1 text-xs text-gray-600'>Submit any Instagram verification code or clear the stale browser session there.</div>
                    </div>
                    <div class='rounded-lg border border-amber-200 bg-white px-4 py-3'>
                        <div class='text-xs uppercase tracking-wide text-gray-500'>Step 3</div>
                        <div class='mt-1 text-sm font-semibold text-gray-900'>Refresh raw status</div>
                        <div class='mt-1 text-xs text-gray-600'>The raw workspace should turn connected before scans are run.</div>
                    </div>
                </div>
            </div>
        </template>
        <template x-if="statusPayload.data?.worker?.final?.screenshot_data_url">
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 space-y-3">
                <div class="text-sm font-medium text-slate-900">Latest browser screenshot</div>
                <img :src="statusPayload.data.worker.final.screenshot_data_url" alt="Instagram browser screenshot" class="w-full max-w-3xl rounded-lg border border-slate-200 bg-white">
            </div>
        </template>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-4">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Raw action history</h2>
                <p class="mt-1 text-sm text-gray-500">Every status check, profile scan, story pull, and post import is saved here so the proof survives reloads.</p>
            </div>
            <a href="{{ route('instagram.raw') }}#raw-history" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-50">Open proof link</a>
        </div>

        <div id="raw-history">
            <x-hexa-log-viewer
                title="Instagram Raw History"
                log-var="historyLog"
                slug="instagram-raw-history"
                theme="light"
                :persist="true"
                :collapsible="true"
                :start-collapsed="false" />
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Profile scan</h2>
                <p class="mt-1 text-sm text-gray-500">Pull rendered profile metadata and recent post links through the authenticated browser session.</p>
            </div>
            <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_7rem_auto]">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Instagram username</label>
                    <input type="text" x-model="profileForm.instagram_username" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="jpnmiami">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Limit</label>
                    <input type="number" min="1" max="30" x-model="profileForm.limit" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono">
                </div>
                <div class="flex items-end">
                    <button type="button" @click="runProfileScan()" :disabled="loading.profileScan" class="inline-flex items-center justify-center px-4 py-2 bg-emerald-600 text-white text-sm font-medium rounded-lg hover:bg-emerald-700 disabled:opacity-50 w-full">
                        <span x-text="loading.profileScan ? 'Scanning...' : 'Run profile scan'"></span>
                    </button>
                </div>
            </div>
            <template x-if="profilePosts().length">
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 space-y-3">
                    <div class="text-sm font-semibold text-emerald-900">Recent posts found</div>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="post in profilePosts()" :key="post">
                            <button type="button"
                                    @click="usePostUrl(post)"
                                    class="inline-flex items-center px-3 py-1.5 rounded-lg border border-emerald-300 bg-white text-sm font-medium text-emerald-800 hover:bg-emerald-100">
                                <span class="truncate max-w-[24rem]" x-text="post"></span>
                            </button>
                        </template>
                    </div>
                    <p class="text-xs text-emerald-800">Click a post to send it straight into the post-import tool below.</p>
                </div>
            </template>
            <div class="rounded-xl border border-slate-200 bg-slate-950 text-slate-100 p-4 text-xs font-mono min-h-[16rem] whitespace-pre-wrap" x-text="pretty(profilePayload)"></div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Story pull</h2>
                <p class="mt-1 text-sm text-gray-500">Inspect the currently visible story media URLs for the given username.</p>
            </div>
            <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_auto]">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Instagram username</label>
                    <input type="text" x-model="storyForm.instagram_username" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="jpnmiami">
                </div>
                <div class="flex items-end">
                    <button type="button" @click="runStoryScan()" :disabled="loading.storyScan" class="inline-flex items-center justify-center px-4 py-2 bg-emerald-600 text-white text-sm font-medium rounded-lg hover:bg-emerald-700 disabled:opacity-50 w-full">
                        <span x-text="loading.storyScan ? 'Loading...' : 'Run story pull'"></span>
                    </button>
                </div>
            </div>
            <template x-if="storyMedia().length">
                <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-4 space-y-3">
                    <div class="text-sm font-semibold text-indigo-900">Retrieved story media</div>
                    <div class="space-y-2">
                        <template x-for="item in storyMedia()" :key="item.url">
                            <div class="rounded-lg border border-indigo-200 bg-white px-3 py-2">
                                <div class="text-xs uppercase tracking-wide text-indigo-700" x-text="item.type"></div>
                                <a :href="item.url" target="_blank" rel="noopener" class="mt-1 block text-sm font-medium text-blue-700 underline break-all" x-text="item.url"></a>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
            <div class="rounded-xl border border-slate-200 bg-slate-950 text-slate-100 p-4 text-xs font-mono min-h-[16rem] whitespace-pre-wrap" x-text="pretty(storyPayload)"></div>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-[1.1fr_.9fr]">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Public post import</h2>
                <p class="mt-1 text-sm text-gray-500">Tests the extracted Instagram import service directly, including the optional Meta token path and the public scrape fallback.</p>
            </div>
            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Instagram post URL</label>
                    <input type="url" x-model="importForm.url" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="https://www.instagram.com/p/.../">
                </div>
                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" x-model="importForm.include_image_data" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                    Include image data / dimensions
                </label>
                <button type="button" @click="runImport()" :disabled="loading.importPost" class="inline-flex items-center justify-center px-4 py-2 bg-orange-600 text-white text-sm font-medium rounded-lg hover:bg-orange-700 disabled:opacity-50">
                    <span x-text="loading.importPost ? 'Importing...' : 'Run post import'"></span>
                </button>
            </div>
            <template x-if="importSummary().title || importSummary().caption">
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 space-y-3">
                    <div class="text-sm font-semibold text-amber-900">Retrieved post summary</div>
                    <template x-if="importSummary().title">
                        <div>
                            <div class="text-xs uppercase tracking-wide text-amber-700">Title</div>
                            <div class="mt-1 text-sm font-medium text-gray-900" x-text="importSummary().title"></div>
                        </div>
                    </template>
                    <template x-if="importSummary().caption">
                        <div>
                            <div class="text-xs uppercase tracking-wide text-amber-700">Caption</div>
                            <div class="mt-1 text-sm text-gray-900 whitespace-pre-wrap" x-text="importSummary().caption"></div>
                        </div>
                    </template>
                    <template x-if="importSummary().image_url">
                        <div>
                            <div class="text-xs uppercase tracking-wide text-amber-700">Image URL</div>
                            <a :href="importSummary().image_url" target="_blank" rel="noopener" class="mt-1 block text-sm font-medium text-blue-700 underline break-all" x-text="importSummary().image_url"></a>
                        </div>
                    </template>
                </div>
            </template>
            <div class="rounded-xl border border-slate-200 bg-slate-950 text-slate-100 p-4 text-xs font-mono min-h-[18rem] whitespace-pre-wrap" x-text="pretty(importPayload)"></div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Worker logs</h2>
                <p class="mt-1 text-sm text-gray-500">Recent browser-worker entries help prove what the runtime actually did.</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-950 text-slate-100 p-4 text-xs font-mono min-h-[34rem] whitespace-pre-wrap overflow-y-auto" x-text="pretty(logsPayload)"></div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function instagramRawWorkspace() {
    return {
        accounts: @json($status['accounts']),
        profile: @json($status['active_profile']),
        activeAccount: @json($status['active_account']),
        historyLog: @json($status['raw_history'] ?? []),
        loading: {
            status: false,
            integrity: false,
            logs: false,
            profileScan: false,
            storyScan: false,
            importPost: false,
        },
        statusPayload: {
            success: false,
            message: 'Refresh the status to inspect the active Instagram browser profile.',
            detail: '',
            data: {},
        },
        integrityPayload: {
            success: false,
            message: 'Run the integrity test to inspect worker and Instagram session health.',
            detail: '',
            data: {},
        },
        logsPayload: { message: 'Refresh logs to inspect the recent worker activity.' },
        profilePayload: { message: 'Run the profile scan to load recent posts and profile metadata.' },
        storyPayload: { message: 'Run the story pull to load currently visible media URLs.' },
        importPayload: { message: 'Run the post import to test the extracted Instagram import service.' },
        profileForm: {
            instagram_username: @json($status['default_profile_username']),
            limit: 12,
        },
        storyForm: {
            instagram_username: @json($status['default_story_username']),
        },
        importForm: {
            url: @json($status['default_post_url']),
            include_image_data: true,
        },

        init() {
            this.setActiveAccountFromProfile();
            this.loadStatus();
            this.loadLogs();
            if (this.historyLog.length === 0) {
                this.logHistory('info', 'Instagram raw workspace opened.', {
                    profile: this.profile,
                });
            }
        },

        csrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.content || '';
        },

        pretty(payload) {
            try {
                return JSON.stringify(payload, null, 2);
            } catch (error) {
                return String(payload);
            }
        },

        setActiveAccountFromProfile() {
            this.activeAccount = this.accounts.find((account) => account.profile === this.profile) || null;
        },

        now() {
            return new Date().toLocaleTimeString([], { hour12: false });
        },

        logHistory(type, message, detail = null) {
            this.historyLog.push({
                type,
                message,
                detail,
                time: this.now(),
            });
        },

        profilePosts() {
            return this.profilePayload?.data?.scan?.post_links || [];
        },

        storyMedia() {
            const images = (this.storyPayload?.data?.scan?.image_urls || []).map((url) => ({ type: 'image', url }));
            const videos = (this.storyPayload?.data?.scan?.video_urls || []).map((url) => ({ type: 'video', url }));
            return [...images, ...videos];
        },

        importSummary() {
            return {
                title: this.importPayload?.data?.title || '',
                caption: this.importPayload?.data?.caption || '',
                image_url: this.importPayload?.data?.image_url || '',
            };
        },

        usePostUrl(url) {
            this.importForm.url = url;
            this.logHistory('info', 'Selected Instagram post for import.', { url });
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

        async loadStatus() {
            this.loading.status = true;
            try {
                const url = new URL('{{ route('instagram.status') }}', window.location.origin);
                url.searchParams.set('profile', this.profile || 'instagram-main');
                const { data } = await this.request(url);
                this.statusPayload = data;
                this.logHistory(data.success ? 'success' : 'warning', 'Refreshed Instagram raw status.', {
                    profile: this.profile,
                    connected: data?.data?.connected ?? null,
                    verification_required: data?.data?.verification_required ?? null,
                    current_url: data?.data?.probe?.url ?? null,
                });
            } finally {
                this.loading.status = false;
            }
        },

        async runIntegrity() {
            this.loading.integrity = true;
            try {
                const url = new URL('{{ route('instagram.integrity') }}', window.location.origin);
                url.searchParams.set('profile', this.profile || 'instagram-main');
                const { data } = await this.request(url);
                this.integrityPayload = data;
                this.statusPayload = data.data?.instagram_status || this.statusPayload;
                this.logHistory(data.success ? 'success' : 'warning', 'Ran Instagram raw integrity test.', {
                    profile: this.profile,
                    connected: data?.data?.connected ?? null,
                    detail: data?.detail || null,
                });
            } finally {
                this.loading.integrity = false;
            }
        },

        async loadLogs() {
            this.loading.logs = true;
            try {
                const url = new URL('{{ route('instagram.logs') }}', window.location.origin);
                url.searchParams.set('limit', '80');
                const { data } = await this.request(url);
                this.logsPayload = data;
            } finally {
                this.loading.logs = false;
            }
        },

        async runProfileScan() {
            this.loading.profileScan = true;
            try {
                const { data } = await this.request('{{ route('instagram.profile-scan') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        profile: this.profile,
                        instagram_username: this.profileForm.instagram_username,
                        limit: this.profileForm.limit,
                    }),
                });
                this.profilePayload = data;
                this.logHistory(data.success ? 'success' : 'warning', 'Ran Instagram profile scan.', {
                    profile: this.profile,
                    instagram_username: this.profileForm.instagram_username,
                    post_count: data?.data?.scan?.post_links?.length || 0,
                    title: data?.data?.scan?.title || null,
                });
            } finally {
                this.loading.profileScan = false;
            }
        },

        async runStoryScan() {
            this.loading.storyScan = true;
            try {
                const { data } = await this.request('{{ route('instagram.story-scan') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        profile: this.profile,
                        instagram_username: this.storyForm.instagram_username,
                    }),
                });
                this.storyPayload = data;
                this.logHistory(data.success ? 'success' : 'warning', 'Ran Instagram story pull.', {
                    profile: this.profile,
                    instagram_username: this.storyForm.instagram_username,
                    image_count: data?.data?.scan?.image_urls?.length || 0,
                    video_count: data?.data?.scan?.video_urls?.length || 0,
                });
            } finally {
                this.loading.storyScan = false;
            }
        },

        async runImport() {
            this.loading.importPost = true;
            try {
                const { data } = await this.request('{{ route('instagram.import-post') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(this.importForm),
                });
                this.importPayload = data;
                this.logHistory(data.success ? 'success' : 'warning', 'Ran Instagram post import.', {
                    url: this.importForm.url,
                    method_used: data?.data?.method_used || null,
                    title: data?.data?.title || null,
                    caption_length: (data?.data?.caption || '').length,
                });
            } finally {
                this.loading.importPost = false;
            }
        },
    };
}
</script>
@endpush
@endsection
