<?php
// GPLv3 — see LICENSE.

namespace mod_bunnystream\output;

defined('MOODLE_INTERNAL') || die();

class mobile {

    public static function mobile_init() {
        return [
            'javascript' => '',
        ];
    }

    /**
     * Returns an Ionic template rendering a signed Bunny iframe.
     */
    public static function mobile_view(array $args): array {
        global $DB, $OUTPUT;

        $cmid = (int)($args['cmid'] ?? 0);
        $cm = get_coursemodule_from_id('bunnystream', $cmid, 0, false, MUST_EXIST);
        $activity = $DB->get_record('bunnystream', ['id' => $cm->instance], '*', MUST_EXIST);

        $embedurl = '';
        if (!empty($activity->guid) && !empty($activity->library_id)) {
            try {
                $cfg = \mod_bunnystream\config::decrypted();
                $embedurl = \mod_bunnystream\token::embed_url_for(
                    $activity->library_id,
                    $activity->guid,
                    $cfg->security_key,
                    ['autoplay' => 'false', 'preload' => 'true', 'responsive' => 'true']
                );
            } catch (\Throwable $e) {
                $embedurl = \mod_bunnystream\token::unsigned_embed(
                    $activity->library_id,
                    $activity->guid
                );
            }
        }

        $template = '
<core-loading [hideUntil]="loaded">
  <ion-card>
    <ion-card-header><ion-card-title>{{ activity.name }}</ion-card-title></ion-card-header>
    <ion-card-content>
      <div *ngIf="!embedUrl"><p>{{ "plugin.mod_bunnystream.error_no_video" | translate }}</p></div>
      <div *ngIf="embedUrl" style="position:relative;padding-top:56.25%;">
        <iframe [src]="embedUrl | safeURL"
                style="position:absolute;inset:0;width:100%;height:100%;border:0"
                allow="accelerometer;gyroscope;autoplay;encrypted-media;picture-in-picture"
                allowfullscreen></iframe>
      </div>
    </ion-card-content>
  </ion-card>
</core-loading>';

        return [
            'templates' => [
                ['id' => 'main', 'html' => $template],
            ],
            'javascript' => '',
            'otherdata'  => [
                'activity' => (object)['id' => $activity->id, 'name' => $activity->name],
                'embedUrl' => $embedurl,
                'loaded'   => true,
            ],
        ];
    }
}
