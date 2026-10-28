<?php

namespace GSpot\UserPlugins\Mambers;

use GSpot\Plugin\AbstractPlugin;
use Symfony\Component\Yaml\Yaml;

class MambersPlugin extends AbstractPlugin
{
    private string $dataPath;

    public function __construct($app, array $config)
    {
        parent::__construct($app, $config);
        $this->dataPath = $app->rootPath() . '/user/plugins/mambers/data';
    }

    public function onPageRoute(mixed &$route): void
    {
        // Inject profile data when viewing member pages
    }

    public function onPageRender(mixed &$page): void
    {
        // Add member data to page context
    }

    // === Profile Management ===

    public function getProfile(string $username): ?array
    {
        $file = $this->dataPath . '/profiles/' . $this->sanitize($username) . '.yaml';
        if (!file_exists($file)) return null;
        return Yaml::parseFile($file);
    }

    public function createProfile(string $username, array $data): void
    {
        $profile = array_merge([
            'username' => $username,
            'display_name' => $data['display_name'] ?? $username,
            'bio' => $data['bio'] ?? '',
            'avatar' => $data['avatar'] ?? '',
            'joined' => date('Y-m-d'),
            'role' => $data['role'] ?? 'member',
        ], $data);

        $file = $this->dataPath . '/profiles/' . $this->sanitize($username) . '.yaml';
        file_put_contents($file, Yaml::dump($profile, 4, 2));
    }

    public function updateProfile(string $username, array $data): bool
    {
        $existing = $this->getProfile($username);
        if ($existing === null) return false;

        $merged = array_merge($existing, $data);
        $file = $this->dataPath . '/profiles/' . $this->sanitize($username) . '.yaml';
        file_put_contents($file, Yaml::dump($merged, 4, 2));
        return true;
    }

    public function listProfiles(): array
    {
        $profiles = [];
        $files = glob($this->dataPath . '/profiles/*.yaml');
        foreach ($files as $file) {
            $profile = Yaml::parseFile($file);
            if (is_array($profile)) {
                $profiles[] = $profile;
            }
        }
        return $profiles;
    }

    // === Social Graph ===

    public function follow(string $follower, string $target): void
    {
        $graph = $this->getGraph($follower);
        if (!in_array($target, $graph['following'])) {
            $graph['following'][] = $target;
            $this->saveGraph($follower, $graph);
        }

        $targetGraph = $this->getGraph($target);
        if (!in_array($follower, $targetGraph['followers'])) {
            $targetGraph['followers'][] = $follower;
            $this->saveGraph($target, $targetGraph);
        }

        $this->addFeedItem($target, [
            'type' => 'follow',
            'actor' => $follower,
            'message' => "{$follower} started following you",
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    public function unfollow(string $follower, string $target): void
    {
        $graph = $this->getGraph($follower);
        $graph['following'] = array_values(array_filter($graph['following'], fn($u) => $u !== $target));
        $this->saveGraph($follower, $graph);

        $targetGraph = $this->getGraph($target);
        $targetGraph['followers'] = array_values(array_filter($targetGraph['followers'], fn($u) => $u !== $follower));
        $this->saveGraph($target, $targetGraph);
    }

    public function block(string $blocker, string $target): void
    {
        $graph = $this->getGraph($blocker);
        if (!in_array($target, $graph['blocked'])) {
            $graph['blocked'][] = $target;
        }
        $graph['following'] = array_values(array_filter($graph['following'], fn($u) => $u !== $target));
        $this->saveGraph($blocker, $graph);

        $this->unfollow($target, $blocker);
    }

    public function getFollowers(string $username): array
    {
        $graph = $this->getGraph($username);
        return $graph['followers'];
    }

    public function getFollowing(string $username): array
    {
        $graph = $this->getGraph($username);
        return $graph['following'];
    }

    public function isFollowing(string $follower, string $target): bool
    {
        $graph = $this->getGraph($follower);
        return in_array($target, $graph['following']);
    }

    public function isBlocked(string $blocker, string $target): bool
    {
        $graph = $this->getGraph($blocker);
        return in_array($target, $graph['blocked']);
    }

    // === Activity Feed ===

    public function addFeedItem(string $username, array $item): void
    {
        $feedFile = $this->dataPath . '/feed/' . $this->sanitize($username) . '.json';
        $feed = file_exists($feedFile) ? json_decode(file_get_contents($feedFile), true) : [];

        array_unshift($feed, $item);
        $feed = array_slice($feed, 0, 100); // Keep last 100 items

        file_put_contents($feedFile, json_encode($feed, JSON_PRETTY_PRINT));
    }

    public function getFeed(string $username, int $limit = 20): array
    {
        $feedFile = $this->dataPath . '/feed/' . $this->sanitize($username) . '.json';
        if (!file_exists($feedFile)) return [];

        $feed = json_decode(file_get_contents($feedFile), true) ?? [];
        return array_slice($feed, 0, $limit);
    }

    public function getTimelineFeed(string $username, int $limit = 20): array
    {
        $following = $this->getFollowing($username);
        $following[] = $username;

        $allItems = [];
        foreach ($following as $user) {
            $items = $this->getFeed($user, 50);
            foreach ($items as $item) {
                $allItems[] = $item;
            }
        }

        usort($allItems, fn($a, $b) => strcmp($b['timestamp'] ?? '', $a['timestamp'] ?? ''));
        return array_slice($allItems, 0, $limit);
    }

    // === Helpers ===

    private function getGraph(string $username): array
    {
        $file = $this->dataPath . '/graph/' . $this->sanitize($username) . '.yaml';
        if (!file_exists($file)) {
            return ['following' => [], 'followers' => [], 'blocked' => []];
        }
        $data = Yaml::parseFile($file);
        return array_merge(['following' => [], 'followers' => [], 'blocked' => []], $data ?? []);
    }

    private function saveGraph(string $username, array $graph): void
    {
        $file = $this->dataPath . '/graph/' . $this->sanitize($username) . '.yaml';
        file_put_contents($file, Yaml::dump($graph, 4, 2));
    }

    private function sanitize(string $name): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '', $name);
    }
}
