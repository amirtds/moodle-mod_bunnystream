# mod_bunnystream

Moodle activity module that embeds [Bunny.net Stream](https://bunny.net/stream/) videos with **Token Authentication**, direct-to-Bunny TUS upload, encoding-status webhooks, completion + gradebook integration, and Moodle Mobile App support.

> Status: 0.1.0 — early. Direct port of [`bunny-xblock`](https://github.com/amirtds/bunny-xblock) (Open edX) at full feature parity.

## Why

The Moodle community lacks a dedicated Bunny.net integration. The closest is `mod_interactivevideo`, which lists Bunny as one of ~20 generic iframe sources — no signed tokens, no upload UX, no Moodle Mobile App handling. This plugin fills that gap with the same authoring + playback model Cubite ships on Open edX.

## Compatibility

| Component | Supported versions |
| --- | --- |
| **Moodle** | 4.5 LTS, 5.0, 5.1, 5.2 |
| **PHP** | 8.1+ (matches Moodle 4.5 LTS minimum) |
| **Bunny.net** | Stream — any plan. DRM (MediaCage Premium) optional. |

## Install

```bash
# 1. Copy this directory into your Moodle install:
git clone https://github.com/cubite/moodle-mod_bunnystream.git \
    /path/to/moodle/mod/bunnystream

# 2. Visit /admin/upgrade.php (or run the CLI upgrade):
php admin/cli/upgrade.php --non-interactive
```

Then:

1. **Site administration → Plugins → Activity modules → Bunny Stream**
2. Paste `Library ID`, `API Key`, `Security Key`, and `CDN Hostname` (all from Bunny dashboard → Stream → Library → API / Security).
3. Save. The page now shows a **Webhook URL** — copy it into Bunny dashboard → Stream → Library → Webhooks.

## Configure

| Field | Where to find it in Bunny |
| --- | --- |
| Library ID | Stream → Library → API |
| API Key | Stream → Library → API |
| Security Key | Stream → Library → Security → "Token Authentication Key" (enable Token Authentication first) |
| CDN Hostname | Stream → Library → API → "Pull Zone Hostname" — looks like `vz-xxxxxxxx-xxx.b-cdn.net` |

API key + Security key are encrypted at rest via Moodle's core `\core\encryption` (libsodium AEAD).

## Authoring

In a course, **Add an activity or resource → Bunny Stream**:

- **Drag a file** onto the drop zone (or click "Choose video"). Upload is TUS direct to Bunny — bytes never touch your Moodle server.
- The form flips to **"Bunny is processing…"** while encoding.
- When the webhook fires, the form switches to a **Ready** state with thumbnail, title, captions tab, and chapters tab.
- Upload a custom thumbnail, attach `.vtt` subtitles, trigger Bunny auto-transcription, or define chapter markers — all without leaving the form.

## Playback

The student-facing view renders a signed Bunny iframe (`iframe.mediadelivery.net`). The URL is signed at render time:

```
https://iframe.mediadelivery.net/embed/<library>/<guid>?token=<sha256(securityKey+guid+expires)>&expires=<unix+6h>
```

Hot-linking the URL from another origin returns 401 (when Token Authentication is enabled).

## Completion + gradebook

Each activity has a configurable **completion threshold** (% watched, default 90). The student player listens to Bunny's `postMessage` events (`timeupdate`, `ended`) and reports the highest watched percentage back to Moodle. When the threshold is crossed:

- Activity completion is marked complete.
- The gradebook receives the % watched as a 0–100 grade.

## Moodle Mobile App

`db/mobile.php` registers a custom Ionic template that renders the signed iframe inside the mobile webview, working around Bunny's referrer rules that break naive embeds in the official Moodle app.

## Webhooks

Bunny POSTs encoding-status updates to a per-instance public URL:

```
https://<moodle-host>/mod/bunnystream/webhook.php?token=<webhook_secret>
```

Three guards (mirroring the XBlock):

1. Constant-time secret match (DB-indexed lookup + `hash_equals`).
2. Library mismatch check — payload's `VideoLibraryId` must equal the configured library.
3. Lifecycle-regression guard — `ready`/`failed` rows can't be downgraded.

## Architecture

- One Bunny library per Moodle instance — credentials in `mdl_bunnystream_config` (singleton, `id=1`). API key + Security key encrypted via `\core\encryption`.
- Upload goes TUS-direct to `https://video.bunnycdn.com/tusupload`. Moodle only mints a per-upload signature (`sha256(libraryId + apiKey + expires + guid)`, 1h TTL).
- Embed URLs signed at render time (`sha256(securityKey + guid + expires)`, 6h TTL).
- Webhook auth is URL-token-only (Bunny does not sign payloads).

## License

GPL v3. See `LICENSE`.

Maintained by [Cubite](https://cubite.io). Works with any Bunny.net account — no Cubite tenancy required.
