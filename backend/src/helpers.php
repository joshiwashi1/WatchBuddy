<?php
declare(strict_types=1);

// helpers.php
// Response::json is available because you require response.php before helpers.php.

function parseJsonBody(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false) {
        Response::json(['ok' => false, 'error' => 'Could not read request body'], 400);
    }

    // Treat empty body as empty object (useful for endpoints that allow optional fields)
    if ($raw === '' || trim($raw) === '') {
        return [];
    }

    // Decode
    $data = json_decode($raw, true);

    // If decoding failed, report a JSON error (don’t fall back to $_POST on PUT/PATCH/DELETE)
    if (json_last_error() !== JSON_ERROR_NONE) {
        Response::json([
            'ok'         => false,
            'error'      => 'Body is not valid JSON',
            'json_error' => json_last_error_msg(),
        ], 400);
    }

    if (!is_array($data)) {
        Response::json(['ok' => false, 'error' => 'Body must be a JSON object'], 400);
    }

    return $data;
}
