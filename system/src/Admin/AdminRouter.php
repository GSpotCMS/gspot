<?php

declare(strict_types=1);

namespace GSpot\Admin;

use GSpot\Application;
use GSpot\Security\Csrf;
use GSpot\Security\PathGuard;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\Session;

class AdminRouter
{
    private Application $app;
    private AuthManager $auth;
    private string $prefix;
    private array $routes = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->prefix = '/' . trim($app->config()->get('system.admin.prefix', 'gspot-admin'), '/');
        $this->auth = new AuthManager(
            $app->rootPath() . '/user/accounts',
            $app->rootPath() . '/user/.cache',
            (int) $app->config()->get('system.admin.login.max_attempts', 5),
            (int) $app->config()->get('system.admin.login.lockout_seconds', 900),
        );
        $this->registerRoutes();
    }

    public function handles(string $uri): bool
    {
        return str_starts_with($uri, $this->prefix);
    }

    public function dispatch(Request $request): Response
    {
        $uri = $request->getPathInfo();
        $path = substr($uri, strlen($this->prefix)) ?: '/';
        $method = $request->getMethod();

        if (!$this->auth->isAuthenticated() && $path !== '/login') {
            return new RedirectResponse($this->prefix . '/login');
        }

        if ($path === '/login') {
            return $this->handleLogin($request);
        }

        if ($path === '/logout') {
            $this->auth->logout();
            return new RedirectResponse($this->prefix . '/login');
        }

        if ($method === 'POST' && $path !== '/login') {
            if (!Csrf::validate($request->request->get('_csrf'))) {
                return new Response('Forbidden: invalid or missing CSRF token.', 403);
            }
        }

        foreach ($this->routes as $route) {
            if ($route['method'] === $method && $route['path'] === $path) {
                return ($route['handler'])($request);
            }
            if (preg_match($route['pattern'], $path, $matches)) {
                if ($route['method'] === $method) {
                    return ($route['handler'])($request, $matches);
                }
            }
        }

        return new Response('Not Found', 404);
    }

    private function handleLogin(Request $request): Response
    {
        if ($request->getMethod() === 'POST') {
            $username = (string) $request->request->get('username', '');
            $password = (string) $request->request->get('password', '');
            $clientIp = $request->getClientIp() ?? '0.0.0.0';

            if ($this->auth->isLoginThrottled($username, $clientIp)) {
                $wait = $this->auth->loginThrottleSecondsRemaining($username, $clientIp);
                $mins = max(1, (int) ceil($wait / 60));

                return new Response(
                    $this->renderLogin("Too many login attempts. Try again in about {$mins} minute(s)."),
                    429,
                );
            }

            if ($this->auth->attempt($username, $password, $clientIp)) {
                return new RedirectResponse($this->prefix . '/');
            }

            return new Response($this->renderLogin('Invalid credentials'), 401);
        }

        if ($this->auth->isAuthenticated()) {
            return new RedirectResponse($this->prefix . '/');
        }

        return new Response($this->renderLogin());
    }

    private function registerRoutes(): void
    {
        $this->route('GET', '/', fn($r) => $this->dashboard($r));
        $this->route('GET', '/pages', fn($r) => $this->pagesList($r));
        $this->route('GET', '/pages/new', fn($r) => $this->pageEditor($r));
        $this->route('POST', '/pages/new', fn($r) => $this->pageSave($r));
        $this->route('GET', '/pages/edit', fn($r) => $this->pageEditor($r));
        $this->route('POST', '/pages/edit', fn($r) => $this->pageSave($r));
        $this->route('POST', '/pages/delete', fn($r) => $this->pageDelete($r));
        $this->route('GET', '/media', fn($r) => $this->mediaManager($r));
        $this->route('POST', '/media/upload', fn($r) => $this->mediaUpload($r));
        $this->route('POST', '/media/delete', fn($r) => $this->mediaDelete($r));
        $this->route('GET', '/settings', fn($r) => $this->settings($r));
        $this->route('POST', '/settings', fn($r) => $this->settingsSave($r));
        $this->route('GET', '/plugins', fn($r) => $this->pluginsList($r));
        $this->route('POST', '/plugins/toggle', fn($r) => $this->pluginToggle($r));
        $this->route('GET', '/themes', fn($r) => $this->themesList($r));
        $this->route('POST', '/themes/activate', fn($r) => $this->themeActivate($r));
        $this->route('GET', '/javabean', fn($r) => $this->javabeanSettings($r));
        $this->route('POST', '/javabean', fn($r) => $this->javabeanSave($r));
        $this->route('GET', '/mud/patterns', fn($r) => $this->mudPatternsList($r));
        $this->route('GET', '/mud/patterns/edit', fn($r) => $this->mudPatternEdit($r));
        $this->route('GET', '/mud/patterns/new', fn($r) => $this->mudPatternEdit($r));
        $this->route('POST', '/mud/patterns/save', fn($r) => $this->mudPatternSave($r));
        $this->route('POST', '/mud/patterns/delete', fn($r) => $this->mudPatternDelete($r));
    }

    private function route(string $method, string $path, callable $handler): void
    {
        $pattern = '#^' . preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $path) . '$#';
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'pattern' => $pattern,
            'handler' => $handler,
        ];
    }

    private function dashboard(Request $request): Response
    {
        $pages = $this->app->router()->listPages();
        $plugins = $this->app->plugins()->getPlugins();

        return new Response($this->render('dashboard', [
            'pageCount' => count($pages),
            'pluginCount' => count($plugins),
            'version' => Application::VERSION,
            'phpVersion' => PHP_VERSION,
            'theme' => $this->app->themes()->activeTheme(),
            'recentPages' => array_slice($pages, 0, 5),
        ]));
    }

    private function pagesList(Request $request): Response
    {
        $pages = $this->app->router()->listPages();
        return new Response($this->render('pages', ['pages' => $pages]));
    }

    private function pageEditor(Request $request): Response
    {
        $slug = $request->query->get('slug', '');
        $page = null;
        $rawContent = '';

        if ($slug) {
            $contentDir = $this->app->rootPath() . '/user/content';
            $dirs = glob($contentDir . '/*', GLOB_ONLYDIR);
            foreach ($dirs as $dir) {
                $cleanName = preg_replace('/^\d+\./', '', basename($dir));
                if ($cleanName === $slug) {
                    $file = $dir . '/page.md';
                    if (file_exists($file)) {
                        $rawContent = file_get_contents($file);
                    }
                    break;
                }
            }
        }

        return new Response($this->render('editor', [
            'slug' => $slug,
            'content' => $rawContent,
            'isNew' => empty($slug),
        ]));
    }

    private function pageSave(Request $request): Response
    {
        $slug = PathGuard::sanitizeSlug((string) $request->request->get('slug', ''));
        $content = $request->request->get('content', '');
        $isNew = $request->request->get('is_new', '0') === '1';

        if ($slug === '') {
            return new Response('Invalid page slug.', 400);
        }

        if ($isNew && $slug) {
            $contentDir = $this->app->rootPath() . '/user/content';
            $existing = glob($contentDir . '/*', GLOB_ONLYDIR);
            $order = count($existing) + 1;
            $dirName = str_pad((string)$order, 2, '0', STR_PAD_LEFT) . '.' . $slug;
            $dir = $contentDir . '/' . $dirName;
            mkdir($dir, 0755, true);
            file_put_contents($dir . '/page.md', $content);
        } else {
            $contentDir = $this->app->rootPath() . '/user/content';
            $dirs = glob($contentDir . '/*', GLOB_ONLYDIR);
            foreach ($dirs as $dir) {
                $cleanName = preg_replace('/^\d+\./', '', basename($dir));
                if ($cleanName === $slug) {
                    file_put_contents($dir . '/page.md', $content);
                    break;
                }
            }
        }

        return new RedirectResponse($this->prefix . '/pages');
    }

    private function pageDelete(Request $request): Response
    {
        $slug = PathGuard::sanitizeSlug((string) $request->request->get('slug', ''));
        if ($slug === '') {
            return new Response('Invalid page slug.', 400);
        }
        $contentDir = $this->app->rootPath() . '/user/content';
        $dirs = glob($contentDir . '/*', GLOB_ONLYDIR);

        foreach ($dirs as $dir) {
            $cleanName = preg_replace('/^\d+\./', '', basename($dir));
            if ($cleanName === $slug) {
                $this->deleteDir($dir);
                break;
            }
        }

        return new RedirectResponse($this->prefix . '/pages');
    }

    private function mediaManager(Request $request): Response
    {
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
                        'modified' => filemtime($path),
                        'url' => '/media/' . $item,
                    ];
                }
            }
        }

        return new Response($this->render('media', ['files' => $files]));
    }

    private function mediaUpload(Request $request): Response
    {
        $mediaDir = $this->app->rootPath() . '/user/media';
        if (!is_dir($mediaDir)) {
            mkdir($mediaDir, 0755, true);
        }

        $file = $request->files->get('file');
        if ($file && $file->isValid()) {
            $name = PathGuard::sanitizeFilename($file->getClientOriginalName());
            if ($name !== '') {
                $dest = PathGuard::resolveUnder($mediaDir, $name);
                if ($dest !== null) {
                    $file->move(dirname($dest), basename($dest));
                }
            }
        }

        return new RedirectResponse($this->prefix . '/media');
    }

    private function mediaDelete(Request $request): Response
    {
        $mediaDir = $this->app->rootPath() . '/user/media';
        $name = PathGuard::sanitizeFilename((string) $request->request->get('name', ''));
        $file = $name !== '' ? PathGuard::resolveUnder($mediaDir, $name) : null;

        if ($file !== null && file_exists($file) && is_file($file)) {
            unlink($file);
        }

        return new RedirectResponse($this->prefix . '/media');
    }

    private function settings(Request $request): Response
    {
        $config = $this->app->config()->all();
        return new Response($this->render('settings', ['config' => $config]));
    }

    private function settingsSave(Request $request): Response
    {
        $siteTitle = $request->request->get('site_title', '');
        $siteDesc = $request->request->get('site_description', '');
        $siteUrl = $request->request->get('site_url', '');
        $theme = PathGuard::sanitizeName((string) $request->request->get('theme', 'starter'));
        if ($theme === '') {
            $theme = 'starter';
        }

        $this->app->config()->save('site', [
            'title' => $siteTitle,
            'description' => $siteDesc,
            'url' => $siteUrl,
            'author' => $this->app->config()->get('site.author', ''),
        ]);

        $systemConfig = $this->app->config()->get('system', []);
        $systemConfig['theme'] = $theme;
        $this->app->config()->save('system', $systemConfig);

        return new RedirectResponse($this->prefix . '/settings?saved=1');
    }

    private function pluginsList(Request $request): Response
    {
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
                        'description' => $config['description'] ?? '',
                        'enabled' => $config['enabled'] ?? true,
                    ];
                }
            }
        }

        return new Response($this->render('plugins', ['plugins' => $plugins]));
    }

    private function pluginToggle(Request $request): Response
    {
        $pluginsDir = $this->app->rootPath() . '/user/plugins';
        $name = PathGuard::sanitizeName((string) $request->request->get('name', ''));
        $enabled = $request->request->get('enabled', '1') === '1';
        $manifest = $name !== ''
            ? PathGuard::resolveUnder($pluginsDir, $name, 'plugin.yaml')
            : null;

        if ($manifest !== null && file_exists($manifest)) {
            $config = \Symfony\Component\Yaml\Yaml::parseFile($manifest);
            $config['enabled'] = $enabled;
            file_put_contents($manifest, \Symfony\Component\Yaml\Yaml::dump($config, 4, 2));
        }

        return new RedirectResponse($this->prefix . '/plugins');
    }

    private function themesList(Request $request): Response
    {
        $themes = $this->app->themes()->listThemes();
        $active = $this->app->themes()->activeTheme();
        return new Response($this->render('themes', [
            'themes' => $themes,
            'active' => $active,
        ]));
    }

    private function themeActivate(Request $request): Response
    {
        $name = PathGuard::sanitizeName((string) $request->request->get('name', ''));
        if ($name === '') {
            return new Response('Invalid theme name.', 400);
        }
        $systemConfig = $this->app->config()->get('system', []);
        $systemConfig['theme'] = $name;
        $this->app->config()->save('system', $systemConfig);
        return new RedirectResponse($this->prefix . '/themes');
    }

    private function render(string $template, array $data = []): string
    {
        $data['prefix'] = $this->prefix;
        $data['user'] = $this->auth->currentUser();
        $data['csrf'] = Csrf::field();
        $method = 'render' . ucfirst($template);

        if (method_exists($this, $method)) {
            return $this->{$method}($data);
        }

        return $this->adminLayout($template, $data);
    }

    private function adminLayout(string $page, array $data): string
    {
        $nav = $this->renderNav($data['prefix']);
        $content = $this->renderPage($page, $data);
        $user = htmlspecialchars($data['user'] ?? 'admin');
        $javabeanCss = $this->getJavaBeanCss();
        $googleFont = $this->getJavaBeanGoogleFont();

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GSpot Admin — {$page}</title>
    {$googleFont}
    <style>{$this->adminCSS()}</style>
    <style id="javabean-theme">{$javabeanCss}</style>
</head>
<body>
    <aside class="admin-sidebar">
        <div class="admin-brand">
            <span class="brand-icon">◉</span>
            <span class="brand-text">GSpot</span>
        </div>
        {$nav}
        <div class="admin-user">
            <span>{$user}</span>
            <a href="{$data['prefix']}/logout">Logout</a>
        </div>
    </aside>
    <main class="admin-main">
        {$content}
    </main>
</body>
</html>
HTML;
    }

    private function renderNav(string $prefix): string
    {
        $items = [
            ['/', 'Dashboard', '◉'],
            ['/pages', 'Pages', '📄'],
            ['/media', 'Media', '🖼️'],
            ['/plugins', 'Plugins', '🔌'],
            ['/themes', 'Themes', '🎨'],
            ['/javabean', 'JavaBean', '☕'],
            ['/mud/patterns', 'MUD Patterns', '🧱'],
            ['/settings', 'Settings', '⚙️'],
        ];

        $html = '<nav class="admin-nav">';
        foreach ($items as [$path, $label, $icon]) {
            $html .= "<a href=\"{$prefix}{$path}\" class=\"nav-item\">{$icon} {$label}</a>";
        }
        $html .= '</nav>';
        return $html;
    }

    private function renderPage(string $page, array $data): string
    {
        return match ($page) {
            'dashboard' => $this->renderDashboardContent($data),
            'pages' => $this->renderPagesContent($data),
            'editor' => $this->renderEditorContent($data),
            'media' => $this->renderMediaContent($data),
            'settings' => $this->renderSettingsContent($data),
            'plugins' => $this->renderPluginsContent($data),
            'themes' => $this->renderThemesContent($data),
            'javabean' => $this->renderJavaBeanContent($data),
            'mud-patterns' => $this->renderMudPatternsContent($data),
            'mud-pattern-edit' => $this->renderMudPatternEditContent($data),
            default => '<h1>404</h1>',
        };
    }

    private function renderDashboardContent(array $data): string
    {
        $pages = $data['pageCount'];
        $plugins = $data['pluginCount'];
        $version = $data['version'];
        $php = $data['phpVersion'];
        $theme = htmlspecialchars($data['theme']);

        $recentHtml = '';
        foreach ($data['recentPages'] ?? [] as $page) {
            $title = htmlspecialchars($page->title());
            $slug = htmlspecialchars($page->slug());
            $recentHtml .= "<li><a href=\"{$data['prefix']}/pages/edit?slug={$slug}\">{$title}</a></li>";
        }

        return <<<HTML
<h1>Dashboard</h1>
<div class="stats-grid">
    <div class="stat-card"><span class="stat-num">{$pages}</span><span class="stat-label">Pages</span></div>
    <div class="stat-card"><span class="stat-num">{$plugins}</span><span class="stat-label">Plugins</span></div>
    <div class="stat-card"><span class="stat-num">{$theme}</span><span class="stat-label">Theme</span></div>
    <div class="stat-card"><span class="stat-num">{$version}</span><span class="stat-label">Version</span></div>
</div>
<div class="panel">
    <h2>Recent Pages</h2>
    <ul>{$recentHtml}</ul>
</div>
<div class="panel">
    <h2>Quick Actions</h2>
    <a href="{$data['prefix']}/pages/new" class="btn btn-primary">New Page</a>
    <a href="{$data['prefix']}/media" class="btn">Upload Media</a>
</div>
HTML;
    }

    private function renderPagesContent(array $data): string
    {
        $html = '<h1>Pages</h1>';
        $html .= "<a href=\"{$data['prefix']}/pages/new\" class=\"btn btn-primary\">New Page</a>";
        $html .= '<table class="admin-table"><thead><tr><th>Title</th><th>Slug</th><th>Template</th><th>Actions</th></tr></thead><tbody>';

        foreach ($data['pages'] as $page) {
            $title = htmlspecialchars($page->title());
            $slug = htmlspecialchars($page->slug());
            $tpl = htmlspecialchars($page->template());
            $html .= <<<HTML
<tr>
    <td>{$title}</td>
    <td><code>{$slug}</code></td>
    <td>{$tpl}</td>
    <td>
        <a href="{$data['prefix']}/pages/edit?slug={$slug}" class="btn btn-sm">Edit</a>
        <form method="POST" action="{$data['prefix']}/pages/delete" style="display:inline">
            {$data['csrf']}
            <input type="hidden" name="slug" value="{$slug}">
            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete {$title}?')">Delete</button>
        </form>
    </td>
</tr>
HTML;
        }

        $html .= '</tbody></table>';
        return $html;
    }

    private function renderEditorContent(array $data): string
    {
        $slug = htmlspecialchars($data['slug'] ?? '');
        $content = htmlspecialchars($data['content'] ?? "---\ntitle: New Page\ntemplate: default\n---\n\n# New Page\n\nStart writing here.");
        $isNew = $data['isNew'] ? '1' : '0';
        $heading = $data['isNew'] ? 'New Page' : 'Edit: ' . $slug;
        $action = $data['isNew'] ? 'new' : 'edit';

        return <<<HTML
<h1>{$heading}</h1>
<form method="POST" action="{$data['prefix']}/pages/{$action}" class="editor-form">
    {$data['csrf']}
    <input type="hidden" name="is_new" value="{$isNew}">
    <div class="form-group">
        <label>Slug</label>
        <input type="text" name="slug" value="{$slug}" {$this->readonlyIf(!$data['isNew'])} required>
    </div>
    <div class="form-group">
        <label>Content (Markdown + YAML frontmatter)</label>
        <textarea name="content" class="code-editor" rows="25">{$content}</textarea>
    </div>
    <button type="submit" class="btn btn-primary">Save Page</button>
    <a href="{$data['prefix']}/pages" class="btn">Cancel</a>
</form>
HTML;
    }

    private function renderMediaContent(array $data): string
    {
        $html = '<h1>Media</h1>';
        $html .= <<<HTML
<form method="POST" action="{$data['prefix']}/media/upload" enctype="multipart/form-data" class="upload-form">
    {$data['csrf']}
    <input type="file" name="file" required>
    <button type="submit" class="btn btn-primary">Upload</button>
</form>
HTML;

        if (empty($data['files'])) {
            $html .= '<p class="empty">No media files uploaded yet.</p>';
        } else {
            $html .= '<div class="media-grid">';
            foreach ($data['files'] as $file) {
                $name = htmlspecialchars($file['name']);
                $size = $this->formatBytes($file['size']);
                $url = htmlspecialchars($file['url']);
                $html .= <<<HTML
<div class="media-card">
    <div class="media-preview"><a href="{$url}" target="_blank">{$name}</a></div>
    <div class="media-info">{$size}</div>
    <form method="POST" action="{$data['prefix']}/media/delete">
        {$data['csrf']}
        <input type="hidden" name="name" value="{$name}">
        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
    </form>
</div>
HTML;
            }
            $html .= '</div>';
        }

        return $html;
    }

    private function renderSettingsContent(array $data): string
    {
        $config = $data['config'];
        $title = htmlspecialchars($config['site']['title'] ?? '');
        $desc = htmlspecialchars($config['site']['description'] ?? '');
        $url = htmlspecialchars($config['site']['url'] ?? '');
        $theme = htmlspecialchars($config['system']['theme'] ?? 'starter');
        $saved = isset($_GET['saved']) ? '<div class="alert alert-success">Settings saved.</div>' : '';

        return <<<HTML
<h1>Settings</h1>
{$saved}
<form method="POST" class="settings-form">
    {$data['csrf']}
    <h2>Site</h2>
    <div class="form-group"><label>Title</label><input type="text" name="site_title" value="{$title}"></div>
    <div class="form-group"><label>Description</label><input type="text" name="site_description" value="{$desc}"></div>
    <div class="form-group"><label>URL</label><input type="url" name="site_url" value="{$url}"></div>
    <h2>System</h2>
    <div class="form-group"><label>Theme</label><input type="text" name="theme" value="{$theme}"></div>
    <button type="submit" class="btn btn-primary">Save Settings</button>
</form>
HTML;
    }

    private function renderPluginsContent(array $data): string
    {
        $html = '<h1>Plugins</h1>';

        if (empty($data['plugins'])) {
            $html .= '<p class="empty">No plugins installed. Add plugins to <code>user/plugins/</code></p>';
        } else {
            $html .= '<div class="plugins-list">';
            foreach ($data['plugins'] as $plugin) {
                $name = htmlspecialchars($plugin['name']);
                $version = htmlspecialchars($plugin['version']);
                $desc = htmlspecialchars($plugin['description']);
                $enabled = $plugin['enabled'];
                $btnText = $enabled ? 'Disable' : 'Enable';
                $btnClass = $enabled ? 'btn-danger' : 'btn-primary';
                $newState = $enabled ? '0' : '1';
                $status = $enabled ? '<span class="badge badge-green">Active</span>' : '<span class="badge badge-gray">Disabled</span>';

                $html .= <<<HTML
<div class="plugin-card">
    <div class="plugin-info">
        <strong>{$name}</strong> <small>v{$version}</small> {$status}
        <p>{$desc}</p>
    </div>
    <form method="POST" action="{$data['prefix']}/plugins/toggle">
        {$data['csrf']}
        <input type="hidden" name="name" value="{$name}">
        <input type="hidden" name="enabled" value="{$newState}">
        <button type="submit" class="btn btn-sm {$btnClass}">{$btnText}</button>
    </form>
</div>
HTML;
            }
            $html .= '</div>';
        }

        return $html;
    }

    private function renderThemesContent(array $data): string
    {
        $html = '<h1>Themes</h1>';
        $html .= '<div class="themes-grid">';

        foreach ($data['themes'] as $theme) {
            $name = htmlspecialchars($theme['name']);
            $title = htmlspecialchars($theme['title']);
            $version = htmlspecialchars($theme['version']);
            $desc = htmlspecialchars($theme['description']);
            $isActive = $theme['name'] === $data['active'];
            $badge = $isActive ? '<span class="badge badge-green">Active</span>' : '';
            $btn = $isActive ? '' : <<<HTML
<form method="POST" action="{$data['prefix']}/themes/activate">
    {$data['csrf']}
    <input type="hidden" name="name" value="{$name}">
    <button type="submit" class="btn btn-sm btn-primary">Activate</button>
</form>
HTML;

            $presets = implode(', ', $theme['presets'] ?? []);
            $vibes = implode(', ', $theme['vibes'] ?? []);

            $html .= <<<HTML
<div class="theme-card">
    <h3>{$title} <small>v{$version}</small> {$badge}</h3>
    <p>{$desc}</p>
    <p><small>Presets: {$presets} | Vibes: {$vibes}</small></p>
    {$btn}
</div>
HTML;
        }

        $html .= '</div>';
        return $html;
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

    private function mudPatternsList(Request $request): Response
    {
        $registry = $this->mudPatternRegistry();
        if ($registry === null) {
            return new Response($this->render('mud-patterns', ['installed' => false]));
        }

        return new Response($this->render('mud-patterns', [
            'installed' => true,
            'patterns' => $registry->all(),
            'saved' => $request->query->has('saved'),
            'deleted' => $request->query->has('deleted'),
        ]));
    }

    private function mudPatternEdit(Request $request): Response
    {
        $registry = $this->mudPatternRegistry();
        if ($registry === null) {
            return new RedirectResponse($this->prefix . '/mud/patterns');
        }

        $slug = (string) $request->query->get('slug', '');
        $pattern = $slug !== '' ? $registry->get($slug) : null;
        $isNew = $slug === '' || $pattern === null;

        return new Response($this->render('mud-pattern-edit', [
            'installed' => true,
            'pattern' => $pattern,
            'isNew' => $isNew,
            'slug' => $slug,
            'categories' => $registry->categories(),
        ]));
    }

    private function mudPatternSave(Request $request): Response
    {
        $registry = $this->mudPatternRegistry();
        if ($registry === null) {
            return new RedirectResponse($this->prefix . '/mud/patterns');
        }

        $slug = PathGuard::sanitizeSlug((string) $request->request->get('slug', ''));
        if ($slug === '') {
            return new Response('Invalid pattern slug.', 400);
        }
        $registry->save($slug, [
            'title' => $request->request->get('title', ''),
            'category' => $request->request->get('category', 'general'),
            'description' => $request->request->get('description', ''),
        ], (string) $request->request->get('html', ''));

        return new RedirectResponse($this->prefix . '/mud/patterns/edit?slug=' . urlencode($slug) . '&saved=1');
    }

    private function mudPatternDelete(Request $request): Response
    {
        $registry = $this->mudPatternRegistry();
        if ($registry !== null) {
            $slug = PathGuard::sanitizeSlug((string) $request->request->get('slug', ''));
            if ($slug !== '') {
                $registry->delete($slug);
            }
        }

        return new RedirectResponse($this->prefix . '/mud/patterns?deleted=1');
    }

    private function renderMudPatternsContent(array $data): string
    {
        if (empty($data['installed'])) {
            return '<h1>🧱 MUD Patterns</h1><p class="empty">gspot-mud plugin not installed.</p>';
        }

        $saved = !empty($data['saved']) ? '<div class="alert alert-success">Pattern saved.</div>' : '';
        $deleted = !empty($data['deleted']) ? '<div class="alert alert-success">Pattern deleted.</div>' : '';
        $prefix = $data['prefix'];
        $rows = '';

        foreach ($data['patterns'] ?? [] as $p) {
            $slug = htmlspecialchars($p['slug'] ?? '');
            $title = htmlspecialchars($p['title'] ?? $slug);
            $cat = htmlspecialchars($p['category'] ?? 'general');
            $desc = htmlspecialchars($p['description'] ?? '');
            $shortcode = htmlspecialchars('[mud-pattern slug="' . ($p['slug'] ?? '') . '"]');
            $rows .= <<<HTML
<tr>
  <td><strong>{$title}</strong><br><small class="muted">{$desc}</small></td>
  <td><code>{$slug}</code></td>
  <td>{$cat}</td>
  <td><code>{$shortcode}</code></td>
  <td><a class="btn btn-sm" href="{$prefix}/mud/patterns/edit?slug={$slug}">Edit</a></td>
</tr>
HTML;
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="5" class="empty">No patterns yet.</td></tr>';
        }

        return <<<HTML
<h1>🧱 MUD Patterns</h1>
<p>WP-style HTML patterns — GravMUD fence CSS, editable in WYSIWYG. Insert with shortcode or WP block comment.</p>
{$saved}{$deleted}
<p><a class="btn btn-primary" href="{$prefix}/mud/patterns/new">+ New Pattern</a></p>
<div class="panel">
  <table class="admin-table">
    <thead><tr><th>Title</th><th>Slug</th><th>Category</th><th>Shortcode</th><th></th></tr></thead>
    <tbody>{$rows}</tbody>
  </table>
</div>
HTML;
    }

    private function renderMudPatternEditContent(array $data): string
    {
        if (empty($data['installed'])) {
            return '<h1>404</h1>';
        }

        $prefix = $data['prefix'];
        $pattern = $data['pattern'] ?? null;
        $isNew = !empty($data['isNew']);
        $slug = htmlspecialchars($pattern['slug'] ?? ($data['slug'] ?? ''));
        $title = htmlspecialchars($pattern['title'] ?? '');
        $category = htmlspecialchars($pattern['category'] ?? 'general');
        $desc = htmlspecialchars($pattern['description'] ?? '');
        $html = htmlspecialchars($pattern['html'] ?? '', ENT_QUOTES);
        $saved = isset($_GET['saved']) ? '<div class="alert alert-success">Pattern saved!</div>' : '';
        $heading = $isNew ? 'New MUD Pattern' : 'Edit Pattern: ' . $title;
        $mudCss = '/plugin-assets/gspot-mud/css/gspot-mud-fences.css';
        $editorCss = '/plugin-assets/gspot-mud/admin/pattern-editor.css';
        $editorJs = '/plugin-assets/gspot-mud/admin/pattern-editor.js';
        $deleteForm = '';
        if (!$isNew && $slug !== '') {
            $deleteForm = <<<HTML
<form method="POST" action="{$prefix}/mud/patterns/delete" class="mud-delete-form" onsubmit="return confirm('Delete pattern {$slug}?')">
  {$data['csrf']}
  <input type="hidden" name="slug" value="{$slug}">
  <button type="submit" class="btn btn-danger">Delete pattern</button>
</form>
HTML;
        }

        return <<<HTML
<h1>🧱 {$heading}</h1>
{$saved}
<p class="muted"><a href="/mud-demo" target="_blank" rel="noopener">Open MUD demo page</a> to see patterns on the front end.</p>
<link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet">
<link rel="stylesheet" href="{$editorCss}">
<form method="POST" action="{$prefix}/mud/patterns/save" id="mud-pattern-form" data-mud-css="{$mudCss}">
  {$data['csrf']}
  <div class="panel">
    <div class="form-group"><label>Slug (url-safe id)</label><input type="text" name="slug" value="{$slug}" required pattern="[a-z0-9-]+" {$this->readonlyIf(!$isNew && $slug !== '')}></div>
    <div class="form-group"><label>Title</label><input type="text" name="title" value="{$title}" required></div>
    <div class="form-group"><label>Category</label><input type="text" name="category" value="{$category}" placeholder="header, columns, callout, pricing"></div>
    <div class="form-group"><label>Description</label><input type="text" name="description" value="{$desc}"></div>
  </div>
  <div class="panel">
    <h2>Pattern body</h2>
    <div class="mud-editor-snippet-bar">
      <select id="mud-snippet-select">
        <option value="">Insert snippet…</option>
        <option value="hero-split">Hero (split)</option>
        <option value="hero-fullscreen">Hero (fullscreen)</option>
        <option value="cards-three">Cards (×3)</option>
        <option value="callout">Callout band</option>
        <option value="mud-callout-info">Info callout</option>
        <option value="quote">Quote block</option>
        <option value="manifesto">Manifesto</option>
        <option value="pricing-card">Pricing grid</option>
      </select>
      <button type="button" class="btn btn-sm" id="mud-snippet-insert">Insert</button>
    </div>
    <div class="mud-editor-tabs">
      <button type="button" class="is-active" data-mud-tab="visual">Visual</button>
      <button type="button" data-mud-tab="source">HTML source</button>
      <button type="button" data-mud-tab="preview">Live preview</button>
    </div>
    <div class="mud-editor-pane is-active" data-mud-pane="visual">
      <div id="mud-quill-editor" class="mud-quill-wrap"></div>
    </div>
    <div class="mud-editor-pane" data-mud-pane="source">
      <textarea id="mud-html-source-editor" class="mud-source-editor" spellcheck="false"></textarea>
    </div>
    <div class="mud-editor-pane" data-mud-pane="preview">
      <iframe id="mud-preview-frame" class="mud-preview-frame" title="Pattern preview"></iframe>
    </div>
    <textarea name="html" id="mud-html-source" style="display:none">{$html}</textarea>
  </div>
  <div class="mud-editor-actions">
    <button type="submit" class="btn btn-primary">Save pattern</button>
    <a class="btn" href="{$prefix}/mud/patterns">Back to library</a>
  </div>
</form>
{$deleteForm}
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>
<script src="{$editorJs}"></script>
HTML;
    }

    private function renderLogin(string $error = ''): string
    {
        $errorHtml = $error ? "<div class=\"alert alert-error\">{$error}</div>" : '';
        $javabeanCss = $this->getJavaBeanCss();
        $googleFont = $this->getJavaBeanGoogleFont();
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GSpot Admin — Login</title>
    {$googleFont}
    <style>{$this->adminCSS()}</style>
    <style id="javabean-theme">{$javabeanCss}</style>
</head>
<body class="login-page">
    <div class="login-box">
        <div class="login-brand"><span class="brand-icon">◉</span> GSpot CMS</div>
        {$errorHtml}
        <form method="POST">
            <div class="form-group"><label>Username</label><input type="text" name="username" required autofocus></div>
            <div class="form-group"><label>Password</label><input type="password" name="password" required></div>
            <button type="submit" class="btn btn-primary btn-block">Login</button>
        </form>
    </div>
</body>
</html>
HTML;
    }

    private function readonlyIf(bool $condition): string
    {
        return $condition ? 'readonly' : '';
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < 3) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 1) . ' ' . $units[$i];
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

    private function javabeanSettings(Request $request): Response
    {
        $pluginDir = $this->app->rootPath() . '/user/plugins/javabean-admin';
        $manifest = $pluginDir . '/plugin.yaml';

        if (!file_exists($manifest)) {
            return new Response($this->render('javabean', ['installed' => false]));
        }

        $config = \Symfony\Component\Yaml\Yaml::parseFile($manifest);
        require_once $pluginDir . '/classes/JavaBeanFontCatalog.php';
        require_once $pluginDir . '/classes/JavaBeanPresetRegistry.php';

        $presets = \GSpot\UserPlugins\JavabeanAdmin\JavaBeanPresetRegistry::all();
        $fonts = \GSpot\UserPlugins\JavabeanAdmin\JavaBeanFontCatalog::forClient();

        return new Response($this->render('javabean', [
            'installed' => true,
            'config' => $config['config'] ?? [],
            'presets' => $presets,
            'fonts' => $fonts,
            'saved' => $request->query->has('saved'),
        ]));
    }

    private function javabeanSave(Request $request): Response
    {
        $pluginDir = $this->app->rootPath() . '/user/plugins/javabean-admin';
        $manifest = $pluginDir . '/plugin.yaml';

        if (!file_exists($manifest)) {
            return new RedirectResponse($this->prefix . '/javabean');
        }

        $config = \Symfony\Component\Yaml\Yaml::parseFile($manifest);
        $config['config']['active_preset'] = $request->request->get('active_preset', 'javabean-classic');
        $config['config']['density'] = $request->request->get('density', 'comfy');
        $config['config']['custom_css'] = $request->request->get('custom_css', '');

        $yaml = \Symfony\Component\Yaml\Yaml::dump($config, 4);
        file_put_contents($manifest, $yaml);

        return new RedirectResponse($this->prefix . '/javabean?saved=1');
    }

    private function renderJavaBeanContent(array $data): string
    {
        if (empty($data['installed'])) {
            return '<h1>☕ JavaBean Admin</h1><p class="empty">JavaBean Admin plugin not installed. Add it to <code>user/plugins/javabean-admin/</code></p>';
        }

        $saved = !empty($data['saved']) ? '<div class="alert alert-success">Theme saved! Refresh to see changes.</div>' : '';
        $config = $data['config'] ?? [];
        $presets = $data['presets'] ?? [];
        $fonts = $data['fonts'] ?? [];
        $activePreset = $config['active_preset'] ?? 'javabean-classic';
        $density = $config['density'] ?? 'comfy';
        $customCss = htmlspecialchars($config['custom_css'] ?? '');

        $presetCards = '';
        foreach ($presets as $preset) {
            $slug = htmlspecialchars($preset['slug']);
            $name = htmlspecialchars($preset['name']);
            $tagline = htmlspecialchars($preset['tagline'] ?? '');
            $light = $preset['tokens']['light'] ?? [];
            $dark = $preset['tokens']['dark'] ?? [];
            $bg = $light['background'] ?? 'hsl(0 0% 100%)';
            $primary = $light['primary'] ?? 'hsl(271 91% 55%)';
            $sidebar = $light['sidebar'] ?? $bg;
            $isActive = ($slug === $activePreset) ? 'checked' : '';
            $activeClass = ($slug === $activePreset) ? ' preset-active' : '';

            $presetCards .= <<<HTML
<label class="preset-card{$activeClass}">
    <input type="radio" name="active_preset" value="{$slug}" {$isActive} style="display:none">
    <div class="preset-swatch" style="background: {$bg}; border-left: 4px solid {$primary};">
        <span class="preset-sidebar-swatch" style="background: {$sidebar};"></span>
    </div>
    <div class="preset-meta">
        <strong>{$name}</strong>
        <small>{$tagline}</small>
    </div>
</label>
HTML;
        }

        $densityOptions = '';
        foreach (['compact' => 'Compact', 'comfy' => 'Comfy', 'spacious' => 'Spacious'] as $val => $label) {
            $sel = ($density === $val) ? ' selected' : '';
            $densityOptions .= "<option value=\"{$val}\"{$sel}>{$label}</option>";
        }

        return <<<HTML
<h1>☕ JavaBean Admin</h1>
{$saved}
<form method="POST" action="{$data['prefix']}/javabean">
    {$data['csrf']}
    <div class="panel">
        <h2>Theme Preset</h2>
        <div class="preset-grid">
            {$presetCards}
        </div>
    </div>

    <div class="panel">
        <h2>Density</h2>
        <div class="form-group">
            <select name="density">{$densityOptions}</select>
        </div>
    </div>

    <div class="panel">
        <h2>Custom CSS</h2>
        <div class="form-group">
            <textarea name="custom_css" rows="6" class="code-editor" placeholder="/* your overrides */">{$customCss}</textarea>
        </div>
    </div>

    <button type="submit" class="btn btn-primary">Save Theme</button>
</form>
<style>
.preset-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 0.75rem; margin-top: 0.75rem; }
.preset-card { background: var(--card); border: 2px solid var(--border); border-radius: var(--radius); padding: 0.75rem; cursor: pointer; transition: all 0.2s; display: flex; flex-direction: column; gap: 0.5rem; }
.preset-card:hover { border-color: var(--primary); transform: translateY(-1px); }
.preset-card.preset-active, .preset-card:has(input:checked) { border-color: var(--primary); box-shadow: 0 0 0 2px var(--primary); }
.preset-swatch { height: 2.5rem; border-radius: 4px; display: flex; align-items: stretch; overflow: hidden; }
.preset-sidebar-swatch { width: 2rem; }
.preset-meta { display: flex; flex-direction: column; }
.preset-meta strong { font-size: 0.85rem; color: var(--text); }
.preset-meta small { font-size: 0.7rem; color: var(--muted); }
</style>
<script>
document.querySelectorAll('.preset-card').forEach(card => {
    card.addEventListener('click', () => {
        document.querySelectorAll('.preset-card').forEach(c => c.classList.remove('preset-active'));
        card.classList.add('preset-active');
        card.querySelector('input[type=radio]').checked = true;
    });
});
</script>
HTML;
    }

    private function adminCSS(): string
    {
        return <<<CSS
* { margin: 0; padding: 0; box-sizing: border-box; }
:root { --primary: #6b21a8; --secondary: #f43f5e; --bg: #0f0a1e; --surface: #1a1130; --card: #231845; --text: #f1f5f9; --muted: #94a3b8; --border: rgba(168,85,247,0.2); --radius: 8px; }
body { font-family: 'Inter', -apple-system, sans-serif; background: var(--bg); color: var(--text); display: flex; min-height: 100vh; }
.login-page { display: flex; align-items: center; justify-content: center; }
.login-box { background: var(--surface); padding: 2.5rem; border-radius: var(--radius); border: 1px solid var(--border); width: 100%; max-width: 380px; }
.login-brand { text-align: center; font-size: 1.5rem; font-weight: 700; margin-bottom: 2rem; }
.brand-icon { color: var(--secondary); font-size: 1.3em; }
.admin-sidebar { width: 240px; background: var(--surface); border-right: 1px solid var(--border); padding: 1.5rem 0; display: flex; flex-direction: column; position: fixed; top: 0; left: 0; bottom: 0; }
.admin-brand { padding: 0 1.5rem 1.5rem; font-size: 1.3rem; font-weight: 700; border-bottom: 1px solid var(--border); }
.admin-nav { flex: 1; padding: 1rem 0; }
.nav-item { display: block; padding: 0.7rem 1.5rem; color: var(--muted); text-decoration: none; transition: all 0.2s; }
.nav-item:hover { color: var(--text); background: rgba(168,85,247,0.1); }
.admin-user { padding: 1rem 1.5rem; border-top: 1px solid var(--border); font-size: 0.875rem; display: flex; justify-content: space-between; align-items: center; }
.admin-user a { color: var(--secondary); text-decoration: none; }
.admin-main { margin-left: 240px; padding: 2rem 3rem; flex: 1; }
h1 { font-size: 1.8rem; margin-bottom: 1.5rem; background: linear-gradient(135deg, var(--secondary), var(--primary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
h2 { font-size: 1.2rem; margin: 1.5rem 0 0.8rem; color: var(--muted); }
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
.stat-card { background: var(--card); padding: 1.5rem; border-radius: var(--radius); border: 1px solid var(--border); text-align: center; }
.stat-num { display: block; font-size: 1.5rem; font-weight: 700; color: var(--text); }
.stat-label { font-size: 0.8rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.05em; }
.panel { background: var(--card); padding: 1.5rem; border-radius: var(--radius); border: 1px solid var(--border); margin-bottom: 1.5rem; }
.panel ul { list-style: none; padding: 0; }
.panel li { padding: 0.4rem 0; }
.panel a { color: #06b6d4; text-decoration: none; }
.btn { display: inline-block; padding: 0.5rem 1rem; border-radius: 6px; border: 1px solid var(--border); background: var(--surface); color: var(--text); cursor: pointer; text-decoration: none; font-size: 0.875rem; margin-right: 0.5rem; margin-top: 0.5rem; transition: all 0.2s; }
.btn:hover { border-color: var(--primary); }
.btn-primary { background: var(--primary); border-color: var(--primary); color: white; }
.btn-primary:hover { opacity: 0.9; }
.btn-danger { background: var(--secondary); border-color: var(--secondary); color: white; }
.btn-sm { padding: 0.3rem 0.6rem; font-size: 0.75rem; }
.btn-block { width: 100%; text-align: center; }
.form-group { margin-bottom: 1rem; }
.form-group label { display: block; margin-bottom: 0.3rem; font-size: 0.875rem; color: var(--muted); }
.form-group input, .form-group textarea, .form-group select { width: 100%; padding: 0.6rem 0.8rem; border-radius: 6px; border: 1px solid var(--border); background: var(--bg); color: var(--text); font-size: 0.9rem; }
.form-group input:focus, .form-group textarea:focus { outline: none; border-color: var(--primary); }
.code-editor { font-family: 'JetBrains Mono', 'Fira Code', monospace; font-size: 0.85rem; line-height: 1.6; resize: vertical; }
.admin-table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
.admin-table th, .admin-table td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid var(--border); }
.admin-table th { color: var(--muted); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; }
.admin-table code { background: rgba(168,85,247,0.1); padding: 0.15rem 0.4rem; border-radius: 4px; font-size: 0.8rem; }
.media-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem; margin-top: 1rem; }
.media-card { background: var(--card); padding: 1rem; border-radius: var(--radius); border: 1px solid var(--border); }
.media-preview a { color: #06b6d4; text-decoration: none; word-break: break-all; }
.media-info { font-size: 0.75rem; color: var(--muted); margin: 0.5rem 0; }
.upload-form { margin-bottom: 1.5rem; display: flex; gap: 0.5rem; align-items: center; }
.upload-form input[type="file"] { color: var(--muted); }
.plugins-list, .themes-grid { display: grid; gap: 1rem; margin-top: 1rem; }
.plugin-card, .theme-card { background: var(--card); padding: 1.2rem; border-radius: var(--radius); border: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
.theme-card { flex-direction: column; align-items: flex-start; }
.plugin-info p { color: var(--muted); font-size: 0.85rem; margin-top: 0.3rem; }
.badge { font-size: 0.7rem; padding: 0.15rem 0.5rem; border-radius: 10px; text-transform: uppercase; letter-spacing: 0.05em; }
.badge-green { background: rgba(34,197,94,0.2); color: #22c55e; }
.badge-gray { background: rgba(148,163,184,0.2); color: #94a3b8; }
.alert { padding: 0.8rem 1rem; border-radius: 6px; margin-bottom: 1rem; }
.alert-success { background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.3); color: #22c55e; }
.alert-error { background: rgba(244,63,94,0.1); border: 1px solid rgba(244,63,94,0.3); color: #f43f5e; }
.empty { color: var(--muted); font-style: italic; }
.settings-form { max-width: 500px; }
body { font-family: var(--font-body, 'Inter', -apple-system, sans-serif); }
CSS;
    }

    private function getJavaBeanCss(): string
    {
        $pluginDir = $this->app->rootPath() . '/user/plugins/javabean-admin';
        $manifest = $pluginDir . '/plugin.yaml';

        if (!file_exists($manifest)) {
            return '';
        }

        $config = \Symfony\Component\Yaml\Yaml::parseFile($manifest);
        if (empty($config['enabled'])) {
            return '';
        }

        require_once $pluginDir . '/classes/JavaBeanFontCatalog.php';
        require_once $pluginDir . '/classes/JavaBeanPresetRegistry.php';
        require_once $pluginDir . '/classes/JavaBeanThemeEngine.php';

        $engine = new \GSpot\UserPlugins\JavabeanAdmin\JavaBeanThemeEngine();
        return $engine->buildCss([
            'enabled' => true,
            'active_preset' => $config['config']['active_preset'] ?? 'javabean-classic',
            'density' => $config['config']['density'] ?? 'comfy',
            'custom_css' => $config['config']['custom_css'] ?? '',
            'styling' => $config['config']['styling'] ?? [],
        ]);
    }

    private function getJavaBeanGoogleFont(): string
    {
        $pluginDir = $this->app->rootPath() . '/user/plugins/javabean-admin';
        $manifest = $pluginDir . '/plugin.yaml';

        if (!file_exists($manifest)) {
            return '';
        }

        $config = \Symfony\Component\Yaml\Yaml::parseFile($manifest);
        if (empty($config['enabled'])) {
            return '';
        }

        require_once $pluginDir . '/classes/JavaBeanFontCatalog.php';
        require_once $pluginDir . '/classes/JavaBeanPresetRegistry.php';

        $presetSlug = $config['config']['active_preset'] ?? 'javabean-classic';
        $preset = \GSpot\UserPlugins\JavabeanAdmin\JavaBeanPresetRegistry::get($presetSlug);
        $fontKey = $preset['fontFamily'] ?? 'jost';

        $styling = $config['config']['styling'] ?? [];
        if (isset($styling['usePresetFont']) && $styling['usePresetFont'] === false && !empty($styling['fontFamily'])) {
            $fontKey = (string) $styling['fontFamily'];
        }

        $fonts = \GSpot\UserPlugins\JavabeanAdmin\JavaBeanFontCatalog::all();
        $fontMeta = $fonts[$fontKey] ?? null;

        if (empty($fontMeta['google'])) {
            return '';
        }

        $spec = htmlspecialchars((string) $fontMeta['google'], ENT_QUOTES, 'UTF-8');
        return '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=' . $spec . '&display=swap">';
    }
}
