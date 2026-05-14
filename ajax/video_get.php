<?php
// GPLv3 — see LICENSE.

require_once(__DIR__ . '/../../../config.php');

use mod_bunnystream\ajax_helper;
use mod_bunnystream\bunny_client;

global $DB;

require_login();
if (!confirm_sesskey() && !isloggedin()) {
    ajax_helper::fail('invalid_session', 403);
}

$guid = optional_param('guid', '', PARAM_ALPHANUMEXT);
if ($guid === '') {
    ajax_helper::fail('missing_guid', 400);
}

$row = $DB->get_record('bunnystream_videos', ['guid' => $guid]);
if (!$row) {
    ajax_helper::fail('unknown_video', 404);
}

// 30s refresh floor — skip Bunny round-trip if the row is fresh or terminal.
if (!bunny_client::is_terminal_status($row->status)) {
    $age = time() - (int)$row->timemodified;
    if ($age > 30) {
        try {
            $cfg = \mod_bunnystream\config::decrypted();
            $client = new bunny_client($cfg);
            $meta = $client->get_video($guid);
            if ($meta) {
                $row->status = bunny_client::map_status($meta['status'] ?? null);
                $thumb = bunny_client::thumbnail_url($cfg->cdn_hostname, $guid, $meta['thumbnailFileName'] ?? null);
                if ($thumb) $row->thumbnail_url = $thumb;
                $length = $meta['length'] ?? null;
                if (is_numeric($length) && $length > 0) $row->duration_sec = (int)round($length);
                $row->timemodified = time();
                $DB->update_record('bunnystream_videos', $row);
            }
        } catch (\Throwable $e) {
            // Return cached row.
            debugging('[bunny:video_get] sync_failed_returning_cached: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}

ajax_helper::json(ajax_helper::serialize_video($row));
