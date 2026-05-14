<?php
// GPLv3 — see LICENSE.

namespace mod_bunnystream\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('bunnystream_progress', [
            'userid'        => 'privacy:metadata:bunnystream_progress:userid',
            'bunnystreamid' => 'privacy:metadata:bunnystream_progress:bunnystreamid',
            'max_percent'   => 'privacy:metadata:bunnystream_progress:max_percent',
            'timemodified'  => 'privacy:metadata:bunnystream_progress:timemodified',
        ], 'privacy:metadata:bunnystream_progress');

        $collection->add_external_location_link('bunny.net', [
            'guid' => 'privacy:metadata:bunny_net:guid',
        ], 'privacy:metadata:bunny_net');

        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :modulelevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'bunnystream'
                  JOIN {bunnystream_progress} bp ON bp.bunnystreamid = cm.instance
                 WHERE bp.userid = :userid";
        $contextlist->add_from_sql($sql, ['modulelevel' => CONTEXT_MODULE, 'userid' => $userid]);
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) return;
        $cm = get_coursemodule_from_id('bunnystream', $context->instanceid);
        if (!$cm) return;
        $userlist->add_from_sql('userid', 'SELECT userid FROM {bunnystream_progress} WHERE bunnystreamid = :id', ['id' => $cm->instance]);
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        foreach ($contextlist as $context) {
            if (!$context instanceof \context_module) continue;
            $cm = get_coursemodule_from_id('bunnystream', $context->instanceid);
            if (!$cm) continue;
            $row = $DB->get_record('bunnystream_progress', [
                'bunnystreamid' => $cm->instance,
                'userid' => $contextlist->get_user()->id,
            ]);
            if ($row) {
                writer::with_context($context)->export_data(
                    [get_string('modulename', 'mod_bunnystream')],
                    (object)['max_percent' => $row->max_percent, 'timemodified' => $row->timemodified]
                );
            }
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if (!$context instanceof \context_module) return;
        $cm = get_coursemodule_from_id('bunnystream', $context->instanceid);
        if (!$cm) return;
        $DB->delete_records('bunnystream_progress', ['bunnystreamid' => $cm->instance]);
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist as $context) {
            if (!$context instanceof \context_module) continue;
            $cm = get_coursemodule_from_id('bunnystream', $context->instanceid);
            if (!$cm) continue;
            $DB->delete_records('bunnystream_progress', ['bunnystreamid' => $cm->instance, 'userid' => $userid]);
        }
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) return;
        $cm = get_coursemodule_from_id('bunnystream', $context->instanceid);
        if (!$cm) return;
        $userids = $userlist->get_userids();
        if (empty($userids)) return;
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['bid'] = $cm->instance;
        $DB->delete_records_select('bunnystream_progress', "bunnystreamid = :bid AND userid {$insql}", $params);
    }
}
