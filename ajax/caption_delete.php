<?php
// GPLv3 — see LICENSE.

require_once(__DIR__ . '/../../../config.php');

use mod_bunnystream\ajax_helper;
use mod_bunnystream\bunny_client;

$cfg = ajax_helper::require_manage();
$guid = required_param('guid', PARAM_ALPHANUMEXT);
$srclang = strtolower(trim(required_param('srclang', PARAM_RAW)));
if ($srclang === '') {
    ajax_helper::fail('missing_srclang', 400);
}

$client = new bunny_client($cfg);
try {
    $client->delete_caption($guid, $srclang);
    $captions = $client->list_captions($guid);
} catch (\Throwable $e) {
    ajax_helper::fail($e->getMessage(), 502);
}
ajax_helper::json(['ok' => true, 'captions' => $captions]);
