<?php

declare(strict_types=1);

$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$publicRoot = realpath(__DIR__ . '/public');
$file = realpath(__DIR__ . '/public' . $path);

// The PHP development server was started from the project root, so returning
// false would make it search for /assets beside /public. Serve public assets
// directly while preventing path traversal and accidental PHP source exposure.
if ($path !== '/' && $publicRoot && $file && is_file($file) && str_starts_with($file, $publicRoot . DIRECTORY_SEPARATOR)) {
    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if ($extension !== 'php') {
        $mimeTypes = [
            'css' => 'text/css; charset=utf-8',
            'js' => 'application/javascript; charset=utf-8',
            'svg' => 'image/svg+xml; charset=utf-8',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'ico' => 'image/x-icon',
        ];
        header('Content-Type: ' . ($mimeTypes[$extension] ?? 'application/octet-stream'));
        header('Content-Length: ' . filesize($file));
        header('X-Content-Type-Options: nosniff');
        readfile($file);
        exit;
    }
}

require __DIR__ . '/public/index.php';

