<?php
// GPLv3 — see LICENSE.
//
// Mirror plain admin_setting values into the encrypted singleton row.
// admin_setting_configpasswordunmask stores in {config_plugins} as plaintext —
// we read it once, persist encrypted into mdl_bunnystream_config, and clear
// the plaintext field so it never sits at rest in two places.

namespace mod_bunnystream;

defined('MOODLE_INTERNAL') || die();

class admin_settings_observer {

    /**
     * Called from settings.php save path via admin_setting hook OR explicitly
     * after $ADMIN tree commits. We call this from lib.php's
     * bunnystream_after_config function so it runs on every settings save.
     */
    public static function sync_from_config_plugins(): void {
        // Bail when the database tables aren't ready yet (early boot,
        // PHPUnit init before fixture creation, install in progress).
        try {
            $libraryid    = get_config('mod_bunnystream', 'library_id');
            $apikey       = get_config('mod_bunnystream', 'api_key');
            $securitykey  = get_config('mod_bunnystream', 'security_key');
            $cdnhostname  = get_config('mod_bunnystream', 'cdn_hostname');
        } catch (\Throwable $e) {
            return;
        }

        // No-op if nothing useful is set.
        if (empty($libraryid) && empty($apikey) && empty($securitykey) && empty($cdnhostname)) {
            return;
        }

        config::save(
            $libraryid !== false ? (string)$libraryid : null,
            // Empty string means "clear" — but only if the field was actively cleared.
            // Treat the literal placeholder "***unset***" Moodle sometimes uses as no-change.
            $apikey === false ? null : ($apikey === '***unset***' ? null : (string)$apikey),
            $securitykey === false ? null : ($securitykey === '***unset***' ? null : (string)$securitykey),
            $cdnhostname !== false ? (string)$cdnhostname : null
        );

        // Scrub plaintext key material from {config_plugins} now that we've
        // moved it into the encrypted singleton row.
        if (!empty($apikey)) {
            set_config('api_key', '', 'mod_bunnystream');
        }
        if (!empty($securitykey)) {
            set_config('security_key', '', 'mod_bunnystream');
        }
    }
}
