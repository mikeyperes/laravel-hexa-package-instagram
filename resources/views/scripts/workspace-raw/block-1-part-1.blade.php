
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
