<?php

declare(strict_types=1);

namespace GSpot\UserPlugins\Commerce;

use GSpot\Application;
use GSpot\Security\Csrf;

final class CommerceAdmin
{
    public function __construct(private readonly Application $app) {}

    public function ordersListContent(): string
    {
        $prefix = $this->adminPrefix();
        require_once $this->app->rootPath() . '/user/plugins/commerce/classes/OrderRegistry.php';
        $orders = (new OrderRegistry($this->app->rootPath() . '/user/plugins/commerce/data/orders'))->all(30);
        $rows = '';
        foreach ($orders as $o) {
            $id = htmlspecialchars($o['id'] ?? '');
            $email = htmlspecialchars($o['email'] ?? '—');
            $amt = number_format(((int) ($o['amount_total'] ?? 0)) / 100, 2);
            $cur = strtoupper($o['currency'] ?? 'aud');
            $status = htmlspecialchars($o['status'] ?? '');
            $created = htmlspecialchars($o['created'] ?? '');
            $view = $prefix . '/commerce/orders/view?id=' . rawurlencode($o['id'] ?? '');
            $rows .= "<tr><td><code>{$id}</code></td><td>{$email}</td><td>{$cur} {$amt}</td><td>{$status}</td><td>{$created}</td><td><a class=\"btn btn-sm\" href=\"{$view}\">View</a></td></tr>";
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="6" class="empty">No orders yet. Complete a Stripe test checkout with webhooks configured.</td></tr>';
        }

        return <<<HTML
<h1>🛒 Commerce — Orders</h1>
<p><a class="btn" href="{$prefix}/commerce/products">← Products</a></p>
<table class="admin-table">
  <thead><tr><th>Session</th><th>Email</th><th>Total</th><th>Status</th><th>Created</th><th></th></tr></thead>
  <tbody>{$rows}</tbody>
</table>
HTML;
    }

