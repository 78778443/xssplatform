<?php
/**
 * Mock DingTalk webhook for local integration tests.
 * POST /send stores the raw body; GET /last returns it.
 */
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if ($path === '/send' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    file_put_contents('/tmp/dingding-last.json', file_get_contents('php://input'));
    header('Content-Type: application/json');
    echo '{"errcode":0,"errmsg":"ok"}';
    return;
}
if ($path === '/last') {
    header('Content-Type: application/json');
    echo is_file('/tmp/dingding-last.json') ? (string) file_get_contents('/tmp/dingding-last.json') : '{}';
    return;
}
http_response_code(404);
echo 'not found';
