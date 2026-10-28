<?php

declare(strict_types=1);

namespace GSpot\UserPlugins\Commerce;

use GSpot\Plugin\AbstractPlugin;
use GSpot\Router\Page;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class CommercePlugin extends AbstractPlugin
{
    private ?ProductRegistry $registry = null;
    private ?Cart $cart = null;

    public function onAppBoot(mixed &$app): void
    {
        require_once __DIR__ . '/classes/ProductRegistry.php';
        require_once __DIR__ . '/classes/Cart.php';
        require_once __DIR__ . '/classes/StripeCheckout.php';
        require_once __DIR__ . '/classes/OrderRegistry.php';
        require_once __DIR__ . '/classes/StripeWebhook.php';
        require_once __DIR__ . '/classes/CouponRegistry.php';
        require_once __DIR__ . '/classes/DiscountCalculator.php';
        require_once __DIR__ . '/classes/OrderReceiptNotifier.php';
        require_once __DIR__ . '/classes/DownloadGrantRegistry.php';
        require_once __DIR__ . '/classes/DigitalFulfillment.php';

        $this->app->renderer()->addViewPath(__DIR__ . '/views');
        $this->app->renderer()->share('commerceCss', '/plugin-assets/commerce/css/shop.css');
    }

    public function onPageRender(Page &$page): void
    {
        $content = $page->content();

        if (str_contains($content, '[commerce-shop]')) {
            $html = '<p class="commerce-embed"><a class="btn primary" href="/commerce/shop">Open shop →</a></p>';
            $content = str_replace('[commerce-shop]', $html, $content);
        }

        if (preg_match_all('/\[commerce-product\s+slug="([a-z0-9-]+)"\]/', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $product = $this->registry()->get($match[1]);
                if ($product === null) {
                    continue;
                }
                $title = htmlspecialchars($product['title'] ?? $match[1]);
                $price = number_format(((int) ($product['price_cents'] ?? 0)) / 100, 2);
                $cur = strtoupper($product['currency'] ?? 'aud');
                $slug = htmlspecialchars($product['slug'] ?? $match[1]);
                $embed = <<<HTML
<div class="commerce-embed-card">
  <strong>{$title}</strong> — {$cur} {$price}
  <form method="POST" action="/commerce/cart/add" style="display:inline;margin-left:0.5rem">
    <input type="hidden" name="slug" value="{$slug}">
    <input type="hidden" name="qty" value="1">
    <button type="submit">Add to cart</button>
  </form>
  <a href="/commerce/product/{$slug}">Details</a>
</div>
HTML;
                $content = str_replace($match[0], $embed, $content);
            }
        }

        $page->setContent($content);
    }

    public function handleHttp(string $uri, Request $request): ?Response
    {
        $path = rtrim(parse_url($uri, PHP_URL_PATH) ?: $uri, '/') ?: '/commerce/shop';

        return match (true) {
            $path === '/commerce/shop' => $this->shop($request),
            $path === '/commerce/cart' => $this->cartPage($request),
            $path === '/commerce/checkout' && $request->isMethod('POST') => $this->checkout($request),
            $path === '/commerce/cart/add' && $request->isMethod('POST') => $this->cartAdd($request),
            $path === '/commerce/cart/update' && $request->isMethod('POST') => $this->cartUpdate($request),
            $path === '/commerce/cart/coupon' && $request->isMethod('POST') => $this->cartCoupon($request),
            $path === '/commerce/cart/coupon/remove' && $request->isMethod('POST') => $this->cartCouponRemove($request),
            $path === '/commerce/success' => $this->success($request),
            $path === '/commerce/webhook/stripe' && $request->isMethod('POST') => $this->stripeWebhook($request),
            str_starts_with($path, '/commerce/product/') => $this->product($path),
            str_starts_with($path, '/commerce/download/') => $this->downloadFile($path),
            default => null,
        };
    }

    public function orders(): OrderRegistry
    {
        return new OrderRegistry($this->ordersDir());
    }

    public function registry(): ProductRegistry
    {
        return $this->registry ??= new ProductRegistry($this->productsDir());
    }

    public function cart(): Cart
    {
        return $this->cart ??= new Cart($this->registry(), $this->coupons());
    }

    public function coupons(): CouponRegistry
    {
        return new CouponRegistry($this->couponsDir());
    }

    private function shop(Request $request): Response
    {
        return new Response($this->renderView('shop', [
            'products' => $this->registry()->all(),
            'site' => $this->siteData(),
        ]));
    }

    private function product(string $path): Response
    {
        $slug = basename($path);
        $product = $this->registry()->get($slug);
        if ($product === null) {
            return new Response('Product not found', 404);
        }

        return new Response($this->renderView('product', [
            'product' => $product,
            'site' => $this->siteData(),
        ]));
    }

    private function cartPage(Request $request): Response
    {
        $coupon = $this->cart()->appliedCoupon();
        $flatShip = $this->flatShippingCents();

        return new Response($this->renderView('cart', [
            'lines' => $this->cart()->lines(),
            'subtotal_cents' => $this->cart()->subtotalCents(),
            'discount_cents' => $this->cart()->discountCents(),
            'shipping_cents' => $this->cart()->shippingCents($flatShip),
            'total_cents' => $this->cart()->totalCents($flatShip),
            'coupon' => $coupon,
            'stripe_ready' => $this->stripe()->isConfigured(),
            'site' => $this->siteData(),
        ]));
    }

    private function cartAdd(Request $request): Response
    {
        $this->cart()->add(
            (string) $request->request->get('slug', ''),
            (int) $request->request->get('qty', 1),
        );

        $redirect = $request->request->get('redirect', '/commerce/cart');

        return new RedirectResponse((string) $redirect);
    }

    private function cartUpdate(Request $request): Response
    {
        $qtys = $request->request->all()['qty'] ?? [];
        if (!is_array($qtys)) {
            $qtys = [];
        }
        foreach ($qtys as $slug => $qty) {
            $this->cart()->update((string) $slug, (int) $qty);
        }

        return new RedirectResponse('/commerce/cart');
    }

    private function cartCoupon(Request $request): Response
    {
        $error = $this->cart()->applyCoupon((string) $request->request->get('code', ''));
        if ($error !== null) {
            $flatShip = $this->flatShippingCents();

            return new Response($this->renderView('cart', [
                'lines' => $this->cart()->lines(),
                'subtotal_cents' => $this->cart()->subtotalCents(),
                'discount_cents' => $this->cart()->discountCents(),
                'shipping_cents' => $this->cart()->shippingCents($flatShip),
                'total_cents' => $this->cart()->totalCents($flatShip),
                'coupon' => $this->cart()->appliedCoupon(),
                'stripe_ready' => $this->stripe()->isConfigured(),
                'error' => $error,
                'site' => $this->siteData(),
            ]), 422);
        }

        return new RedirectResponse('/commerce/cart');
    }

    private function cartCouponRemove(Request $request): Response
    {
        $this->cart()->removeCoupon();

        return new RedirectResponse('/commerce/cart');
    }

    private function checkout(Request $request): Response
    {
        if ($this->cart()->isEmpty()) {
            return new RedirectResponse('/commerce/cart');
        }

        try {
            $cartSnap = json_encode($this->cart()->snapshotForOrder()) ?: '[]';
            $coupon = $this->cart()->appliedCoupon();
            $discount = $this->cart()->discountCents();
            $lines = $this->cart()->lines();
            if ($discount > 0) {
                $lines = (new DiscountCalculator())->applyToLines($lines, $discount);
            }

            $flatShip = $this->flatShippingCents();
            $session = $this->stripe()->createSession(
                $lines,
                $this->defaultCurrency(),
                [
                    'coupon' => (string) ($coupon['code'] ?? ''),
                    'cart' => $cartSnap,
                ],
                $this->cart()->shippingCents($flatShip),
            );
            $this->cart()->clear();
            $this->cart()->removeCoupon();

            $url = $session->url ?? '';
            if ($url === '') {
                throw new \RuntimeException('Stripe did not return a checkout URL');
            }

            return new RedirectResponse($url);
        } catch (\Throwable $e) {
            $flatShip = $this->flatShippingCents();

            return new Response($this->renderView('cart', [
                'lines' => $this->cart()->lines(),
                'subtotal_cents' => $this->cart()->subtotalCents(),
                'discount_cents' => $this->cart()->discountCents(),
                'shipping_cents' => $this->cart()->shippingCents($flatShip),
                'total_cents' => $this->cart()->totalCents($flatShip),
                'coupon' => $this->cart()->appliedCoupon(),
                'stripe_ready' => $this->stripe()->isConfigured(),
                'error' => $e->getMessage(),
                'site' => $this->siteData(),
            ]), 502);
        }
    }

    private function downloadFile(string $path): Response
    {
        $token = basename($path);
        $grant = $this->downloadGrants()->redeem($token);
        if ($grant === null) {
            return new Response('Download link expired or invalid.', 410);
        }

        $slug = (string) ($grant['slug'] ?? '');
        $file = $this->registry()->resolveDownloadPath($slug);
        if ($file === null) {
            return new Response('Digital file not found.', 404);
        }

        $response = new BinaryFileResponse($file);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            basename($file),
        );

        return $response;
    }

    private function success(Request $request): Response
    {
        $sessionId = (string) $request->query->get('session_id', '');
        $order = $sessionId !== '' ? $this->orders()->get($sessionId) : null;

        return new Response($this->renderView('success', [
            'site' => $this->siteData(),
            'order' => $order,
            'session_id' => $sessionId,
        ]));
    }

    private function stripeWebhook(Request $request): Response
    {
        try {
            $handler = new StripeWebhook(
                $this->stripeSecret(),
                $this->webhookSecret(),
                $this->orders(),
                $this->registry(),
                $this->coupons(),
                $this->receiptNotifier(),
                $this->digitalFulfillment(),
            );
            $handler->handle($request->getContent(), $request->headers->get('Stripe-Signature'));

            return new Response('ok', 200);
        } catch (\Throwable $e) {
            return new Response($e->getMessage(), 400);
        }
    }

    private function webhookSecret(): string
    {
        $env = (string) ($this->config['config']['stripe_webhook_env'] ?? 'STRIPE_WEBHOOK_SECRET');
        $fromEnv = getenv($env) ?: ($_ENV[$env] ?? '');

        return is_string($fromEnv) ? trim($fromEnv) : '';
    }

    private function stripe(): StripeCheckout
    {
        $base = rtrim($this->app->config()->get('site.url', 'http://localhost:8080'), '/');

        return new StripeCheckout(
            $this->stripeSecret(),
            $base . '/commerce/success?session_id={CHECKOUT_SESSION_ID}',
            $base . '/commerce/cart',
        );
    }

    private function stripeSecret(): string
    {
        $env = (string) ($this->config['config']['stripe_secret_env'] ?? 'STRIPE_SECRET_KEY');
        $fromEnv = getenv($env) ?: ($_ENV[$env] ?? '');

        return is_string($fromEnv) ? trim($fromEnv) : '';
    }

    private function defaultCurrency(): string
    {
        return strtolower((string) ($this->config['config']['currency'] ?? 'aud'));
    }

    private function flatShippingCents(): int
    {
        return max(0, (int) ($this->config['config']['flat_shipping_cents'] ?? 0));
    }

    private function downloadGrants(): DownloadGrantRegistry
    {
        $rel = (string) ($this->config['config']['download_grants_path'] ?? 'data/download_grants');

        return new DownloadGrantRegistry(__DIR__ . '/' . ltrim($rel, '/'));
    }

    private function digitalFulfillment(): DigitalFulfillment
    {
        return new DigitalFulfillment(
            $this->registry(),
            $this->downloadGrants(),
            rtrim((string) $this->app->config()->get('site.url', 'http://localhost:8080'), '/'),
            max(1, (int) ($this->config['config']['download_ttl_days'] ?? 14)),
            max(1, (int) ($this->config['config']['download_max_hits'] ?? 10)),
        );
    }

    private function productsDir(): string
    {
        $rel = (string) ($this->config['config']['products_path'] ?? 'data/products');

        return __DIR__ . '/' . ltrim($rel, '/');
    }

    private function ordersDir(): string
    {
        $rel = (string) ($this->config['config']['orders_path'] ?? 'data/orders');

        return __DIR__ . '/' . ltrim($rel, '/');
    }

    private function couponsDir(): string
    {
        $rel = (string) ($this->config['config']['coupons_path'] ?? 'data/coupons');

        return __DIR__ . '/' . ltrim($rel, '/');
    }

    private function receiptsDir(): string
    {
        $rel = (string) ($this->config['config']['receipts_path'] ?? 'data/receipts');

        return __DIR__ . '/' . ltrim($rel, '/');
    }

    private function receiptNotifier(): OrderReceiptNotifier
    {
        $env = (string) ($this->config['config']['notify_email_env'] ?? 'COMMERCE_NOTIFY_EMAIL');
        $fromEnv = getenv($env) ?: ($_ENV[$env] ?? '');
        $email = is_string($fromEnv) ? trim($fromEnv) : '';

        return new OrderReceiptNotifier(
            $this->receiptsDir(),
            $email,
            (string) $this->app->config()->get('site.title', 'GSpot Shop'),
        );
    }

    /** @param array<string, mixed> $data */
    private function renderView(string $view, array $data): string
    {
        $data['formatMoney'] = fn (int $cents, string $cur = 'aud') => strtoupper($cur) . ' ' . number_format($cents / 100, 2);

        return $this->app->renderer()->render('commerce.' . $view, $data);
    }

    /** @return array<string, mixed> */
    private function siteData(): array
    {
        return [
            'title' => $this->app->config()->get('site.title', 'GSpot CMS'),
            'url' => $this->app->config()->get('site.url', 'http://localhost:8080'),
        ];
    }
}
