<?php

declare(strict_types=1);

namespace GSpot\UserPlugins\Commerce;

use GSpot\Application;
use GSpot\Security\Csrf;

final class CommerceAdmin
{
    public function __construct(private readonly Application $app) {}

    public function productsListContent(): string
    {
        $prefix = $this->adminPrefix();
        $rows = '';
        foreach ($this->registry()->all(false) as $p) {
            $slug = htmlspecialchars($p['slug'] ?? '');
            $title = htmlspecialchars($p['title'] ?? '');
            $price = number_format(((int) ($p['price_cents'] ?? 0)) / 100, 2);
            $cur = strtoupper($p['currency'] ?? 'aud');
            $active = ($p['active'] ?? true) ? 'Yes' : 'No';
            $rows .= <<<HTML
<tr>
  <td><strong>{$title}</strong></td>
  <td><code>{$slug}</code></td>
  <td>{$cur} {$price}</td>
  <td>{$active}</td>
  <td><a class="btn btn-sm" href="{$prefix}/commerce/products/edit?slug={$slug}">Edit</a></td>
</tr>
HTML;
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="5" class="empty">No products yet.</td></tr>';
        }

        return <<<HTML
<h1>🛒 Commerce — Products</h1>
<p><a class="btn btn-primary" href="{$prefix}/commerce/products/new">+ New product</a>
   <a class="btn" href="/commerce/shop" target="_blank" rel="noopener">View shop</a></p>
<table class="admin-table">
  <thead><tr><th>Title</th><th>Slug</th><th>Price</th><th>Active</th><th></th></tr></thead>
  <tbody>{$rows}</tbody>
</table>
HTML;
    }

    public function productEditContent(string $slug, string $csrf): string
    {
        $prefix = $this->adminPrefix();
        $product = $slug !== '' ? $this->registry()->get($slug) : null;
        $isNew = $product === null;
        $s = htmlspecialchars($product['slug'] ?? $slug);
        $title = htmlspecialchars($product['title'] ?? '');
        $desc = htmlspecialchars($product['description'] ?? '');
        $price = number_format(((int) ($product['price_cents'] ?? 0)) / 100, 2, '.', '');
        $currency = htmlspecialchars($product['currency'] ?? 'aud');
        $image = htmlspecialchars($product['image'] ?? '');
        $stripePrice = htmlspecialchars($product['stripe_price_id'] ?? '');
        $active = ($product['active'] ?? true) ? 'checked' : '';
        $readonly = $isNew ? '' : 'readonly';
        $heading = $isNew ? 'New product' : 'Edit product';

        $delete = '';
        if (!$isNew && $s !== '') {
            $delete = <<<HTML
<form method="POST" action="{$prefix}/commerce/products/delete" style="margin-top:1rem" onsubmit="return confirm('Delete product?')">
  {$csrf}
  <input type="hidden" name="slug" value="{$s}">
  <button type="submit" class="btn btn-danger">Delete product</button>
</form>
HTML;
        }

        return <<<HTML
<h1>🛒 {$heading}</h1>
<form method="POST" action="{$prefix}/commerce/products/save">
  {$csrf}
  <div class="panel">
    <div class="form-group"><label>Slug</label><input name="slug" value="{$s}" required pattern="[a-z0-9-]+" {$readonly}></div>
    <div class="form-group"><label>Title</label><input name="title" value="{$title}" required></div>
    <div class="form-group"><label>Description</label><textarea name="description" rows="3">{$desc}</textarea></div>
    <div class="form-group"><label>Price (dollars)</label><input name="price" value="{$price}" type="number" step="0.01" min="0"></div>
    <div class="form-group"><label>Currency</label><input name="currency" value="{$currency}" maxlength="3"></div>
    <div class="form-group"><label>Image URL/path</label><input name="image" value="{$image}" placeholder="/media/product.png"></div>
    <div class="form-group"><label>Stripe Price ID (optional)</label><input name="stripe_price_id" value="{$stripePrice}" placeholder="price_..."></div>
    <div class="form-group"><label><input type="checkbox" name="active" value="1" {$active}> Active</label></div>
  </div>
  <button type="submit" class="btn btn-primary">Save</button>
  <a class="btn" href="{$prefix}/commerce/products">Back</a>
</form>
{$delete}
HTML;
    }

    public function registry(): ProductRegistry
    {
        require_once $this->app->rootPath() . '/user/plugins/commerce/classes/ProductRegistry.php';

        return new ProductRegistry($this->app->rootPath() . '/user/plugins/commerce/data/products');
    }

    private function adminPrefix(): string
    {
        return '/' . trim($this->app->config()->get('system.admin.prefix', 'gspot-admin'), '/');
    }
}
