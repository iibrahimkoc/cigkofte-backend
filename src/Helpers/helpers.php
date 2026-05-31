<?php
/**
 * Helpers - Yardımcı Fonksiyonlar
 */

function jsonResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function getPost(string $key, mixed $default = null): mixed
{
    return isset($_POST[$key]) ? trim($_POST[$key]) : $default;
}

function getQuery(string $key, mixed $default = null): mixed
{
    return isset($_GET[$key]) ? trim($_GET[$key]) : $default;
}

function getJsonBody(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function hashPassword(string $sifre): string
{
    return password_hash($sifre, PASSWORD_BCRYPT, ['cost' => 12]);
}

function escape(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
