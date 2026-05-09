<?php

declare(strict_types=1);

/**
 * Validate and normalize a face descriptor into a strict 128-float vector.
 *
 * @return array<int, float>|null
 */
function face_descriptor_normalize(array $descriptor): ?array
{
    if (count($descriptor) !== 128) {
        return null;
    }

    $normalized = [];
    foreach ($descriptor as $value) {
        if (!is_numeric($value)) {
            return null;
        }
        $normalized[] = (float) $value;
    }

    return $normalized;
}

/**
 * Euclidean (L2) norm for a face descriptor vector.
 */
function face_descriptor_norm(array $descriptor): float
{
    $sum = 0.0;
    foreach ($descriptor as $value) {
        $v = (float) $value;
        $sum += $v * $v;
    }
    return sqrt($sum);
}

/**
 * Euclidean distance between two descriptor vectors.
 */
function face_descriptor_distance(array $a, array $b): float
{
    $sum = 0.0;
    $len = min(count($a), count($b));
    for ($i = 0; $i < $len; $i++) {
        $diff = (float) $a[$i] - (float) $b[$i];
        $sum += $diff * $diff;
    }
    return sqrt($sum);
}

/**
 * Normalize names to improve duplicate matching consistency.
 */
function face_name_normalize(string $name): string
{
    $name = trim((string) preg_replace('/\s+/', ' ', $name));
    if (function_exists('mb_strtolower')) {
        return mb_strtolower($name, 'UTF-8');
    }
    return strtolower($name);
}
