<?php
// GPLv3 — see LICENSE.

require_once(__DIR__ . '/../../../config.php');

use mod_bunnystream\ajax_helper;
use mod_bunnystream\bunny_client;

$cfg = ajax_helper::require_manage();
$body = ajax_helper::read_json_body();
$guid = trim((string)($body['guid'] ?? ''));
$language = strtolower(trim((string)($body['language'] ?? 'en')));
$force = !empty($body['force']);

if ($guid === '') ajax_helper::fail('missing_guid', 400);
if (!preg_match('/^[a-zA-Z]{2,3}(-[a-zA-Z0-9]{2,8})?$/', $language)) {
    ajax_helper::fail('bad_language', 400);
}

$client = new bunny_client($cfg);
try {
    $client->transcribe($guid, $language, $force);
} catch (\Throwable $e) {
    ajax_helper::fail($e->getMessage(), 502);
}
ajax_helper::json(['ok' => true, 'language' => $language]);
