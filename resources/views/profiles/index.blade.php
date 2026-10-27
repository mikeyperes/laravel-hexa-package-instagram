@extends('layouts.app')

@section('title', 'Instagram Profiles')
@section('header', 'Instagram Profiles')

@section('content')
<div class="igp" x-data="{tab: 'locations', query: '', adding: false, refreshing: false}">
    <div class="igp-heading">
        <div><span class="igp-eyebrow">INSTAGRAM / PROFILES</span><h1>Your city workspaces</h1><p>Websites, accounts and the people and places behind them.</p></div>
        <button type="button" class="igp-button igp-button-primary" @click="adding = !adding" :aria-expanded="adding">Add profile</button>
    </div>

    @if(session('status'))<div class="igp-notice" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())
        <div class="igp-notice igp-warning" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
    @endif

    <section class="igp-panel" x-show="adding || @js($errors->has('slug') || $errors->has('site') || $errors->has('source_key'))" x-cloak>
        <h2>Add a website profile</h2>
        <p class="igp-muted">Choose a saved website. Its Instagram account and desktop come from that website's record.</p>
        <form action="{{ route('instagram.profiles.store') }}" method="POST" class="igp-form">
            @csrf
            <label>Profile name<input name="slug" value="{{ old('slug') }}" required maxlength="80" pattern="[a-z0-9][a-z0-9-]*" placeholder="jpn-city"></label>
            <label>Website<select name="site" required><option value="">Choose a website</option>@foreach($sites as $site)<option value="{{ $site['source'].':'.$site['key'] }}" @selected(old('site') === $site['source'].':'.$site['key'])>{{ $site['name'] }} · {{ $site['site_url'] }}</option>@endforeach</select></label>
            <label class="igp-wide">Notes<textarea name="notes" maxlength="4000" rows="2" placeholder="Useful context for this profile">{{ old('notes') }}</textarea></label>
            <div><button class="igp-button igp-button-primary" type="submit">Save profile</button></div>
        </form>
    </section>

    <nav class="igp-cards" aria-label="Website profiles">
        @forelse($profiles as $item)
            <a class="igp-card {{ $profile && $profile['id'] === $item['id'] ? 'igp-selected' : '' }}" href="{{ route('instagram.profiles.index', ['id' => $item['id']]) }}" @if($profile && $profile['id'] === $item['id']) aria-current="page" @endif>
                <div class="igp-card-top"><span class="igp-avatar">{{ mb_substr(str_replace('JPN ', '', $item['name']), 0, 1) }}</span><span class="igp-pill {{ $item['paused'] ? 'igp-pill-amber' : '' }}">{{ $item['paused'] ? 'Automation paused' : 'Website profile' }}</span></div>
                <h2>{{ $item['name'] }}</h2><p class="igp-mono">{{ $item['slug'] }}</p><p class="igp-muted">{{ parse_url($item['site_url'] ?? '', PHP_URL_HOST) ?: 'Website unavailable' }}</p>
                <div class="igp-card-stats"><span><strong>{{ $item['synced_at'] ? count($item['locations']) : '–' }}</strong> locations</span><span><strong>{{ $item['synced_at'] ? count($item['hosts']) : '–' }}</strong> hosts</span></div>
            </a>
        @empty
            <section class="igp-panel igp-wide"><h2>No profiles yet</h2><p class="igp-muted">Add a website profile to bring its account, locations and hosts together.</p></section>
        @endforelse
    </nav>

    @if($profile)
        <section class="igp-panel">
            <div class="igp-section-heading">
                <div><span class="igp-eyebrow">PROFILE DETAILS</span><h2>{{ $profile['name'] }}</h2><p class="igp-muted">{{ $profile['region'] }}</p></div>
                @if($profile['site_url'])<a class="igp-button" href="{{ $profile['site_url'] }}" target="_blank" rel="noopener noreferrer">Open website ↗</a>@endif
            </div>
            <dl class="igp-details">
                <div><dt>Instagram account</dt><dd>@if($profile['instagram_username'])<a href="https://www.instagram.com/{{ rawurlencode($profile['instagram_username']) }}/" target="_blank" rel="noopener noreferrer">&#64;{{ $profile['instagram_username'] }}</a>@else Not assigned @endif</dd></div>
                <div><dt>Browser desktop / RDP</dt><dd class="igp-mono">{{ $profile['browser_profile'] ?: 'Not assigned' }}</dd></div>
                <div><dt>Time zone</dt><dd>{{ $profile['timezone'] }}</dd></div>
                <div><dt>Website</dt><dd>@if($profile['site_url'])<a href="{{ $profile['site_url'] }}" target="_blank" rel="noopener noreferrer">{{ parse_url($profile['site_url'], PHP_URL_HOST) }}</a>@else Unavailable @endif</dd></div>
            </dl>
            <div class="igp-desktop">
                <div><strong>Desktop connection</strong><p class="igp-muted">Manage sign-in and the network provider in this profile's desktop.</p>
                    <p class="igp-small">Last recorded login: {{ $profile['login_state'] === 'verified' ? 'verified' : 'not verified' }}. {{ $profile['account_bound'] ? 'Account binding saved.' : 'Account binding needs attention.' }}</p>
                </div>
                @if($profile['console_url'])<a class="igp-button" href="{{ $profile['console_url'] }}">Open desktop ↗</a>@else<span class="igp-pill igp-pill-amber">Desktop not registered</span>@endif
            </div>
            <div class="igp-desktop" style="margin-top:14px">
                <div><strong>Instagram scanner list</strong>
                    @if($profile['instagram_list'])
                        <p class="igp-muted">{{ $profile['instagram_list']['name'] }} · {{ $profile['instagram_list']['accounts_count'] }} saved accounts</p>
                    @else
                        <p class="igp-muted">No Instagram list is assigned to this website.</p>
                    @endif
                </div>
                <div class="igp-host-links">
                    @if($profile['instagram_list'])<a class="igp-button" href="{{ $profile['instagram_list']['url'] }}">Open this list ↗</a>@endif
                    @if($profile['scanner_url'])<a class="igp-button" href="{{ $profile['scanner_url'] }}">Open scanner ↗</a>@endif
                </div>
            </div>
            @if($profile['paused'])<div class="igp-notice igp-warning">Automation is paused for this desktop. Website refreshes remain available.</div>@endif
            @if(!$profile['source_available'])<div class="igp-notice igp-warning">The linked website record is unavailable. Restore it before refreshing this profile.</div>@endif
            <details class="igp-notes"><summary>Profile notes and name</summary>
                <form action="{{ route('instagram.profiles.store') }}" method="POST" class="igp-form">
                    @csrf
                    <input type="hidden" name="site" value="{{ $profile['source'].':'.$profile['source_key'] }}">
                    <label>Profile name<input name="slug" value="{{ $profile['slug'] }}" required maxlength="80" pattern="[a-z0-9][a-z0-9-]*"></label>
                    <label class="igp-wide">Notes<textarea name="notes" maxlength="4000" rows="3">{{ $profile['notes'] }}</textarea></label>
                    <div><button type="submit" class="igp-button">Save changes</button></div>
                </form>
            </details>
        </section>

        <section class="igp-panel">
            <div class="igp-section-heading">
                <div><span class="igp-eyebrow">FROM THE WEBSITE</span><h2>Locations and hosts</h2><p class="igp-muted">{{ $profile['synced_at_display'] ? 'Last successful refresh: '.$profile['synced_at_display'] : 'No website catalog has been loaded yet.' }}</p></div>
                <form action="{{ route('instagram.profiles.refresh', ['profile' => $profile['id']]) }}" method="POST" @submit="refreshing = true">
                    @csrf
                    <button class="igp-button igp-button-primary" type="submit" :disabled="refreshing" @disabled(!$profile['source_available']) x-text="refreshing ? 'Refreshing…' : 'Refresh from website'">Refresh from website</button>
                </form>
            </div>
            @if($profile['last_error'])<div class="igp-notice igp-warning"><strong>Latest refresh failed.</strong> The previous successful list is still shown. {{ $profile['last_error'] }}</div>@endif

            <div class="igp-toolbar">
                <div class="igp-tabs" role="tablist" aria-label="Website catalog">
                    <button type="button" id="igp-locations-tab" role="tab" aria-controls="igp-locations" :aria-selected="tab === 'locations'" :class="tab === 'locations' ? 'igp-tab-active' : ''" @click="tab = 'locations'; query = ''">Locations <span>{{ count($profile['locations']) }}</span></button>
                    <button type="button" id="igp-hosts-tab" role="tab" aria-controls="igp-hosts" :aria-selected="tab === 'hosts'" :class="tab === 'hosts' ? 'igp-tab-active' : ''" @click="tab = 'hosts'; query = ''">Hosts <span>{{ count($profile['hosts']) }}</span></button>
                </div>
                <label class="igp-search"><span class="igp-sr-only">Search locations or hosts</span><input type="search" x-model="query" placeholder="Search this list…"></label>
            </div>

            <div id="igp-locations" role="tabpanel" aria-labelledby="igp-locations-tab" x-show="tab === 'locations'">
                <div class="igp-list">
                    @forelse($profile['locations'] as $location)
                        <article class="igp-location" data-search="{{ mb_strtolower($location['name'].' '.$location['slug']) }}" x-show="$el.dataset.search.includes(query.toLowerCase().trim())">
                            <span class="igp-location-icon" aria-hidden="true">⌖</span><div><strong>{{ $location['name'] }}</strong><p class="igp-muted igp-mono">{{ $location['slug'] }}</p></div>
                        </article>
                    @empty
                        <div class="igp-empty"><h3>{{ $profile['synced_at'] ? 'No locations on this website yet' : 'Load this website’s locations' }}</h3><p>{{ $profile['synced_at'] ? 'Locations added to the website will appear after the next refresh.' : 'Use Refresh from website to load the current list.' }}</p></div>
                    @endforelse
                </div>
                @if(count($profile['locations']))<p x-show="query.trim() && !@js(array_map(fn($r) => mb_strtolower($r['name'].' '.$r['slug']), $profile['locations'])).some(text => text.includes(query.toLowerCase().trim()))" x-cloak class="igp-empty">No locations match your search.</p>@endif
            </div>

            <div id="igp-hosts" role="tabpanel" aria-labelledby="igp-hosts-tab" x-show="tab === 'hosts'" x-cloak>
                <div class="igp-host-list">
                    @forelse($profile['hosts'] as $host)
                        <article class="igp-host" data-search="{{ mb_strtolower($host['name'].' '.$host['instagram_username'].' '.$host['location']) }}" x-show="$el.dataset.search.includes(query.toLowerCase().trim())">
                            <div><h3>{{ $host['name'] }}</h3>@if($host['location'])<p class="igp-muted">{{ $host['location'] }}</p>@endif
                                @if($host['instagram_url'])<a class="igp-small" href="{{ $host['instagram_url'] }}" target="_blank" rel="noopener noreferrer">&#64;{{ $host['instagram_username'] }}</a>@endif
                            </div>
                            <div class="igp-host-links">
                                @if($host['url'])<a href="{{ $host['url'] }}" target="_blank" rel="noopener noreferrer">Host page ↗</a>@endif
                                @if($host['website'])<a href="{{ $host['website'] }}" target="_blank" rel="noopener noreferrer">Website ↗</a>@endif
                            </div>
                        </article>
                    @empty
                        <div class="igp-empty"><h3>{{ $profile['synced_at'] ? 'No hosts on this website yet' : 'Load this website’s hosts' }}</h3><p>{{ $profile['synced_at'] ? 'Hosts added to the website will appear after the next refresh.' : 'Use Refresh from website to load the current list.' }}</p></div>
                    @endforelse
                </div>
                @if(count($profile['hosts']))<p x-show="query.trim() && !@js(array_map(fn($r) => mb_strtolower($r['name'].' '.$r['instagram_username'].' '.$r['location']), $profile['hosts'])).some(text => text.includes(query.toLowerCase().trim()))" x-cloak class="igp-empty">No hosts match your search.</p>@endif
            </div>
        </section>
    @endif
