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
<script>
function instagramAccountsPage() {
    return {
        accounts: @json($status['accounts']),
        activeProfile: @json($status['active_profile']),
        runtimeReports: @json($runtimeReports ?? []),
        newAccount: { label: '', profile: '', instagram_username: '', set_active: true },
        profileTouched: false,
        saving: false,
        actionMap: {},
        accountStates: {},
        payloads: {},
        workerScreens: {},
        verificationCodes: {},
        accountLog: [],
        toast: { show: false, message: '', type: 'success' },
        toastTimer: null,

        init() {
            this.log("info", "Instagram accounts page loaded. Click Check status when you want to inspect the attached worker profile.");
        },


        runtimeReport(profile) {
            return this.runtimeReports[profile] || {};
        },

        runtimeHeadline(profile) {
            const r = this.runtimeReport(profile);
            return (r?.browser?.account_label || profile) + " -> " + (r.proxy_ok ? "proxy verified" : "proxy needs attention");
        },

        runtimeBrowserValue(profile) {
            const r = this.runtimeReport(profile);
            return r?.browser?.profile || profile || "No browser assigned";
        },

        runtimeProxyValue(profile) {
            const r = this.runtimeReport(profile);
            return r?.proxy?.name || r?.proxy?.profile_key || "No proxy profile selected";
        },

        runtimeProxyServer(profile) {
            const r = this.runtimeReport(profile);
            return r?.proxy?.server || "No proxy server visible";
        },

        runtimeServerIp(profile) {
            return this.runtimeReport(profile)?.direct_ip || "Not checked";
        },

        runtimeProxyIp(profile) {
            const r = this.runtimeReport(profile);
            return r?.proxy_ip || r?.proxy_message || "Not verified";
        },

        runtimeProxyIpClass(profile) {
            return this.runtimeReport(profile)?.proxy_ok ? "text-emerald-700" : "text-amber-700";
        },

        runtimeProxyBadge(profile) {
            return this.runtimeReport(profile)?.proxy_ok ? "Proxy working" : "Proxy not verified";
        },

        runtimeProxyBadgeClass(profile) {
            return this.runtimeReport(profile)?.proxy_ok ? "bg-emerald-100 text-emerald-800" : "bg-amber-100 text-amber-800";
        },

        runtimeIdentityLine(profile) {
            const identity = this.runtimeReport(profile)?.identity || {};
            const location = [identity.country, identity.region, identity.city].filter(Boolean).join(" - ");
            const provider = [identity.org, identity.isp].filter(Boolean).join(" / ");
            return [location, provider].filter(Boolean).join(" | ") || "Proxy identity lookup is not available yet.";
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
            return this.actionFor(profile) === "login" ? "Running saved login..." : "Run saved login";
        },

        activateButtonLabel(profile) {
            return this.actionFor(profile) === 'activate' ? 'Switching…' : 'Use as active';
        },

        logoutButtonLabel(profile) {
            return this.actionFor(profile) === "logout" ? "Logging out worker..." : "Log out worker session";
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
            const workerFinal = worker.final || {};
            let capturedUrl = workerFinal.final_url || worker.current_url || probe.url || "";
            const detailText = String(payload?.detail || "");
            let captchaDetected = Boolean(data.captcha_required || /recaptcha/i.test(capturedUrl) || /recaptcha/i.test(detailText));
            let challengeDetected = Boolean(data.challenge || captchaDetected || /auth_platform|challenge|checkpoint/i.test(capturedUrl));
            const previousState = this.accountStates[profile] || {};
            if (!captchaDetected && previousState.captcha_required && !data.connected && data.login_form) {
                captchaDetected = true;
                challengeDetected = true;
                capturedUrl = previousState.current_url || capturedUrl;
            }
            this.accountStates[profile] = {
                connected: Boolean(data.connected),
                login_form: Boolean(data.login_form),
                verification_required: Boolean(data.verification_required),
                verification_channel: data.verification_channel || '',
                challenge: challengeDetected,
                captcha_required: captchaDetected,
                detail: payload?.detail || '',
                message: payload?.message || '',
                current_url: capturedUrl,
                current_title: worker.last_title || probe.title || '',
                last_event: worker.last_event || '',
                last_error: worker.last_error || '',
                strong_nav_count: probe.strong_nav_count || 0,
                nav_text_count: probe.nav_text_count || 0,
                nav_text_matches: probe.nav_text_matches || [],
                profile_owner_controls: Boolean(probe.profile_owner_controls),
                login_copy_detected: Boolean(probe.login_copy_detected),
                visible_text_inputs: probe.visible_text_inputs || [],
                visible_password_inputs: probe.visible_password_inputs || 0,
                alerts: probe.alerts || [],
                body_excerpt: probe.body_excerpt || "",
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
            if (!this.proofLoaded(profile)) return "NOT CHECKED";
            if (state.connected) return "PASS: CONNECTED";
            if (state.captcha_required) return "X CAPTCHA REQUIRED";
            if (state.verification_required) return "NEEDS CODE";
            if (state.challenge) return "NEEDS CHALLENGE";
            if (state.login_form && this.passwordRejected(profile)) return "X Password alert";
            if (state.login_form) return "FAILED: LOGIN SCREEN";
            if (state.current_url) return "FAILED: NOT CONFIRMED";
            return "CHECK REQUIRED";
        },

        statusTone(profile) {
            const state = this.stateFor(profile);
            if (state.connected) return "text-emerald-700";
            if (state.verification_required) return "text-amber-700";
            if (state.captcha_required || state.challenge) return "text-rose-700";
            if (state.login_form || state.current_url) return "text-rose-700";
            return "text-slate-500";
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
            if (state.captcha_required || state.challenge) return "bg-rose-100 text-rose-700";
            if (state.login_form) return 'bg-rose-100 text-rose-700';
            return 'bg-slate-100 text-slate-700';
        },

        proofUrl(profile) {
            return this.stateFor(profile).current_url || "No URL captured yet.";
        },

        hasCapturedUrl(profile) {
            const url = this.proofUrl(profile);
            return Boolean(url && url !== "No URL captured yet.");
        },

        proofState(profile) {
            const state = this.stateFor(profile);
            return [state.last_event, state.current_title].filter(Boolean).join(' · ') || 'No worker state captured yet.';
        },

        proofSecondary(profile) {
            const state = this.stateFor(profile);
            const details = [];
            details.push('Saved password: ' + (this.accounts.find((account) => account.profile === profile)?.password_configured ? 'configured' : 'missing'));
            details.push('Second code: ' + this.secondCodeLabel(profile));
            if (state.visible_text_inputs?.length) details.push('Visible text inputs: ' + state.visible_text_inputs.join(', '));
            if (state.visible_password_inputs) details.push('Visible password inputs: ' + state.visible_password_inputs);
            if (state.strong_nav_count) details.push('Strong nav markers: ' + state.strong_nav_count);
            if (state.nav_text_count) details.push("Instagram nav text markers: " + state.nav_text_count);
            if (state.profile_owner_controls) details.push("Logged-in profile controls detected");
            if (state.last_error) details.push('Worker error: ' + state.last_error);
            return details.join(' • ');
        },


        recoveryPanelClass(profile) {
            const state = this.stateFor(profile);
            if (state.connected) return "border-emerald-200 bg-emerald-50";
            if (state.verification_required) return "border-amber-200 bg-amber-50";
            if (state.captcha_required || state.challenge) return "border-rose-200 bg-rose-50";
            if (state.login_form || state.current_url) return "border-rose-200 bg-rose-50";
            return "border-slate-200 bg-slate-50";
        },

        recoveryBadge(profile) {
            const state = this.stateFor(profile);
            if (!this.proofLoaded(profile)) return "Not checked";
            if (state.connected) return "Pass";
            if (state.captcha_required) return "X Captcha required";
            if (state.verification_required) return this.verificationShortLabel(profile);
            if (state.challenge) return "X Needs challenge";
            if (state.login_form && this.passwordRejected(profile)) return "X Password alert";
            if (state.login_form) return "X Login required";
            if (state.current_url) return "X Not confirmed";
            return "Needs action";
        },

        recoveryBadgeClass(profile) {
            const state = this.stateFor(profile);
            if (!this.proofLoaded(profile)) return "bg-slate-100 text-slate-700";
            if (state.connected) return "bg-emerald-100 text-emerald-800";
            if (state.verification_required) return "bg-amber-100 text-amber-800";
            if (state.challenge) return "bg-rose-100 text-rose-800";
            if (state.login_form || state.current_url) return "bg-rose-100 text-rose-800";
            return "bg-slate-100 text-slate-700";
        },

        recoveryTitle(profile) {
            const state = this.stateFor(profile);
            if (!this.proofLoaded(profile)) return "Not checked yet.";
            if (state.connected) return "Connected. The scanner can use this account.";
            if (state.captcha_required) return "Meta reCAPTCHA is blocking the worker profile.";
            if (state.verification_required) return "Instagram accepted login and needs a verification code.";
            if (state.challenge) return "Instagram is blocking the worker profile with a challenge.";
            if (state.login_form && this.passwordRejected(profile)) return "Failed: worker status found a login or password alert.";
            if (state.login_form) return "Failed: the worker browser is on the Instagram login screen.";
            if (state.current_url) return "Failed: Instagram opened, but scanner login was not confirmed.";
            return "Check the attached worker profile.";
        },

        recoverySummary(profile) {
            const state = this.stateFor(profile);
            if (!this.proofLoaded(profile)) return "Click Check status. The system has not inspected this server worker profile yet.";
            if (state.connected) return "This server worker profile is authenticated. JPN scanner jobs can use it.";
            if (state.captcha_required) return "The server worker is blocked by Meta reCAPTCHA. Solving it in a normal local browser does not update the worker profile. Complete it in the server worker session, then click Check status.";
            if (state.verification_required) return "Do not switch browsers. Enter the code below so this exact worker profile can finish login.";
            if (state.challenge) return "Instagram is blocking the server worker. A normal local Instagram tab is not proof. Finish the challenge inside the server worker session, then click Check status again.";
            if (state.login_form && this.passwordRejected(profile)) return "A normal local Instagram tab is not proof. If the worker still shows this alert, click Log out worker session, then Run saved login. Only update the stored password if Instagram shows the same password alert after that.";
            if (state.login_form) return "The worker is at Instagram login or password entry. Click Run saved login to use the stored Hexa credential for this worker profile. If Instagram asks for a code, enter it here.";
            if (state.current_url) return "This is not a pass. The worker reached Instagram but did not show authenticated navigation markers. Use the next action below.";
            return "Run Check status to read the live browser state for this profile.";
        },

        recoveryNextAction(profile) {
            const state = this.stateFor(profile);
            if (!this.proofLoaded(profile)) return "Click Check status.";
            if (state.connected) return "Done. Return to JPN settings or run the scanner.";
            if (state.captcha_required) return "Complete the Meta reCAPTCHA inside the server worker session, not a normal local Instagram tab, then click Check status.";
            if (state.verification_required) return "Enter the Instagram verification code below, then click Submit verification code.";
            if (state.challenge) return "Finish the Instagram challenge inside the server worker session, then click Check status.";
            if (state.login_form && this.passwordRejected(profile)) return "Click Log out worker session, then Run saved login. Only use Change password if Instagram still shows the same password alert after that.";
            if (state.login_form) return "Click Run saved login to enter the saved password for this worker profile. If it still fails, copy the debug payload from this page.";
            if (state.current_url) return "This did not pass. Click Run saved login, then click Check status again.";
            return "Click Check status. If it fails, follow the next action shown here.";
        },

        recoverySteps(profile) {
            const account = this.accounts.find((item) => item.profile === profile) || {};
            const state = this.stateFor(profile);
            const proofLoaded = this.proofLoaded(profile);
            const connected = Boolean(state.connected);
            const blocked = Boolean(state.verification_required || state.captcha_required || state.challenge);
            const failed = Boolean(state.login_form || state.current_url);
            return [
                {
                    number: "1",
                    label: "Check worker",
                    status: proofLoaded ? "success" : (this.busy(profile) ? "pending" : "pending"),
                    detail: proofLoaded ? "Live browser proof loaded for " + profile + "." : "Click Check status to inspect the server worker profile."
                },
                {
                    number: "2",
                    label: "Connect worker",
                    status: connected ? "success" : (blocked ? "warning" : (failed ? "error" : "pending")),
                    detail: connected
                        ? "The worker profile is logged into Instagram."
                        : (blocked
                             ? "Instagram needs CAPTCHA, a code, or a challenge completed in this same worker profile."
                            : (failed
                                ? this.recoveryNextAction(profile)
                                : (account.password_configured ? "Saved credentials exist. Run saved login if the status is not connected." : "Save the Instagram password before running saved login.")))
                },
                {
                    number: "3",
                    label: "Scanner ready",
                    status: connected ? "success" : "pending",
                    detail: connected ? "JPN scanner jobs can use this account." : "Pending until Check status returns Pass."
                }
            ];
        },

        recoveryStepClass(status) {
            if (status === "success") return "border-emerald-200";
            if (status === "error") return "border-rose-200";
            if (status === "warning") return "border-amber-200";
            return "border-slate-200";
        },

        recoveryStepNumberClass(status) {
            if (status === "success") return "bg-emerald-100 text-emerald-800";
            if (status === "error") return "bg-rose-100 text-rose-800";
            if (status === "warning") return "bg-amber-100 text-amber-800";
            return "bg-slate-100 text-slate-700";
        },

        async copyCapturedUrl(profile) {
            const url = this.proofUrl(profile);
            try {
                await navigator.clipboard.writeText(url);
                this.log("info", "Copied captured Instagram worker URL for " + profile + ".", url);
                this.showToast("Captured worker URL copied.");
            } catch (error) {
                this.log("error", "Failed to copy captured Instagram worker URL for " + profile + ".", error?.message || String(error));
                this.showToast("Failed to copy captured URL.", "error");
            }
        },


        workerScreenFor(profile) {
            return this.workerScreens[profile] || { image: null, url: '', error: '' };
        },

        workerScreenButtonLabel(profile) {
            return this.actionFor(profile) === 'workerScreen' ? 'Loading screen...' : 'Load worker screen';
        },

        workerReloadButtonLabel(profile) {
            return this.actionFor(profile) === 'workerReload' ? 'Reloading worker...' : 'Reload worker page';
        },

        async loadWorkerScreen(profile) {
            await this.workerScreenRequest(profile, @json(route('instagram.accounts.worker-screen')), {}, 'workerScreen');
        },

        async reloadWorkerScreen(profile) {
            await this.workerScreenRequest(profile, @json(route('instagram.accounts.worker-reload')), {}, 'workerReload');
        },

        async clickWorkerScreen(profile, event) {
            const img = event.currentTarget;
            const rect = img.getBoundingClientRect();
            const scaleX = img.naturalWidth && rect.width ? img.naturalWidth / rect.width : 1;
            const scaleY = img.naturalHeight && rect.height ? img.naturalHeight / rect.height : 1;
            const x = Math.max(0, Math.round((event.clientX - rect.left) * scaleX));
            const y = Math.max(0, Math.round((event.clientY - rect.top) * scaleY));
            await this.workerScreenRequest(profile, @json(route('instagram.accounts.worker-click')), { x, y }, 'workerClick');
        },

        async workerScreenRequest(profile, endpoint, extraPayload = {}, action = 'workerScreen') {
            this.actionMap[profile] = action;
            this.log('info', 'Server worker recovery request started for ' + profile + '.', extraPayload);
            try {
                const { response, data } = await this.request(endpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ profile, ...extraPayload }),
                });
                if (!response.ok || data?.success === false) throw new Error(data?.detail || data?.message || 'Worker recovery request failed.');
                if (data?.status) this.rememberPayload(profile, data.status);
                this.workerScreens[profile] = {
                    image: data?.data?.screenshot_data_url || null,
                    url: data?.data?.current_url || this.proofUrl(profile),
                    error: data?.data?.screenshot_error || '',
                };
                this.log('success', data?.message || 'Server worker screen updated.', { url: this.workerScreens[profile].url });
                this.showToast(data?.message || 'Server worker screen updated.');
            } catch (error) {
                this.workerScreens[profile] = { ...this.workerScreenFor(profile), error: error?.message || String(error) };
                this.log('error', 'Server worker recovery failed for ' + profile + '.', error?.message || String(error));
                this.showToast(error?.message || 'Server worker recovery failed.', 'error');
            } finally {
                delete this.actionMap[profile];
            }
        },
        async copyDebug(profile) {
            const account = this.accounts.find((item) => item.profile === profile) || {};
            const payload = {
                generated_at: new Date().toISOString(),
                profile,
                active_profile: this.activeProfile,
                account,
                state: this.stateFor(profile),
                payload: this.payloadFor(profile),
            };
            const text = JSON.stringify(payload, null, 2);
            try {
                await navigator.clipboard.writeText(text);
                this.log("info", "Copied Instagram debug payload for " + profile + ".");
                this.showToast("Instagram debug payload copied.");
            } catch (error) {
                this.log("error", "Failed to copy Instagram debug payload for " + profile + ".", error?.message || String(error));
                this.showToast("Failed to copy debug payload.", "error");
            }
        },

        credentialConfigured(profile) {
            return Boolean(this.accounts.find((account) => account.profile === profile)?.password_configured);
        },

        credentialLabel(profile) {
            return this.credentialConfigured(profile) ? 'Configured' : 'Missing';
        },

        credentialTone(profile) {
            return this.credentialConfigured(profile) ? 'text-emerald-700' : 'text-rose-700';
        },

        secondCodeLabel(profile) {
            const state = this.stateFor(profile);
            if (state.verification_required) return this.verificationShortLabel(profile);
            if (state.connected) return "Not needed";
            if (state.captcha_required) return "Blocked by CAPTCHA";
            if (state.login_form) return "Not started yet";
            if (state.challenge) return "Blocked by challenge";
            return 'Unknown';
        },

        secondCodeTone(profile) {
            const state = this.stateFor(profile);
            if (state.connected) return 'text-emerald-700';
            if (state.verification_required || state.challenge) return 'text-amber-700';
            if (state.login_form) return 'text-slate-700';
            return 'text-slate-500';
        },

        secondCodeDetail(profile) {
            const state = this.stateFor(profile);
            if (state.connected) return 'Instagram is already authenticated. No extra code is needed right now.';
            if (state.verification_required) return 'Instagram accepted the password and is waiting for the next code step.';
            if (state.login_form) return 'Instagram is still at the login screen, so no second code is active yet.';
            if (state.challenge) return 'Instagram is asking for a checkpoint/challenge before the session can continue.';
            return 'Run Check status to load the live browser state.';
        },

        stateSummary(profile) {
            const state = this.stateFor(profile);
            if (!this.proofLoaded(profile)) return "No check has been run for this worker profile yet.";
            if (state.connected) return "Pass. The attached server browser is usable.";
            if (state.verification_required) return "Needs action. Instagram is waiting for a verification code.";
            if (state.login_form && this.passwordRejected(profile)) return "Failed. Worker proof found a login or password alert. If the worker URL is visibly logged in, click Check status again.";
            if (state.login_form) return "Failed. The worker browser is still on the login screen.";
            if (state.challenge) return "Needs action. Instagram interrupted the worker with a checkpoint.";
            if (state.current_url) return "Failed. Instagram loaded, but authenticated scanner markers were not found.";
            return "Run Check status to inspect the attached worker session.";
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

        passwordRejected(profile) {
            const state = this.stateFor(profile);
            const visibleAlertText = Array.isArray(state.alerts) ? state.alerts.join(" ") : "";
            const visibleBodyText = state.body_excerpt || "";
            return /input password is invalid|password you entered is incorrect|incorrect password/i.test([visibleAlertText, visibleBodyText].join(" "));
        },

        proofNextStep(profile) {
            return "Next step: " + this.recoveryNextAction(profile);
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
