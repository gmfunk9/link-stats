<?php

declare(strict_types=1);

function sendJsonResponse(array $payload): void
{
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES);

    if ($json === false) {
        $fallback = ['error' => 'Failed to encode JSON: ' . json_last_error_msg()];
        echo json_encode($fallback);
        exit;
    }

    echo $json;
    exit;
}

function buildErrorPayload(string $message): array
{
    return ['error' => $message];
}
