<?php
// GPLv3 — see LICENSE.

defined('MOODLE_INTERNAL') || die();

const FEATURE_BUNNYSTREAM_MAX_PERCENT = 100;

/**
 * Declares which optional mod features this plugin supports.
 */
function bunnystream_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
        case FEATURE_SHOW_DESCRIPTION:
        case FEATURE_BACKUP_MOODLE2:
        case FEATURE_GRADE_HAS_GRADE:
        case FEATURE_COMPLETION_TRACKS_VIEWS:
        case FEATURE_COMPLETION_HAS_RULES:
        case FEATURE_MOD_PURPOSE:
            return true;
        case FEATURE_MOD_INTRO ?? null:
            return true;
        case FEATURE_GROUPS:
        case FEATURE_GROUPINGS:
        case FEATURE_IDNUMBER:
            return false;
        default:
            return null;
    }
}

function bunnystream_get_extra_capabilities() {
    return [];
}

/**
 * Add a new bunnystream activity instance.
 */
function bunnystream_add_instance(stdClass $data, $mform = null) {
    global $DB;
    $data->timecreated  = time();
    $data->timemodified = time();
    $data->status = $data->status ?? 'pending';
    $data->video_style = $data->video_style ?? 'default';
    if (!isset($data->completion_percent) || (int)$data->completion_percent <= 0) {
        $data->completion_percent = (int)(get_config('mod_bunnystream', 'completion_percent') ?: 90);
    }
    $id = $DB->insert_record('bunnystream', $data);
    bunnystream_grade_item_update((object)array_merge((array)$data, ['id' => $id]));
    return $id;
}

/**
 * Update an existing bunnystream activity instance.
 */
function bunnystream_update_instance(stdClass $data, $mform = null) {
    global $DB;
    $data->id = $data->instance;
    $data->timemodified = time();
    $DB->update_record('bunnystream', $data);
    bunnystream_grade_item_update($data);
    return true;
}

/**
 * Delete an activity instance.
 */
function bunnystream_delete_instance($id) {
    global $DB;
    $activity = $DB->get_record('bunnystream', ['id' => $id]);
    if (!$activity) {
        return false;
    }
    $DB->delete_records('bunnystream_progress', ['bunnystreamid' => $id]);
    $DB->delete_records('bunnystream', ['id' => $id]);
    // Note: do NOT delete the video on Bunny here — videos may be shared across
    // instances (in future) and a teacher deleting an activity in error
    // shouldn't lose Bunny-side content. Explicit "Delete video" in the editor
    // makes the Bunny DELETE call.
    bunnystream_grade_item_delete($activity);
    return true;
}

/**
 * Completion check: did this user watch enough of the video?
 */
function bunnystream_get_completion_state($course, $cm, $userid, $type) {
    global $DB;
    $activity = $DB->get_record('bunnystream', ['id' => $cm->instance], '*', MUST_EXIST);
    $threshold = (int)$activity->completion_percent;
    if ($threshold <= 0) {
        return $type;
    }
    $progress = $DB->get_record('bunnystream_progress', [
        'bunnystreamid' => $activity->id,
        'userid'        => $userid,
    ]);
    if (!$progress) {
        return false;
    }
    return (int)$progress->max_percent >= $threshold;
}

/**
 * Create/update the gradebook item for an activity.
 */
function bunnystream_grade_item_update(stdClass $activity, $grades = null) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');
    $params = [
        'itemname' => $activity->name,
        'gradetype' => GRADE_TYPE_VALUE,
        'grademax'  => 100,
        'grademin'  => 0,
    ];
    return grade_update('mod/bunnystream', $activity->course, 'mod', 'bunnystream', $activity->id, 0, $grades, $params);
}

function bunnystream_grade_item_delete(stdClass $activity) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');
    return grade_update('mod/bunnystream', $activity->course, 'mod', 'bunnystream', $activity->id, 0, null, ['deleted' => 1]);
}

/**
 * Update a user's grade for an activity from their stored max-percent.
 */
function bunnystream_update_grades(stdClass $activity, $userid = 0) {
    global $DB, $CFG;
    require_once($CFG->libdir . '/gradelib.php');
    $params = ['bunnystreamid' => $activity->id];
    if ($userid) {
        $params['userid'] = $userid;
    }
    $rows = $DB->get_records('bunnystream_progress', $params);
    $grades = [];
    foreach ($rows as $r) {
        $grades[$r->userid] = (object)[
            'userid'   => $r->userid,
            'rawgrade' => (float)$r->max_percent,
        ];
    }
    if (!empty($grades)) {
        bunnystream_grade_item_update($activity, $grades);
    } else if ($userid) {
        bunnystream_grade_item_update($activity, (object)['userid' => $userid, 'rawgrade' => null]);
    }
}

/**
 * Reset on course reset.
 */
function bunnystream_reset_userdata($data) {
    global $DB;
    $status = [];
    if (!empty($data->reset_bunnystream_progress)) {
        $activities = $DB->get_records('bunnystream', ['course' => $data->courseid], '', 'id');
        foreach ($activities as $a) {
            $DB->delete_records('bunnystream_progress', ['bunnystreamid' => $a->id]);
        }
        $status[] = [
            'component' => get_string('modulenameplural', 'mod_bunnystream'),
            'item'      => get_string('modulenameplural', 'mod_bunnystream'),
            'error'     => false,
        ];
    }
    return $status;
}
