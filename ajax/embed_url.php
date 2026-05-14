<?php
// GPLv3 — see LICENSE.

define('AJAX_SCRIPT', true);
define('NO_DEBUG_DISPLAY', true);
require_once(__DIR__ . '/../../../config.php');

use mod_bunnystream\ajax_helper;
use mod_bunnystream\token;

global $DB;

require_login(null, false);

$guid = optional_param('guid', '', PARAM_ALPHANUMEXT);
if ($guid === '') {
    ajax_helper::fail('missing_guid', 400);
}

$row = $DB->get_record('bunnystream_videos', ['guid' => $guid]);
if (!$row) {
    ajax_helper::fail('unknown_video', 404);
}

try {
    $cfg = \mod_bunnystream\config::decrypted();
} catch (\Throwable $e) {
    ajax_helper::json(['url' => token::unsigned_embed($row->library_id, $guid, [
        'autoplay' => 'true', 'preload' => 'true', 'responsive' => 'true',
    ])]);
}

$url = token::embed_url_for($row->library_id, $guid, $cfg->security_key, [
    'autoplay' => 'true', 'preload' => 'true', 'responsive' => 'true',
]);
ajax_helper::json(['url' => $url]);
