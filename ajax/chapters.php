<?php
// GPLv3 — see LICENSE.

define('AJAX_SCRIPT', true);
define('NO_DEBUG_DISPLAY', true);
require_once(__DIR__ . '/../../../config.php');

use mod_bunnystream\ajax_helper;
use mod_bunnystream\bunny_client;

global $DB;

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    require_login(null, false);
    $guid = required_param('guid', PARAM_ALPHANUMEXT);
    try {
        $cfg = \mod_bunnystream\config::decrypted();
        $client = new bunny_client($cfg);
        ajax_helper::json(['chapters' => $client->get_chapters($guid)]);
    } catch (\Throwable $e) {
        ajax_helper::fail($e->getMessage(), 502);
    }
}

// POST/PUT — replace chapters.
$cfg = ajax_helper::require_manage();
$body = ajax_helper::read_json_body();
$guid = trim((string)($body['guid'] ?? ''));
$raw = $body['chapters'] ?? null;
if ($guid === '') ajax_helper::fail('missing_guid', 400);
if (!is_array($raw)) ajax_helper::fail('chapters_must_be_array', 400);
if (count($raw) > 50) ajax_helper::fail('too_many_chapters', 400);

$cleaned = [];
foreach ($raw as $i => $ch) {
    if (!is_array($ch)) ajax_helper::fail("chapter_{$i}_not_object", 400);
    $title = substr(trim((string)($ch['title'] ?? '')), 0, 120);
    $start = max(0, (int)($ch['start'] ?? 0));
    $end   = max(0, (int)($ch['end']   ?? 0));
    if ($title === '') ajax_helper::fail("chapter_{$i}_needs_title", 400);
    if ($end && $end < $start) ajax_helper::fail("chapter_{$i}_end_before_start", 400);
    $cleaned[] = ['title' => $title, 'start' => $start, 'end' => $end];
}

usort($cleaned, fn($a, $b) => $a['start'] - $b['start']);

// Backfill missing ends with the next chapter's start, or activity duration.
$activity = $DB->get_record('bunnystream', ['guid' => $guid]);
$duration = $activity ? (int)$activity->duration_sec : 0;
foreach ($cleaned as $i => &$ch) {
    if (!$ch['end']) {
        if (isset($cleaned[$i + 1])) {
            $ch['end'] = $cleaned[$i + 1]['start'];
        } else if ($duration) {
            $ch['end'] = $duration;
        } else {
            $ch['end'] = $ch['start'] + 60;
        }
    }
}
unset($ch);

$client = new bunny_client($cfg);
try {
    $client->set_chapters($guid, $cleaned);
} catch (\Throwable $e) {
    ajax_helper::fail($e->getMessage(), 502);
}
ajax_helper::json(['ok' => true, 'chapters' => $cleaned]);
