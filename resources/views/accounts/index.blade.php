@extends('layouts.app')

@section('title', 'Instagram Accounts')
@section('header', 'Instagram Accounts')

@section('content')
<div class="space-y-5" x-data="instagramAccountsPage(@js($status))">
    <section class="rounded-xl border border-blue-200 bg-blue-50 p-5">
        <h2 class="font-semibold text-blue-950">Browser Console owns Instagram login and network routing</h2>
        <p class="mt-1 text-sm text-blue-800">Each saved row only binds an Instagram username to one Browser Worker profile. Open that exact profile to sign in or change its direct/proxy route, then return here and check status.</p>
    </section>

    <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-semibold text-gray-900">Add account binding</h2>
        <form class="mt-4 grid gap-3 md:grid-cols-4" @submit.prevent="save()">
            <input x-model="form.label" required maxlength="255" class="rounded-lg border-gray-300" placeholder="Label">
            <input x-model="form.profile" required maxlength="80" pattern="[A-Za-z0-9._-]+" class="rounded-lg border-gray-300" placeholder="Browser profile">
            <input x-model="form.instagram_username" required maxlength="255" class="rounded-lg border-gray-300" placeholder="Instagram username">
            <button :disabled="saving" class="rounded-lg bg-blue-600 px-4 py-2 font-medium text-white disabled:opacity-50" x-text="saving ? 'Saving…' : 'Save binding'"></button>
        </form>
    </section>

    <section class="space-y-3">
        @forelse($status['accounts'] as $account)
            @php($profile = (string) $account['profile'])
            <article class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-lg font-semibold text-gray-900">{{ $account['label'] }}</h2>
                            @if($status['active_profile'] === $profile)
                                <span class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-700">Default</span>
                            @endif
                        </div>
                        <p class="mt-1 text-sm text-gray-600"><span class="font-mono">{{ $profile }}</span> · &#64;{{ $account['instagram_username'] }}</p>
                        <template x-if="states[@js($profile)]">
                            <div class="mt-3 text-sm">
                                <p class="font-medium" :class="states[@js($profile)].success ? 'text-emerald-700' : 'text-amber-700'" x-text="states[@js($profile)].message"></p>
                                <p class="mt-1 text-gray-600" x-text="states[@js($profile)].detail"></p>
                                <p class="mt-1 text-xs text-gray-500" x-text="statusFacts(@js($profile))"></p>
                            </div>
                        </template>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ $account['console_url'] }}" class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white">Open Browser Console</a>
                        <button type="button" @click="check(@js($profile))" :disabled="busy === @js($profile)" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 disabled:opacity-50">Check status</button>
                        <button type="button" @click="makeDefault(@js($profile))" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700">Set default</button>
                        <button type="button" @click="remove(@js($profile), @js($account['label']))" class="rounded-lg border border-red-200 px-3 py-2 text-sm font-medium text-red-700">Remove binding</button>
                    </div>
                </div>
            </article>
        @empty
            <div class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-gray-500">No Instagram account bindings are saved.</div>
        @endforelse
    </section>

    <p x-show="notice" x-text="notice" class="rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700"></p>
</div>
@endsection

@push('scripts')
<script>
function instagramAccountsPage(initial) {
    return {
        states: {},
        busy: '',
        saving: false,
        notice: '',
        form: {label: '', profile: '', instagram_username: '', set_active: false},
        csrf: @js(csrf_token()),
        async request(url, options = {}) {
            const response = await fetch(url, {
                headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf, ...(options.headers || {})},
                ...options,
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Request failed.');
            return data;
        },
        async check(profile) {
            this.busy = profile;
            try {
                const url = new URL(@js(route('instagram.accounts.status')), window.location.origin);
                url.searchParams.set('profile', profile);
                this.states[profile] = await this.request(url);
            } catch (error) { this.notice = error.message; }
            finally { this.busy = ''; }
        },
        statusFacts(profile) {
            const data = this.states[profile]?.data || {};
            return `Route: ${data.transport || 'unknown'} · Account evidence: ${data.account_verified ? 'verified' : 'not verified'} · Runtime: ${data.runtime_ready ? 'ready' : 'not ready'}`;
        },
        async save() {
            this.saving = true;
            try {
                const data = await this.request(@js(route('instagram.accounts.store')), {
                    method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(this.form),
                });
                this.notice = data.message;
                window.location.reload();
            } catch (error) { this.notice = error.message; }
            finally { this.saving = false; }
        },
        async makeDefault(profile) {
            try {
                const data = await this.request(@js(route('instagram.accounts.activate')), {
                    method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({profile}),
                });
                this.notice = data.message;
                window.location.reload();
            } catch (error) { this.notice = error.message; }
        },
        async remove(profile, label) {
            if (!confirm(`Remove the binding “${label}”? The Browser Console profile and proxy settings will be preserved.`)) return;
            try {
                const data = await this.request(@js(route('instagram.accounts.destroy')), {
                    method: 'DELETE', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({profile}),
                });
                this.notice = data.message;
                window.location.reload();
            } catch (error) { this.notice = error.message; }
        },
    };
}
</script>
@endpush