    public function orderDetailContent(string $orderId, string $csrf = ''): string
    {
        $prefix = $this->adminPrefix();
        require_once $this->app->rootPath() . '/user/plugins/commerce/classes/OrderRegistry.php';
        $order = (new OrderRegistry($this->app->rootPath() . '/user/plugins/commerce/data/orders'))->get($orderId);
        if ($order === null) {
            return '<h1>Order not found</h1><p><a class="btn" href="' . $prefix . '/commerce/orders">Back</a></p>';
        }

        $id = htmlspecialchars($order['id'] ?? '');
        $email = htmlspecialchars($order['email'] ?? '—');
        $amt = number_format(((int) ($order['amount_total'] ?? 0)) / 100, 2);
        $cur = strtoupper($order['currency'] ?? 'aud');
        $coupon = htmlspecialchars($order['coupon'] ?? '');
        $couponRow = $coupon !== '' ? "<tr><th>Coupon</th><td><code>{$coupon}</code></td></tr>" : '';
        $tax = htmlspecialchars($order['tax_country'] ?? '');
        $taxRow = $tax !== '' ? "<tr><th>Tax region</th><td>{$tax}</td></tr>" : '';
        $billing = htmlspecialchars($order['billing_mode'] ?? 'payment');
        $refundStatus = htmlspecialchars($order['refund_status'] ?? 'none');
        $pi = htmlspecialchars($order['stripe_payment_intent'] ?? '');
        $customerId = (string) ($order['stripe_customer_id'] ?? '');
        $portalLink = $customerId !== ''
            ? '<p><a class="btn" href="/commerce/portal?order=' . rawurlencode($orderId) . '" target="_blank" rel="noopener">Stripe Customer Portal</a></p>'
            : '<p class="empty">No Stripe customer on file (legacy order)</p>';

        $refundForm = '';
        if ($pi !== '' && ($order['refund_status'] ?? 'none') !== 'full') {
            $refundForm = <<<HTML
<h2>Refund</h2>
<form method="POST" action="{$prefix}/commerce/orders/refund" onsubmit="return confirm('Issue Stripe refund for this order?')">
  {$csrf}
  <input type="hidden" name="order_id" value="{$id}">
  <div class="form-group"><label>Amount (blank = full refund, dollars)</label><input name="amount" placeholder="optional"></div>
  <button type="submit" class="btn btn-danger">Refund via Stripe</button>
</form>
HTML;
        }

        $cartRows = '';
        $cartJson = (string) ($order['cart_snapshot'] ?? '');
        $cart = json_decode($cartJson, true);
        if (is_array($cart)) {
            foreach ($cart as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $slug = htmlspecialchars((string) ($row['slug'] ?? ''));
                $qty = (int) ($row['qty'] ?? 0);
                $product = $this->registry()->get((string) ($row['slug'] ?? ''));
                $title = htmlspecialchars($product['title'] ?? $slug);
                $cartRows .= "<tr><td>{$title}</td><td><code>{$slug}</code></td><td>{$qty}</td></tr>";
            }
        }
        if ($cartRows === '') {
            $cartRows = '<tr><td colspan="3" class="empty">No cart snapshot (legacy order)</td></tr>';
        }

        $dlRows = '';
        foreach ($order['downloads'] ?? [] as $dl) {
            if (!is_array($dl)) {
                continue;
            }
            $title = htmlspecialchars($dl['title'] ?? 'Download');
            $url = htmlspecialchars($dl['url'] ?? '');
            $token = htmlspecialchars($dl['token'] ?? '');
            $dlRows .= "<tr><td>{$title}</td><td><code>{$token}</code></td><td><a href=\"{$url}\" target=\"_blank\" rel=\"noopener\">Customer link</a></td></tr>";
        }
        if ($dlRows === '') {
            $dlRows = '<tr><td colspan="3" class="empty">No digital grants</td></tr>';
        }

        $stripeLines = '';
        foreach ($order['line_items'] ?? [] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $desc = htmlspecialchars($item['description'] ?? '');
            $qty = (int) ($item['quantity'] ?? 1);
            $sub = number_format(((int) ($item['amount_subtotal'] ?? 0)) / 100, 2);
            $stripeLines .= "<tr><td>{$desc}</td><td>{$qty}</td><td>{$cur} {$sub}</td></tr>";
        }
        if ($stripeLines === '') {
            $stripeLines = '<tr><td colspan="3" class="empty">No Stripe line items</td></tr>';
        }

        return <<<HTML
<h1>🛒 Order</h1>
<p><a class="btn" href="{$prefix}/commerce/orders">← Orders</a></p>
<table class="admin-table">
  <tr><th>Session</th><td><code>{$id}</code></td></tr>
  <tr><th>Email</th><td>{$email}</td></tr>
  <tr><th>Total</th><td>{$cur} {$amt}</td></tr>
  <tr><th>Mode</th><td>{$billing}</td></tr>
  {$couponRow}
  {$taxRow}
  <tr><th>Refund</th><td>{$refundStatus}</td></tr>
  <tr><th>Payment intent</th><td><code>{$pi}</code></td></tr>
</table>
{$portalLink}
{$refundForm}
<h2>Cart snapshot</h2>
<table class="admin-table"><thead><tr><th>Product</th><th>Slug</th><th>Qty</th></tr></thead><tbody>{$cartRows}</tbody></table>
<h2>Stripe line items</h2>
<table class="admin-table"><thead><tr><th>Description</th><th>Qty</th><th>Subtotal</th></tr></thead><tbody>{$stripeLines}</tbody></table>
<h2>Digital downloads</h2>
<table class="admin-table"><thead><tr><th>Title</th><th>Token</th><th>Link</th></tr></thead><tbody>{$dlRows}</tbody></table>
HTML;
    }

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
            $stockLabel = !array_key_exists('stock', $p) || $p['stock'] === null ? '∞' : (string) (int) $p['stock'];
            $rows .= <<<HTML
<tr>
  <td><strong>{$title}</strong></td>
  <td><code>{$slug}</code></td>
  <td>{$cur} {$price}</td>
  <td>{$stockLabel}</td>
  <td>{$active}</td>
  <td><a class="btn btn-sm" href="{$prefix}/commerce/products/edit?slug={$slug}">Edit</a></td>
</tr>
HTML;
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="6" class="empty">No products yet.</td></tr>';
        }

        return <<<HTML
