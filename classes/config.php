<?php
// GPLv3 — see LICENSE.
//
// Reads credentials from Moodle's {config_plugins} table. The API key and
// security key are stored via admin_setting_encryptedpassword, which wraps
// \core\encryption (libsodium AEAD) — Moodle handles encrypt/decrypt for us.
//
// We still keep a tiny {bunnystream_config} table for the webhook secret
// (per-library random token, rotated on library_id change).

namespace mod_bunnystream;

defined('MOODLE_INTERNAL') || die();

class config {

    const TABLE = 'bunnystream_config';

    /**
     * Load (or create) the singleton webhook-secret row. Rotates the secret
     * when the library_id changes.
     */
    public static function ensure_webhook_secret(string $libraryid): \stdClass {
        global $DB;
        $row = $DB->get_record(self::TABLE, ['id' => 1]);
        if (!$row) {
            $row = (object)[
                'id' => 1,
                'library_id' => $libraryid,
                'webhook_secret' => bin2hex(random_bytes(32)),
                'timemodified' => time(),
            ];
            $DB->insert_record_raw(self::TABLE, $row, false, false, true);
            return $row;
        }
        // Rotate when library_id changed to a different non-empty value.
        if ($libraryid !== '' && $row->library_id !== '' && $row->library_id !== $libraryid) {
            $row->library_id = $libraryid;
            $row->webhook_secret = bin2hex(random_bytes(32));
            $row->timemodified = time();
            $DB->update_record(self::TABLE, $row);
        } else if (empty($row->webhook_secret) || $row->library_id !== $libraryid) {
            $row->library_id = $libraryid;
            if (empty($row->webhook_secret)) {
                $row->webhook_secret = bin2hex(random_bytes(32));
            }
            $row->timemodified = time();
            $DB->update_record(self::TABLE, $row);
        }
        return $row;
    }

    /**
     * Return decrypted credentials. Throws if not configured.
     */
    public static function decrypted(): \stdClass {
        $libraryid = (string)(get_config('mod_bunnystream', 'library_id') ?: '');
        if ($libraryid === '') {
            throw new not_configured_exception();
        }

        // admin_setting_encryptedpassword stores the ciphertext in
        // {config_plugins} and reads back the *encrypted* value through
        // get_config. We must explicitly decrypt.
        $apikeyenc      = (string)(get_config('mod_bunnystream', 'api_key') ?: '');
        $securitykeyenc = (string)(get_config('mod_bunnystream', 'security_key') ?: '');
        if ($apikeyenc === '') {
            throw new not_configured_exception();
        }

        try {
            $apikey = \core\encryption::decrypt($apikeyenc);
        } catch (\Throwable $e) {
            throw new undecryptable_exception();
        }
        $securitykey = '';
        if ($securitykeyenc !== '') {
            try {
                $securitykey = \core\encryption::decrypt($securitykeyenc);
            } catch (\Throwable $e) {
                // Optional field — log + fall back to unsigned.
                debugging('[bunny:config] security_key decrypt failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        // Ensure the webhook secret exists (and is bound to this library_id).
        $row = self::ensure_webhook_secret($libraryid);

        $cfg = new \stdClass();
        $cfg->library_id     = $libraryid;
        $cfg->api_key        = $apikey;
        $cfg->cdn_hostname   = (string)(get_config('mod_bunnystream', 'cdn_hostname') ?: '') ?: null;
        $cfg->security_key   = $securitykey !== '' ? $securitykey : null;
        $cfg->webhook_secret = $row->webhook_secret;
        return $cfg;
    }

    /**
     * Return the public webhook URL, or null if not configured.
     */
    public static function webhook_url(): ?string {
        global $CFG, $DB;
        $libraryid = (string)(get_config('mod_bunnystream', 'library_id') ?: '');
        if ($libraryid === '') {
            return null;
        }
        // Re-ensure secret so the URL displayed in admin matches reality.
        $row = self::ensure_webhook_secret($libraryid);
        if (empty($row->webhook_secret)) {
            return null;
        }
        return $CFG->wwwroot . '/mod/bunnystream/webhook.php?token=' . urlencode($row->webhook_secret);
    }
}

class not_configured_exception extends \moodle_exception {
    public function __construct() {
        parent::__construct('error_not_configured', 'mod_bunnystream');
    }
}

class undecryptable_exception extends \moodle_exception {
    public function __construct() {
        parent::__construct('error_undecryptable', 'mod_bunnystream');
    }
}
