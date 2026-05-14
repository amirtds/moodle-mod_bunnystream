<?php
// GPLv3 — see LICENSE.

namespace mod_bunnystream\output;

defined('MOODLE_INTERNAL') || die();

class renderer extends \plugin_renderer_base {

    /**
     * Render the inline author UX (the 5-panel TUS upload surface).
     */
    public function render_author(?\stdClass $activity): string {
        $ctx = [
            'has_video'  => !empty($activity->guid),
            'state'      => $this->compute_state($activity),
            'title'      => $activity->title ?? '',
            'thumbnail'  => $activity->thumbnail_url ?? '',
            'duration'   => $activity->duration_sec ?? 0,
            'guid_short' => !empty($activity->guid) ? substr($activity->guid, 0, 8) . '…' : '',
        ];
        return $this->render_from_template('mod_bunnystream/author', $ctx);
    }

    public function render_player(\stdClass $activity, string $embedurl): string {
        return $this->render_from_template('mod_bunnystream/player', [
            'embed_url' => $embedurl,
            'title'     => $activity->title ?: $activity->name,
            'style'     => $activity->video_style ?: 'default',
            'instance'  => $activity->id,
            'completion_percent' => (int)$activity->completion_percent,
        ]);
    }

    private function compute_state(?\stdClass $activity): string {
        if (!$activity || empty($activity->guid)) {
            return 'empty';
        }
        if ($activity->status === 'ready')  return 'ready';
        if ($activity->status === 'failed') return 'failed';
        return 'processing';
    }
}
