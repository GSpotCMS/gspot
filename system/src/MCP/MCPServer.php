<?php

declare(strict_types=1);

namespace GSpot\MCP;

use GSpot\Application;
use GSpot\Security\PathGuard;

/**
 * GSpot MCP Server — stdio transport
 * 
 * Implements the Model Context Protocol for AI-native CMS management.
 * Communicates via JSON-RPC 2.0 over stdin/stdout.
 */
class MCPServer
{
    private Application $app;
    private array $tools = [];
    private bool $running = false;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->registerCoreTools();
    }

    public function run(): void
    {
        $this->running = true;
        $stdin = fopen('php://stdin', 'r');
        $buffer = '';

        while ($this->running) {
            $line = fgets($stdin);
            if ($line === false) {
                break;
            }

            $buffer .= $line;

            $message = json_decode(trim($buffer), true);
            if ($message !== null) {
                $buffer = '';
                $this->handleMessage($message);
            }
        }

        fclose($stdin);
    }

    public function registerTool(string $name, array $schema, callable $handler): void
    {
        $this->tools[$name] = [
            'schema' => $schema,
            'handler' => $handler,
        ];
    }

    private function handleMessage(array $message): void
    {
        $method = $message['method'] ?? '';
        $id = $message['id'] ?? null;
        $params = $message['params'] ?? [];

        $response = match ($method) {
            'initialize' => $this->handleInitialize($params),
            'tools/list' => $this->handleToolsList(),
            'tools/call' => $this->handleToolCall($params),
            'shutdown' => $this->handleShutdown(),
            default => $this->error($id, -32601, "Method not found: {$method}"),
        };

        if ($id !== null) {
            $response['id'] = $id;
        }
        $response['jsonrpc'] = '2.0';

        $this->send($response);
    }

    private function handleInitialize(array $params): array
    {
        return [
            'result' => [
                'protocolVersion' => '2024-11-05',
                'capabilities' => [
                    'tools' => ['listChanged' => false],
                ],
                'serverInfo' => [
                    'name' => 'gspot-cms',
                    'version' => Application::VERSION,
                ],
            ],
        ];
    }

    private function handleToolsList(): array
    {
        $tools = [];
        foreach ($this->tools as $name => $tool) {
            $tools[] = [
                'name' => $name,
                'description' => $tool['schema']['description'] ?? '',
                'inputSchema' => $tool['schema']['inputSchema'] ?? ['type' => 'object', 'properties' => []],
            ];
        }

        return ['result' => ['tools' => $tools]];
    }

    private function handleToolCall(array $params): array
    {
        $name = $params['name'] ?? '';
        $arguments = $params['arguments'] ?? [];

        if (!isset($this->tools[$name])) {
            return [
                'result' => [
                    'content' => [['type' => 'text', 'text' => "Unknown tool: {$name}"]],
                    'isError' => true,
                ],
            ];
        }

        try {
            $result = ($this->tools[$name]['handler'])($arguments);
            return [
                'result' => [
                    'content' => [['type' => 'text', 'text' => is_string($result) ? $result : json_encode($result, JSON_PRETTY_PRINT)]],
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'result' => [
                    'content' => [['type' => 'text', 'text' => "Error: " . $e->getMessage()]],
                    'isError' => true,
                ],
            ];
        }
    }

    private function handleShutdown(): array
    {
        $this->running = false;
        return ['result' => null];
    }

    private function error(?int $id, int $code, string $message): array
    {
        return ['error' => ['code' => $code, 'message' => $message]];
    }

    private function send(array $response): void
    {
        $json = json_encode($response, JSON_UNESCAPED_SLASHES);
        fwrite(STDOUT, $json . "\n");
        fflush(STDOUT);
    }

    private function registerCoreTools(): void
    {
        $this->registerTool('gspot_list_content', [
            'description' => 'List all content pages in the GSpot CMS site',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'path' => ['type' => 'string', 'description' => 'Subpath to list (optional)'],
                ],
            ],
        ], function (array $args): string {
            $path = $args['path'] ?? '';
            $pages = $this->app->router()->listPages($path);
            $result = array_map(fn($p) => [
                'slug' => $p->slug(),
                'title' => $p->title(),
                'template' => $p->template(),
            ], $pages);
            return json_encode($result, JSON_PRETTY_PRINT);
        });

        $this->registerTool('gspot_create_page', [
            'description' => 'Create a new content page',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'slug' => ['type' => 'string', 'description' => 'Page URL slug'],
                    'title' => ['type' => 'string', 'description' => 'Page title'],
                    'content' => ['type' => 'string', 'description' => 'Markdown content'],
                    'template' => ['type' => 'string', 'description' => 'Template name (default: default)'],
                ],
                'required' => ['slug', 'title', 'content'],
            ],
        ], function (array $args): string {
            $slug = PathGuard::sanitizeSlug($args['slug']);
            if ($slug === '') {
                return 'Error: Invalid slug.';
            }
            $title = $args['title'];
            $content = $args['content'];
            $template = $args['template'] ?? 'default';

            $contentRoot = $this->app->rootPath() . '/user/content';
            $existing = glob($contentRoot . '/*');
            $order = count($existing) + 1;
            $dirName = str_pad((string)$order, 2, '0', STR_PAD_LEFT) . '.' . $slug;
            $dir = PathGuard::resolveUnder($contentRoot, $dirName);
            if ($dir === null) {
                return 'Error: Invalid page path.';
            }

            mkdir($dir, 0755, true);

            $md = "---\ntitle: {$title}\ntemplate: {$template}\n---\n\n{$content}\n";
            file_put_contents($dir . '/page.md', $md);

            return "Page created: {$dirName}/page.md";
        });

        $this->registerTool('gspot_edit_page', [
            'description' => 'Edit an existing content page',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'slug' => ['type' => 'string', 'description' => 'Page slug to edit'],
                    'title' => ['type' => 'string', 'description' => 'New title (optional)'],
                    'content' => ['type' => 'string', 'description' => 'New markdown content (optional)'],
                    'template' => ['type' => 'string', 'description' => 'New template (optional)'],
                ],
                'required' => ['slug'],
            ],
        ], function (array $args): string {
            $slug = PathGuard::sanitizeSlug($args['slug']);
            if ($slug === '') {
                return 'Error: Invalid slug.';
            }
            $contentDir = $this->app->rootPath() . '/user/content';
            $dirs = glob($contentDir . '/*', GLOB_ONLYDIR);

            foreach ($dirs as $dir) {
                $cleanName = preg_replace('/^\d+\./', '', basename($dir));
                if ($cleanName === $slug) {
                    $file = PathGuard::resolveUnder($dir, 'page.md');
                    if ($file === null) {
                        return 'Error: Invalid page path.';
                    }
                    if (!file_exists($file)) {
                        return "Error: page.md not found in {$dir}";
                    }

                    $raw = file_get_contents($file);
                    $parts = preg_split('/^---\s*$/m', $raw, 3);

                    $frontMatter = isset($parts[1]) ? \Symfony\Component\Yaml\Yaml::parse(trim($parts[1])) : [];
                    $body = trim($parts[2] ?? '');

                    if (isset($args['title'])) $frontMatter['title'] = $args['title'];
                    if (isset($args['template'])) $frontMatter['template'] = $args['template'];
                    if (isset($args['content'])) $body = $args['content'];

                    $yaml = \Symfony\Component\Yaml\Yaml::dump($frontMatter);
                    $md = "---\n{$yaml}---\n\n{$body}\n";
                    file_put_contents($file, $md);

                    return "Page '{$slug}' updated.";
                }
            }

            return "Error: Page '{$slug}' not found.";
        });

        $this->registerTool('gspot_delete_page', [
            'description' => 'Delete a content page',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'slug' => ['type' => 'string', 'description' => 'Page slug to delete'],
                ],
                'required' => ['slug'],
            ],
        ], function (array $args): string {
            $slug = PathGuard::sanitizeSlug($args['slug']);
            if ($slug === '') {
                return 'Error: Invalid slug.';
            }
            $contentDir = $this->app->rootPath() . '/user/content';
            $dirs = glob($contentDir . '/*', GLOB_ONLYDIR);

            foreach ($dirs as $dir) {
                $cleanName = preg_replace('/^\d+\./', '', basename($dir));
                if ($cleanName === $slug) {
                    $this->deleteDir($dir);
                    return "Page '{$slug}' deleted.";
                }
            }

            return "Error: Page '{$slug}' not found.";
        });

        $this->registerTool('gspot_search_content', [
            'description' => 'Search page content by keyword',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'query' => ['type' => 'string', 'description' => 'Search query'],
                ],
                'required' => ['query'],
            ],
        ], function (array $args): string {
            $query = strtolower($args['query']);
            $contentDir = $this->app->rootPath() . '/user/content';
            $results = [];

            $dirs = glob($contentDir . '/*', GLOB_ONLYDIR);
            foreach ($dirs as $dir) {
                $file = $dir . '/page.md';
                if (!file_exists($file)) continue;

                $content = strtolower(file_get_contents($file));
                if (str_contains($content, $query)) {
                    $results[] = preg_replace('/^\d+\./', '', basename($dir));
                }
            }

            return empty($results)
                ? "No pages found matching '{$args['query']}'."
                : "Found in: " . implode(', ', $results);
        });

        $this->registerTool('gspot_get_config', [
            'description' => 'Get a configuration value',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'key' => ['type' => 'string', 'description' => 'Dot-notation config key (e.g. site.title)'],
                ],
                'required' => ['key'],
            ],
        ], function (array $args): string {
            $value = $this->app->config()->get($args['key']);
            return $value !== null ? json_encode($value) : "Config key '{$args['key']}' not found.";
        });

        $this->registerTool('gspot_set_config', [
            'description' => 'Set a configuration value',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'key' => ['type' => 'string', 'description' => 'Dot-notation config key'],
                    'value' => ['type' => 'string', 'description' => 'Value to set'],
                ],
                'required' => ['key', 'value'],
            ],
        ], function (array $args): string {
            $key = $args['key'];
            $value = $args['value'];

            $parts = explode('.', $key);
            $file = $parts[0];
            $configKey = implode('.', array_slice($parts, 1));

            $this->app->config()->set($key, $value);

            $fileConfig = $this->app->config()->get($file, []);
            $this->app->config()->save($file, is_array($fileConfig) ? $fileConfig : [$configKey => $value]);

            return "Config '{$key}' set to '{$value}'.";
        });

        $this->registerTool('gspot_list_plugins', [
            'description' => 'List installed plugins and their status',
            'inputSchema' => ['type' => 'object', 'properties' => []],
        ], function (array $args): string {
            $pluginsDir = $this->app->rootPath() . '/user/plugins';
            $plugins = [];

            if (is_dir($pluginsDir)) {
                $dirs = glob($pluginsDir . '/*', GLOB_ONLYDIR);
                foreach ($dirs as $dir) {
                    $manifest = $dir . '/plugin.yaml';
                    if (file_exists($manifest)) {
                        $config = \Symfony\Component\Yaml\Yaml::parseFile($manifest);
                        $plugins[] = [
                            'name' => $config['name'] ?? basename($dir),
                            'version' => $config['version'] ?? '0.0.0',
                            'enabled' => $config['enabled'] ?? true,
                        ];
                    }
                }
            }

            return json_encode($plugins, JSON_PRETTY_PRINT);
        });

        $this->registerTool('gspot_toggle_plugin', [
            'description' => 'Enable or disable a plugin',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string', 'description' => 'Plugin name'],
                    'enabled' => ['type' => 'boolean', 'description' => 'Enable (true) or disable (false)'],
                ],
                'required' => ['name', 'enabled'],
            ],
        ], function (array $args): string {
            $pluginsDir = $this->app->rootPath() . '/user/plugins';
            $name = PathGuard::sanitizeName($args['name']);
            $enabled = $args['enabled'];
            $manifest = $name !== '' ? PathGuard::resolveUnder($pluginsDir, $name, 'plugin.yaml') : null;

            if ($manifest === null || !file_exists($manifest)) {
                return "Error: Plugin '{$name}' not found.";
            }

            $config = \Symfony\Component\Yaml\Yaml::parseFile($manifest);
            $config['enabled'] = $enabled;
            file_put_contents($manifest, \Symfony\Component\Yaml\Yaml::dump($config, 4, 2));

            return "Plugin '{$name}' " . ($enabled ? 'enabled' : 'disabled') . ".";
        });

        $this->registerTool('gspot_set_theme', [
            'description' => 'Set the active theme',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string', 'description' => 'Theme directory name'],
                ],
                'required' => ['name'],
            ],
        ], function (array $args): string {
            $name = PathGuard::sanitizeName($args['name']);
            if ($name === '') {
                return 'Error: Invalid theme name.';
            }
            $themeDir = PathGuard::resolveUnder($this->app->rootPath() . '/user/themes', $name);

            if ($themeDir === null || !is_dir($themeDir)) {
                return "Error: Theme '{$name}' not found.";
            }

            $systemConfig = $this->app->config()->get('system', []);
            $systemConfig['theme'] = $name;
            $this->app->config()->save('system', $systemConfig);
            $this->app->themes()->setTheme($name);

            return "Theme set to '{$name}'.";
        });

        $this->registerTool('gspot_apply_vibe', [
            'description' => 'Apply a vibe preset to the active theme',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'preset' => ['type' => 'string', 'description' => 'Vibe preset name (e.g. synthwave, minimal)'],
                ],
                'required' => ['preset'],
            ],
        ], function (array $args): string {
            $preset = $args['preset'];
            $this->app->themes()->applyVibe($preset);
            $tokens = $this->app->themes()->vibeTokens();
            return "Vibe '{$preset}' applied. Tokens: " . json_encode($tokens);
        });

        $this->registerTool('gspot_list_themes', [
            'description' => 'List all installed themes',
            'inputSchema' => ['type' => 'object', 'properties' => []],
        ], function (array $args): string {
            $themes = $this->app->themes()->listThemes();
            return json_encode($themes, JSON_PRETTY_PRINT);
        });

        $this->registerTool('gspot_list_media', [
            'description' => 'List all files in the media library',
            'inputSchema' => ['type' => 'object', 'properties' => []],
        ], function (array $args): string {
            $mediaDir = $this->app->rootPath() . '/user/media';
            $files = [];

            if (is_dir($mediaDir)) {
                $items = scandir($mediaDir);
                foreach ($items as $item) {
                    if ($item === '.' || $item === '..') continue;
                    $path = $mediaDir . '/' . $item;
                    if (is_file($path)) {
                        $files[] = [
                            'name' => $item,
                            'size' => filesize($path),
                            'url' => '/media/' . $item,
                        ];
                    }
                }
            }

            return json_encode($files, JSON_PRETTY_PRINT);
        });

        $this->registerTool('gspot_upload_media', [
            'description' => 'Upload a file to the media library (base64 encoded content)',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'filename' => ['type' => 'string', 'description' => 'File name with extension'],
                    'content_base64' => ['type' => 'string', 'description' => 'Base64-encoded file content'],
                ],
                'required' => ['filename', 'content_base64'],
            ],
        ], function (array $args): string {
            $mediaDir = $this->app->rootPath() . '/user/media';
            if (!is_dir($mediaDir)) mkdir($mediaDir, 0755, true);

            $name = PathGuard::sanitizeFilename($args['filename']);
            if ($name === '') {
                return 'Error: Invalid filename.';
            }
            $content = base64_decode($args['content_base64']);

            if ($content === false) {
                return "Error: Invalid base64 content.";
            }

            $dest = PathGuard::resolveUnder($mediaDir, $name);
            if ($dest === null) {
                return 'Error: Invalid media path.';
            }

            file_put_contents($dest, $content);
            return "Uploaded: /media/{$name}";
        });

        $this->registerTool('gspot_backup', [
            'description' => 'Create a backup of the user directory as a zip archive',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string', 'description' => 'Backup filename (optional, auto-generated if omitted)'],
                ],
            ],
        ], function (array $args): string {
            $name = PathGuard::sanitizeFilename($args['name'] ?? 'backup-' . date('Y-m-d-His') . '.zip');
            if ($name === '') {
                return 'Error: Invalid backup name.';
            }
            $backupDir = $this->app->rootPath() . '/user/.backups';
            if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);

            $zipPath = PathGuard::resolveUnder($backupDir, $name);
            if ($zipPath === null) {
                return 'Error: Invalid backup path.';
            }
            $userDir = $this->app->rootPath() . '/user';

            if (!class_exists('ZipArchive')) {
                return "Error: ZipArchive extension not available.";
            }

            $zip = new \ZipArchive();
            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                return "Error: Could not create zip archive.";
            }

            $this->addDirToZip($zip, $userDir, 'user/', ['.cache', '.backups']);
            $zip->close();

            $size = round(filesize($zipPath) / 1024, 1);
            return "Backup created: {$name} ({$size} KB)";
        });

        $this->registerTool('gspot_restore', [
            'description' => 'List available backups (restore is manual for safety)',
            'inputSchema' => ['type' => 'object', 'properties' => []],
        ], function (array $args): string {
            $backupDir = $this->app->rootPath() . '/user/.backups';
            if (!is_dir($backupDir)) return "No backups found.";

            $files = glob($backupDir . '/*.zip');
            $backups = [];
            foreach ($files as $file) {
                $backups[] = [
                    'name' => basename($file),
                    'size' => round(filesize($file) / 1024, 1) . ' KB',
                    'date' => date('Y-m-d H:i', filemtime($file)),
                ];
            }

            return empty($backups) ? "No backups found." : json_encode($backups, JSON_PRETTY_PRINT);
        });

        $this->registerTool('gspot_scaffold_plugin', [
            'description' => 'Scaffold a new plugin with boilerplate',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string', 'description' => 'Plugin name (kebab-case)'],
                    'description' => ['type' => 'string', 'description' => 'Plugin description'],
                ],
                'required' => ['name'],
            ],
        ], function (array $args): string {
            try {
                $name = PathGuard::sanitizeName($args['name']);
                if ($name === '') {
                    return 'Error: Invalid plugin name.';
                }
                $this->app->scaffoldPlugin($name);
            } catch (\InvalidArgumentException $e) {
                return 'Error: ' . $e->getMessage();
            }
            return "Plugin '{$name}' scaffolded at user/plugins/{$name}/";
        });

        $this->registerTool('gspot_scaffold_theme', [
            'description' => 'Scaffold a new theme with boilerplate',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string', 'description' => 'Theme name (kebab-case)'],
                ],
                'required' => ['name'],
            ],
        ], function (array $args): string {
            try {
                $name = PathGuard::sanitizeName($args['name']);
                if ($name === '') {
                    return 'Error: Invalid theme name.';
                }
                $this->app->scaffoldTheme($name);
            } catch (\InvalidArgumentException $e) {
                return 'Error: ' . $e->getMessage();
            }
            return "Theme '{$name}' scaffolded at user/themes/{$name}/";
        });

        $this->registerTool('gspot_list_products', [
            'description' => 'List commerce products (requires commerce plugin)',
            'inputSchema' => ['type' => 'object', 'properties' => []],
        ], function (array $args): string {
            $registry = $this->commerceRegistry();
            if ($registry === null) {
                return 'Error: commerce plugin not installed.';
            }

            return json_encode($registry->all(false), JSON_PRETTY_PRINT);
        });

        $this->registerTool('gspot_save_product', [
            'description' => 'Create or update a commerce product',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'slug' => ['type' => 'string'],
                    'title' => ['type' => 'string'],
                    'description' => ['type' => 'string'],
                    'price_cents' => ['type' => 'integer'],
                    'currency' => ['type' => 'string'],
                    'active' => ['type' => 'boolean'],
                    'stock' => ['type' => 'integer', 'description' => 'Omit or null for unlimited'],
                ],
                'required' => ['slug', 'title', 'price_cents'],
            ],
        ], function (array $args): string {
            $registry = $this->commerceRegistry();
            if ($registry === null) {
                return 'Error: commerce plugin not installed.';
            }
            $slug = PathGuard::sanitizeSlug($args['slug']);
            if ($slug === '') {
                return 'Error: Invalid slug.';
            }
            try {
                $meta = [
                    'title' => $args['title'],
                    'description' => $args['description'] ?? '',
                    'price_cents' => (int) $args['price_cents'],
                    'currency' => $args['currency'] ?? 'aud',
                    'active' => $args['active'] ?? true,
                ];
                if (array_key_exists('stock', $args)) {
                    $meta['stock'] = $args['stock'];
                }
                $registry->save($slug, $meta);
            } catch (\InvalidArgumentException $e) {
                return 'Error: ' . $e->getMessage();
            }

            return "Product '{$slug}' saved.";
        });

        $this->registerTool('gspot_list_orders', [
            'description' => 'List recent commerce orders (Stripe checkout sessions)',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'limit' => ['type' => 'integer', 'description' => 'Max orders (default 20)'],
                ],
            ],
        ], function (array $args): string {
            $dir = $this->app->rootPath() . '/user/plugins/commerce/data/orders';
            if (!is_dir($dir)) {
                return 'Error: commerce plugin not installed or no orders yet.';
            }
            require_once $this->app->rootPath() . '/user/plugins/commerce/classes/OrderRegistry.php';
            $limit = (int) ($args['limit'] ?? 20);
            $orders = (new \GSpot\UserPlugins\Commerce\OrderRegistry($dir))->all(max(1, min(100, $limit)));

            return json_encode($orders, JSON_PRETTY_PRINT);
        });

        $this->registerTool('gspot_list_coupons', [
            'description' => 'List commerce coupons (requires commerce plugin)',
            'inputSchema' => ['type' => 'object', 'properties' => []],
        ], function (array $args): string {
            $dir = $this->app->rootPath() . '/user/plugins/commerce/data/coupons';
            if (!is_dir($dir)) {
                return 'Error: commerce plugin not installed.';
            }
            require_once $this->app->rootPath() . '/user/plugins/commerce/classes/CouponRegistry.php';
            $coupons = (new \GSpot\UserPlugins\Commerce\CouponRegistry($dir))->all();

            return json_encode($coupons, JSON_PRETTY_PRINT);
        });

        $this->registerTool('gspot_save_coupon', [
            'description' => 'Create or update a commerce coupon',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'code' => ['type' => 'string'],
                    'type' => ['type' => 'string', 'description' => 'percent or fixed'],
                    'amount' => ['type' => 'integer', 'description' => 'Percent 1-100 or fixed cents'],
                    'active' => ['type' => 'boolean'],
                    'max_uses' => ['type' => 'integer'],
                ],
                'required' => ['code', 'type', 'amount'],
            ],
        ], function (array $args): string {
            $dir = $this->app->rootPath() . '/user/plugins/commerce/data/coupons';
            if (!is_dir($dir)) {
                return 'Error: commerce plugin not installed.';
            }
            require_once $this->app->rootPath() . '/user/plugins/commerce/classes/CouponRegistry.php';
            $registry = new \GSpot\UserPlugins\Commerce\CouponRegistry($dir);
            $code = $registry->normalizeCode((string) ($args['code'] ?? ''));
            if ($code === '') {
                return 'Error: Invalid code.';
            }
            $existing = $registry->get($code);
            try {
                $registry->save($code, [
                    'type' => $args['type'],
                    'amount' => (int) $args['amount'],
                    'active' => $args['active'] ?? true,
                    'max_uses' => $args['max_uses'] ?? null,
                    'uses' => (int) ($existing['uses'] ?? 0),
                    'expires' => (string) ($args['expires'] ?? ''),
                ]);
            } catch (\InvalidArgumentException $e) {
                return 'Error: ' . $e->getMessage();
            }

            return "Coupon '{$code}' saved.";
        });

        $this->registerTool('gspot_list_gift_cards', [
            'description' => 'List commerce gift cards',
            'inputSchema' => ['type' => 'object', 'properties' => []],
        ], function (array $args): string {
            $dir = $this->app->rootPath() . '/user/plugins/commerce/data/gift_cards';
            if (!is_dir($dir)) {
                return 'Error: commerce plugin not installed.';
            }
            require_once $this->app->rootPath() . '/user/plugins/commerce/classes/GiftCardRegistry.php';
            $cards = (new \GSpot\UserPlugins\Commerce\GiftCardRegistry($dir))->all();

            return json_encode($cards, JSON_PRETTY_PRINT);
        });

        $this->registerTool('gspot_save_gift_card', [
            'description' => 'Create or update a gift card balance',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'code' => ['type' => 'string'],
                    'balance_cents' => ['type' => 'integer'],
                    'currency' => ['type' => 'string'],
                    'active' => ['type' => 'boolean'],
                ],
                'required' => ['code', 'balance_cents'],
            ],
        ], function (array $args): string {
            $dir = $this->app->rootPath() . '/user/plugins/commerce/data/gift_cards';
            if (!is_dir($dir)) {
                return 'Error: commerce plugin not installed.';
            }
            require_once $this->app->rootPath() . '/user/plugins/commerce/classes/GiftCardRegistry.php';
            $registry = new \GSpot\UserPlugins\Commerce\GiftCardRegistry($dir);
            $code = $registry->normalizeCode((string) ($args['code'] ?? ''));
            if ($code === '') {
                return 'Error: Invalid code.';
            }
            try {
                $registry->save($code, [
                    'balance_cents' => (int) $args['balance_cents'],
                    'currency' => $args['currency'] ?? 'aud',
                    'active' => $args['active'] ?? true,
                ]);
            } catch (\InvalidArgumentException $e) {
                return 'Error: ' . $e->getMessage();
            }

            return "Gift card '{$code}' saved.";
        });

        $this->registerTool('gspot_list_mud_patterns', [
            'description' => 'List GSpot MUD HTML patterns (requires gspot-mud plugin)',
            'inputSchema' => ['type' => 'object', 'properties' => []],
        ], function (array $args): string {
            $registry = $this->mudPatternRegistry();
            if ($registry === null) {
                return 'Error: gspot-mud plugin not installed.';
            }
            $list = array_map(static fn ($p) => [
                'slug' => $p['slug'] ?? '',
                'title' => $p['title'] ?? '',
                'category' => $p['category'] ?? 'general',
                'description' => $p['description'] ?? '',
            ], $registry->all());

            return json_encode($list, JSON_PRETTY_PRINT);
        });

        $this->registerTool('gspot_get_mud_pattern', [
            'description' => 'Get one MUD pattern metadata and HTML body',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'slug' => ['type' => 'string', 'description' => 'Pattern slug'],
                ],
                'required' => ['slug'],
            ],
        ], function (array $args): string {
            $registry = $this->mudPatternRegistry();
            if ($registry === null) {
                return 'Error: gspot-mud plugin not installed.';
            }
            $slug = PathGuard::sanitizeSlug($args['slug']);
            $pattern = $registry->get($slug);
            if ($pattern === null) {
                return "Error: Pattern '{$slug}' not found.";
            }
            unset($pattern['dir']);

            return json_encode($pattern, JSON_PRETTY_PRINT);
        });

        $this->registerTool('gspot_save_mud_pattern', [
            'description' => 'Create or update a MUD HTML pattern',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'slug' => ['type' => 'string'],
                    'title' => ['type' => 'string'],
                    'category' => ['type' => 'string'],
                    'description' => ['type' => 'string'],
                    'html' => ['type' => 'string', 'description' => 'Pattern HTML body'],
                ],
                'required' => ['slug', 'title', 'html'],
            ],
        ], function (array $args): string {
            $registry = $this->mudPatternRegistry();
            if ($registry === null) {
                return 'Error: gspot-mud plugin not installed.';
            }
            $slug = PathGuard::sanitizeSlug($args['slug']);
            if ($slug === '') {
                return 'Error: Invalid slug.';
            }
            try {
                $registry->save($slug, [
                    'title' => $args['title'],
                    'category' => $args['category'] ?? 'general',
                    'description' => $args['description'] ?? '',
                ], $args['html']);
            } catch (\InvalidArgumentException $e) {
                return 'Error: ' . $e->getMessage();
            }

            return "Pattern '{$slug}' saved.";
        });

        $this->registerTool('gspot_cache_clear', [
            'description' => 'Clear compiled views and page cache',
            'inputSchema' => ['type' => 'object', 'properties' => []],
        ], function (array $args): string {
            $this->app->cache()->clear();
            return "Cache cleared.";
        });

        $this->registerTool('gspot_site_info', [
            'description' => 'Get comprehensive site information (version, PHP, theme, page count, plugins)',
            'inputSchema' => ['type' => 'object', 'properties' => []],
        ], function (array $args): string {
            $pages = $this->app->router()->listPages();
            $themes = $this->app->themes()->listThemes();
            $info = [
                'gspot_version' => Application::VERSION,
                'php_version' => PHP_VERSION,
                'site_title' => $this->app->config()->get('site.title'),
                'active_theme' => $this->app->themes()->activeTheme(),
                'page_count' => count($pages),
                'theme_count' => count($themes),
                'root_path' => $this->app->rootPath(),
            ];
            return json_encode($info, JSON_PRETTY_PRINT);
        });
    }

    private function commerceRegistry(): ?\GSpot\UserPlugins\Commerce\ProductRegistry
    {
        $pluginDir = $this->app->rootPath() . '/user/plugins/commerce';
        if (!is_dir($pluginDir)) {
            return null;
        }

        require_once $pluginDir . '/classes/ProductRegistry.php';

        return new \GSpot\UserPlugins\Commerce\ProductRegistry($pluginDir . '/data/products');
    }

    private function mudPatternRegistry(): ?\GSpot\UserPlugins\GspotMud\MudPatternRegistry
    {
        $pluginDir = $this->app->rootPath() . '/user/plugins/gspot-mud';
        if (!is_dir($pluginDir)) {
            return null;
        }

        require_once $pluginDir . '/classes/MudPatternRegistry.php';

        return new \GSpot\UserPlugins\GspotMud\MudPatternRegistry($pluginDir . '/data/patterns');
    }

    private function addDirToZip(\ZipArchive $zip, string $dir, string $prefix, array $exclude = []): void
    {
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            if (in_array($item, $exclude)) continue;

            $path = $dir . '/' . $item;
            $zipPath = $prefix . $item;

            if (is_dir($path)) {
                $zip->addEmptyDir($zipPath);
                $this->addDirToZip($zip, $path, $zipPath . '/', $exclude);
            } else {
                $zip->addFile($path, $zipPath);
            }
        }
    }

    private function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->deleteDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