</div>
@endsection

@push('styles')
<style>
.igp{max-width:1240px;margin:0 auto;color:#172033;--igp-accent:#176e61}
.igp [x-cloak]{display:none!important}.igp h1{font-size:28px;line-height:1.25;font-weight:700;letter-spacing:-.7px;margin:8px 0}.igp h2{font-size:19px;font-weight:650;line-height:1.4}.igp h3{font-size:15px;font-weight:600}.igp p{line-height:1.6}.igp a{color:var(--igp-accent)}.igp a:hover{text-decoration:underline}
.igp-heading,.igp-section-heading{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:22px}.igp-heading p,.igp-muted{color:#687386;font-size:13px}.igp-eyebrow{font-size:10px;letter-spacing:1.7px;font-weight:700;color:#788394}.igp-mono{font-family:ui-monospace,SFMono-Regular,monospace;font-size:12px}.igp-small{font-size:12px;color:#687386;margin-top:6px}
.igp-button{display:inline-flex;align-items:center;justify-content:center;white-space:nowrap;gap:8px;min-height:40px;padding:9px 15px;border:1px solid #dce2e8;border-radius:9px;background:#fff;color:#354155!important;font-size:13px;font-weight:600;cursor:pointer;transition:background .15s}.igp-button:hover{background:#f4f7f8;text-decoration:none!important}.igp-button-primary{background:var(--igp-accent);border-color:var(--igp-accent);color:#fff!important}.igp-button-primary:hover{background:#125c51}.igp-button:disabled{opacity:.55;cursor:wait}.igp button:focus-visible,.igp a:focus-visible{outline:3px solid #9bd2c8;outline-offset:3px}
.igp-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:18px;margin-bottom:24px}.igp-card{display:block;border:1px solid #e0e5eb;border-radius:13px;background:#fff;padding:20px;color:#172033!important;box-shadow:0 2px 4px #17203303;transition:border-color .15s,box-shadow .15s}.igp-card:hover{text-decoration:none!important;border-color:#89b9b0}.igp-card.igp-selected{border:2px solid var(--igp-accent);padding:19px;box-shadow:0 0 0 3px #176e6108}.igp-card-top{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:15px}.igp-avatar{display:grid;place-items:center;width:39px;height:39px;background:#edf6f3;border-radius:11px;color:var(--igp-accent);font-weight:700;font-size:19px}.igp-pill{display:inline-flex;align-items:center;font-size:10px;font-weight:600;padding:4px 8px;border-radius:20px;background:#f0f4f6;color:#697484}.igp-pill-amber{background:#fff2d8;color:#8b641d}.igp-card h2{margin-bottom:3px}.igp-card .igp-mono{color:#697484;margin-bottom:7px}.igp-card-stats{border-top:1px solid #eef1f4;margin-top:16px;padding-top:13px;display:flex;gap:20px;font-size:12px;color:#788394}.igp-card-stats strong{color:#354155;font-size:16px;margin-right:3px}
.igp-panel{border:1px solid #e0e5eb;border-radius:13px;padding:24px;background:#fff;margin-bottom:24px;box-shadow:0 2px 4px #17203303}.igp-details{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:22px;margin:24px 0}.igp-details dt{color:#788394;font-size:11px;margin-bottom:7px}.igp-details dd{font-size:13px;font-weight:550;overflow-wrap:anywhere}.igp-desktop{display:flex;justify-content:space-between;align-items:center;gap:20px;padding:17px 19px;background:#f6f8fa;border:1px solid #eaf0f2;border-radius:9px}.igp-desktop strong{font-size:13px}.igp-notes{border-top:1px solid #edf0f4;margin-top:20px;padding-top:16px}.igp-notes summary{font-size:12px;color:#697484;cursor:pointer}
.igp-notice{padding:12px 16px;margin-bottom:20px;border-radius:9px;background:#edf7f1;border:1px solid #cce8d6;color:#246443;font-size:13px}.igp-warning{background:#fff9ec;border-color:#f3dfae;color:#856224}.igp-desktop+.igp-notice{margin-top:16px;margin-bottom:0}
.igp-toolbar{display:flex;justify-content:space-between;align-items:center;gap:16px;padding:0 0 14px;border-bottom:1px solid #e9eef2}.igp-tabs{display:flex;gap:5px}.igp-tabs button{font-size:13px;font-weight:600;padding:8px 12px;border-radius:8px;color:#788394}.igp-tabs button.igp-tab-active{background:#edf6f3;color:var(--igp-accent)}.igp-tabs span{margin-left:4px;font-size:11px}.igp-search input{width:230px;font-size:12px;border:1px solid #dce2e8;border-radius:8px;min-height:36px;padding:7px 12px}
.igp-list{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:0 24px;max-height:520px;overflow:auto}.igp-location{display:flex;align-items:center;gap:12px;padding:15px 0;border-bottom:1px solid #f0f3f5}.igp-location strong{font-size:13px;font-weight:550}.igp-location-icon{font-size:20px;color:#8aa59f;width:20px;text-align:center}.igp-host-list{max-height:600px;overflow:auto}.igp-host{display:flex;justify-content:space-between;align-items:center;gap:15px;padding:17px 0;border-bottom:1px solid #edf1f4}.igp-host-links{display:flex;gap:16px;flex-wrap:wrap;font-size:12px}.igp-empty{grid-column:1/-1;padding:35px 16px;text-align:center;color:#788394;font-size:13px}.igp-empty h3{color:#354155;margin-bottom:8px}
.igp-form{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:18px}.igp-form label{font-size:12px;font-weight:600;display:flex;flex-direction:column;gap:7px;color:#697484}.igp-form input,.igp-form select,.igp-form textarea{font-size:13px;border:1px solid #dce2e8;border-radius:8px;padding:9px 12px;color:#354155;max-width:100%;background:#fff}.igp-wide{grid-column:1/-1}.igp-sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
@media(max-width:900px){.igp-details{grid-template-columns:repeat(2,minmax(0,1fr))}.igp-list{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:600px){.igp-heading,.igp-section-heading,.igp-toolbar,.igp-desktop{align-items:flex-start;flex-direction:column;gap:14px}.igp h1{font-size:24px}.igp-panel{padding:18px}.igp-list,.igp-form{grid-template-columns:1fr}.igp-host{align-items:flex-start;flex-direction:column}.igp-search,.igp-search input{width:100%}.igp-details{gap:18px 12px}}
</style>
@endpush
