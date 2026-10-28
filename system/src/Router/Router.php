<?php

declare(strict_types=1);

namespace GSpot\Router;

use GSpot\Config\ConfigManager;
use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Extension\FrontMatter\FrontMatterExtension;
use League\CommonMark\Extension\FrontMatter\Output\RenderedContentWithFrontMatter;
use League\CommonMark\Environment\Environment;
use League\CommonMark\MarkdownConverter;

class Router
{
    private string $contentPath;
    private ConfigManager $config;
    private MarkdownConverter $markdown;

    public function __construct(string $contentPath, ConfigManager $config)
    {
        $this->contentPath = $contentPath;
        $this->config = $config;

        $environment = new Environment([]);
        $environment->addExtension(new \League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension());
        $environment->addExtension(new FrontMatterExtension());
        $this->markdown = new MarkdownConverter($environment);
    }

    public function resolve(string $uri): ?Route
    {
        $uri = '/' . trim($uri, '/');

        if ($uri === '/') {
            $uri = '/home';
        }

        $segments = explode('/', trim($uri, '/'));
        $page = $this->findPage($segments);

        if ($page === null) {
            return null;
        }

        return new Route($uri, $page);
    }

    public function listPages(string $subpath = ''): array
    {
        $path = $this->contentPath . ($subpath ? '/' . trim($subpath, '/') : '');
        $pages = [];

        if (!is_dir($path)) {
            return $pages;
        }

        $dirs = glob($path . '/*', GLOB_ONLYDIR);
        if ($dirs === false) {
            return $pages;
        }

        sort($dirs);

        foreach ($dirs as $dir) {
            $pageFile = $dir . '/page.md';
            if (file_exists($pageFile)) {
                $page = $this->parsePage($pageFile, basename($dir));
                if ($page !== null) {
                    $pages[] = $page;
                }
            }
        }

        return $pages;
    }

    private function findPage(array $segments): ?Page
    {
        $current = $this->contentPath;

        foreach ($segments as $segment) {
            $found = $this->matchSegment($current, $segment);
            if ($found === null) {
                return null;
            }
            $current = $found;
        }

        $pageFile = $current . '/page.md';
        if (!file_exists($pageFile)) {
            return null;
        }

        return $this->parsePage($pageFile, basename($current));
    }

    private function matchSegment(string $parentDir, string $slug): ?string
    {
        if (!is_dir($parentDir)) {
            return null;
        }

        $dirs = glob($parentDir . '/*', GLOB_ONLYDIR);
        if ($dirs === false) {
            return null;
        }

        foreach ($dirs as $dir) {
            $dirName = basename($dir);
            $cleanName = preg_replace('/^\d+\./', '', $dirName);
            if ($cleanName === $slug) {
                return $dir;
            }
        }

        return null;
    }

    private function parsePage(string $filePath, string $folder): ?Page
    {
        $raw = file_get_contents($filePath);
        if ($raw === false) {
            return null;
        }

        $result = $this->markdown->convert($raw);

        $frontMatter = [];
        if ($result instanceof RenderedContentWithFrontMatter) {
            $frontMatter = $result->getFrontMatter();
        }

        $slug = preg_replace('/^\d+\./', '', $folder);

        return new Page(
            slug: $slug,
            title: $frontMatter['title'] ?? ucfirst($slug),
            content: $result->getContent(),
            template: $frontMatter['template'] ?? 'default',
            meta: $frontMatter,
            filePath: $filePath,
        );
    }
}
