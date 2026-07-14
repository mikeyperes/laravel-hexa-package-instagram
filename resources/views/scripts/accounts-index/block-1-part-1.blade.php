
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
