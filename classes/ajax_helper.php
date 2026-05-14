<?php
// GPLv3 — see LICENSE.
//
// Shared bootstrap for the AJAX endpoints under /mod/bunnystream/ajax/.

namespace mod_bunnystream;

defined('MOODLE_INTERNAL') || die();

class ajax_helper {

    /** Send JSON and exit. */
    public static function json($payload, int $code = 200): void {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($payload);
        exit;
    }

    public static function fail(string $message, int $code = 400): void {
        self::json(['error' => $message], $code);
    }

    /**
     * Parse a JSON body off php://input. Returns [] if absent.
     */
    public static function read_json_body(): array {
        $raw = file_get_contents('php://input');
        if (!$raw) {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Confirm the caller is a logged-in user with the manage capability.
     * Throws a moodle_exception if not. Returns the bunny config (decrypted).
     */
    public static function require_manage(): \stdClass {
        require_login();
        if (!confirm_sesskey()) {
            self::fail('invalid_sesskey', 403);
        }
        // System-level check — author UX is gated by mod/bunnystream:addinstance
        // in mod_form. The AJAX endpoints check capability at the system level
        // because they're not always called in a course-module context (e.g.
        // a brand-new instance being created via the form).
        if (!has_capability('moodle/course:manageactivities', \context_system::instance())
            && !self::user_has_manage_in_any_course()) {
            self::fail('forbidden', 403);
        }
        try {
            return config::decrypted();
        } catch (not_configured_exception $e) {
            self::fail($e->getMessage(), 503);
        } catch (undecryptable_exception $e) {
            self::fail($e->getMessage(), 500);
        }
        return new \stdClass();
    }

    private static function user_has_manage_in_any_course(): bool {
        global $DB, $USER;
        // Cheap heuristic: any role assignment with course:manageactivities
        // anywhere. Avoids a full traversal — fine for the AJAX surface since
        // it's still session-authenticated.
        return $DB->record_exists_sql(
            "SELECT 1
               FROM {role_assignments} ra
               JOIN {role_capabilities} rc ON rc.roleid = ra.roleid
              WHERE ra.userid = :userid AND rc.capability = :cap AND rc.permission = 1",
            ['userid' => $USER->id, 'cap' => 'moodle/course:manageactivities']
        );
    }

    /**
     * In-process rate limiter backed by MUC. Returns true if under the cap.
     */
    public static function rate_ok(string $bucket, int $maxperwindow, int $windowseconds = 60): bool {
        $cache = \cache::make('mod_bunnystream', 'ratelimit');
        $now = time();
        $row = $cache->get($bucket);
        if (!$row || $row['reset_at'] <= $now) {
            $cache->set($bucket, ['count' => 1, 'reset_at' => $now + $windowseconds]);
            return true;
        }
        if ($row['count'] >= $maxperwindow) {
            return false;
        }
        $row['count']++;
        $cache->set($bucket, $row);
        return true;
    }

    public static function serialize_video(\stdClass $row): array {
        return [
            'guid'          => $row->guid,
            'library_id'    => $row->library_id,
            'title'         => $row->title,
            'status'        => $row->status,
            'duration_sec'  => (int)$row->duration_sec,
            'thumbnail_url' => $row->thumbnail_url ?: null,
        ];
    }
}
