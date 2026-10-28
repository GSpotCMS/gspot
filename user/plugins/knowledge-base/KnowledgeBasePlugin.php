<?php

namespace GSpot\UserPlugins\KnowledgeBase;

use GSpot\Plugin\AbstractPlugin;
use Symfony\Component\Yaml\Yaml;

class KnowledgeBasePlugin extends AbstractPlugin
{
    private string $dataPath;

    public function __construct($app, array $config)
    {
        parent::__construct($app, $config);
        $this->dataPath = $app->rootPath() . '/user/plugins/knowledge-base/data';
    }

    public function onPageRoute(mixed &$route): void {}
    public function onPageRender(mixed &$page): void {}

    // === Section Management ===

    public function createSection(string $slug, array $data): void
    {
        $section = [
            'slug' => $slug,
            'title' => $data['title'] ?? ucfirst($slug),
            'description' => $data['description'] ?? '',
            'icon' => $data['icon'] ?? '',
            'order' => $data['order'] ?? 0,
            'created' => date('Y-m-d H:i:s'),
        ];

        $dir = $this->dataPath . '/sections/' . $this->sanitize($slug);
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        file_put_contents($dir . '/section.yaml', Yaml::dump($section, 4, 2));
    }

    public function getSection(string $slug): ?array
    {
        $file = $this->dataPath . '/sections/' . $this->sanitize($slug) . '/section.yaml';
        if (!file_exists($file)) return null;
        return Yaml::parseFile($file);
    }

    public function listSections(): array
    {
        $sections = [];
        $dirs = glob($this->dataPath . '/sections/*', GLOB_ONLYDIR);

        foreach ($dirs as $dir) {
            $manifest = $dir . '/section.yaml';
            if (file_exists($manifest)) {
                $section = Yaml::parseFile($manifest);
                if (is_array($section)) {
                    $section['article_count'] = count(glob($dir . '/*.md'));
                    $sections[] = $section;
                }
            }
        }

        usort($sections, fn($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));
        return $sections;
    }

    // === Article Management ===

    public function createArticle(string $section, string $slug, array $data): void
    {
        $dir = $this->dataPath . '/sections/' . $this->sanitize($section);
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $frontMatter = [
            'title' => $data['title'],
            'slug' => $slug,
            'author' => $data['author'] ?? '',
            'order' => $data['order'] ?? 0,
            'created' => date('Y-m-d H:i:s'),
            'updated' => date('Y-m-d H:i:s'),
            'tags' => $data['tags'] ?? [],
        ];

        $content = $data['content'] ?? '';
        $md = "---\n" . Yaml::dump($frontMatter, 4, 2) . "---\n\n{$content}\n";

        file_put_contents($dir . '/' . $this->sanitize($slug) . '.md', $md);
    }

    public function getArticle(string $section, string $slug): ?array
    {
        $file = $this->dataPath . '/sections/' . $this->sanitize($section) . '/' . $this->sanitize($slug) . '.md';
        if (!file_exists($file)) return null;

        return $this->parseArticle($file);
    }

    public function listArticles(string $section): array
    {
        $dir = $this->dataPath . '/sections/' . $this->sanitize($section);
        if (!is_dir($dir)) return [];

        $articles = [];
        $files = glob($dir . '/*.md');

        foreach ($files as $file) {
            $article = $this->parseArticle($file);
            if ($article !== null) {
                $articles[] = $article;
            }
        }

        usort($articles, fn($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));
        return $articles;
    }

    public function updateArticle(string $section, string $slug, array $data): bool
    {
        $existing = $this->getArticle($section, $slug);
        if ($existing === null) return false;

        $frontMatter = $existing['meta'];
        if (isset($data['title'])) $frontMatter['title'] = $data['title'];
        if (isset($data['tags'])) $frontMatter['tags'] = $data['tags'];
        if (isset($data['order'])) $frontMatter['order'] = $data['order'];
        $frontMatter['updated'] = date('Y-m-d H:i:s');

        $content = $data['content'] ?? $existing['raw_content'];
        $md = "---\n" . Yaml::dump($frontMatter, 4, 2) . "---\n\n{$content}\n";

        $file = $this->dataPath . '/sections/' . $this->sanitize($section) . '/' . $this->sanitize($slug) . '.md';
        file_put_contents($file, $md);
        return true;
    }

