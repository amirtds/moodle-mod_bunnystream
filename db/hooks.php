<?php
// GPLv3 — see LICENSE.

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook'     => \core\hook\after_config::class,
        'callback' => [\mod_bunnystream\hook_callbacks::class, 'after_config'],
    ],
];
