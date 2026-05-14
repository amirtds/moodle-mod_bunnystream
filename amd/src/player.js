// GPLv3 — see LICENSE.
//
// Student-side player wiring: listens to Bunny postMessage timeupdate/end
// events and reports max-watched-percent back to Moodle so completion +
// gradebook can be computed.
//
// Bunny iframe postMessage payload shape (player_v3):
//   { event: 'play' | 'pause' | 'timeupdate' | 'ended', currentTime, duration }
//
// We throttle reporting to once per 10s of wall clock and once at video end.

export const init = (config) => {
    const player = document.querySelector('[data-bunny-player][data-instance="' + config.instance + '"]');
    if (!player) return;
    const iframe = player.querySelector('iframe');
    if (!iframe) return;

    let lastReportedPercent = 0;
    let lastReportedAt = 0;
    const REPORT_INTERVAL_MS = 10000;

    const reportPercent = (percent) => {
        const pctInt = Math.min(100, Math.max(0, Math.round(percent)));
        if (pctInt <= lastReportedPercent) return;
        const now = Date.now();
        if (now - lastReportedAt < REPORT_INTERVAL_MS && pctInt < 100) return;
        lastReportedPercent = pctInt;
        lastReportedAt = now;
        fetch(M.cfg.wwwroot + '/mod/bunnystream/ajax/progress.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
            body: JSON.stringify({
                instance: config.instance,
                cmid: config.cmid,
                percent: pctInt,
                sesskey: config.sesskey,
            }),
        }).catch(() => { /* ignore — opportunistic */ });
    };

    window.addEventListener('message', (event) => {
        // Bunny iframe is on iframe.mediadelivery.net.
        if (!event.origin.endsWith('mediadelivery.net')) return;
        const data = event.data || {};
        const ct = Number(data.currentTime);
        const dur = Number(data.duration);
        if (data.event === 'timeupdate' && dur > 0 && ct >= 0) {
            reportPercent((ct / dur) * 100);
        } else if (data.event === 'ended') {
            reportPercent(100);
        }
    });
};
