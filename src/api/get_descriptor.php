<?php

declare(strict_types=1);

header('Content-Type: application/json');
http_response_code(410);
echo json_encode([
    'success' => false,
    'message' => 'This endpoint has been retired to reduce biometric data exposure.'
]);
