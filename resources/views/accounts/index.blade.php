@extends('layouts.app')

@section('title', 'Instagram Accounts')
@section('header', 'Instagram Accounts')

@section('content')
@php
    $consoleData = data_get($browserConsole, "data", []);
    $consoleOnline = !empty($consoleData["web_online"]) && !empty($consoleData["vnc_online"]);
    $consoleBadgeClass = $consoleOnline ? "bg-emerald-100 text-emerald-800" : "bg-rose-100 text-rose-800";
    $consoleBadgeLabel = $consoleOnline ? "Console online" : "Console offline";
    $consoleUrl = $consoleData["url"] ?? route("browser-console.index");
    $consolePassword = $consoleData["password"] ?? "";
@endphp
<style>
    #ig-page > * + * { margin-top:1.25rem; }
    #ig-page button, #ig-page input, #ig-page select, #ig-page textarea, #ig-page a.ig-btn, #ig-page summary { border-radius:0.5rem; }
    #ig-page .ig-card { border:1px solid #e6e9ef; background:#fff; border-radius:14px; }
    #ig-page .ig-pad { padding:20px; }
    #ig-page .ig-grid { display:grid; grid-template-columns:1fr; gap:1rem; }
    @media (min-width:768px){ #ig-page .ig-grid { grid-template-columns:1fr 1fr; } }
    #ig-page .ig-actions { display:flex; flex-wrap:wrap; gap:0.5rem; }
    #ig-page .ig-btn { display:inline-flex; align-items:center; justify-content:center; gap:0.375rem; padding:0.5rem 1rem; font-size:0.875rem; font-weight:600; border-radius:0.5rem; border:1px solid transparent; cursor:pointer; text-decoration:none; }
    #ig-page .ig-btn:disabled { opacity:0.5; cursor:not-allowed; }
    #ig-page .ig-btn-primary { background:#2563eb; color:#fff; }
    #ig-page .ig-btn-primary:hover:not(:disabled) { background:#1d4ed8; }
    #ig-page .ig-btn-secondary { background:#fff; color:#374151; border:1px solid #d1d5db; }
    #ig-page .ig-btn-secondary:hover:not(:disabled) { background:#f9fafb; }
    #ig-page .ig-btn-danger { background:#fff; color:#dc2626; border:1px solid #fecaca; }
    #ig-page .ig-btn-danger:hover:not(:disabled) { background:#fef2f2; }
    #ig-page .ig-sectlabel { font-size:11px; font-weight:800; letter-spacing:.1em; text-transform:uppercase; color:#94a3b8; }
    #ig-page .ig-meaning { display:inline-flex; align-items:center; gap:8px; border-radius:10px; padding:8px 12px; font-size:13px; font-weight:600; }
    #ig-page .ig-spine { display:grid; grid-template-columns:1fr; gap:0.6rem; }
    @media (min-width:768px){ #ig-page .ig-spine { grid-template-columns:repeat(3,1fr); } }
    #ig-page .ig-step { border:1px solid #e6e9ef; border-radius:10px; padding:12px; background:#fff; }
    #ig-page .ig-step-n { width:24px; height:24px; border-radius:999px; font-size:12px; font-weight:800; display:flex; align-items:center; justify-content:center; }
    #ig-page .ig-step-lbl { font-size:13px; font-weight:700; color:#0f172a; }
    #ig-page .ig-step-d { font-size:11.5px; line-height:1.5; color:#64748b; margin-top:6px; }
    #ig-page .ig-hero { border:1px solid #fcd34d; background:#fffbeb; border-radius:12px; padding:16px; }
    #ig-page .ig-vnc-steps { display:flex; flex-wrap:wrap; gap:0.5rem; }
    #ig-page .ig-vnc-step { flex:1 1 160px; border:1px solid #a5f3fc; background:#fff; border-radius:8px; padding:8px 11px; font-size:12.5px; color:#0e7490; }
    #ig-page details.ig-drawer { border:1px solid #e6e9ef; border-radius:12px; background:#fff; }
    #ig-page details.ig-drawer > summary { cursor:pointer; padding:13px 16px; font-size:13px; font-weight:700; color:#475569; }
    #ig-page .ig-mono { font-family:ui-monospace,Menlo,monospace; }
</style>
<div id="ig-page" x-data="instagramAccountsPage()" class="max-w-5xl">

    <div class="ig-card ig-pad">
        <div class="flex items-start justify-between gap-3 flex-wrap">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Instagram accounts</h1>
                <p class="mt-1 max-w-2xl text-sm text-gray-500">Prove the <strong>server worker browser</strong> is logged into Instagram so the scanner can run. Being logged in in your own browser does <strong>not</strong> count &mdash; only the worker profile does.</p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wide {{ $consoleBadgeClass }}">{{ $consoleBadgeLabel }}</span>
                @if(Route::has("jpn-miami.settings"))
                <a href="{{ route("jpn-miami.settings") }}" target="_blank" rel="noopener" class="ig-btn ig-btn-secondary">JPN settings &#8599;</a>
                @endif
            </div>
        </div>
    </div>

    <div class="ig-card ig-pad" style="border-color:#a5f3fc;background:#ecfeff" x-data="{ showVnc:false, vncCopied:false }">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <div class="ig-sectlabel" style="color:#0e7490">Live server browser &mdash; recovery console</div>
                <h2 class="mt-1 text-lg font-bold text-gray-900">Solve CAPTCHA, 2FA, or login challenges here</h2>
                <p class="mt-1 text-sm text-gray-700">This opens the <strong>actual server browser profile</strong>. Solving a challenge in your normal browser does not update the worker session.</p>
            </div>
            <span class="inline-flex rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wide {{ $consoleBadgeClass }}">{{ $consoleBadgeLabel }}</span>
        </div>
        <div class="ig-vnc-steps mt-4">
            <div class="ig-vnc-step"><strong>1.</strong> Open the live console.</div>
            <div class="ig-vnc-step"><strong>2.</strong> Enter the VNC password if prompted.</div>
            <div class="ig-vnc-step"><strong>3.</strong> Complete the Instagram challenge in that window.</div>
            <div class="ig-vnc-step"><strong>4.</strong> Return here and click Check status.</div>
        </div>
        <div class="mt-4 flex flex-wrap items-center gap-3">
            <a href="{{ $consoleUrl }}" target="_blank" rel="noopener" class="ig-btn ig-btn-primary">Open live server browser &#8599;</a>
            <a href="{{ route("browser-console.index") }}" target="_blank" rel="noopener" class="ig-btn ig-btn-secondary">Open full console &#8599;</a>
            @if($consolePassword !== "")
                <span class="inline-flex items-center gap-2 rounded-lg border border-cyan-200 bg-white px-3 py-2 text-sm">
                    <span class="text-gray-500">VNC password:</span>
                    <code class="ig-mono text-cyan-950" x-text="showVnc ? @js($consolePassword) : '••••••••'"></code>
                    <button type="button" class="ig-btn ig-btn-secondary" style="padding:3px 9px" @click="showVnc=!showVnc" x-text="showVnc ? 'Hide' : 'Reveal'"></button>
                    <button type="button" class="ig-btn ig-btn-secondary" style="padding:3px 9px" @click="navigator.clipboard.writeText(@js($consolePassword)); vncCopied=true; setTimeout(()=>vncCopied=false,1500)" x-text="vncCopied ? 'Copied ✓' : 'Copy'"></button>
                </span>
            @endif
        </div>
    </div>

    <details class="ig-card ig-pad">
        <summary class="cursor-pointer text-sm font-semibold text-gray-900">+ Add an Instagram account</summary>
        <form @submit.prevent="addAccount()" class="ig-grid mt-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Account label</label>
                <input type="text" x-model="newAccount.label" @input="syncProfileFromLabel()" class="w-full border border-gray-300 px-3 py-2 text-sm" placeholder="JPN Miami">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Browser profile</label>
                <input type="text" x-model="newAccount.profile" @input="profileTouched = true" class="w-full border border-gray-300 px-3 py-2 text-sm ig-mono" placeholder="jpn-miami">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Instagram username</label>
                <input type="text" x-model="newAccount.instagram_username" class="w-full border border-gray-300 px-3 py-2 text-sm" placeholder="miamijpn">
            </div>
            <div class="flex items-end">
                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" x-model="newAccount.set_active" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                    Set active now
                </label>
            </div>
            <div class="flex items-center justify-end gap-3 md:col-span-2">
                <button type="button" @click="clearForm()" class="ig-btn ig-btn-secondary">Clear</button>
                <button type="submit" :disabled="saving" class="ig-btn ig-btn-primary"><span x-text="saving ? 'Saving...' : 'Save account'"></span></button>
            </div>
        </form>
    </details>

    @if(empty($status['accounts']))
        <div class="ig-card border-dashed p-10 text-center text-sm text-gray-500">No Instagram account profiles saved yet. Use &ldquo;Add an Instagram account&rdquo; above.</div>
    @else
        @foreach($status['accounts'] as $account)
            @php
                $profile = (string) ($account['profile'] ?? '');
                $username = (string) ($account['instagram_username'] ?? '');
                $passwordKey = 'account_password_' . $profile;
            @endphp
            <div class="ig-card overflow-hidden">
                <div class="flex items-start justify-between gap-3 border-b border-gray-100 px-5 py-4 flex-wrap">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h2 class="text-lg font-bold text-gray-900">{{ $account["label"] }}</h2>
                            <span x-show="activeProfile === @js($profile)" x-cloak class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">Active</span>
                        </div>
                        <div class="mt-0.5 text-xs text-gray-400 ig-mono">{{ $username ? "@".ltrim($username, "@") : "no username" }} &middot; profile {{ $profile }}</div>
                    </div>
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wide" :class="recoveryBadgeClass(@js($profile))" x-text="recoveryBadge(@js($profile))"></span>
                </div>

                <div class="px-5 py-5" style="display:flex;flex-direction:column;gap:1.15rem">

                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <div class="flex items-start justify-between gap-3 flex-wrap">
                            <div>
                                <div class="ig-sectlabel">Worker assignment report</div>
                                <h3 class="mt-1 text-base font-bold text-gray-900" x-text="runtimeHeadline(@js($profile))"></h3>
                                <p class="mt-1 text-xs text-gray-500" x-text="runtimeIdentityLine(@js($profile))"></p>
                            </div>
                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wide" :class="runtimeProxyBadgeClass(@js($profile))" x-text="runtimeProxyBadge(@js($profile))"></span>
                        </div>
                        <div class="mt-4 grid gap-3 md:grid-cols-4">
                            <div class="rounded-lg border border-white bg-white px-3 py-3">
                                <div class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Browser ID</div>
                                <div class="mt-1 text-sm font-semibold text-slate-950 break-all" x-text="runtimeBrowserValue(@js($profile))"></div>
                            </div>
                            <div class="rounded-lg border border-white bg-white px-3 py-3">
                                <div class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Proxy profile</div>
                                <div class="mt-1 text-sm font-semibold text-slate-950 break-all" x-text="runtimeProxyValue(@js($profile))"></div>
                                <div class="mt-1 text-[11px] text-slate-500 break-all" x-text="runtimeProxyServer(@js($profile))"></div>
                            </div>
                            <div class="rounded-lg border border-white bg-white px-3 py-3">
                                <div class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Server IP</div>
                                <div class="mt-1 text-sm font-semibold text-slate-950" x-text="runtimeServerIp(@js($profile))"></div>
                            </div>
                            <div class="rounded-lg border border-white bg-white px-3 py-3">
                                <div class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Proxy IP</div>
                                <div class="mt-1 text-sm font-semibold" :class="runtimeProxyIpClass(@js($profile))" x-text="runtimeProxyIp(@js($profile))"></div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <div class="text-lg font-semibold text-gray-900" x-text="recoveryTitle(@js($profile))"></div>
                        <div class="mt-2">
                            <template x-if="!proofLoaded(@js($profile))"><span class="ig-meaning" style="background:#f1f5f9;color:#475569">Not checked yet &mdash; click Check status to see if this account is connected.</span></template>
                            <template x-if="proofLoaded(@js($profile)) && stateFor(@js($profile)).connected"><span class="ig-meaning" style="background:#dcfce7;color:#166534">&#10003; Connected &mdash; the scanner can use this account.</span></template>
                            <template x-if="proofLoaded(@js($profile)) && !stateFor(@js($profile)).connected && stateFor(@js($profile)).verification_required"><span class="ig-meaning" style="background:#fef3c7;color:#92400e">Needs code &mdash; enter the code Instagram sent (below).</span></template>
                            <template x-if="proofLoaded(@js($profile)) && !stateFor(@js($profile)).connected && !stateFor(@js($profile)).verification_required && stateFor(@js($profile)).captcha_required"><span class="ig-meaning" style="background:#fee2e2;color:#991b1b">Blocked &mdash; solve the CAPTCHA/challenge in the server browser.</span></template>
                            <template x-if="proofLoaded(@js($profile)) && !stateFor(@js($profile)).connected && !stateFor(@js($profile)).verification_required && !stateFor(@js($profile)).captcha_required"><span class="ig-meaning" style="background:#e0e7ff;color:#3730a3">Needs login &mdash; run the saved login below.</span></template>
                        </div>
                        <p class="mt-2 text-sm leading-6 text-gray-600" x-text="recoverySummary(@js($profile))"></p>
                        <p class="mt-2 text-sm text-gray-900"><span class="font-semibold">Next:</span> <span class="text-gray-600" x-text="recoveryNextAction(@js($profile))"></span></p>
                    </div>

                    <div>
                        <div class="ig-sectlabel mb-2">Connection steps</div>
                        <div class="ig-spine">
                            <template x-for="step in recoverySteps(@js($profile))" :key="step.number">
                                <div class="ig-step" :class="recoveryStepClass(step.status)">
                                    <div class="flex items-center gap-2">
                                        <span class="ig-step-n" :class="recoveryStepNumberClass(step.status)" x-text="step.number"></span>
                                        <div class="ig-step-lbl" x-text="step.label"></div>
                                    </div>
                                    <div class="ig-step-d" x-text="step.detail"></div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <template x-if="needsVerificationCode(@js($profile))">
                        <div class="ig-hero">
                            <div class="text-sm font-bold text-amber-900" x-text="verificationHeading(@js($profile))"></div>
                            <p class="mt-1 text-sm text-amber-800">Enter the code Instagram sent. It must finish inside this worker profile.</p>
                            <div class="mt-3 flex items-end gap-3 flex-wrap">
                                <div class="min-w-[14rem] flex-1">
                                    <label class="block text-sm font-medium text-amber-900 mb-1">Verification code</label>
                                    <input type="text" inputmode="numeric" x-model="verificationCodes[@js($profile)]" class="w-full border border-amber-300 px-3 py-2 text-sm bg-white" placeholder="Code from Instagram">
                                </div>
                                <button type="button" @click="submitCode(@js($profile))" :disabled="actionFor(@js($profile)) === &quot;submitCode&quot; || !(verificationCodes[@js($profile)] || &quot;&quot;).trim()" class="ig-btn ig-btn-primary"><span x-text="actionFor(@js($profile)) === &quot;submitCode&quot; ? &quot;Submitting...&quot; : &quot;Submit code&quot;"></span></button>
                            </div>
                        </div>
                    </template>

                    <div>
                        <div class="ig-sectlabel mb-2">Actions</div>
                        <div class="ig-actions">
                            <button type="button" @click="loadStatus(@js($profile))" :disabled="busy(@js($profile))" class="ig-btn ig-btn-secondary"><span x-text="statusButtonLabel(@js($profile))"></span></button>
                            <button type="button" @click="login(@js($profile))" :disabled="busy(@js($profile)) || {{ !empty($account["password_configured"]) ? "false" : "true" }}" class="ig-btn ig-btn-primary"><span x-text="loginButtonLabel(@js($profile))"></span></button>
                            <button type="button" @click="logout(@js($profile))" :disabled="busy(@js($profile))" class="ig-btn ig-btn-secondary"><span x-text="logoutButtonLabel(@js($profile))"></span></button>
                        </div>
                        @if(empty($account["password_configured"]))
                            <p class="mt-2 text-xs text-amber-700">Set the Instagram password below before you can run the saved login.</p>
                        @endif
                    </div>

                    <div x-show="proofLoaded(@js($profile)) && !stateFor(@js($profile)).connected" x-cloak class="rounded-lg border border-slate-300 bg-slate-50 p-4" style="display:flex;flex-direction:column;gap:0.75rem">
                        <div>
                            <div class="ig-sectlabel" style="color:#475569">Recover this blocked session</div>
                            <p class="mt-1 text-sm text-gray-700">Instagram needs a human. Recover it one of two ways &mdash; both act on the <strong>server</strong> worker, not your browser:</p>
                        </div>
                        <div class="rounded-lg border border-cyan-200 bg-cyan-50 px-4 py-3">
                            <div class="text-sm font-semibold text-cyan-900">Option A &mdash; Open the live server browser (VNC)</div>
                            <p class="mt-1 text-xs text-cyan-900">Best for visual CAPTCHA / 2FA. Use the cyan console box at the top of this page: open it, paste the VNC password, solve the challenge, then click Check status.</p>
                            <a href="{{ $consoleUrl }}" target="_blank" rel="noopener" class="ig-btn ig-btn-primary mt-2">Open live server browser &#8599;</a>
                        </div>
                        <div class="rounded-lg border border-slate-300 bg-white px-4 py-3">
                            <div class="flex items-start justify-between gap-3 flex-wrap">
                                <div>
                                    <div class="text-sm font-semibold text-gray-900">Option B &mdash; Drive the worker here</div>
                                    <p class="mt-1 text-xs text-gray-600">Loads the worker browser as a screenshot. Click inside it to send real clicks to the server session.</p>
                                </div>
                                <div class="ig-actions">
                                    <button type="button" @click="loadWorkerScreen(@js($profile))" :disabled="busy(@js($profile))" class="ig-btn ig-btn-primary"><span x-text="workerScreenButtonLabel(@js($profile))"></span></button>
                                    <button type="button" @click="reloadWorkerScreen(@js($profile))" :disabled="busy(@js($profile))" class="ig-btn ig-btn-secondary"><span x-text="workerReloadButtonLabel(@js($profile))"></span></button>
                                </div>
                            </div>
                            <template x-if="workerScreenFor(@js($profile)).url">
                                <div class="mt-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2">
                                    <div class="text-[11px] uppercase tracking-wide text-blue-700 font-semibold">Current internal worker URL</div>
                                    <div class="mt-1 text-xs ig-mono text-blue-700 break-all" x-text="workerScreenFor(@js($profile)).url"></div>
                                </div>
                            </template>
                            <template x-if="workerScreenFor(@js($profile)).image">
                                <div class="mt-2 rounded-lg border border-gray-200 bg-white p-2">
                                    <img :src="workerScreenFor(@js($profile)).image" @click="clickWorkerScreen(@js($profile), $event)" alt="Server worker browser screen" class="w-full max-h-[640px] object-contain cursor-crosshair rounded border border-gray-200">
                                    <p class="mt-2 text-xs text-gray-500">Clicking this image sends the click to the server worker browser at the matching coordinates, then refreshes the screenshot.</p>
                                </div>
                            </template>
                            <template x-if="workerScreenFor(@js($profile)).error">
                                <div class="mt-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700" x-text="workerScreenFor(@js($profile)).error"></div>
                            </template>
                        </div>
                    </div>

                    <div>
                        <div class="ig-sectlabel mb-2">Instagram password</div>
                        <x-hexa-credential-field
                            slug="instagram"
                            :key-name="$passwordKey"
                            label="Instagram password"
                            help="Stored in Hexa Core credentials for this worker profile. Required before Run saved login." />
                    </div>

                    <details class="ig-drawer">
                        <summary>Diagnostics &amp; account management</summary>
                        <div class="border-t border-gray-100 p-4" style="display:flex;flex-direction:column;gap:1rem">
                            <template x-if="hasCapturedUrl(@js($profile))">
                                <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                                    <div class="text-xs uppercase tracking-wide text-gray-500 font-semibold">Captured worker URL</div>
                                    <div class="mt-1 flex items-center gap-2 flex-wrap">
                                        <a :href="proofUrl(@js($profile))" target="_blank" rel="noopener" class="ig-mono text-xs text-blue-700 underline break-all flex-1 min-w-[14rem]" x-text="proofUrl(@js($profile))"></a>
                                        <button type="button" @click="copyCapturedUrl(@js($profile))" class="ig-btn ig-btn-secondary" style="padding:3px 9px">Copy</button>
                                    </div>
                                    <p class="mt-1 text-[11px] text-gray-500">Diagnostic only &mdash; opening it does not authenticate the worker.</p>
                                </div>
                            </template>
                            <div class="grid gap-3 md:grid-cols-2">
                                <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                                    <div class="text-xs uppercase tracking-wide text-gray-500">Saved credential</div>
                                    <div class="mt-1 text-sm font-semibold" :class="credentialTone(@js($profile))" x-text="credentialLabel(@js($profile))"></div>
                                    <div class="mt-1 text-xs ig-mono text-gray-400 break-all">instagram.{{ $passwordKey }}</div>
                                </div>
                                <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                                    <div class="text-xs uppercase tracking-wide text-gray-500">Second code</div>
                                    <div class="mt-1 text-sm font-semibold" :class="secondCodeTone(@js($profile))" x-text="secondCodeLabel(@js($profile))"></div>
                                    <div class="mt-1 text-xs text-gray-500" x-text="secondCodeDetail(@js($profile))"></div>
                                </div>
                                <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 md:col-span-2">
                                    <div class="text-xs uppercase tracking-wide text-gray-500">Worker state</div>
                                    <div class="mt-1 text-sm text-gray-900" x-text="proofState(@js($profile))"></div>
                                    <p class="mt-1 text-xs text-gray-400" x-text="proofSecondary(@js($profile))"></p>
                                </div>
                            </div>
                            <div class="ig-actions">
                                <button type="button" @click="setActive(@js($profile))" :disabled="busy(@js($profile)) || activeProfile === @js($profile)" class="ig-btn ig-btn-secondary"><span x-text="activateButtonLabel(@js($profile))"></span></button>
                                <button type="button" @click="copyDebug(@js($profile))" class="ig-btn ig-btn-secondary">Copy debug payload</button>
                                <button type="button" @click="removeAccount(@js($profile), @js($account["label"]))" :disabled="busy(@js($profile))" class="ig-btn ig-btn-danger"><span x-text="removeButtonLabel(@js($profile))"></span></button>
                            </div>
                            <details x-show="payloadFor(@js($profile))" x-cloak class="rounded-lg border border-gray-200 bg-white">
                                <summary class="cursor-pointer px-4 py-3 text-sm font-medium text-gray-700">Show raw worker payload</summary>
                                <div class="border-t border-gray-100 px-4 py-3">
                                    <pre class="text-xs ig-mono whitespace-pre-wrap break-words text-gray-700" x-text="pretty(payloadFor(@js($profile)))"></pre>
                                </div>
                            </details>
                        </div>
                    </details>
                </div>
            </div>
        @endforeach
    @endif

    <x-hexa-log-viewer
        title="Instagram Account Activity"
        log-var="accountLog"
        slug="instagram-accounts"
        theme="dark"
        :persist="false" />

    <template x-if="toast.show">
        <div class="fixed bottom-6 right-6 z-50 max-w-sm w-full rounded-xl border px-5 py-4 shadow-lg" :class="toast.type === 'success' ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800'">
            <div class="text-sm font-medium" x-text="toast.message"></div>
        </div>
    </template>
</div>

@push('scripts')
<script>@include('instagram::scripts.accounts-index.block-1-part-1')</script>
@endpush
@endsection
