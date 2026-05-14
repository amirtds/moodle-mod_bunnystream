<?php
// GPLv3 — see LICENSE.

defined('MOODLE_INTERNAL') || die();

$definitions = [
    'ratelimit' => [
        'mode'                   => cache_store::MODE_APPLICATION,
        'simpledata'             => true,
        'staticacceleration'     => true,
        'staticaccelerationsize' => 50,
        'ttl'                    => 60,
    ],
];
