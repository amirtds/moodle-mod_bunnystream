<?php
// GPLv3 — see LICENSE.

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/bunnystream/backup/moodle2/restore_bunnystream_stepslib.php');

class restore_bunnystream_activity_task extends restore_activity_task {

    protected function define_my_settings() {
    }

    protected function define_my_steps() {
        $this->add_step(new restore_bunnystream_activity_structure_step('bunnystream_structure', 'bunnystream.xml'));
    }

    public static function define_decode_contents() {
        return [new restore_decode_content('bunnystream', ['intro'], 'bunnystream')];
    }

    public static function define_decode_rules() {
        return [new restore_decode_rule('BUNNYSTREAMVIEWBYID', '/mod/bunnystream/view.php?id=$1', 'course_module')];
    }
}
