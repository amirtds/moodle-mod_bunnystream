<?php
// GPLv3 — see LICENSE.

defined('MOODLE_INTERNAL') || die();

class restore_bunnystream_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {
        $paths = [];
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('bunnystream', '/activity/bunnystream');
        if ($userinfo) {
            $paths[] = new restore_path_element('bunnystream_progress', '/activity/bunnystream/progresses/progress');
        }
        return $this->prepare_activity_structure($paths);
    }

    protected function process_bunnystream($data) {
        global $DB;
        $data = (object)$data;
        $data->course = $this->get_courseid();
        $data->timecreated = time();
        $data->timemodified = time();
        $newid = $DB->insert_record('bunnystream', $data);
        $this->apply_activity_instance($newid);
    }

    protected function process_bunnystream_progress($data) {
        global $DB;
        $data = (object)$data;
        $data->bunnystreamid = $this->get_new_parentid('bunnystream');
        $data->userid = $this->get_mappingid('user', $data->userid);
        if ($data->userid) {
            $DB->insert_record('bunnystream_progress', $data);
        }
    }

    protected function after_execute() {
        $this->add_related_files('mod_bunnystream', 'intro', null);
    }
}
