<?php

declare(strict_types=1);

/**
 * GSpot CMS — Development Server Router
 * 
 * Routes requests through the GSpot application.
 * Usage: php -S localhost:8080 -t public system/server.php
 */

define('GSPOT_ROOT', dirname(__DIR__));

require GSPOT_ROOT . '/vendor/autoload.php';

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Serve static files directly
$publicFile = GSPOT_ROOT . '/public' . $uri;
if ($uri !== '/' && file_exists($publicFile) && is_file($publicFile)) {
    return false;
}

// Serve theme assets (/assets/* → user/themes/{active}/assets/*)
if (str_starts_with($uri, '/assets/')) {
    $config = new \GSpot\Config\ConfigManager(
        GSPOT_ROOT . '/system/config',
        GSPOT_ROOT . '/user/config'
    );
    $theme = (string) $config->get('system.theme', 'starter');
    $relative = ltrim(substr($uri, strlen('/assets/')), '/');
    $assetFile = GSPOT_ROOT . '/user/themes/' . $theme . '/assets/' . $relative;

    if ($relative !== '' && file_exists($assetFile) && is_file($assetFile)) {
        $mimes = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'woff2' => 'font/woff2',
        ];
        $ext = pathinfo($assetFile, PATHINFO_EXTENSION);
        if (isset($mimes[$ext])) {
            header('Content-Type: ' . $mimes[$ext]);
        }
        readfile($assetFile);
        return;
    }

    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Theme asset not found.';
    return;
}

// Serve plugin assets
if (preg_match('#^/plugin-assets/([a-z0-9-]+)/(.+)$#', $uri, $m)) {
    $assetFile = GSPOT_ROOT . '/user/plugins/' . $m[1] . '/assets/' . $m[2];
    if (file_exists($assetFile) && is_file($assetFile)) {
        $mimes = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'woff2' => 'font/woff2',
        ];
        $ext = pathinfo($assetFile, PATHINFO_EXTENSION);
        if (isset($mimes[$ext])) {
            header('Content-Type: ' . $mimes[$ext]);
        }
        readfile($assetFile);
        return;
    }
}

// Serve uploaded media
if (str_starts_with($uri, '/media/')) {
    $mediaFile = GSPOT_ROOT . '/user' . $uri;
    if (file_exists($mediaFile) && is_file($mediaFile)) {
        $mimeType = mime_content_type($mediaFile);
        header('Content-Type: ' . $mimeType);
        readfile($mediaFile);
        return;
    }
}

use GSpot\Application;
use GSpot\Admin\AdminRouter;
use Symfony\Component\HttpFoundation\Request;

$app = Application::getInstance();
$app->boot();

// Commerce plugin HTTP (cart, checkout, storefront)
if (str_starts_with($uri, '/commerce')) {
    $commerce = $app->plugins()->getPlugin('commerce');
    if ($commerce !== null && method_exists($commerce, 'handleHttp')) {
        $request = Request::createFromGlobals();
        $response = $commerce->handleHttp($uri, $request);
        if ($response !== null) {
            $response->send();
            return;
        }
    }
}

// Handle admin routes
if (str_starts_with($uri, '/' . trim($app->config()->get('system.admin.prefix', 'gspot-admin'), '/'))) {
    $admin = new AdminRouter($app);
    $request = Request::createFromGlobals();
    $response = $admin->dispatch($request);
    $response->send();
    return;
}

// Handle the request
$response = $app->handleRequest($uri, $_SERVER['REQUEST_METHOD']);

echo $response;
