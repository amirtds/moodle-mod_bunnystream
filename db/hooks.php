<?php
// GPLv3 — see LICENSE.

defined('MOODLE_INTERNAL') || die();

// No hook callbacks needed in v0.1 — credentials are encrypted at write-time
// by Moodle's admin_setting_encryptedpassword, not migrated post-hoc.
$callbacks = [];
