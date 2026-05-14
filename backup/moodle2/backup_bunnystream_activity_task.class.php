<?php
// GPLv3 — see LICENSE.

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/bunnystream/backup/moodle2/backup_bunnystream_stepslib.php');

class backup_bunnystream_activity_task extends backup_activity_task {

    protected function define_my_settings() {
    }

    protected function define_my_steps() {
        $this->add_step(new backup_bunnystream_activity_structure_step('bunnystream_structure', 'bunnystream.xml'));
    }

    public static function encode_content_links($content) {
        global $CFG;
        $base = preg_quote($CFG->wwwroot, '/');
        $search = "/(($base\/mod\/bunnystream\/view\.php\?id\=)([0-9]+))/";
        return preg_replace($search, '$@BUNNYSTREAMVIEWBYID*$3@$', $content);
    }
}
