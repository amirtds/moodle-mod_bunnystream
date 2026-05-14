<?php
// GPLv3 — see LICENSE.

namespace mod_bunnystream;

defined('MOODLE_INTERNAL') || die();

class hook_callbacks {
    public static function after_config(\core\hook\after_config $hook): void {
        admin_settings_observer::sync_from_config_plugins();
    }
}
