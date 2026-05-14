<?php
// GPLv3 — see LICENSE.
//
// Lightweight title update — used by the author UX's debounced title input.

require_once(__DIR__ . '/../../../config.php');

use mod_bunnystream\ajax_helper;
use mod_bunnystream\bunny_client;

global $DB;

$cfg = ajax_helper::require_manage();
$body = ajax_helper::read_json_body();
$guid = trim((string)($body['guid'] ?? ''));
$title = substr(trim((string)($body['title'] ?? '')), 0, 250);
if ($guid === '' || $title === '') ajax_helper::fail('missing_fields', 400);

$row = $DB->get_record('bunnystream_videos', ['guid' => $guid]);
if (!$row) ajax_helper::fail('unknown_video', 404);

$client = new bunny_client($cfg);
try {
    $client->update_video($guid, $title);
} catch (\Throwable $e) {
    ajax_helper::fail($e->getMessage(), 502);
}

$row->title = $title;
$row->timemodified = time();
$DB->update_record('bunnystream_videos', $row);

// Mirror into any activity rows.
$activities = $DB->get_records('bunnystream', ['guid' => $guid]);
foreach ($activities as $a) {
    $a->title = $title;
    $a->timemodified = time();
    $DB->update_record('bunnystream', $a);
}

ajax_helper::json(['ok' => true, 'title' => $title]);
