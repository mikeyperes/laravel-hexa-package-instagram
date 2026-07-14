@extends('layouts.app')

@section('title', 'Instagram Settings')
@section('header', 'Instagram Settings')

@section('content')
<div x-data="instagramSettingsPage()" class="max-w-6xl space-y-6">

    {{-- ============================================================
         STATUS STRIP — replaces 4-tile grid + main flow blue card.
         One thin row: connection state + attached account + nav.
         ============================================================ --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="flex flex-wrap items-center gap-x-6 gap-y-3 px-5 py-4">
            <div class="flex items-center gap-3 min-w-0">
                @php
                    $isConfigured = !empty($status['active_account']['password_configured']);
                    $hasActive = !empty($status['active_profile']);
                @endphp
                <span class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full text-xs font-semibold {{ $hasActive ? ($isConfigured ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200') : 'bg-gray-100 text-gray-600 border border-gray-200' }}">
                    <span class="w-1.5 h-1.5 rounded-full {{ $hasActive ? ($isConfigured ? 'bg-emerald-500' : 'bg-amber-500') : 'bg-gray-400' }}"></span>
                    {{ $hasActive ? ($isConfigured ? 'Attached' : 'No saved password') : 'No active profile' }}
                </span>
                <div class="min-w-0 leading-tight">
                    <div class="text-sm font-semibold text-gray-900 truncate">
                        {{ $status['active_account']['instagram_username'] ?? '—' }}
                        @if($hasActive)
                            <span class="text-gray-400 font-normal">·</span>
                            <span class="font-mono text-xs text-gray-500">{{ $status['active_profile'] }}</span>
                        @endif
                    </div>
                    <div class="text-xs text-gray-500 mt-0.5">
                        @if($isConfigured)
                            <span class="font-mono text-emerald-700">{{ $status['active_account']['password_masked'] ?? 'configured' }}</span>
                        @else
                            <span class="text-rose-600">password missing</span>
                        @endif
                        <span class="text-gray-300 mx-1">·</span>
                        {{ count($status['accounts']) }} saved {{ Str::plural('account', count($status['accounts'])) }}
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2 flex-wrap ml-auto">
                <a href="{{ route('instagram.accounts') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 text-white text-xs font-semibold rounded-md hover:bg-emerald-700">
                    Accounts
                </a>
                <a href="{{ route('instagram.raw') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-gray-300 text-xs font-semibold rounded-md text-gray-700 hover:bg-gray-50">
                    Raw Workspace
                </a>
                <a href="{{ route('settings.browser-worker') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-gray-300 text-xs font-semibold rounded-md text-gray-700 hover:bg-gray-50">
                    Browser Worker
                </a>
                <a href="https://www.instagram.com/" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-gray-500 hover:text-gray-700">
                    Open Instagram
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                </a>
            </div>
        </div>
    </div>

    {{-- ============================================================
         CONNECTION PANEL — the main job.
         Single primary CTA driven by state. Inline verification.
         ============================================================ --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-100">
            <div class="flex items-start justify-between gap-3 flex-wrap">
                <div class="min-w-0">
                    <h2 class="text-lg font-semibold text-gray-900">Connection</h2>
                    <p class="mt-0.5 text-sm text-gray-500">Verify the saved Instagram session and run the deeper content probe.</p>
                </div>
                <a href="{{ route('instagram.accounts') }}" class="inline-flex items-center gap-1 text-xs font-medium text-gray-500 hover:text-gray-700">
                    Manage accounts
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>
        </div>

        <div class="px-6 py-5 space-y-5">
            {{-- Account selector --}}
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">Saved account</label>
                <select x-model="testProfile" @change="clearSessionResult()" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-emerald-400 focus:border-emerald-400">
                    <option value="">Select a saved Instagram account</option>
                    @foreach($status['accounts'] as $account)
                        <option value="{{ $account['profile'] }}">{{ $account['label'] }} — {{ $account['instagram_username'] ?: $account['profile'] }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Primary CTA strip — driven by state --}}
            <div class="rounded-lg border bg-gray-50 px-4 py-4 space-y-3"
                 :class="primaryStripClass()"
                 x-show="testProfile" x-cloak>
                <div class="flex items-start gap-3">
                    <div class="flex-1 min-w-0">
                        <div class="text-xs font-semibold uppercase tracking-wide" :class="primaryHeaderTextClass()" x-text="primaryHeaderLabel()"></div>
                        <div class="mt-1 text-sm font-semibold text-gray-900" x-text="recoveryTitle()"></div>
                        <p class="mt-0.5 text-xs text-gray-600" x-text="recoveryDetail()"></p>
                    </div>
                    <div>
                        <button type="button" @click="primaryAction()" :disabled="primaryDisabled()"
                                class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white rounded-lg disabled:opacity-50 disabled:cursor-not-allowed"
                                :class="primaryButtonClass()">
                            <svg x-show="sessionBusy() || testing" x-cloak class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                            <span x-text="primaryLabel()"></span>
                        </button>
                    </div>
                </div>

                {{-- Inline verification code — only visible when needed --}}
                <div class="grid grid-cols-1 md:grid-cols-[minmax(0,1fr)_auto] gap-2 items-end pt-2 border-t border-amber-200" x-show="needsVerificationCode()" x-cloak>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-amber-700 mb-1" x-text="verificationLabel()"></label>
                        <input type="text" inputmode="numeric" x-model="verificationCode"
                               class="w-full border border-amber-300 rounded-lg px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-amber-400 focus:border-amber-400"
                               placeholder="Enter the code from Instagram">
                    </div>
                    <button type="button" @click="submitVerificationCode()" :disabled="sessionBusy() || !(verificationCode || '').trim()"
                            class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-amber-600 text-white text-sm font-semibold rounded-lg hover:bg-amber-700 disabled:opacity-50">
                        <svg x-show="sessionAction === 'submitCode'" x-cloak class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                        <span x-text="submitCodeActionLabel()"></span>
                    </button>
                </div>
            </div>

            {{-- Secondary actions — small text-button row --}}
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs" x-show="testProfile" x-cloak>
                <button type="button" @click="checkStatus()" :disabled="sessionBusy() || !testProfile"
                        class="inline-flex items-center gap-1 text-gray-500 hover:text-gray-900 font-medium disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    <span x-text="statusActionLabel()"></span>
                </button>
                <button type="button" @click="reconnect()" :disabled="sessionBusy() || !testProfile"
                        class="inline-flex items-center gap-1 text-gray-500 hover:text-gray-900 font-medium disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                    <span x-text="reconnectActionLabel()"></span>
                </button>
                <button type="button" @click="runTest()" :disabled="testing || sessionBusy() || !testProfile"
                        class="inline-flex items-center gap-1 text-gray-500 hover:text-gray-900 font-medium disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                    <span x-show="!testing">Run full content test</span>
                    <span x-show="testing" x-cloak>Running full test...</span>
                </button>
            </div>
        </div>

        {{-- ============================================================
             RESULT BLOCK — only after a test runs.
             Compact rows + clickable URL + collapsible sections.
             ============================================================ --}}
        <div x-show="testResult" x-cloak class="border-t border-gray-100 bg-gray-50 px-6 py-5 space-y-4">
            <div class="flex items-center justify-between gap-2 flex-wrap">
                <div class="flex items-center gap-2 min-w-0">
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold" :class="testBadgeClass()" x-text="testBadge()"></span>
                    <span class="text-sm font-semibold" :class="testSummaryClass()" x-text="testSummary()"></span>
                </div>
            </div>
            <p class="text-sm text-gray-600" x-text="testDetail()"></p>

            <template x-if="testResult?.data?.instagram_status?.data">
                <dl class="divide-y divide-gray-200 rounded-lg bg-white border border-gray-200 text-sm">
                    <div class="flex justify-between gap-3 px-3 py-2">
                        <dt class="text-gray-500">Account</dt>
                        <dd class="text-gray-900 font-medium text-right" x-text="testAccountLabel()"></dd>
                    </div>
                    <div class="flex justify-between gap-3 px-3 py-2">
                        <dt class="text-gray-500">Profile</dt>
                        <dd class="text-gray-900 font-mono text-right" x-text="testProfile || '-'"></dd>
                    </div>
                    <div class="flex justify-between gap-3 px-3 py-2">
                        <dt class="text-gray-500 shrink-0">Current URL</dt>
                        <dd class="text-right min-w-0 flex-1">
                            <template x-if="testCurrentUrlHref()">
                                <a :href="testCurrentUrlHref()" target="_blank" rel="noopener noreferrer"
                                   class="inline-flex items-start gap-1 text-blue-600 hover:text-blue-800 underline break-all"
                                   x-text="testCurrentUrl()">
                                </a>
                            </template>
                            <template x-if="!testCurrentUrlHref()">
                                <span class="text-gray-900 break-all" x-text="testCurrentUrl()"></span>
                            </template>
                            <template x-if="testCurrentUrlHref()">
                                <a :href="testCurrentUrlHref()" target="_blank" rel="noopener noreferrer"
                                   class="inline-flex items-center text-blue-600 hover:text-blue-800 ml-1 align-text-bottom"
                                   title="Open in new tab">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                </a>
                            </template>
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3 px-3 py-2">
                        <dt class="text-gray-500">Worker state</dt>
                        <dd class="text-gray-900 text-right" x-text="testWorkerState()"></dd>
                    </div>
                </dl>
            </template>

            {{-- Following / posts / story sample (collapsed) --}}
            <details class="rounded-lg border border-gray-200 bg-white" x-show="testResult?.data?.following_sample" x-cloak>
                <summary class="cursor-pointer px-3 py-2 text-sm font-medium text-gray-800 hover:bg-gray-50 rounded-lg">Following / posts / story sample</summary>
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
                                <a :href="link" target="_blank" rel="noopener noreferrer" class="inline-flex items-start gap-1 break-all text-xs font-medium text-blue-600 hover:text-blue-800 underline">
                                    <span x-text="link"></span>
                                    <svg class="w-3 h-3 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                </a>
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
                                <a :href="m.url" target="_blank" rel="noopener noreferrer" class="inline-flex items-start gap-1 break-all text-xs font-medium text-blue-600 hover:text-blue-800 underline">
                                    <span x-text="m.type + ': ' + m.url"></span>
                                    <svg class="w-3 h-3 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                </a>
                            </template>
                        </div>
                    </div>
                </div>
            </details>

            {{-- Raw payload (collapsed) --}}
            <details class="rounded-lg border border-gray-200 bg-white">
                <summary class="cursor-pointer px-3 py-2 text-sm font-medium text-gray-800 hover:bg-gray-50 rounded-lg">Raw payload</summary>
                <div class="border-t border-gray-200 px-3 py-3">
                    <pre class="text-[11px] font-mono whitespace-pre-wrap break-words text-gray-700 max-h-96 overflow-y-auto" x-text="pretty(testResult)"></pre>
                </div>
            </details>

            {{-- Activity log (collapsed by default) --}}
            <details class="rounded-lg border border-gray-200 bg-white">
                <summary class="cursor-pointer px-3 py-2 text-sm font-medium text-gray-800 hover:bg-gray-50 rounded-lg">Activity log</summary>
                <div class="border-t border-gray-200">
                    <x-hexa-log-viewer
                        title="Instagram Connection Test Log"
                        log-var="testLog"
                        slug="instagram-settings-test"
                        theme="dark"
                        :persist="false" />
                </div>
            </details>
        </div>

        {{-- Idle activity log — shown when no test has run yet --}}
        <div x-show="!testResult" x-cloak class="border-t border-gray-100">
            <details class="bg-white">
                <summary class="cursor-pointer px-6 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2a4 4 0 014-4h4m-4 0l-4-4m4 4l-4 4m13-7v10a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h10"/></svg>
                    Activity log
                </summary>
                <div class="border-t border-gray-100">
                    <x-hexa-log-viewer
                        title="Instagram Connection Test Log"
                        log-var="testLog"
                        slug="instagram-settings-test-idle"
                        theme="dark"
                        :persist="false" />
                </div>
            </details>
        </div>
    </div>

    {{-- ============================================================
         DEFAULT TEST TARGETS — preserved, restyled.
         ============================================================ --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-6 py-5 border-b border-gray-100">
            <h2 class="text-lg font-semibold text-gray-900">Default test targets</h2>
            <p class="mt-0.5 text-sm text-gray-500">Prefill values for the Raw Workspace and public import checks.</p>
        </div>
        <form @submit.prevent="save()" class="px-6 py-5 grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Default profile username</label>
                <input type="text" x-model="form.default_profile_username" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-400 focus:border-emerald-400" placeholder="jpnmiami">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Default story username</label>
                <input type="text" x-model="form.default_story_username" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-400 focus:border-emerald-400" placeholder="jpnmiami">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Default post URL</label>
                <input type="url" x-model="form.default_post_url" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-400 focus:border-emerald-400" placeholder="https://www.instagram.com/p/.../">
            </div>
            <div class="md:col-span-3 flex justify-end">
                <button type="submit" :disabled="saving" class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white text-sm font-semibold rounded-lg hover:bg-emerald-700 disabled:opacity-50">
                    <svg x-show="saving" x-cloak class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                    <span x-show="!saving">Save settings</span>
                    <span x-show="saving" x-cloak>Saving...</span>
                </button>
            </div>
        </form>
    </div>

    {{-- ============================================================
         OPTIONAL META TOKEN — preserved as disclosure.
         ============================================================ --}}
    <details class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <summary class="cursor-pointer px-6 py-4 text-base font-semibold text-gray-900 hover:bg-gray-50 rounded-xl flex items-center justify-between gap-2">
            <span>Optional Meta oEmbed Token</span>
            <span class="text-xs font-normal text-gray-400">Last updated: <span class="font-mono">{{ $status['credential_updated_at'] ?: 'unknown' }}</span></span>
        </summary>
        <div class="px-6 py-5 border-t border-gray-100 space-y-3">
            <p class="text-sm text-gray-500">Optional. The Instagram package can scrape many public posts without it. Save this only if you want the official Meta oEmbed path before falling back to public scraping.</p>
            <x-hexa-credential-field
                slug="instagram"
                key-name="meta_access_token"
                label="Instagram / Meta Access Token"
                :test-url="route('settings.instagram.test-meta-token')"
                help="Optional Meta oEmbed token. Public scrape fallback remains available without it."
            />
        </div>
    </details>

    {{-- Toast --}}
    <template x-if="toast.show">
        <div class="fixed bottom-6 right-6 z-50 max-w-sm w-full rounded-xl border px-5 py-4 shadow-lg"
             :class="toast.type === 'success' ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800'">
            <div class="text-sm font-medium" x-text="toast.message"></div>
        </div>
    </template>
</div>

@push('scripts')
<script>@include('instagram::scripts.settings-index.block-1-part-1')</script>
@endpush
@endsection
