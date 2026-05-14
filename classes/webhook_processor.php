<?php
// GPLv3 — see LICENSE.

namespace mod_bunnystream;

defined('MOODLE_INTERNAL') || die();

class webhook_processor {

    public static function process(string $token, string $rawbody): void {
        global $DB;

        $cfgrow = $DB->get_record('bunnystream_config', ['webhook_secret' => $token]);
        if (!$cfgrow) {
            self::reject('unknown_token', 401, ['token_prefix' => substr($token, 0, 4)]);
            return;
        }

        if (!hash_equals((string)($cfgrow->webhook_secret ?? ''), $token)) {
            self::reject('token_compare_mismatch', 401);
            return;
        }

        $body = json_decode($rawbody, true);
        if (!is_array($body)) {
            self::reject('invalid_json', 400);
            return;
        }

        $guid = trim((string)($body['VideoGuid'] ?? ''));
        $eventlib = $body['VideoLibraryId'] ?? null;
        $statuscode = $body['Status'] ?? null;

        if ($guid === '' || $eventlib === null || !is_int($statuscode)) {
            self::reject('malformed_payload', 400, [
                'has_guid' => $guid !== '',
                'has_lib' => $eventlib !== null,
                'status_type' => gettype($statuscode),
            ]);
            return;
        }

        $eventlibstr = (string)$eventlib;

        // Library mismatch.
        if (!$cfgrow->library_id || !hash_equals((string)$cfgrow->library_id, $eventlibstr)) {
            self::reject('library_mismatch', 403, [
                'event_library_id' => $eventlibstr,
                'configured' => $cfgrow->library_id,
            ]);
            return;
        }

        $video = $DB->get_record('bunnystream_videos', ['guid' => $guid]);
        if (!$video) {
            // Acknowledge so Bunny stops retrying. Log so ops can spot orphans.
            self::ok('unknown_video');
            return;
        }

        if ($video->library_id !== $eventlibstr) {
            self::reject('tenant_mismatch', 403, [
                'event_library_id' => $eventlibstr,
                'row_library_id' => $video->library_id,
            ]);
            return;
        }

        $newstatus = bunny_client::map_status($statuscode);

        // Lifecycle regression: ready/failed cannot be flipped back.
        if (bunny_client::is_terminal_status($video->status) && $newstatus !== $video->status) {
            self::reject('terminal_state_regression', 200, [
                'guid' => $guid, 'current' => $video->status, 'incoming' => $newstatus,
            ]);
            return;
        }

        // On material transitions, refresh metadata from Bunny so the activity
        // row has duration + thumbnail.
        if ($newstatus !== $video->status &&
            ($newstatus === bunny_client::STATUS_READY || $newstatus === bunny_client::STATUS_ENCODING)) {
            try {
                $cfg = config::decrypted();
                $client = new bunny_client($cfg);
                $meta = $client->get_video($guid);
                if ($meta) {
                    $thumb = bunny_client::thumbnail_url($cfg->cdn_hostname, $guid, $meta['thumbnailFileName'] ?? null);
                    if ($thumb) {
                        $video->thumbnail_url = $thumb;
                    }
                    $length = $meta['length'] ?? null;
                    if (is_numeric($length) && $length > 0) {
                        $video->duration_sec = (int)round($length);
                    }
                }
            } catch (\Throwable $e) {
                // Best-effort refresh — webhook handling continues with whatever
                // metadata we have. The status flip is the important part.
                debugging('[bunny:webhook] meta_refresh_failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        $video->status = $newstatus;
        $video->timemodified = time();
        $DB->update_record('bunnystream_videos', $video);

        // Mirror into any activity row(s) pointing at this guid.
        $activities = $DB->get_records('bunnystream', ['guid' => $guid]);
        foreach ($activities as $a) {
            $a->status = $newstatus;
            if (!empty($video->duration_sec)) {
                $a->duration_sec = $video->duration_sec;
            }
            if (!empty($video->thumbnail_url)) {
                $a->thumbnail_url = $video->thumbnail_url;
            }
            $a->timemodified = time();
            $DB->update_record('bunnystream', $a);
        }

        self::ok();
    }

    private static function reject(string $reason, int $code, array $ctx = []): void {
        debugging("[bunny:webhook] reject:{$reason} " . json_encode($ctx), DEBUG_DEVELOPER);
        http_response_code($code);
        echo json_encode(['error' => $reason]);
    }

    private static function ok(?string $note = null): void {
        http_response_code(200);
        $body = ['ok' => true];
        if ($note) {
            $body['note'] = $note;
        }
        echo json_encode($body);
    }
}
