/* eslint-disable no-invalid-this */
// GPLv3 — see LICENSE.
//
// Author UX: 5-panel inline TUS upload UI. Direct port of
// bunny-xblock/static/js/author_view.js with Moodle-flavoured networking.
//
//   empty → uploading → processing ──webhook/poll──▶ ready
//                                                └▶ failed

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import {get_string as getString} from 'core/str';
import tus from 'mod_bunnystream/tus';

const TUS_ENDPOINT = 'https://video.bunnycdn.com/tusupload';

export const init = (config) => {
    const root = document.querySelector('.bunnystream-author');
    if (!root) {
        return;
    }

    // --- DOM ---------------------------------------------------------------
    const panels = {
        empty:      root.querySelector('[data-panel="empty"]'),
        uploading:  root.querySelector('[data-panel="uploading"]'),
        processing: root.querySelector('[data-panel="processing"]'),
        ready:      root.querySelector('[data-panel="ready"]'),
        failed:     root.querySelector('[data-panel="failed"]'),
    };
    const fileInput      = root.querySelector('[data-bunny-file]');
    const dropzone       = root.querySelector('[data-bunny-dropzone]');
    const progressBar    = root.querySelector('[data-bunny-progress]');
    const progressPct    = root.querySelector('[data-bunny-pct]');
    const filenameLabel  = root.querySelector('[data-bunny-filename]');
    const errorBox       = root.querySelector('[data-bunny-error]');
    const guidLabel      = root.querySelector('[data-bunny-guid]');
    const durationContainer = root.querySelector('[data-bunny-duration]');
    const durationValue  = root.querySelector('[data-bunny-duration-value]');
    const elapsedContainer = root.querySelector('[data-bunny-elapsed]');
    const elapsedValue   = root.querySelector('[data-bunny-elapsed-value]');
    const thumbnailImg   = root.querySelector('[data-bunny-thumbnail-img]');
    const thumbnailFile  = root.querySelector('[data-bunny-thumbnail-file]');
    const thumbnailStatus = root.querySelector('[data-bunny-thumbnail-status]');
    const titleInput     = root.querySelector('[data-bunny-title]');
    const modal          = root.querySelector('[data-bunny-modal]');

    const captionsList   = root.querySelector('[data-bunny-captions-list]');
    const captionsEmpty  = root.querySelector('[data-bunny-captions-empty]');
    const captionFile    = root.querySelector('[data-bunny-caption-file]');
    const captionStatus  = root.querySelector('[data-bunny-caption-status]');

    const chaptersRows   = root.querySelector('[data-bunny-chapters-rows]');
    const saveChaptersBtn = root.querySelector('[data-bunny-save-chapters]');
    const chapterStatus  = root.querySelector('[data-bunny-chapter-status]');

    // Hidden inputs that ride along on the activity form.
    const form = root.closest('form');
    const hidden = (name) => form && form.elements[name];

    // --- State -------------------------------------------------------------
    const state = {
        guid: config.guid || '',
        libraryId: config.libraryId || '',
        title: config.title || '',
        status: config.status || (config.guid ? 'encoding' : ''),
        durationSec: config.durationSec || 0,
        thumbnailUrl: config.thumbnailUrl || '',
        currentUpload: null,
        pollAbort: null,
    };

    // Sync hidden form fields with state so a "Save" persists video identity.
    const syncHidden = () => {
        if (!form) return;
        if (hidden('guid'))         hidden('guid').value         = state.guid;
        if (hidden('library_id'))   hidden('library_id').value   = state.libraryId;
        if (hidden('title'))        hidden('title').value        = state.title;
        if (hidden('status'))       hidden('status').value       = state.status;
        if (hidden('thumbnail_url')) hidden('thumbnail_url').value = state.thumbnailUrl;
        if (hidden('duration_sec')) hidden('duration_sec').value = state.durationSec;
    };
    syncHidden();

    // --- Panel switching ---------------------------------------------------
    const show = (panel) => {
        Object.keys(panels).forEach((key) => {
            if (!panels[key]) return;
            const match = key === panel;
            panels[key].hidden = !match;
            panels[key].style.display = match ? '' : 'none';
        });
        root.setAttribute('data-state', panel);

        if (panel === 'processing' && state.guid) startPolling();
        if (panel !== 'processing' && state.pollAbort) {
            state.pollAbort.abort();
            state.pollAbort = null;
        }
        if (panel !== 'processing') stopElapsedTimer();
    };

    const showError = (msg) => {
        if (!errorBox) return;
        errorBox.textContent = msg || '';
        errorBox.hidden = !msg;
    };

    // --- Endpoints ---------------------------------------------------------
    const endpoint = (path) => M.cfg.wwwroot + '/mod/bunnystream/ajax/' + path;
    const sesskey = config.sesskey || M.cfg.sesskey;

    const postJson = (path, body) => fetch(endpoint(path), {
        method: 'POST',
        credentials: 'same-origin',
        headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
        body: JSON.stringify({...(body || {}), sesskey: sesskey}),
    });

    const getJson = (path, params, signal) => {
        const url = endpoint(path) + (params ? ('?' + new URLSearchParams(params).toString()) : '');
        return fetch(url, {credentials: 'same-origin', signal});
    };

    const deleteJson = (path, params) => {
        const qs = new URLSearchParams({...(params || {}), sesskey: sesskey}).toString();
        return fetch(endpoint(path) + '?' + qs, {method: 'POST', credentials: 'same-origin', headers: {'X-HTTP-Method-Override': 'DELETE'}});
    };

    // --- Metadata rendering ------------------------------------------------
    const formatDuration = (sec) => {
        if (!sec || sec <= 0) return '';
        const m = Math.floor(sec / 60);
        const s = Math.floor(sec % 60);
        return m + ':' + (s < 10 ? '0' : '') + s;
    };

    const syncReadyMeta = () => {
        if (guidLabel) guidLabel.textContent = state.guid ? state.guid.slice(0, 8) + '…' : '—';
        if (durationContainer && durationValue) {
            const f = formatDuration(state.durationSec);
            if (f) {
                durationValue.textContent = f;
                durationContainer.hidden = false;
            } else {
                durationContainer.hidden = true;
            }
        }
        if (thumbnailImg) {
            if (state.thumbnailUrl) {
                const bust = (state.thumbnailUrl.indexOf('?') === -1 ? '?' : '&') + 'v=' + (state.durationSec || 0);
                thumbnailImg.src = state.thumbnailUrl + bust;
                thumbnailImg.style.display = '';
            } else {
                thumbnailImg.removeAttribute('src');
                thumbnailImg.style.display = 'none';
            }
        }
        if (titleInput && titleInput.value !== state.title) {
            titleInput.value = state.title;
        }
        syncHidden();
    };

    // --- Upload flow -------------------------------------------------------
    const startUpload = (file) => {
        if (!file) return;
        showError('');
        if (filenameLabel) filenameLabel.textContent = file.name || 'video';
        if (progressBar) progressBar.value = 0;
        if (progressPct) progressPct.textContent = '0%';
        show('uploading');

        postJson('upload_token.php', {title: file.name || state.title || 'Untitled video'})
            .then((res) => res.ok ? res.json() : res.json().then((b) => Promise.reject(new Error(b.error || 'Upload token failed'))))
            .then((token) => {
                const upload = new tus.Upload(file, {
                    endpoint: TUS_ENDPOINT,
                    retryDelays: [0, 1000, 3000, 5000, 10000],
                    headers: {
                        AuthorizationSignature: token.signature,
                        AuthorizationExpire: String(token.expires),
                        VideoId: token.guid,
                        LibraryId: token.library_id,
                    },
                    metadata: {filetype: file.type, title: file.name},
                    onError: (err) => {
                        // eslint-disable-next-line no-console
                        console.error('[bunny:author] tus upload error', err);
                        show('empty');
                        showError('Upload failed. Check your connection and try again.');
                    },
                    onProgress: (bytesUploaded, bytesTotal) => {
                        const pct = bytesTotal > 0 ? Math.round((bytesUploaded / bytesTotal) * 100) : 0;
                        if (progressBar) progressBar.value = pct;
                        if (progressPct) progressPct.textContent = pct + '%';
                    },
                    onSuccess: () => finalizeUpload(token.guid, token.library_id),
                });
                upload.start();
                state.currentUpload = upload;
            })
            .catch((err) => {
                // eslint-disable-next-line no-console
                console.error('[bunny:author] upload-token failure', err);
                show('empty');
                showError(err.message || 'Could not start upload.');
            });
    };

    const finalizeUpload = (guid, libraryId) => {
        state.guid = guid;
        state.libraryId = libraryId;
        state.currentUpload = null;
        syncReadyMeta();
        show('processing');

        postJson('finalize.php', {guid})
            .then((res) => res.ok ? res.json() : Promise.reject(new Error('Finalize failed')))
            .then((meta) => {
                state.title = meta.title || state.title;
                state.status = meta.status || 'encoding';
                state.durationSec = meta.duration_sec || 0;
                if (meta.thumbnail_url) state.thumbnailUrl = meta.thumbnail_url;
                syncReadyMeta();
                if (state.status === 'ready') flipToReady();
                else show('processing');
            })
            .catch((err) => {
                // eslint-disable-next-line no-console
                console.error('[bunny:author] finalize chain failed', err);
                showError("Upload finished but couldn't reconcile the metadata.");
                show('empty');
            });
    };

    const cancelUpload = () => {
        if (state.currentUpload) {
            try { state.currentUpload.abort(true); } catch (e) { /* ignore */ }
            state.currentUpload = null;
        }
        show('empty');
    };

    // --- Polling -----------------------------------------------------------
    let elapsedInterval = null;
    let elapsedStartedAt = null;

    const startElapsedTimer = () => {
        if (elapsedInterval) return;
        elapsedStartedAt = Date.now();
        if (elapsedContainer) elapsedContainer.hidden = false;
        if (elapsedValue) elapsedValue.textContent = '0:00';
        elapsedInterval = setInterval(() => {
            if (!elapsedValue) return;
            const sec = Math.floor((Date.now() - elapsedStartedAt) / 1000);
            elapsedValue.textContent = formatDuration(sec) || (sec + 's');
        }, 1000);
    };
    const stopElapsedTimer = () => {
        if (elapsedInterval) { clearInterval(elapsedInterval); elapsedInterval = null; }
        elapsedStartedAt = null;
        if (elapsedContainer) elapsedContainer.hidden = true;
    };

    const startPolling = () => {
        startElapsedTimer();
        if (state.pollAbort) state.pollAbort.abort();
        state.pollAbort = new AbortController();
        const signal = state.pollAbort.signal;
        const INTERVAL = 5000;
        const TIMEOUT = 30 * 60 * 1000;
        const started = Date.now();

        const tick = () => {
            if (signal.aborted) return;
            if (Date.now() - started > TIMEOUT) return;
            getJson('video_get.php', {guid: state.guid}, signal)
                .then((res) => res.ok ? res.json() : Promise.reject(new Error('video_get ' + res.status)))
                .then((meta) => {
                    if (signal.aborted) return;
                    if (typeof meta.duration_sec === 'number' && meta.duration_sec > 0) state.durationSec = meta.duration_sec;
                    if (meta.thumbnail_url) state.thumbnailUrl = meta.thumbnail_url;
                    state.status = meta.status;
                    syncReadyMeta();
                    if (meta.status === 'ready') flipToReady();
                    else if (meta.status === 'failed') show('failed');
                    else setTimeout(tick, INTERVAL);
                })
                .catch((err) => {
                    if (signal.aborted) return;
                    // eslint-disable-next-line no-console
                    console.warn('[bunny:author] poll error', err);
                    setTimeout(tick, INTERVAL);
                });
        };
        tick();
    };

    const flipToReady = () => {
        syncReadyMeta();
        refreshCaptions();
        refreshChapters();
        show('ready');
    };

    // --- Modal -------------------------------------------------------------
    let modalReturnFocus = null;
    const openModal = () => {
        if (!modal) return;
        modalReturnFocus = document.activeElement;
        modal.setAttribute('data-open', 'true');
        const c = modal.querySelector('[data-action="modal-confirm"]');
        if (c) c.focus();
    };
    const closeModal = () => {
        if (!modal) return;
        modal.removeAttribute('data-open');
        if (modalReturnFocus && modalReturnFocus.focus) modalReturnFocus.focus();
    };

    // --- Replace / delete --------------------------------------------------
    const replaceVideo = () => {
        state.guid = '';
        state.libraryId = '';
        state.status = '';
        state.title = '';
        state.durationSec = 0;
        state.thumbnailUrl = '';
        if (titleInput) titleInput.value = '';
        syncReadyMeta();
        show('empty');
    };

    const deleteVideo = () => {
        if (!state.guid) return;
        closeModal();
        const guid = state.guid;
        deleteJson('video_delete.php', {guid})
            .then((res) => {
                if (!res.ok && res.status !== 404) throw new Error('delete ' + res.status);
                state.guid = '';
                state.libraryId = '';
                state.status = '';
                state.title = '';
                state.durationSec = 0;
                state.thumbnailUrl = '';
                if (titleInput) titleInput.value = '';
                syncReadyMeta();
                show('empty');
            })
            .catch((err) => {
                // eslint-disable-next-line no-console
                console.error('[bunny:author] delete failed', err);
                showError("Couldn't delete from Bunny. Check the console for details.");
            });
    };

    // --- Thumbnail ---------------------------------------------------------
    const setThumbnailStatus = (msg, kind) => {
        if (!thumbnailStatus) return;
        if (msg) {
            thumbnailStatus.textContent = msg;
            thumbnailStatus.hidden = false;
            thumbnailStatus.setAttribute('data-state', kind || 'info');
        } else {
            thumbnailStatus.textContent = '';
            thumbnailStatus.hidden = true;
        }
    };

    const uploadThumbnail = (file) => {
        if (!file || !state.guid) return;
        if (!/^image\/(jpeg|png|webp)$/.test(file.type)) {
            setThumbnailStatus('Use a JPG, PNG, or WebP image.', 'error');
            return;
        }
        setThumbnailStatus('Uploading…', 'info');
        const fd = new FormData();
        fd.append('thumbnail', file);
        fd.append('guid', state.guid);
        fd.append('sesskey', sesskey);
        fetch(endpoint('thumbnail_upload.php'), {method: 'POST', credentials: 'same-origin', body: fd})
            .then((res) => res.ok ? res.json() : res.json().then((b) => Promise.reject(new Error(b.error || 'failed'))))
            .then((data) => {
                if (data.thumbnail_url) {
                    state.thumbnailUrl = data.thumbnail_url;
                    if (thumbnailImg) {
                        const bust = (data.thumbnail_url.indexOf('?') === -1 ? '?' : '&') + 't=' + Date.now();
                        thumbnailImg.src = data.thumbnail_url + bust;
                        thumbnailImg.style.display = '';
                    }
                    syncHidden();
                }
                setThumbnailStatus('Updated', 'success');
                setTimeout(() => setThumbnailStatus('', null), 2500);
            })
            .catch((err) => {
                // eslint-disable-next-line no-console
                console.error('[bunny:author] thumbnail upload failed', err);
                setThumbnailStatus(err.message || 'Upload failed', 'error');
            });
    };

    // --- Captions ----------------------------------------------------------
    const setCaptionStatus = (msg, kind) => {
        if (!captionStatus) return;
        if (msg) {
            captionStatus.textContent = msg;
            captionStatus.hidden = false;
            captionStatus.setAttribute('data-state', kind || 'info');
        } else {
            captionStatus.textContent = '';
            captionStatus.hidden = true;
        }
    };

    const renderCaptions = (captions) => {
        if (!captionsList) return;
        captionsList.innerHTML = '';
        if (!captions || captions.length === 0) {
            if (captionsEmpty) captionsEmpty.hidden = false;
            return;
        }
        if (captionsEmpty) captionsEmpty.hidden = true;
        for (const c of captions) {
            const li = document.createElement('li');
            li.className = 'bunnystream-caption-row';
            li.setAttribute('data-srclang', c.srclang || '');
            li.innerHTML =
                '<span class="bunny-xblock__caption-lang"></span>' +
                '<span class="bunny-xblock__caption-label"></span>' +
                '<button type="button" class="btn btn-sm btn-danger" data-action="delete-caption">Remove</button>';
            li.querySelector('.bunny-xblock__caption-lang').textContent = c.srclang || '??';
            li.querySelector('.bunny-xblock__caption-label').textContent = c.label || c.srclang || 'Untitled';
            captionsList.appendChild(li);
        }
    };

    const refreshCaptions = () => {
        if (!state.guid) return;
        getJson('captions.php', {guid: state.guid})
            .then((res) => res.ok ? res.json() : null)
            .then((data) => { if (data && data.captions) renderCaptions(data.captions); })
            .catch(() => { /* ignore */ });
    };

    const uploadCaption = (file) => {
        if (!file || !state.guid) return;
        const srclang = (window.prompt('Language code (e.g. en, pt-BR):', 'en') || '').trim().toLowerCase();
        if (!srclang) return;
        const label = (window.prompt('Display label:', srclang.toUpperCase()) || srclang.toUpperCase()).trim();
        setCaptionStatus('Uploading…', 'info');
        const fd = new FormData();
        fd.append('vtt', file);
        fd.append('srclang', srclang);
        fd.append('label', label);
        fd.append('guid', state.guid);
        fd.append('sesskey', sesskey);
        fetch(endpoint('captions.php'), {method: 'POST', credentials: 'same-origin', body: fd})
            .then((res) => res.ok ? res.json() : res.json().then((b) => Promise.reject(new Error(b.error || 'failed'))))
            .then((data) => {
                if (data && data.captions) renderCaptions(data.captions);
                setCaptionStatus('Added ' + srclang, 'success');
                setTimeout(() => setCaptionStatus('', null), 2500);
            })
            .catch((err) => {
                // eslint-disable-next-line no-console
                console.error('[bunny:author] caption upload failed', err);
                setCaptionStatus(err.message || 'Upload failed', 'error');
            });
    };

    const deleteCaption = (srclang) => {
        if (!state.guid || !srclang) return;
        if (!window.confirm("Remove the '" + srclang + "' subtitle track?")) return;
        setCaptionStatus('Removing…', 'info');
        const qs = new URLSearchParams({guid: state.guid, srclang, sesskey}).toString();
        fetch(endpoint('caption_delete.php') + '?' + qs, {method: 'POST', credentials: 'same-origin'})
            .then((res) => res.ok ? res.json() : res.json().then((b) => Promise.reject(new Error(b.error || 'failed'))))
            .then((data) => {
                if (data && data.captions) renderCaptions(data.captions);
                setCaptionStatus('Removed', 'success');
                setTimeout(() => setCaptionStatus('', null), 2500);
            })
            .catch((err) => {
                // eslint-disable-next-line no-console
                console.error('[bunny:author] caption delete failed', err);
                setCaptionStatus(err.message || 'Remove failed', 'error');
            });
    };

    const transcribeAudio = () => {
        if (!state.guid) return;
        if (!window.confirm('Bunny will transcribe the audio and add a subtitle track. This may take a few minutes — continue?')) return;
        setCaptionStatus('Transcription started — check back in a minute.', 'info');
        postJson('transcribe.php', {guid: state.guid, language: 'en'})
            .then((res) => res.ok ? res.json() : res.json().then((b) => Promise.reject(new Error(b.error || 'failed'))))
            .then(() => {
                let attempts = 0;
                const iv = setInterval(() => {
                    attempts++;
                    refreshCaptions();
                    if (attempts >= 15) clearInterval(iv);
                }, 20000);
            })
            .catch((err) => {
                // eslint-disable-next-line no-console
                console.error('[bunny:author] transcribe failed', err);
                setCaptionStatus(err.message || 'Transcribe failed', 'error');
            });
    };

    // --- Chapters ----------------------------------------------------------
    const parseTime = (value) => {
        if (typeof value === 'number') return Math.max(0, Math.floor(value));
        const s = (value || '').trim();
        if (!s) return 0;
        if (/^\d+$/.test(s)) return parseInt(s, 10);
        const m = s.match(/^(\d+):(\d{1,2})$/);
        if (m) return parseInt(m[1], 10) * 60 + parseInt(m[2], 10);
        const h = s.match(/^(\d+):(\d{1,2}):(\d{1,2})$/);
        if (h) return parseInt(h[1], 10) * 3600 + parseInt(h[2], 10) * 60 + parseInt(h[3], 10);
        return -1;
    };
    const fmtTime = (sec) => {
        sec = Math.max(0, Math.floor(sec || 0));
        const h = Math.floor(sec / 3600);
        const m = Math.floor((sec % 3600) / 60);
        const s = sec % 60;
        const mm = (m < 10 && h > 0 ? '0' : '') + m;
        const ss = (s < 10 ? '0' : '') + s;
        return h > 0 ? h + ':' + mm + ':' + ss : m + ':' + ss;
    };

    const setChapterStatus = (msg, kind) => {
        if (!chapterStatus) return;
        if (msg) { chapterStatus.textContent = msg; chapterStatus.hidden = false; chapterStatus.setAttribute('data-state', kind || 'info'); }
        else { chapterStatus.textContent = ''; chapterStatus.hidden = true; }
    };
    const markChaptersDirty = (dirty) => { if (saveChaptersBtn) saveChaptersBtn.disabled = !dirty; };

    const addChapterRow = (chapter) => {
        if (!chaptersRows) return;
        const tr = document.createElement('tr');
        tr.innerHTML =
            '<td><input type="text" class="bunnystream-chapter-time-input" placeholder="0:00"></td>' +
            '<td><input type="text" class="bunnystream-chapter-title-input" placeholder="Chapter title"></td>' +
            '<td><button type="button" class="btn btn-sm btn-link" data-action="remove-chapter">×</button></td>';
        const t = tr.querySelector('.bunnystream-chapter-time-input');
        const ti = tr.querySelector('.bunnystream-chapter-title-input');
        if (chapter) { t.value = fmtTime(chapter.start || 0); ti.value = chapter.title || ''; }
        t.addEventListener('input', () => markChaptersDirty(true));
        ti.addEventListener('input', () => markChaptersDirty(true));
        chaptersRows.appendChild(tr);
    };

    const renderChapters = (chapters) => {
        if (!chaptersRows) return;
        chaptersRows.innerHTML = '';
        if (!chapters || chapters.length === 0) { markChaptersDirty(false); return; }
        for (const c of chapters) addChapterRow(c);
        markChaptersDirty(false);
    };

    const refreshChapters = () => {
        if (!state.guid) return;
        getJson('chapters.php', {guid: state.guid})
            .then((res) => res.ok ? res.json() : null)
            .then((data) => { if (data && data.chapters) renderChapters(data.chapters); })
            .catch(() => { /* ignore */ });
    };

    const collectChapters = () => {
        const rows = chaptersRows ? chaptersRows.querySelectorAll('tr') : [];
        const out = [];
        for (let i = 0; i < rows.length; i++) {
            const t = rows[i].querySelector('.bunnystream-chapter-time-input').value;
            const title = rows[i].querySelector('.bunnystream-chapter-title-input').value;
            const start = parseTime(t);
            if (start < 0) return {error: 'Row ' + (i + 1) + ": '" + t + "' isn't a valid time."};
            if (!title.trim()) return {error: 'Row ' + (i + 1) + ': missing title.'};
            out.push({title: title.trim(), start, end: 0});
        }
        return {chapters: out};
    };

    const saveChapters = () => {
        if (!state.guid) return;
        const c = collectChapters();
        if (c.error) { setChapterStatus(c.error, 'error'); return; }
        setChapterStatus('Saving…', 'info');
        postJson('chapters.php', {guid: state.guid, chapters: c.chapters, action: 'put'})
            .then((res) => res.ok ? res.json() : res.json().then((b) => Promise.reject(new Error(b.error || 'failed'))))
            .then((data) => {
                if (data && data.chapters) renderChapters(data.chapters);
                setChapterStatus('Saved', 'success');
                setTimeout(() => setChapterStatus('', null), 2500);
            })
            .catch((err) => {
                // eslint-disable-next-line no-console
                console.error('[bunny:author] chapters save failed', err);
                setChapterStatus(err.message || 'Save failed', 'error');
            });
    };

    // --- Title -------------------------------------------------------------
    let titleDebounce = null;
    const commitTitle = (value) => {
        state.title = value;
        syncHidden();
        if (!state.guid) return;
        postJson('video_update.php', {guid: state.guid, title: value}).catch(() => {});
    };
    const onTitleInput = (e) => {
        const v = (e.target.value || '').slice(0, 250);
        if (titleDebounce) clearTimeout(titleDebounce);
        titleDebounce = setTimeout(() => commitTitle(v.trim()), 600);
    };

    // --- Wiring ------------------------------------------------------------
    root.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-action]');
        if (!btn) return;
        switch (btn.getAttribute('data-action')) {
            case 'choose': fileInput && fileInput.click(); break;
            case 'cancel-upload': cancelUpload(); break;
            case 'replace': replaceVideo(); break;
            case 'delete': openModal(); break;
            case 'modal-cancel': closeModal(); break;
            case 'modal-confirm': deleteVideo(); break;
            case 'upload-thumbnail': thumbnailFile && thumbnailFile.click(); break;
            case 'upload-caption': captionFile && captionFile.click(); break;
            case 'transcribe': transcribeAudio(); break;
            case 'delete-caption': {
                const row = btn.closest('[data-srclang]');
                if (row) deleteCaption(row.getAttribute('data-srclang'));
                break;
            }
            case 'add-chapter': addChapterRow(null); markChaptersDirty(true); break;
            case 'remove-chapter': {
                const tr = btn.closest('tr');
                if (tr) tr.remove();
                markChaptersDirty(true);
                break;
            }
            case 'save-chapters': saveChapters(); break;
            default: break;
        }
    });

    if (fileInput) {
        fileInput.addEventListener('change', (e) => {
            const file = e.target.files && e.target.files[0];
            if (file) startUpload(file);
            e.target.value = '';
        });
    }
    if (thumbnailFile) {
        thumbnailFile.addEventListener('change', (e) => {
            const file = e.target.files && e.target.files[0];
            if (file) uploadThumbnail(file);
            e.target.value = '';
        });
    }
    if (captionFile) {
        captionFile.addEventListener('change', (e) => {
            const file = e.target.files && e.target.files[0];
            if (file) uploadCaption(file);
            e.target.value = '';
        });
    }
    if (dropzone) {
        ['dragover', 'dragenter'].forEach((ev) => dropzone.addEventListener(ev, (e) => {
            e.preventDefault();
            dropzone.classList.add('is-dragover');
        }));
        ['dragleave', 'dragend', 'drop'].forEach((ev) => dropzone.addEventListener(ev, (e) => {
            e.preventDefault();
            dropzone.classList.remove('is-dragover');
        }));
        dropzone.addEventListener('drop', (e) => {
            const file = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
            if (file) startUpload(file);
        });
        dropzone.addEventListener('keydown', (e) => {
            if ((e.key === 'Enter' || e.key === ' ') && fileInput) {
                e.preventDefault();
                fileInput.click();
            }
        });
    }
    if (titleInput) {
        titleInput.addEventListener('input', onTitleInput);
        titleInput.addEventListener('blur', (e) => commitTitle((e.target.value || '').trim()));
    }
    if (modal) {
        modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modal.getAttribute('data-open') === 'true') closeModal();
        });
    }

    // --- Initial render ----------------------------------------------------
    syncReadyMeta();
    if (state.guid && state.status && state.status !== 'ready' && state.status !== 'failed') {
        show('processing');
    } else if (state.status === 'failed') {
        show('failed');
    } else if (state.guid && state.libraryId) {
        refreshCaptions();
        refreshChapters();
        show('ready');
    } else {
        show('empty');
    }

    // Suppress unused-import warning from eslint runner.
    void Ajax; void Notification; void getString;
};
