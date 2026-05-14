<?php
// GPLv3 — see LICENSE.

require_once(__DIR__ . '/../../../config.php');

use mod_bunnystream\ajax_helper;
use mod_bunnystream\bunny_client;

global $DB, $USER;

$cfg = ajax_helper::require_manage();

$guid = optional_param('guid', '', PARAM_ALPHANUMEXT);
if ($guid === '') {
    ajax_helper::fail('missing_guid', 400);
}

if (!ajax_helper::rate_ok('delete:' . $USER->id, 120)) {
    ajax_helper::fail('error_too_many_deletes', 429);
}

$row = $DB->get_record('bunnystream_videos', ['guid' => $guid]);
if (!$row) {
    ajax_helper::fail('unknown_video', 404);
}

$client = new bunny_client($cfg);
try {
    $client->delete_video($guid);
} catch (\Throwable $e) {
    ajax_helper::fail($e->getMessage(), 502);
}

$DB->delete_records('bunnystream_videos', ['guid' => $guid]);

// Detach from any activity rows that pointed here.
$activities = $DB->get_records('bunnystream', ['guid' => $guid]);
foreach ($activities as $a) {
    $a->guid = null;
    $a->library_id = null;
    $a->title = null;
    $a->duration_sec = 0;
    $a->thumbnail_url = null;
    $a->status = 'pending';
    $a->timemodified = time();
    $DB->update_record('bunnystream', $a);
}

ajax_helper::json(['ok' => true]);