<h1>🛒 Commerce — Products</h1>
<p><a class="btn btn-primary" href="{$prefix}/commerce/products/new">+ New product</a>
   <a class="btn" href="{$prefix}/commerce/orders">Orders</a>
   <a class="btn" href="{$prefix}/commerce/coupons">Coupons</a>
   <a class="btn" href="{$prefix}/commerce/gift-cards">Gift cards</a>
   <a class="btn" href="{$prefix}/commerce/subscriptions">Subscriptions</a>
   <a class="btn" href="{$prefix}/commerce/bundles">Bundles</a>
   <a class="btn" href="{$prefix}/commerce/affiliates">Affiliates</a>
   <a class="btn" href="/commerce/shop" target="_blank" rel="noopener">View shop</a></p>
<table class="admin-table">
  <thead><tr><th>Title</th><th>Slug</th><th>Price</th><th>Stock</th><th>Active</th><th></th></tr></thead>
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
        $downloadFile = htmlspecialchars($product['download_file'] ?? '');
        $shipRequired = ($product['ship_required'] ?? true) ? 'checked' : '';
        $billing = htmlspecialchars($product['billing_type'] ?? 'one_time');
        $active = ($product['active'] ?? true) ? 'checked' : '';
        $stockVal = array_key_exists('stock', $product) && $product['stock'] !== null
            ? (string) (int) $product['stock']
            : '';
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
    <div class="form-group"><label>Stock (blank = unlimited)</label><input name="stock" value="{$stockVal}" type="number" min="0" step="1"></div>
    <div class="form-group"><label>Billing</label>
      <select name="billing_type">
        <option value="one_time" {$this->selected($billing, 'one_time')}>One-time</option>
        <option value="subscription" {$this->selected($billing, 'subscription')}>Subscription (Stripe Price ID required)</option>
      </select>
    </div>
    <div class="form-group"><label>Digital file (relative to product folder)</label><input name="download_file" value="{$downloadFile}" placeholder="supporter-pack.txt"></div>
    <div class="form-group"><label><input type="checkbox" name="ship_required" value="1" {$shipRequired}> Requires shipping (flat rate)</label></div>
    <div class="form-group"><label><input type="checkbox" name="active" value="1" {$active}> Active</label></div>
  </div>
  <button type="submit" class="btn btn-primary">Save</button>
  <a class="btn" href="{$prefix}/commerce/products">Back</a>
