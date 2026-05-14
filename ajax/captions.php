<?php
// GPLv3 — see LICENSE.

require_once(__DIR__ . '/../../../config.php');

use mod_bunnystream\ajax_helper;
use mod_bunnystream\bunny_client;

global $DB;

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    require_login();
    $guid = required_param('guid', PARAM_ALPHANUMEXT);
    $row = $DB->get_record('bunnystream_videos', ['guid' => $guid]);
    if (!$row) ajax_helper::fail('unknown_video', 404);
    try {
        $cfg = \mod_bunnystream\config::decrypted();
        $client = new bunny_client($cfg);
        ajax_helper::json(['captions' => $client->list_captions($guid)]);
    } catch (\Throwable $e) {
        ajax_helper::fail($e->getMessage(), 502);
    }
}

// POST — upload a caption.
$cfg = ajax_helper::require_manage();
$guid = required_param('guid', PARAM_ALPHANUMEXT);
$srclang = strtolower(trim(required_param('srclang', PARAM_RAW)));
$label = substr(trim(optional_param('label', '', PARAM_TEXT)), 0, 60);

if (!preg_match('/^[a-zA-Z]{2,3}(-[a-zA-Z0-9]{2,8})?$/', $srclang)) {
    ajax_helper::fail('bad_srclang', 400);
}
if (empty($_FILES['vtt'])) {
    ajax_helper::fail('missing_file', 400);
}
$file = $_FILES['vtt'];
if ($file['size'] > 1 * 1024 * 1024) {
    ajax_helper::fail('vtt_too_large', 413);
}
$contenttype = strtolower($file['type'] ?? '');
if ($contenttype && !in_array($contenttype, ['text/vtt', 'text/plain', 'application/octet-stream'], true)) {
    ajax_helper::fail('unsupported_caption_type', 400);
}

$row = $DB->get_record('bunnystream_videos', ['guid' => $guid]);
if (!$row) ajax_helper::fail('unknown_video', 404);

$bytes = file_get_contents($file['tmp_name']);
if ($bytes === false) {
    ajax_helper::fail('read_failed', 500);
}

$client = new bunny_client($cfg);
try {
    $client->upload_caption($guid, $srclang, $label ?: strtoupper($srclang), $bytes);
    $captions = $client->list_captions($guid);
} catch (\Throwable $e) {
    ajax_helper::fail($e->getMessage(), 502);
}
ajax_helper::json(['ok' => true, 'captions' => $captions]);
