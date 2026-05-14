<?php
// GPLv3 — see LICENSE.
//
// Singleton site-level Bunny config, mirrors XBlock BunnyConfiguration.
// Secrets-at-rest handled by \core\encryption (libsodium AEAD).

namespace mod_bunnystream;

defined('MOODLE_INTERNAL') || die();

class config {

    const TABLE = 'bunnystream_config';

    /** @var \stdClass|null cached singleton row */
    private static $cached = null;

    /**
     * Load (or create) the singleton config row.
     */
    public static function load(): \stdClass {
        if (self::$cached !== null) {
            return self::$cached;
        }
        global $DB;
        $row = $DB->get_record(self::TABLE, ['id' => 1]);
        if (!$row) {
            $row = new \stdClass();
            $row->id = 1;
            $row->library_id = '';
            $row->api_key_ciphertext = '';
            $row->security_key_ciphertext = '';
            $row->cdn_hostname = '';
            $row->webhook_secret = null;
            $row->timemodified = time();
            $DB->insert_record_raw(self::TABLE, $row, false, false, true);
        }
        self::$cached = $row;
        return $row;
    }

    public static function reset_cache(): void {
        self::$cached = null;
    }

    /**
     * Update credentials. Empty string for a key clears it; null leaves it untouched.
     * Mints (or rotates) the webhook secret when library_id changes.
     */
    public static function save(?string $libraryid, ?string $apikey, ?string $securitykey, ?string $cdnhostname): \stdClass {
        global $DB;
        $row = self::load();
        $prior = clone $row;

        if ($libraryid !== null) {
            $row->library_id = $libraryid;
        }
        if ($apikey !== null) {
            $row->api_key_ciphertext = $apikey === '' ? '' : self::encrypt($apikey);
        }
        if ($securitykey !== null) {
            $row->security_key_ciphertext = $securitykey === '' ? '' : self::encrypt($securitykey);
        }
        if ($cdnhostname !== null) {
            $row->cdn_hostname = $cdnhostname;
        }

        // Webhook secret lifecycle.
        // - Mint on first API key save when we don't have one yet.
        // - Rotate when library_id actually changes to a new non-empty value.
        // - Clear when the API key is wiped (disconnect).
        if (empty($row->api_key_ciphertext)) {
            $row->webhook_secret = null;
        } else if (empty($row->webhook_secret)) {
            $row->webhook_secret = bin2hex(random_bytes(32));
        } else if ($prior->library_id && $prior->library_id !== $row->library_id && !empty($row->library_id)) {
            $row->webhook_secret = bin2hex(random_bytes(32));
        }

        $row->timemodified = time();
        $DB->update_record(self::TABLE, $row);
        self::$cached = $row;
        return $row;
    }

    /**
     * Decrypt and return a populated stdClass — ready for bunny_client.
     * Throws not_configured_exception if library_id/api_key are missing.
     * Throws undecryptable_exception if ciphertext is unreadable.
     */
    public static function decrypted(): \stdClass {
        $row = self::load();
        if (empty($row->library_id) || empty($row->api_key_ciphertext)) {
            throw new not_configured_exception();
        }
        $apikey = self::decrypt($row->api_key_ciphertext);
        if ($apikey === '') {
            throw new undecryptable_exception();
        }
        $cfg = new \stdClass();
        $cfg->library_id = $row->library_id;
        $cfg->api_key = $apikey;
        $cfg->cdn_hostname = $row->cdn_hostname ?: null;
        $cfg->security_key = !empty($row->security_key_ciphertext)
            ? (self::decrypt($row->security_key_ciphertext) ?: null)
            : null;
        $cfg->webhook_secret = $row->webhook_secret;
        return $cfg;
    }

    public static function webhook_url(): ?string {
        global $CFG;
        $row = self::load();
        if (empty($row->webhook_secret)) {
            return null;
        }
        return $CFG->wwwroot . '/mod/bunnystream/webhook.php?token=' . urlencode($row->webhook_secret);
    }

    // ---- Encryption -------------------------------------------------------

    /**
     * Encrypt with \core\encryption. Falls back to a sodium-direct path on
     * pre-4.0 Moodles in theory, but we require 4.5+ so the core path is fine.
     */
    public static function encrypt(string $plaintext): string {
        if ($plaintext === '') {
            return '';
        }
        return \core\encryption::encrypt($plaintext);
    }

    /**
     * Decrypt. Returns empty string on failure — callers translate that into
     * a user-visible "re-paste credentials" message.
     */
    public static function decrypt(string $ciphertext): string {
        if ($ciphertext === '') {
            return '';
        }
        try {
            return \core\encryption::decrypt($ciphertext);
        } catch (\Throwable $e) {
            return '';
        }
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
