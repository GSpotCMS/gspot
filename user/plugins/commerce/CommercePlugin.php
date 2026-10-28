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
        require_once __DIR__ . '/classes/TaxRegionRegistry.php';
        require_once __DIR__ . '/classes/TaxCalculator.php';
        require_once __DIR__ . '/classes/BuyerReceiptMailer.php';
        require_once __DIR__ . '/classes/AbandonedCartRegistry.php';
        require_once __DIR__ . '/classes/AbandonedCartReminder.php';
        require_once __DIR__ . '/classes/GiftCardRegistry.php';
        require_once __DIR__ . '/classes/SubscriptionRegistry.php';
        require_once __DIR__ . '/classes/BundleRegistry.php';
        require_once __DIR__ . '/classes/StripeRefundService.php';
        require_once __DIR__ . '/classes/StripeCustomerPortal.php';
        require_once __DIR__ . '/classes/CheckoutSmokeTest.php';
        require_once __DIR__ . '/classes/CommerceCheckoutAnalyzer.php';

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

        if (preg_match_all('/\[commerce-bundle\s+slug="([a-z0-9-]+)"\]/', $content, $bundleMatches, PREG_SET_ORDER)) {
            foreach ($bundleMatches as $match) {
                $bundle = $this->bundles()->get($match[1]);
                if ($bundle === null) {
                    continue;
                }
                $title = htmlspecialchars($bundle['title'] ?? $match[1]);
                $desc = htmlspecialchars($bundle['description'] ?? '');
                $slug = htmlspecialchars($bundle['slug'] ?? $match[1]);
                $embed = <<<HTML
<div class="commerce-embed-card">
  <strong>{$title}</strong> — bundle<br>{$desc}
  <form method="POST" action="/commerce/cart/add-bundle" style="margin-top:0.5rem">
    <input type="hidden" name="slug" value="{$slug}">
    <button type="submit">Add bundle to cart</button>
  </form>
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
            $path === '/commerce/cart/add-bundle' && $request->isMethod('POST') => $this->cartAddBundle($request),
            $path === '/commerce/portal' => $this->customerPortal($request),
            $path === '/commerce/cart/update' && $request->isMethod('POST') => $this->cartUpdate($request),
            $path === '/commerce/cart/coupon' && $request->isMethod('POST') => $this->cartCoupon($request),
            $path === '/commerce/cart/coupon/remove' && $request->isMethod('POST') => $this->cartCouponRemove($request),
            $path === '/commerce/cart/tax-country' && $request->isMethod('POST') => $this->cartTaxCountry($request),
            $path === '/commerce/cart/reminder-email' && $request->isMethod('POST') => $this->cartReminderEmail($request),
            $path === '/commerce/cart/gift-card' && $request->isMethod('POST') => $this->cartGiftCard($request),
            $path === '/commerce/cart/gift-card/remove' && $request->isMethod('POST') => $this->cartGiftCardRemove($request),
            $path === '/commerce/cron/abandoned' => $this->cronAbandoned($request),
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
        return $this->cart ??= new Cart($this->registry(), $this->coupons(), $this->giftCards());
    }

    public function coupons(): CouponRegistry
    {
        return new CouponRegistry($this->couponsDir());
    }

    public function giftCards(): GiftCardRegistry
    {
        return new GiftCardRegistry($this->giftCardsDir());
    }

    private function shop(Request $request): Response
    {
        return new Response($this->renderView('shop', [
            'products' => $this->registry()->all(),
            'bundles' => $this->bundles()->all(),
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
        return new Response($this->renderView('cart', $this->cartViewData()));
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

    private function cartAddBundle(Request $request): Response
    {
        $slug = (string) $request->request->get('slug', '');
        foreach ($this->bundles()->lineItems($slug) as $row) {
            $this->cart()->add($row['slug'], $row['qty']);
        }

        return new RedirectResponse('/commerce/cart');
    }

    private function customerPortal(Request $request): Response
    {
        $orderId = (string) $request->query->get('order', '');
        $order = $orderId !== '' ? $this->orders()->get($orderId) : null;
        $customerId = (string) ($order['stripe_customer_id'] ?? '');
        if ($customerId === '') {
            return new Response('Customer portal unavailable for this order.', 404);
        }

        try {
            $base = rtrim((string) $this->app->config()->get('site.url', 'http://localhost:8080'), '/');
            $portal = new StripeCustomerPortal(
                $this->stripeSecret(),
                $base . '/commerce/shop',
            );
            $session = $portal->createSession($customerId);

            return new RedirectResponse((string) ($session->url ?? '/commerce/shop'));
        } catch (\Throwable $e) {
            return new Response($e->getMessage(), 502);
        }
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

        $this->syncAbandonedCart();

        return new RedirectResponse('/commerce/cart');
    }

    private function cartCoupon(Request $request): Response
    {
        $error = $this->cart()->applyCoupon((string) $request->request->get('code', ''));
        if ($error !== null) {
            return new Response($this->renderView('cart', $this->cartViewData($error)), 422);
        }

        return new RedirectResponse('/commerce/cart');
    }

    private function cartCouponRemove(Request $request): Response
    {
        $this->cart()->removeCoupon();

        return new RedirectResponse('/commerce/cart');
    }

    private function cartTaxCountry(Request $request): Response
    {
        $this->cart()->setTaxCountry((string) $request->request->get('country', 'AU'));

        return new RedirectResponse('/commerce/cart');
    }

    private function cartReminderEmail(Request $request): Response
    {
        $this->cart()->setReminderEmail((string) $request->request->get('email', ''));
        $this->syncAbandonedCart();

        return new RedirectResponse('/commerce/cart');
    }

    private function cartGiftCard(Request $request): Response
    {
        $error = $this->cart()->applyGiftCard((string) $request->request->get('code', ''));
        if ($error !== null) {
            return new Response($this->renderView('cart', $this->cartViewData($error)), 422);
        }

        return new RedirectResponse('/commerce/cart');
    }

    private function cartGiftCardRemove(Request $request): Response
    {
        $this->cart()->removeGiftCard();

        return new RedirectResponse('/commerce/cart');
    }

    private function cronAbandoned(Request $request): Response
    {
        if (!$this->cronAuthorized($request)) {
            return new Response('Forbidden', 403);
        }

        $idle = max(15, (int) ($this->config['config']['abandoned_idle_minutes'] ?? 60));
        $result = $this->abandonedReminder()->run($idle);

        return new Response(json_encode($result), 200, ['Content-Type' => 'application/json']);
    }

    private function checkout(Request $request): Response
    {
        if ($this->cart()->isEmpty()) {
            return new RedirectResponse('/commerce/cart');
        }

        try {
            $modeError = $this->cart()->validateCheckoutMode();
            if ($modeError !== null) {
                throw new \RuntimeException($modeError);
            }
            $currencyError = $this->cart()->validateCheckoutCurrency();
            if ($currencyError !== null) {
                throw new \RuntimeException($currencyError);
            }

            $cartSnap = json_encode($this->cart()->snapshotForOrder()) ?: '[]';
            $coupon = $this->cart()->appliedCoupon();
            $giftCard = $this->cart()->appliedGiftCard();
            $discount = $this->cart()->discountCents();
            $flatShip = $this->flatShippingCents();
            $currency = $this->cart()->checkoutCurrency();
            $giftRed = $this->cart()->giftRedemptionCents($flatShip, $currency);
            $lines = $this->cart()->lines();
            $totalDiscount = $discount + $giftRed;
            if ($totalDiscount > 0 && $this->cart()->checkoutMode() === 'payment') {
                $lines = (new DiscountCalculator())->applyToLines($lines, $totalDiscount);
            }

            $mode = $this->cart()->checkoutMode();
            $autoTax = $this->stripeAutomaticTax();
            $ship = $mode === 'payment' ? $this->cart()->shippingCents($flatShip) : 0;
            $tax = 0;
            if ($mode === 'payment' && !$autoTax) {
                $rate = $this->taxRegions()->rateFor($this->cart()->taxCountry());
                $tax = (new TaxCalculator())->taxCents($this->cart()->taxableCents($flatShip, $currency), $rate);
            }

            $reminderEmail = $this->cart()->reminderEmail();
            $session = $this->stripe()->createSession(
                $lines,
                $currency !== '' ? $currency : $this->defaultCurrency(),
                [
                    'coupon' => (string) ($coupon['code'] ?? ''),
                    'cart' => $cartSnap,
                    'tax_country' => $this->cart()->taxCountry(),
                    'reminder_email' => $reminderEmail,
                    'gift_card' => (string) ($giftCard['code'] ?? ''),
                    'gift_cents' => $giftRed,
                ],
                $ship,
                $tax,
                $mode,
                $autoTax,
            );
            $this->cart()->clearCheckoutSession();
            if ($reminderEmail !== '') {
                $this->abandonedCarts()->deleteByEmail($reminderEmail);
            }

            $url = $session->url ?? '';
            if ($url === '') {
                throw new \RuntimeException('Stripe did not return a checkout URL');
            }

            return new RedirectResponse($url);
        } catch (\Throwable $e) {
            return new Response($this->renderView('cart', $this->cartViewData($e->getMessage())), 502);
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
                $this->buyerReceiptMailer(),
                $this->abandonedCarts(),
                $this->giftCards(),
                $this->subscriptions(),
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

    private function stripeAutomaticTax(): bool
    {
        $env = (string) ($this->config['config']['stripe_automatic_tax_env'] ?? 'STRIPE_AUTOMATIC_TAX');
        $flag = strtolower(trim((string) (getenv($env) ?: ($_ENV[$env] ?? '0'))));

        return in_array($flag, ['1', 'true', 'yes', 'on'], true);
    }

    private function giftCardsDir(): string
    {
        $rel = (string) ($this->config['config']['gift_cards_path'] ?? 'data/gift_cards');

        return __DIR__ . '/' . ltrim($rel, '/');
    }

    private function subscriptions(): SubscriptionRegistry
    {
        $rel = (string) ($this->config['config']['subscriptions_path'] ?? 'data/subscriptions');

        return new SubscriptionRegistry(__DIR__ . '/' . ltrim($rel, '/'));
    }

    private function bundles(): BundleRegistry
    {
        $rel = (string) ($this->config['config']['bundles_path'] ?? 'data/bundles');

        return new BundleRegistry(__DIR__ . '/' . ltrim($rel, '/'), $this->registry());
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

    /** @return array<string, mixed> */
    private function cartViewData(?string $error = null): array
    {
        $flatShip = $this->flatShippingCents();
        $mode = $this->cart()->checkoutMode();
        $currency = $this->cart()->checkoutCurrency();
        $displayCurrency = $currency !== '' ? strtoupper($currency) : '—';
        $autoTax = $this->stripeAutomaticTax();
        $rate = $this->taxRegions()->rateFor($this->cart()->taxCountry());
        $tax = ($mode === 'payment' && !$autoTax && $currency !== '')
            ? (new TaxCalculator())->taxCents($this->cart()->taxableCents($flatShip, $currency), $rate)
            : 0;
        $giftRed = $currency !== '' ? $this->cart()->giftRedemptionCents($flatShip, $currency) : 0;

        $data = [
            'lines' => $this->cart()->lines(),
            'subtotal_cents' => $this->cart()->subtotalCents(),
            'discount_cents' => $this->cart()->discountCents(),
            'gift_redemption_cents' => $giftRed,
            'gift_card' => $currency !== '' ? $this->cart()->appliedGiftCard($currency) : null,
            'checkout_currency' => $displayCurrency,
            'shipping_cents' => $mode === 'payment' ? $this->cart()->shippingCents($flatShip) : 0,
            'tax_cents' => $tax,
            'tax_rate' => $rate,
            'tax_country' => $this->cart()->taxCountry(),
            'tax_regions' => $this->taxRegions()->countryOptions(),
            'stripe_automatic_tax' => $autoTax,
            'total_cents' => $currency !== '' ? $this->cart()->totalCents($flatShip, $tax, $currency) : 0,
            'coupon' => $this->cart()->appliedCoupon(),
            'reminder_email' => $this->cart()->reminderEmail(),
            'checkout_mode' => $mode,
            'stripe_ready' => $this->stripe()->isConfigured(),
            'site' => $this->siteData(),
        ];
        if ($error !== null) {
            $data['error'] = $error;
        }

        return $data;
    }

    private function syncAbandonedCart(): void
    {
        $email = $this->cart()->reminderEmail();
        if ($email === '' || $this->cart()->isEmpty()) {
            return;
        }

        $this->abandonedCarts()->upsert($email, $this->cart()->snapshotForOrder());
    }

    private function cronAuthorized(Request $request): bool
    {
        $env = (string) ($this->config['config']['cron_key_env'] ?? 'COMMERCE_CRON_KEY');
        $expected = trim((string) (getenv($env) ?: ($_ENV[$env] ?? '')));
        if ($expected === '') {
            return false;
        }

        return hash_equals($expected, (string) $request->query->get('key', ''));
    }

    private function taxRegions(): TaxRegionRegistry
    {
        $rel = (string) ($this->config['config']['tax_regions_path'] ?? 'data/tax/regions.yaml');

        return new TaxRegionRegistry(__DIR__ . '/' . ltrim($rel, '/'));
    }

    private function abandonedCarts(): AbandonedCartRegistry
    {
        $rel = (string) ($this->config['config']['abandoned_path'] ?? 'data/abandoned');

        return new AbandonedCartRegistry(__DIR__ . '/' . ltrim($rel, '/'));
    }

    private function abandonedReminder(): AbandonedCartReminder
    {
        $base = rtrim((string) $this->app->config()->get('site.url', 'http://localhost:8080'), '/');

        return new AbandonedCartReminder(
            $this->abandonedCarts(),
            $this->registry(),
            $base,
            (string) $this->app->config()->get('site.title', 'GSpot Shop'),
        );
    }

    private function buyerReceiptMailer(): BuyerReceiptMailer
    {
        $env = (string) ($this->config['config']['buyer_receipts_env'] ?? 'COMMERCE_BUYER_RECEIPTS');
        $flag = strtolower(trim((string) (getenv($env) ?: ($_ENV[$env] ?? '1'))));

        return new BuyerReceiptMailer(
            (string) $this->app->config()->get('site.title', 'GSpot Shop'),
            in_array($flag, ['1', 'true', 'yes', 'on'], true),
        );
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
