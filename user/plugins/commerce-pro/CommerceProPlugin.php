<?php

declare(strict_types=1);

namespace GSpot\UserPlugins\CommercePro;

use GSpot\Plugin\AbstractPlugin;

class CommerceProPlugin extends AbstractPlugin
{
    public function onAppBoot(mixed &$app): void
    {
        require_once __DIR__ . '/classes/ProLicense.php';
        require_once __DIR__ . '/classes/VendorRegistry.php';
    }

    public function isLicensed(): bool
    {
        return ProLicense::isLicensed($this->app);
    }

    public function vendors(): VendorRegistry
    {
        $rel = (string) ($this->config['config']['vendors_path'] ?? 'data/vendors');

        return new VendorRegistry(__DIR__ . '/' . ltrim($rel, '/'));
    }

    public function vendorsListAdminHtml(): string
    {
        if (!$this->isLicensed()) {
            return $this->proRequiredHtml();
        }

        $prefix = '/' . trim($this->app->config()->get('system.admin.prefix', 'gspot-admin'), '/');
        $rows = '';
        foreach ($this->vendors()->all(false) as $v) {
            $slug = htmlspecialchars($v['slug'] ?? '');
            $name = htmlspecialchars($v['name'] ?? '');
            $fee = (float) ($v['platform_fee_percent'] ?? 0);
            $active = ($v['active'] ?? true) ? 'Yes' : 'No';
            $shop = '/commerce/shop?vendor=' . rawurlencode($v['slug'] ?? '');
            $rows .= "<tr><td>{$name}</td><td><code>{$slug}</code></td><td>{$fee}%</td><td>{$active}</td><td><a href=\"{$shop}\" target=\"_blank\" rel=\"noopener\">Shop</a></td></tr>";
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="5" class="empty">No vendors yet. Add YAML under commerce-pro/data/vendors/</td></tr>';
        }

        return <<<HTML
<h1>🏪 Commerce Pro — Vendors</h1>
<p><a class="btn" href="{$prefix}/commerce/products">← Commerce</a></p>
<p>Assign <code>vendor_slug</code> on products (admin product edit). Shoppers filter via <code>?vendor=slug</code>.</p>
<table class="admin-table"><thead><tr><th>Name</th><th>Slug</th><th>Platform fee</th><th>Active</th><th></th></tr></thead><tbody>{$rows}</tbody></table>
HTML;
    }

    public function proRequiredHtml(): string
    {
        return <<<'HTML'
<h1>Commerce Pro</h1>
<p>This feature requires a valid <strong>Commerce Pro</strong> license.</p>
<p>Set <code>COMMERCE_PRO_LICENSE</code> in your environment or place your key in <code>user/plugins/commerce-pro/license.key</code>.</p>
<p>Core checkout (single-store) remains free in the base commerce plugin.</p>
HTML;
    }
}