</form>
{$delete}
HTML;
    }

    public function couponsListContent(): string
    {
        $prefix = $this->adminPrefix();
        $rows = '';
        foreach ($this->coupons()->all() as $c) {
            $code = htmlspecialchars($c['code'] ?? '');
            $type = htmlspecialchars($c['type'] ?? 'percent');
            $amount = (int) ($c['amount'] ?? 0);
            $label = $type === 'fixed' ? 'AUD ' . number_format($amount / 100, 2) : $amount . '%';
            $uses = (int) ($c['uses'] ?? 0);
            $max = $c['max_uses'] ?? '∞';
            $active = ($c['active'] ?? true) ? 'Yes' : 'No';
            $rows .= <<<HTML
<tr>
  <td><code>{$code}</code></td>
  <td>{$label}</td>
  <td>{$uses} / {$max}</td>
  <td>{$active}</td>
  <td><a class="btn btn-sm" href="{$prefix}/commerce/coupons/edit?code={$code}">Edit</a></td>
</tr>
HTML;
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="5" class="empty">No coupons yet.</td></tr>';
        }

        return <<<HTML
<h1>🛒 Commerce — Coupons</h1>
<p><a class="btn btn-primary" href="{$prefix}/commerce/coupons/new">+ New coupon</a>
   <a class="btn" href="{$prefix}/commerce/products">Products</a></p>
<table class="admin-table">
  <thead><tr><th>Code</th><th>Discount</th><th>Uses</th><th>Active</th><th></th></tr></thead>
  <tbody>{$rows}</tbody>
</table>
HTML;
    }

    public function couponEditContent(string $code, string $csrf): string
    {
        $prefix = $this->adminPrefix();
        $coupon = $code !== '' ? $this->coupons()->get($code) : null;
        $isNew = $coupon === null;
        $c = htmlspecialchars($coupon['code'] ?? $code);
        $type = htmlspecialchars($coupon['type'] ?? 'percent');
        $amount = (int) ($coupon['amount'] ?? 10);
        $amountDisplay = ($coupon['type'] ?? 'percent') === 'fixed'
            ? number_format($amount / 100, 2, '.', '')
            : (string) $amount;
        $maxUses = $coupon['max_uses'] ?? '';
        $maxUses = $maxUses === null ? '' : (string) $maxUses;
        $expires = htmlspecialchars($coupon['expires'] ?? '');
        $active = ($coupon['active'] ?? true) ? 'checked' : '';
        $readonly = $isNew ? '' : 'readonly';
        $heading = $isNew ? 'New coupon' : 'Edit coupon';

        $delete = '';
        if (!$isNew && $c !== '') {
            $delete = <<<HTML
<form method="POST" action="{$prefix}/commerce/coupons/delete" style="margin-top:1rem" onsubmit="return confirm('Delete coupon?')">
  {$csrf}
  <input type="hidden" name="code" value="{$c}">
  <button type="submit" class="btn btn-danger">Delete coupon</button>
</form>
HTML;
        }

        return <<<HTML
<h1>🛒 {$heading}</h1>
<form method="POST" action="{$prefix}/commerce/coupons/save">
  {$csrf}
  <div class="panel">
    <div class="form-group"><label>Code</label><input name="code" value="{$c}" required pattern="[A-Za-z0-9_-]+" {$readonly}></div>
    <div class="form-group"><label>Type</label>
      <select name="type">
        <option value="percent" {$this->selected($type, 'percent')}>Percent off</option>
        <option value="fixed" {$this->selected($type, 'fixed')}>Fixed amount (cents in DB; enter dollars below if fixed)</option>
      </select>
    </div>
    <div class="form-group"><label>Amount (percent 1–100, or dollars if fixed)</label><input name="amount" value="{$amountDisplay}" required></div>
    <div class="form-group"><label>Max uses (blank = unlimited)</label><input name="max_uses" value="{$maxUses}"></div>
    <div class="form-group"><label>Expires (ISO date, optional)</label><input name="expires" value="{$expires}" placeholder="2026-12-31"></div>
    <div class="form-group"><label><input type="checkbox" name="active" value="1" {$active}> Active</label></div>
  </div>
  <button type="submit" class="btn btn-primary">Save</button>
  <a class="btn" href="{$prefix}/commerce/coupons">Back</a>
</form>
{$delete}
HTML;
    }

    public function registry(): ProductRegistry
    {
        require_once $this->app->rootPath() . '/user/plugins/commerce/classes/ProductRegistry.php';

        return new ProductRegistry($this->app->rootPath() . '/user/plugins/commerce/data/products');
    }

    public function affiliatesListContent(): string
    {
        $prefix = $this->adminPrefix();
        require_once $this->app->rootPath() . '/user/plugins/commerce/classes/AffiliateRegistry.php';
        $rows = '';
        foreach ((new AffiliateRegistry($this->app->rootPath() . '/user/plugins/commerce/data/affiliates'))->all() as $a) {
            $code = htmlspecialchars($a['code'] ?? '');
            $name = htmlspecialchars($a['name'] ?? '');
            $pct = (float) ($a['commission_percent'] ?? 0);
            $owed = number_format(((int) ($a['commission_owed_cents'] ?? 0)) / 100, 2);
            $orders = (int) ($a['orders'] ?? 0);
            $rows .= "<tr><td><code>{$code}</code></td><td>{$name}</td><td>{$pct}%</td><td>{$orders}</td><td>AUD {$owed}</td></tr>";
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="5" class="empty">No affiliates</td></tr>';
        }

        return <<<HTML
<h1>🛒 Affiliates</h1>
<p><a class="btn" href="{$prefix}/commerce/products">← Products</a></p>
<p>Share links like <code>/commerce/shop?ref=TEAMDC</code> · YAML in <code>data/affiliates/</code></p>
<table class="admin-table"><thead><tr><th>Code</th><th>Name</th><th>Commission</th><th>Orders</th><th>Owed</th></tr></thead><tbody>{$rows}</tbody></table>
HTML;
    }

    public function bundlesListContent(): string
    {
        $prefix = $this->adminPrefix();
        require_once $this->app->rootPath() . '/user/plugins/commerce/classes/BundleRegistry.php';
        $registry = new BundleRegistry(
            $this->app->rootPath() . '/user/plugins/commerce/data/bundles',
            $this->registry(),
        );
        $rows = '';
        foreach ($registry->all(false) as $bundle) {
            $slug = htmlspecialchars($bundle['slug'] ?? '');
            $title = htmlspecialchars($bundle['title'] ?? '');
            $count = count($registry->lineItems($slug));
            $active = ($bundle['active'] ?? true) ? 'Yes' : 'No';
            $rows .= "<tr><td>{$title}</td><td><code>{$slug}</code></td><td>{$count}</td><td>{$active}</td></tr>";
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="4" class="empty">No bundles</td></tr>';
        }

        return <<<HTML
<h1>🛒 Bundles</h1>
<p><a class="btn" href="{$prefix}/commerce/products">← Products</a></p>
<p>YAML under <code>data/bundles/</code> · shortcode <code>[commerce-bundle slug="spot-starter"]</code></p>
<table class="admin-table"><thead><tr><th>Title</th><th>Slug</th><th>Items</th><th>Active</th></tr></thead><tbody>{$rows}</tbody></table>
HTML;
    }

    public function giftCardsListContent(): string
    {
        $prefix = $this->adminPrefix();
        require_once $this->app->rootPath() . '/user/plugins/commerce/classes/GiftCardRegistry.php';
        $rows = '';
        foreach ((new GiftCardRegistry($this->app->rootPath() . '/user/plugins/commerce/data/gift_cards'))->all() as $card) {
            $code = htmlspecialchars($card['code'] ?? '');
            $bal = number_format(((int) ($card['balance_cents'] ?? 0)) / 100, 2);
            $cur = strtoupper($card['currency'] ?? 'aud');
            $active = ($card['active'] ?? true) ? 'Yes' : 'No';
            $rows .= "<tr><td><code>{$code}</code></td><td>{$cur} {$bal}</td><td>{$active}</td></tr>";
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="3" class="empty">No gift cards</td></tr>';
        }

        return <<<HTML
<h1>🛒 Gift cards</h1>
<p><a class="btn" href="{$prefix}/commerce/products">← Products</a></p>
<p>Demo: <code>SPOT-GIFT-25</code> (AUD 25.00). Edit YAML under <code>data/gift_cards/</code> or use MCP <code>gspot_save_gift_card</code>.</p>
<table class="admin-table"><thead><tr><th>Code</th><th>Balance</th><th>Active</th></tr></thead><tbody>{$rows}</tbody></table>
HTML;
    }

    public function subscriptionsListContent(): string
    {
        $prefix = $this->adminPrefix();
        require_once $this->app->rootPath() . '/user/plugins/commerce/classes/SubscriptionRegistry.php';
        $rows = '';
        foreach ((new SubscriptionRegistry($this->app->rootPath() . '/user/plugins/commerce/data/subscriptions'))->all() as $sub) {
            $id = htmlspecialchars($sub['id'] ?? '');
            $status = htmlspecialchars($sub['status'] ?? '');
            $email = htmlspecialchars($sub['email'] ?? '—');
            $inv = htmlspecialchars($sub['last_invoice_status'] ?? '—');
            $rows .= "<tr><td><code>{$id}</code></td><td>{$status}</td><td>{$email}</td><td>{$inv}</td></tr>";
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="4" class="empty">No subscriptions filed yet (Stripe webhooks)</td></tr>';
        }

        return <<<HTML
<h1>🛒 Subscriptions</h1>
<p><a class="btn" href="{$prefix}/commerce/products">← Products</a></p>
<table class="admin-table"><thead><tr><th>Stripe ID</th><th>Status</th><th>Email</th><th>Last invoice</th></tr></thead><tbody>{$rows}</tbody></table>
HTML;
    }

    public function coupons(): CouponRegistry
    {
        require_once $this->app->rootPath() . '/user/plugins/commerce/classes/CouponRegistry.php';

        return new CouponRegistry($this->app->rootPath() . '/user/plugins/commerce/data/coupons');
    }

    private function selected(string $current, string $value): string
    {
        return $current === $value ? 'selected' : '';
    }

    private function adminPrefix(): string
    {
        return '/' . trim($this->app->config()->get('system.admin.prefix', 'gspot-admin'), '/');
    }
}