    public function deleteArticle(string $section, string $slug): bool
    {
        $file = $this->dataPath . '/sections/' . $this->sanitize($section) . '/' . $this->sanitize($slug) . '.md';
        if (!file_exists($file)) return false;
        unlink($file);
        return true;
    }

    // === Table of Contents ===

    public function generateTOC(string $content): array
    {
        $toc = [];
        preg_match_all('/^(#{2,4})\s+(.+)$/m', $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $level = strlen($match[1]) - 1;
            $text = trim($match[2]);
            $id = strtolower(preg_replace('/[^a-z0-9]+/', '-', $text));

            $toc[] = [
                'level' => $level,
                'text' => $text,
                'id' => $id,
            ];
        }

        return $toc;
    }

    // === Ratings ===

    public function rateArticle(string $section, string $slug, int $rating, string $user = 'anonymous'): void
    {
        $ratingsFile = $this->dataPath . '/sections/' . $this->sanitize($section) . '/' . $this->sanitize($slug) . '.ratings.json';
        $ratings = file_exists($ratingsFile) ? json_decode(file_get_contents($ratingsFile), true) : [];

        $ratings[$user] = [
            'rating' => max(1, min(5, $rating)),
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        file_put_contents($ratingsFile, json_encode($ratings, JSON_PRETTY_PRINT));
    }

    public function getArticleRating(string $section, string $slug): array
    {
        $ratingsFile = $this->dataPath . '/sections/' . $this->sanitize($section) . '/' . $this->sanitize($slug) . '.ratings.json';
        if (!file_exists($ratingsFile)) {
            return ['average' => 0, 'count' => 0];
        }

        $ratings = json_decode(file_get_contents($ratingsFile), true) ?? [];
        if (empty($ratings)) {
            return ['average' => 0, 'count' => 0];
        }

        $values = array_map(fn($r) => $r['rating'], $ratings);
        return [
            'average' => round(array_sum($values) / count($values), 1),
            'count' => count($values),
        ];
    }

    // === Search ===

    public function search(string $query): array
    {
        $results = [];
        $query = strtolower($query);
        $sections = $this->listSections();

        foreach ($sections as $section) {
            $articles = $this->listArticles($section['slug']);
            foreach ($articles as $article) {
                $searchable = strtolower($article['title'] . ' ' . ($article['raw_content'] ?? ''));
                if (str_contains($searchable, $query)) {
                    $results[] = [
                        'section' => $section['slug'],
                        'section_title' => $section['title'],
                        'slug' => $article['slug'],
                        'title' => $article['title'],
                    ];
                }
            }
        }

        return $results;
    }

    // === Helpers ===

    private function parseArticle(string $file): ?array
    {
        $raw = file_get_contents($file);
        if ($raw === false) return null;

        $parts = preg_split('/^---\s*$/m', $raw, 3);
        $frontMatter = isset($parts[1]) ? Yaml::parse(trim($parts[1])) : [];
        $content = trim($parts[2] ?? '');

        if (!is_array($frontMatter)) $frontMatter = [];

        return [
            'title' => $frontMatter['title'] ?? basename($file, '.md'),
            'slug' => $frontMatter['slug'] ?? basename($file, '.md'),
            'author' => $frontMatter['author'] ?? '',
            'order' => $frontMatter['order'] ?? 0,
            'created' => $frontMatter['created'] ?? '',
            'updated' => $frontMatter['updated'] ?? '',
            'tags' => $frontMatter['tags'] ?? [],
            'meta' => $frontMatter,
            'raw_content' => $content,
            'toc' => $this->generateTOC($content),
        ];
    }

    private function sanitize(string $name): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '', $name);
    }
}
