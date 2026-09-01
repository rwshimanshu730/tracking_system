import './bootstrap';

const body = document.body;

if (body?.dataset.autoRefresh === 'true') {
    const refreshInterval = Number(body.dataset.autoRefreshInterval || 15000);
    const baseRefreshUrl = body.dataset.autoRefreshUrl || window.location.pathname;
    let refreshTimer = null;

    const buildRefreshUrl = () => {
        const refreshUrl = new URL(baseRefreshUrl, window.location.origin);
        const currentUrl = new URL(window.location.href);

        currentUrl.searchParams.forEach((value, key) => {
            if (key !== '_refresh') {
                refreshUrl.searchParams.set(key, value);
            }
        });

        refreshUrl.searchParams.set('_refresh', Date.now().toString());

        return refreshUrl.toString();
    };

    const scheduleRefresh = () => {
        window.clearTimeout(refreshTimer);

        refreshTimer = window.setTimeout(() => {
            if (document.hidden) {
                scheduleRefresh();
                return;
            }

            window.location.replace(buildRefreshUrl());
        }, refreshInterval);
    };

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            scheduleRefresh();
        }
    });

    scheduleRefresh();
}
