<?php
// GPLv3 — see LICENSE.
//
// Bunny.net Stream webhook receiver. Public endpoint.
// Auth: secret token in the URL query — Bunny does not sign payloads.
// Three guards (matches XBlock webhooks.py):
//   1. Constant-time secret match (DB-indexed lookup + hash_equals).
//   2. Library mismatch check (event VideoLibraryId vs configured).
//   3. Terminal-state regression guard (ready/failed can't be downgraded).

define('NO_MOODLE_COOKIES', true);
define('NO_DEBUG_DISPLAY', true);

require_once(__DIR__ . '/../../config.php');

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$token = optional_param('token', '', PARAM_ALPHANUMEXT);
if (!$token || strlen($token) < 16) {
    http_response_code(401);
    echo json_encode(['error' => 'malformed_token']);
    exit;
}

\mod_bunnystream\webhook_processor::process($token, file_get_contents('php://input') ?: '');
