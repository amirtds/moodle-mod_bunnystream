<?php
// GPLv3 — see LICENSE.
//
// Receives % watched updates from the student player and persists them as
// the user's max-watched percent. Drives completion + gradebook.

define('AJAX_SCRIPT', true);
define('NO_DEBUG_DISPLAY', true);
require_once(__DIR__ . '/../../../config.php');

use mod_bunnystream\ajax_helper;

global $DB, $USER;

require_login(null, false);
$body = ajax_helper::read_json_body();
$sesskey = optional_param('sesskey', '', PARAM_RAW) ?: ($body['sesskey'] ?? '');
if (!confirm_sesskey($sesskey ?: null)) ajax_helper::fail('invalid_sesskey', 403);
$instance = (int)($body['instance'] ?? 0);
$cmid     = (int)($body['cmid'] ?? 0);
$percent  = max(0, min(100, (int)($body['percent'] ?? 0)));
if (!$instance || !$cmid) ajax_helper::fail('missing_ids', 400);

$cm = get_coursemodule_from_id('bunnystream', $cmid, 0, false, MUST_EXIST);
$activity = $DB->get_record('bunnystream', ['id' => $instance], '*', MUST_EXIST);
require_capability('mod/bunnystream:view', context_module::instance($cm->id));

$existing = $DB->get_record('bunnystream_progress', [
    'bunnystreamid' => $activity->id,
    'userid'        => $USER->id,
]);

if ($existing) {
    if ($percent > (int)$existing->max_percent) {
        $existing->max_percent = $percent;
        $existing->timemodified = time();
        $DB->update_record('bunnystream_progress', $existing);
    }
} else {
    $DB->insert_record('bunnystream_progress', (object)[
        'bunnystreamid' => $activity->id,
        'userid'        => $USER->id,
        'max_percent'   => $percent,
        'timemodified'  => time(),
    ]);
}

// Recompute grade for this user.
require_once(__DIR__ . '/../lib.php');
bunnystream_update_grades($activity, $USER->id);

// Trigger completion recompute.
if ($percent >= (int)$activity->completion_percent) {
    $course = $DB->get_record('course', ['id' => $activity->course]);
    $completion = new \completion_info($course);
    if ($completion->is_enabled($cm)) {
        $completion->update_state($cm, COMPLETION_COMPLETE, $USER->id);
    }
}

ajax_helper::json(['ok' => true, 'max_percent' => $percent]);
