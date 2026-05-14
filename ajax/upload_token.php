<?php
// GPLv3 — see LICENSE.

define('AJAX_SCRIPT', true);
define('NO_DEBUG_DISPLAY', true);
require_once(__DIR__ . '/../../../config.php');

use mod_bunnystream\ajax_helper;
use mod_bunnystream\bunny_client;
use mod_bunnystream\token;

global $USER, $DB;

$cfg = ajax_helper::require_manage();

$body = ajax_helper::read_json_body();
$rawtitle = trim((string)($body['title'] ?? 'Untitled video'));

// Strip the trailing file extension when the title looks like a filename.
if (strpos($rawtitle, '.') !== false) {
    $parts = explode('.', $rawtitle);
    $ext = array_pop($parts);
    $stem = implode('.', $parts);
    if ($stem && $ext && ctype_alnum($ext) && strlen($ext) >= 1 && strlen($ext) <= 6) {
        $rawtitle = $stem;
    }
}
$title = substr($rawtitle, 0, 250) ?: 'Untitled video';

if (!ajax_helper::rate_ok('mint:' . $USER->id, 60)) {
    ajax_helper::fail('error_too_many_uploads', 429);
}

$client = new bunny_client($cfg);
try {
    $guid = $client->create_video($title);
} catch (\Throwable $e) {
    ajax_helper::fail($e->getMessage(), 502);
}

// Persist the row before returning the signature. Rollback on insert failure
// so we don't leak a billed orphan on Bunny.
$row = (object)[
    'guid'         => $guid,
    'library_id'   => $cfg->library_id,
    'title'        => $title,
    'status'       => bunny_client::STATUS_PENDING,
    'created_by'   => $USER->id,
    'timecreated'  => time(),
    'timemodified' => time(),
];
try {
    $DB->insert_record('bunnystream_videos', $row);
} catch (\Throwable $e) {
    try { $client->delete_video($guid); } catch (\Throwable $e2) { /* manual cleanup */ }
    ajax_helper::fail('Could not record upload. Please try again.', 500);
}

$signature = token::sign_tus($cfg->library_id, $cfg->api_key, $guid);
ajax_helper::json([
    'guid'       => $guid,
    'library_id' => $cfg->library_id,
    'expires'    => $signature['expires'],
    'signature'  => $signature['signature'],
]);
