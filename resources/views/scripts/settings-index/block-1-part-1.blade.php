
function instagramSettingsPage() {
    return {
        accounts: @json($status['accounts']),
        form: {
            default_profile_username: @json($settings['default_profile_username']),
            default_story_username: @json($settings['default_story_username']),
            default_post_url: @json($settings['default_post_url']),
        },
        saving: false,
        testing: false,
        sessionAction: '',
        verificationCode: '',
        testProfile: @json($status['active_profile']),
        testResult: null,
        testLog: [],
        toast: { show: false, message: '', type: 'success' },
        toastTimer: null,

        init() {
            this.log('info', 'Instagram settings page loaded. Pick a saved account to verify that exact attached session, then sample followed posts and stories.');
        },

        csrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.content || '';
        },

        now() {
            return new Date().toLocaleTimeString();
        },

        log(type, message, detail = null) {
            this.testLog.push({ type, message, detail, time: this.now() });
        },

        showToast(message, type = 'success') {
            if (this.toastTimer) clearTimeout(this.toastTimer);
            this.toast = { show: true, message, type };
            this.toastTimer = setTimeout(() => { this.toast.show = false; }, 3500);
        },

        pretty(payload) {
            try { return JSON.stringify(payload, null, 2); } catch (error) { return String(payload); }
        },

        async save() {
            this.saving = true;
            try {
                const response = await fetch('{{ route('settings.instagram.update') }}', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken(),
                    },
                    body: JSON.stringify(this.form),
                });

                const data = await response.json();
                if (response.ok && data.success) {
                    this.log('success', 'Saved Instagram settings.', this.form);
                    this.showToast(data.message || 'Instagram settings saved.');
                } else {
                    this.log('error', data.message || 'Failed to save Instagram settings.', data.errors || null);
                    this.showToast(data.message || 'Failed to save Instagram settings.', 'error');
                }
            } catch (error) {
                this.log('error', 'Failed to save Instagram settings.', error?.message || String(error));
                this.showToast('Failed to save Instagram settings.', 'error');
            } finally {
                this.saving = false;
            }
        },

        async request(url, options = {}) {
            const init = { ...options };
            const headers = { "Accept": "application/json", ...(init.headers || {}) };
            const method = String(init.method || "GET").toUpperCase();
            if (method !== "GET") headers["X-CSRF-TOKEN"] = this.csrfToken();
            if (init.body && !headers["Content-Type"]) headers["Content-Type"] = "application/json";
            init.headers = headers;

            const response = await fetch(url, init);
            const raw = await response.text();
            let data;
            try { data = raw ? JSON.parse(raw) : {}; } catch (error) { data = { success: false, message: "Request did not return JSON.", detail: raw.slice(0, 1000) }; }
            return { response, data };
        },

        sessionBusy() {
            return this.sessionAction !== '' || this.testing;
        },

        statusActionLabel() {
            return this.sessionAction === 'status' ? 'Checking status...' : 'Check status';
        },

        reconnectActionLabel() {
            return this.sessionAction === 'reconnect' ? 'Reconnecting...' : 'Reconnect with saved credentials';
        },

        submitCodeActionLabel() {
            return this.sessionAction === 'submitCode' ? 'Submitting code...' : 'Submit verification code';
        },

        clearSessionResult() {
            this.testResult = null;
            this.verificationCode = '';
        },

        sessionStatusData() {
            return this.testResult?.data?.instagram_status?.data || {};
        },

        isSessionConnected() {
            return Boolean(this.sessionStatusData().connected);
        },

        needsVerificationCode() {
            return Boolean(this.sessionStatusData().verification_required);
        },

        verificationLabel() {
            const channel = this.sessionStatusData().verification_channel || 'code';
            if (channel === 'email') return 'Instagram email code';
            if (channel === 'whatsapp') return 'Instagram WhatsApp code';
            if (channel === 'sms') return 'Instagram SMS code';
            return 'Instagram verification code';
        },

        recoveryTitle() {
            const state = this.sessionStatusData();
            if (state.connected) return 'Connected. Run the full content test when ready.';
            if (state.verification_required) return 'Verification code required.';
            if (state.challenge) return 'Instagram challenge/checkpoint blocking this session.';
            if (state.login_form) return 'Browser is at the Instagram login screen.';
            if (this.testResult) return 'Reconnect this account from here.';
            return 'Start by checking status.';
        },

        recoveryDetail() {
            const state = this.sessionStatusData();
            if (state.connected) return 'The attached browser session is authenticated. Use the full content test for the deeper following/posts/stories sample.';
            if (state.verification_required) return 'Enter the code Instagram sent below, then submit it here.';
            if (state.challenge) return 'Finish the challenge in the attached browser/Instagram, then check status again.';
            if (state.login_form) return 'Reconnect with saved credentials submits the saved username and password into this profile.';
            return 'Check status first to see whether this profile is still authenticated.';
        },

        primaryHeaderLabel() {
            const state = this.sessionStatusData();
            if (state.connected) return 'CONNECTED';
            if (state.verification_required) return 'CODE NEEDED';
            if (state.challenge) return 'CHALLENGE';
            if (state.login_form) return 'NEEDS LOGIN';
            if (this.testResult) return 'NOT READY';
            return 'NEXT STEP';
        },

        primaryHeaderTextClass() {
            const state = this.sessionStatusData();
            if (state.connected) return 'text-emerald-700';
            if (state.verification_required) return 'text-amber-700';
            if (state.challenge) return 'text-amber-700';
            if (state.login_form) return 'text-rose-700';
            return 'text-gray-500';
        },

        primaryStripClass() {
            const state = this.sessionStatusData();
            if (state.connected) return 'border-emerald-200 bg-emerald-50';
            if (state.verification_required) return 'border-amber-200 bg-amber-50';
            if (state.challenge) return 'border-amber-200 bg-amber-50';
            if (state.login_form) return 'border-rose-200 bg-rose-50';
            return 'border-gray-200 bg-gray-50';
        },

        primaryButtonClass() {
            const state = this.sessionStatusData();
            if (state.connected) return 'bg-blue-600 hover:bg-blue-700';
            if (state.verification_required) return 'bg-amber-600 hover:bg-amber-700';
            if (state.challenge) return 'bg-gray-600 hover:bg-gray-700';
            if (state.login_form) return 'bg-emerald-600 hover:bg-emerald-700';
            return 'bg-gray-700 hover:bg-gray-800';
        },

        primaryLabel() {
            const state = this.sessionStatusData();
            if (this.sessionBusy() || this.testing) {
                if (this.testing) return 'Running full test...';
                if (this.sessionAction === 'status') return 'Checking status...';
                if (this.sessionAction === 'reconnect') return 'Reconnecting...';
                if (this.sessionAction === 'submitCode') return 'Submitting code...';
                return 'Working...';
            }
            if (state.connected) return 'Run full content test';
            if (state.verification_required) return 'Submit verification code';
            if (state.challenge) return 'Recheck status';
            if (state.login_form) return 'Reconnect with saved credentials';
            return 'Check status';
        },

        primaryDisabled() {
            if (!this.testProfile) return true;
            if (this.sessionBusy() || this.testing) return true;
            const state = this.sessionStatusData();
            if (state.verification_required) {
                return !(this.verificationCode || '').trim();
            }
            return false;
        },

        primaryAction() {
            const state = this.sessionStatusData();
            if (state.connected) return this.runTest();
            if (state.verification_required) return this.submitVerificationCode();
            if (state.challenge) return this.checkStatus();
            if (state.login_form) return this.reconnect();
            return this.checkStatus();
        },

        wrapSessionPayload(data) {
            const account = this.selectedAccount();
            return {
                success: Boolean(data?.success && data?.data?.connected),
                message: data?.message || 'Instagram session action completed.',
                detail: data?.detail || '',
                status_code: data?.status_code || 0,
                data: {
                    instagram_status: data,
                    selected_account: account,
                },
            };
        },

        async sessionRequest(action, label, url, options = {}) {
            if (!this.testProfile) {
                this.showToast('Select a saved Instagram account first.', 'error');
                return;
            }
            this.sessionAction = action;
            this.log('info', label + ' for ' + this.testProfile + '.');
            try {
                const { response, data } = await this.request(url, options);
                this.testResult = this.wrapSessionPayload(data);
                this.log(response.ok && data.success ? 'success' : 'error', label + ' finished for ' + this.testProfile + '.', {
                    message: data.message || '',
                    detail: data.detail || '',
                });
                this.showToast(data.message || (response.ok ? label + ' finished.' : label + ' failed.'), response.ok && data.success ? 'success' : 'error');
            } catch (error) {
                this.testResult = { success: false, message: label + ' failed.', detail: error?.message || String(error), data: { selected_account: this.selectedAccount(), instagram_status: { data: {} } } };
                this.log('error', label + ' request failed for ' + this.testProfile + '.', error?.message || String(error));
                this.showToast(label + ' failed.', 'error');
            } finally {
                this.sessionAction = '';
            }
        },

        async checkStatus() {
            const url = new URL('{{ route("instagram.accounts.status") }}', window.location.origin);
            url.searchParams.set('profile', this.testProfile || '');
            await this.sessionRequest('status', 'Instagram status check', url.toString());
        },

        async reconnect() {
            await this.sessionRequest('reconnect', 'Instagram reconnect', '{{ route("instagram.accounts.login") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ profile: this.testProfile }),
            });
        },

        async submitVerificationCode() {
            await this.sessionRequest('submitCode', 'Instagram verification code submit', '{{ route("instagram.accounts.submit-code") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ profile: this.testProfile, code: this.verificationCode || '' }),
            });
        },

        async runTest() {
            if (!this.testProfile) {
                this.showToast('Select a saved Instagram account first.', 'error');
                return;
            }

            this.testing = true;
            this.log('info', 'Running Instagram connection test for ' + this.testProfile + '.');

            try {
                const response = await fetch('{{ route('settings.instagram.test') }}', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken(),
                    },
                    body: JSON.stringify({ profile: this.testProfile }),
                });

                const data = await response.json();
                this.testResult = data;

                if (response.ok && data.success) {
                    this.log('success', 'Instagram connection test passed for ' + this.testProfile + '.', { message: data.message || '', detail: data.detail || '' });
                    this.showToast(data.message || 'Instagram connection is healthy.');
                } else {
                    this.log('error', 'Instagram connection test failed for ' + this.testProfile + '.', { message: data.message || '', detail: data.detail || '' });
                    this.showToast(data.message || 'Instagram connection test failed.', 'error');
                }
            } catch (error) {
                this.testResult = { success: false, message: 'Instagram connection test failed.', detail: error?.message || String(error) };
                this.log('error', 'Instagram connection test request failed for ' + this.testProfile + '.', error?.message || String(error));
                this.showToast('Instagram connection test failed.', 'error');
            } finally {
                this.testing = false;
            }
        },

        selectedAccount() {
            return this.accounts.find((account) => account.profile === this.testProfile) || null;
        },

        testSummary() {
            if (!this.testResult) return 'No connection test run yet.';
            return this.testResult.message || 'Instagram connection test completed.';
        },

        testSummaryClass() {
            if (!this.testResult) return 'text-slate-700';
            return this.testResult.success ? 'text-emerald-700' : 'text-amber-700';
        },

        testBadge() {
            if (!this.testResult) return 'Idle';
            const status = this.testResult?.data?.instagram_status?.data || {};
            if (status.connected) return 'Connected';
            if (status.challenge) return 'Challenge';
            if (status.login_form) return 'Needs login';
            return this.testResult.success ? 'Healthy' : 'Not ready';
        },

        testBadgeClass() {
            if (!this.testResult) return 'bg-slate-100 text-slate-700';
            const status = this.testResult?.data?.instagram_status?.data || {};
            if (status.connected) return 'bg-emerald-100 text-emerald-700';
            if (status.challenge) return 'bg-amber-100 text-amber-700';
            if (status.login_form) return 'bg-rose-100 text-rose-700';
            return this.testResult.success ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-700';
        },

        testDetail() {
            if (!this.testResult) return 'Select a saved account and run the live test.';
            return this.testResult.detail || 'No additional detail returned.';
        },

        testCurrentUrl() {
            const worker = this.testResult?.data?.instagram_status?.data?.worker || {};
            const probe = this.testResult?.data?.instagram_status?.data?.probe || {};
            return worker.current_url || worker.final?.final_url || probe.url || 'No URL captured.';
        },

        testCurrentUrlHref() {
            const worker = this.testResult?.data?.instagram_status?.data?.worker || {};
            const probe = this.testResult?.data?.instagram_status?.data?.probe || {};
            const url = worker.current_url || worker.final?.final_url || probe.url || '';
            if (typeof url !== 'string') return '';
            if (!/^https?:\/\//i.test(url)) return '';
            return url;
        },

        testWorkerState() {
            const worker = this.testResult?.data?.instagram_status?.data?.worker || {};
            const probe = this.testResult?.data?.instagram_status?.data?.probe || {};
            return [worker.last_event, worker.last_error, probe.title].filter(Boolean).join(' · ') || 'No worker state captured.';
        },

        testAccountLabel() {
            const account = this.selectedAccount();
            if (!account) return 'No saved account selected';
            return account.label + (account.instagram_username ? ' · ' + account.instagram_username : '');
        },

        followingSample() { return this.testResult?.data?.following_sample || null; },
        followingUsernames() { return this.followingSample()?.usernames || []; },
        activeStoryCandidates() { return this.testResult?.data?.active_story_candidates?.usernames || []; },
        randomFollowingPost() { return this.testResult?.data?.random_following_post || null; },
        randomFollowingStory() { return this.testResult?.data?.random_following_story || null; },
        checkedUsernames(sample) { return Array.isArray(sample?.checked_usernames) ? sample.checked_usernames : []; },
        randomStoryMedia() {
            const sample = this.randomFollowingStory();
            const media = [];
            const images = Array.isArray(sample?.image_urls) ? sample.image_urls : [];
            const videos = Array.isArray(sample?.video_urls) ? sample.video_urls : [];
            images.forEach((url) => media.push({ type: 'Image', url }));
            videos.forEach((url) => media.push({ type: 'Video', url }));
            return media;
        },
        storySourceLabel() {
            const source = this.randomFollowingStory()?.source || '';
            if (source === 'following_fallback') return 'Following fallback';
            if (source === 'active_story_candidates') return 'Home story tray';
            return 'No story source yet';
        },
    };
}
