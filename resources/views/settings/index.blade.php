@extends('layouts.app')

@section('title', 'Instagram Settings')
@section('header', 'Instagram Settings')

@section('content')
<div class="max-w-5xl space-y-5" x-data="instagramSettingsPage()">
    <section class="rounded-xl border border-blue-200 bg-blue-50 p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="font-semibold text-blue-950">Connection setup lives in Browser Console</h2>
                <p class="mt-1 text-sm text-blue-800">Use Accounts to bind an expected Instagram username to a profile. Use Browser Console for human login and route changes.</p>
            </div>
            <a href="{{ route('instagram.accounts') }}" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white">Manage accounts</a>
        </div>
    </section>

    <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-semibold text-gray-900">Connection and collection test</h2>
        <p class="mt-1 text-sm text-gray-500">This verifies the exact profile/account binding, then samples followed content through the same connection.</p>
        <div class="mt-4 flex flex-wrap gap-3">
            <select x-model="testProfile" class="min-w-72 rounded-lg border-gray-300">
                <option value="">Select an account</option>
                @foreach($status['accounts'] as $account)
                    <option value="{{ $account['profile'] }}">{{ $account['label'] }} — &#64;{{ $account['instagram_username'] }}</option>
                @endforeach
            </select>
            <button @click="runTest()" :disabled="testing || !testProfile" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50" x-text="testing ? 'Testing…' : 'Run test'"></button>
            <a x-show="testProfile" :href="consoleUrl()" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700">Open Browser Console</a>
        </div>
        <template x-if="testResult">
            <div class="mt-4 rounded-lg border p-4" :class="testResult.success ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50'">
                <p class="font-medium" x-text="testResult.message"></p>
                <p class="mt-1 text-sm" x-text="testResult.detail"></p>
                <details class="mt-3"><summary class="cursor-pointer text-sm font-medium">Technical result</summary><pre class="mt-2 max-h-96 overflow-auto whitespace-pre-wrap text-xs" x-text="JSON.stringify(testResult, null, 2)"></pre></details>
            </div>
        </template>
    </section>

    <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-semibold text-gray-900">Default collection targets</h2>
        <form @submit.prevent="save()" class="mt-4 grid gap-4 md:grid-cols-3">
            <label class="text-sm text-gray-700">Default profile username<input x-model="form.default_profile_username" class="mt-1 w-full rounded-lg border-gray-300"></label>
            <label class="text-sm text-gray-700">Default story username<input x-model="form.default_story_username" class="mt-1 w-full rounded-lg border-gray-300"></label>
            <label class="text-sm text-gray-700">Default post URL<input type="url" x-model="form.default_post_url" class="mt-1 w-full rounded-lg border-gray-300"></label>
            <div class="md:col-span-3"><button :disabled="saving" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white" x-text="saving ? 'Saving…' : 'Save settings'"></button></div>
        </form>
    </section>

    <details class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <summary class="cursor-pointer font-semibold text-gray-900">Optional Meta oEmbed token</summary>
        <div class="mt-4">
            <x-hexa-credential-field slug="instagram" key-name="meta_access_token" label="Instagram / Meta Access Token" :test-url="route('settings.instagram.test-meta-token')" help="Optional Meta oEmbed token. Public scrape fallback remains available without it." />
        </div>
    </details>

    <p x-show="notice" x-text="notice" class="rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm"></p>
</div>
@endsection

@push('scripts')
<script>
function instagramSettingsPage() {
    return {
        testProfile: @js($status['active_profile'] ?? ''),
        testing: false,
        saving: false,
        testResult: null,
        notice: '',
        form: @js([
            'default_profile_username' => $settings['default_profile_username'],
            'default_story_username' => $settings['default_story_username'],
            'default_post_url' => $settings['default_post_url'],
        ]),
        csrf: @js(csrf_token()),
        async request(url, body) {
            const response = await fetch(url, {method: 'POST', headers: {'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':this.csrf}, body: JSON.stringify(body)});
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Request failed.');
            return data;
        },
        consoleUrl() { return @js(route('browser-console.sessions')) + '?profile=' + encodeURIComponent(this.testProfile); },
        async runTest() {
            this.testing = true;
            try { this.testResult = await this.request(@js(route('settings.instagram.test')), {profile: this.testProfile}); }
            catch (error) { this.notice = error.message; }
            finally { this.testing = false; }
        },
        async save() {
            this.saving = true;
            try { const data = await this.request(@js(route('settings.instagram.update')), this.form); this.notice = data.message; }
            catch (error) { this.notice = error.message; }
            finally { this.saving = false; }
        },
    };
}
</script>
@endpush
