<?php
// GPLv3 — see LICENSE.

require_once(__DIR__ . '/../../../config.php');

use mod_bunnystream\ajax_helper;
use mod_bunnystream\bunny_client;

global $DB;

$cfg = ajax_helper::require_manage();

$guid = required_param('guid', PARAM_ALPHANUMEXT);

if (empty($_FILES['thumbnail'])) {
    ajax_helper::fail('missing_file', 400);
}
$file = $_FILES['thumbnail'];
$contenttype = strtolower($file['type'] ?? '');
if (!in_array($contenttype, ['image/jpeg', 'image/png', 'image/webp'], true)) {
    ajax_helper::fail('unsupported_image_type', 400);
}
if ($file['size'] > 5 * 1024 * 1024) {
    ajax_helper::fail('image_too_large', 413);
}

$row = $DB->get_record('bunnystream_videos', ['guid' => $guid]);
if (!$row) {
    ajax_helper::fail('unknown_video', 404);
}

$bytes = file_get_contents($file['tmp_name']);
if ($bytes === false) {
    ajax_helper::fail('read_failed', 500);
}

$client = new bunny_client($cfg);
try {
    $client->set_thumbnail($guid, $bytes, $contenttype);
    // Refresh meta to pick up Bunny's new filename.
    $meta = $client->get_video($guid);
    if ($meta) {
        $thumb = bunny_client::thumbnail_url($cfg->cdn_hostname, $guid, $meta['thumbnailFileName'] ?? null);
        if ($thumb) {
            $row->thumbnail_url = $thumb;
            $row->timemodified = time();
            $DB->update_record('bunnystream_videos', $row);
        }
    }
} catch (\Throwable $e) {
    ajax_helper::fail($e->getMessage(), 502);
}

ajax_helper::json(['ok' => true, 'thumbnail_url' => $row->thumbnail_url ?: null]);
