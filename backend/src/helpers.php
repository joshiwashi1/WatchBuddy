<?php
declare(strict_types=1);

function parseJsonBody(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode((string)$raw, true);
    if (!is_array($data)) {
        $data = $_POST ?? [];
    }
    return $data;
}
