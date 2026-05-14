<?php
// GPLv3 — see LICENSE.
//
// Moodle Mobile App component registration. Renders the signed iframe inside
// the Ionic webview. This is the partnership-grade differentiator — community
// plugins routinely fail in the mobile app because of Bunny's referrer rules;
// surfacing a freshly-signed token URL per session sidesteps that.

defined('MOODLE_INTERNAL') || die();

$addons = [
    'mod_bunnystream' => [
        'handlers' => [
            'view' => [
                'displaydata'   => ['icon' => $CFG->wwwroot . '/mod/bunnystream/pix/icon.png', 'class' => ''],
                'delegate'      => 'CoreCourseModuleDelegate',
                'method'        => 'mobile_view',
                'init'          => 'mobile_init',
                'offlinefunctions' => [],
                'styles'        => [
                    'url'     => $CFG->wwwroot . '/mod/bunnystream/styles/mobile.css',
                    'version' => 1,
                ],
            ],
        ],
        'lang' => [
            ['pluginname', 'mod_bunnystream'],
            ['video', 'mod_bunnystream'],
            ['error_no_video', 'mod_bunnystream'],
        ],
    ],
];
