<?php
// GPLv3 — see LICENSE.

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

class mod_bunnystream_mod_form extends moodleform_mod {

    protected function definition() {
        $mform = $this->_form;

        // Standard activity name.
        $mform->addElement('text', 'name', get_string('name', 'mod_bunnystream'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', null, 'maxlength', 255, 'client');

        // Standard intro/description.
        $this->standard_intro_elements();

        // ---- Video section: the inline author UX lives here ----------------

        $mform->addElement('header', 'videoheader', get_string('video', 'mod_bunnystream'));
        $mform->setExpanded('videoheader', true);

        // The author template ("empty/uploading/processing/ready/failed" panels)
        // is rendered via a static HTML element; the AMD module hydrates it.
        // The hidden fields below carry the persisted video identity through
        // form submission.
        global $PAGE, $OUTPUT;
        $instance = !empty($this->_instance) ? (int)$this->_instance : 0;
        $activity = null;
        if ($instance) {
            $activity = $this->_db_record_for_instance($instance);
        }

        $renderer = $PAGE->get_renderer('mod_bunnystream');
        $authorhtml = $renderer->render_author($activity);
        $mform->addElement('html', $authorhtml);

        // Hidden fields keep the video identity attached to the form on save.
        foreach (['guid', 'library_id', 'title', 'thumbnail_url', 'status'] as $f) {
            $mform->addElement('hidden', $f, '');
            $mform->setType($f, $f === 'thumbnail_url' ? PARAM_URL : PARAM_RAW);
        }
        $mform->addElement('hidden', 'duration_sec', 0);
        $mform->setType('duration_sec', PARAM_INT);

        // ---- Display style -------------------------------------------------

        $styles = [
            'default' => get_string('style_default', 'mod_bunnystream'),
            'rounded' => get_string('style_rounded', 'mod_bunnystream'),
            'padded'  => get_string('style_padded',  'mod_bunnystream'),
            'cinema'  => get_string('style_cinema',  'mod_bunnystream'),
            'compact' => get_string('style_compact', 'mod_bunnystream'),
        ];
        $mform->addElement('select', 'video_style', get_string('video_style', 'mod_bunnystream'), $styles);
        $mform->setDefault('video_style', 'default');

        // ---- Completion threshold -----------------------------------------

        $mform->addElement('text', 'completion_percent', get_string('completion_percent', 'mod_bunnystream'), ['size' => 4]);
        $mform->setType('completion_percent', PARAM_INT);
        $mform->setDefault('completion_percent', (int)(get_config('mod_bunnystream', 'completion_percent') ?: 90));
        $mform->addHelpButton('completion_percent', 'completion_percent', 'mod_bunnystream');

        // Standard course-module elements + buttons.
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();

        // Hydrate the inline author UX.
        $PAGE->requires->js_call_amd('mod_bunnystream/author', 'init', [[
            'instance'   => $instance,
            'guid'       => $activity->guid ?? '',
            'libraryId'  => $activity->library_id ?? '',
            'title'      => $activity->title ?? '',
            'status'     => $activity->status ?? '',
            'durationSec' => $activity->duration_sec ?? 0,
            'thumbnailUrl' => $activity->thumbnail_url ?? '',
            'sesskey'    => sesskey(),
        ]]);
    }

    private function _db_record_for_instance(int $id): ?stdClass {
        global $DB;
        return $DB->get_record('bunnystream', ['id' => $id]) ?: null;
    }
}
