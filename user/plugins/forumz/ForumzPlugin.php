<?php

namespace GSpot\UserPlugins\Forumz;

use GSpot\Plugin\AbstractPlugin;
use Symfony\Component\Yaml\Yaml;

class ForumzPlugin extends AbstractPlugin
{
    private string $dataPath;

    public function __construct($app, array $config)
    {
        parent::__construct($app, $config);
        $this->dataPath = $app->rootPath() . '/user/plugins/forumz/data';
    }

    public function onPageRoute(mixed &$route): void {}
    public function onPageRender(mixed &$page): void {}

    // === Category Management ===

    public function createCategory(string $slug, array $data): void
    {
        $category = array_merge([
            'slug' => $slug,
            'title' => $data['title'] ?? ucfirst($slug),
            'description' => $data['description'] ?? '',
            'order' => $data['order'] ?? 0,
            'created' => date('Y-m-d H:i:s'),
        ], $data);

        $file = $this->dataPath . '/categories/' . $this->sanitize($slug) . '.yaml';
        file_put_contents($file, Yaml::dump($category, 4, 2));
    }

    public function getCategory(string $slug): ?array
    {
        $file = $this->dataPath . '/categories/' . $this->sanitize($slug) . '.yaml';
        if (!file_exists($file)) return null;
        return Yaml::parseFile($file);
    }

    public function listCategories(): array
    {
        $categories = [];
        $files = glob($this->dataPath . '/categories/*.yaml');
        foreach ($files as $file) {
            $cat = Yaml::parseFile($file);
            if (is_array($cat)) $categories[] = $cat;
        }
        usort($categories, fn($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));
        return $categories;
    }

    // === Thread Management ===

    public function createThread(string $category, array $data): string
    {
        $id = $this->generateId();
        $threadDir = $this->dataPath . '/threads/' . $this->sanitize($category);
        if (!is_dir($threadDir)) mkdir($threadDir, 0755, true);

        $thread = [
            'id' => $id,
            'category' => $category,
            'title' => $data['title'],
            'author' => $data['author'],
            'content' => $data['content'],
            'created' => date('Y-m-d H:i:s'),
            'updated' => date('Y-m-d H:i:s'),
            'pinned' => $data['pinned'] ?? false,
            'locked' => $data['locked'] ?? false,
            'replies' => [],
        ];

        $file = $threadDir . '/' . $id . '.yaml';
        file_put_contents($file, Yaml::dump($thread, 6, 2));
        return $id;
    }

    public function getThread(string $category, string $id): ?array
    {
        $file = $this->dataPath . '/threads/' . $this->sanitize($category) . '/' . $this->sanitize($id) . '.yaml';
        if (!file_exists($file)) return null;
        return Yaml::parseFile($file);
    }

    public function listThreads(string $category, int $page = 1, int $perPage = 20): array
    {
        $dir = $this->dataPath . '/threads/' . $this->sanitize($category);
        if (!is_dir($dir)) return [];

        $threads = [];
        $files = glob($dir . '/*.yaml');

        foreach ($files as $file) {
            $thread = Yaml::parseFile($file);
            if (is_array($thread)) $threads[] = $thread;
        }

        usort($threads, function ($a, $b) {
            if (($a['pinned'] ?? false) !== ($b['pinned'] ?? false)) {
                return ($b['pinned'] ?? false) <=> ($a['pinned'] ?? false);
            }
            return strcmp($b['updated'] ?? '', $a['updated'] ?? '');
        });

        $offset = ($page - 1) * $perPage;
        return array_slice($threads, $offset, $perPage);
    }

    // === Reply Management ===

    public function addReply(string $category, string $threadId, array $data): bool
    {
        $thread = $this->getThread($category, $threadId);
        if ($thread === null || ($thread['locked'] ?? false)) return false;

        $reply = [
            'id' => $this->generateId(),
            'author' => $data['author'],
            'content' => $data['content'],
            'created' => date('Y-m-d H:i:s'),
        ];

        $thread['replies'][] = $reply;
        $thread['updated'] = date('Y-m-d H:i:s');

        $file = $this->dataPath . '/threads/' . $this->sanitize($category) . '/' . $this->sanitize($threadId) . '.yaml';
        file_put_contents($file, Yaml::dump($thread, 6, 2));
        return true;
    }

    public function getReplyCount(string $category, string $threadId): int
    {
        $thread = $this->getThread($category, $threadId);
        return count($thread['replies'] ?? []);
    }

    // === Thread Moderation ===

    public function pinThread(string $category, string $threadId, bool $pinned = true): bool
    {
        return $this->updateThread($category, $threadId, ['pinned' => $pinned]);
    }

    public function lockThread(string $category, string $threadId, bool $locked = true): bool
    {
        return $this->updateThread($category, $threadId, ['locked' => $locked]);
    }

    public function deleteThread(string $category, string $threadId): bool
    {
        $file = $this->dataPath . '/threads/' . $this->sanitize($category) . '/' . $this->sanitize($threadId) . '.yaml';
        if (!file_exists($file)) return false;
        unlink($file);
        return true;
    }

    // === Search ===

    public function search(string $query, ?string $category = null): array
    {
        $results = [];
        $query = strtolower($query);

        $searchDirs = [];
        if ($category) {
            $searchDirs[] = $this->dataPath . '/threads/' . $this->sanitize($category);
        } else {
            $dirs = glob($this->dataPath . '/threads/*', GLOB_ONLYDIR);
            $searchDirs = $dirs ?: [];
        }

        foreach ($searchDirs as $dir) {
            $files = glob($dir . '/*.yaml');
            foreach ($files as $file) {
                $thread = Yaml::parseFile($file);
                if (!is_array($thread)) continue;

                $searchable = strtolower(($thread['title'] ?? '') . ' ' . ($thread['content'] ?? ''));
                if (str_contains($searchable, $query)) {
                    $results[] = [
                        'id' => $thread['id'],
                        'category' => $thread['category'] ?? basename($dir),
                        'title' => $thread['title'],
                        'author' => $thread['author'],
                        'replies' => count($thread['replies'] ?? []),
                    ];
                }
            }
        }

        return $results;
    }

    // === Helpers ===

    private function updateThread(string $category, string $threadId, array $data): bool
    {
        $thread = $this->getThread($category, $threadId);
        if ($thread === null) return false;

        $thread = array_merge($thread, $data);
        $file = $this->dataPath . '/threads/' . $this->sanitize($category) . '/' . $this->sanitize($threadId) . '.yaml';
        file_put_contents($file, Yaml::dump($thread, 6, 2));
        return true;
    }

    private function generateId(): string
    {
        return bin2hex(random_bytes(8));
    }

    private function sanitize(string $name): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '', $name);
    }
}
