<?php
// GPLv3 — see LICENSE.

define('AJAX_SCRIPT', true);
define('NO_DEBUG_DISPLAY', true);
require_once(__DIR__ . '/../../../config.php');

use mod_bunnystream\ajax_helper;
use mod_bunnystream\bunny_client;

global $DB;

$cfg = ajax_helper::require_manage();
$body = ajax_helper::read_json_body();
$guid = trim((string)($body['guid'] ?? ''));
if ($guid === '') {
    ajax_helper::fail('missing_guid', 400);
}

$row = $DB->get_record('bunnystream_videos', ['guid' => $guid]);
if (!$row) {
    ajax_helper::fail('unknown_video', 404);
}

$client = new bunny_client($cfg);
try {
    $meta = $client->get_video($guid);
} catch (\Throwable $e) {
    ajax_helper::fail($e->getMessage(), 502);
}
if (!$meta) {
    ajax_helper::fail('not_found_on_bunny', 404);
}

$row->status = bunny_client::map_status($meta['status'] ?? null);
$thumb = bunny_client::thumbnail_url($cfg->cdn_hostname, $guid, $meta['thumbnailFileName'] ?? null);
if ($thumb) {
    $row->thumbnail_url = $thumb;
}
$length = $meta['length'] ?? null;
if (is_numeric($length) && $length > 0) {
    $row->duration_sec = (int)round($length);
}
if (!empty($meta['title'])) {
    $row->title = substr($meta['title'], 0, 250);
}
$row->timemodified = time();
$DB->update_record('bunnystream_videos', $row);

ajax_helper::json(ajax_helper::serialize_video($row));
