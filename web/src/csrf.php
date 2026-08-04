<?php
declare(strict_types=1);

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    $token = csrf_token();
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

function csrf_verify(): void
{
    $token    = $_POST['_csrf'] ?? '';
    $expected = $_SESSION['csrf_token'] ?? '';

    if (!hash_equals($expected, $token)) {
        http_response_code(403);
        die('Invalid CSRF token. Please go back and try again.');
    }
}
