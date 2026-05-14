<?php
// GPLv3 — see LICENSE.

defined('MOODLE_INTERNAL') || die();

class backup_bunnystream_activity_structure_step extends backup_activity_structure_step {

    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');

        $activity = new backup_nested_element('bunnystream', ['id'], [
            'course', 'name', 'intro', 'introformat', 'guid', 'library_id', 'title',
            'duration_sec', 'thumbnail_url', 'status', 'video_style', 'completion_percent',
            'timecreated', 'timemodified',
        ]);

        $progresslist = new backup_nested_element('progresses');
        $progress = new backup_nested_element('progress', ['id'], [
            'userid', 'max_percent', 'timemodified',
        ]);

        $activity->add_child($progresslist);
        $progresslist->add_child($progress);

        $activity->set_source_table('bunnystream', ['id' => backup::VAR_ACTIVITYID]);
        if ($userinfo) {
            $progress->set_source_table('bunnystream_progress', ['bunnystreamid' => backup::VAR_PARENTID]);
            $progress->annotate_ids('user', 'userid');
        }

        return $this->prepare_activity_structure($activity);
    }
}
