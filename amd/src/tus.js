// GPLv3 — see LICENSE.
//
// Promise-resolved tus-js-client. The bundled UMD lives at
// /mod/bunnystream/js/tus.min.js and self-registers either on window.tus
// (browser branch) or as an AMD module. We shadow `define`/`exports`/`module`
// during eval so the UMD wrapper falls through to the global branch — same
// trick the XBlock author_view.js uses.

let loaded = null;

const loadOnce = () => {
    if (loaded) return loaded;
    loaded = new Promise((resolve, reject) => {
        const src = M.cfg.wwwroot + '/mod/bunnystream/js/tus.min.js';
        // Plain <script> injection — fastest path, avoids RequireJS picking the
        // UMD AMD branch and registering anonymously instead of attaching to
        // window.tus.
        const s = document.createElement('script');
        s.src = src;
        s.async = true;
        s.onload = () => {
            if (window.tus) resolve(window.tus);
            else reject(new Error('tus-js-client loaded but window.tus missing'));
        };
        s.onerror = () => reject(new Error('Failed to load tus-js-client from ' + src));
        document.head.appendChild(s);
    });
    return loaded;
};

// Synchronous proxy: callers do `new tus.Upload(...)`. We export a wrapper
// that lazy-resolves on first use. The constructor returns a Promise that
// resolves to a real Upload instance after the bundle has loaded; we expose
// .start() as an async wrapper too.
class LazyUpload {
    constructor(file, options) {
        this._file = file;
        this._options = options;
        this._readyPromise = loadOnce().then((tus) => {
            this._real = new tus.Upload(file, options);
            return this._real;
        });
    }
    start() {
        this._readyPromise.then((u) => u.start()).catch((err) => {
            if (this._options && this._options.onError) this._options.onError(err);
        });
    }
    abort(shouldTerminate) {
        return this._readyPromise.then((u) => u.abort(shouldTerminate)).catch(() => {});
    }
}

export default {
    Upload: LazyUpload,
};
