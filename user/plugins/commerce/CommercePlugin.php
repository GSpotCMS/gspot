<?php

declare(strict_types=1);

namespace GSpot\UserPlugins\Commerce;

use GSpot\Plugin\AbstractPlugin;
use GSpot\Router\Page;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CommercePlugin extends AbstractPlugin
{
    private ?ProductRegistry $registry = null;
    private ?Cart $cart = null;

    public function onAppBoot(mixed &$app): void
    {
        require_once __DIR__ . '/classes/ProductRegistry.php';
        require_once __DIR__ . '/classes/Cart.php';
        require_once __DIR__ . '/classes/StripeCheckout.php';

        $this->app->renderer()->addViewPath(__DIR__ . '/views');
        $this->app->renderer()->share('commerceCss', '/plugin-assets/commerce/css/shop.css');
    }

    public function onPageRender(Page &$page): void
    {
        if (str_contains($page->content(), '[commerce-shop]')) {
            $html = '<p class="commerce-embed"><a class="btn primary" href="/commerce/shop">Open shop →</a></p>';
            $page->setContent(str_replace('[commerce-shop]', $html, $page->content()));
        }
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
            $path === '/commerce/success' => $this->success($request),
            str_starts_with($path, '/commerce/product/') => $this->product($path),
            default => null,
        };
    }

    public function registry(): ProductRegistry
    {
        return $this->registry ??= new ProductRegistry($this->productsDir());
    }

    public function cart(): Cart
    {
        return $this->cart ??= new Cart($this->registry());
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
        return new Response($this->renderView('cart', [
            'lines' => $this->cart()->lines(),
            'subtotal_cents' => $this->cart()->subtotalCents(),
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

    private function checkout(Request $request): Response
    {
        if ($this->cart()->isEmpty()) {
            return new RedirectResponse('/commerce/cart');
        }

        try {
            $session = $this->stripe()->createSession(
                $this->cart()->lines(),
                $this->defaultCurrency(),
            );
            $this->cart()->clear();

            $url = $session->url ?? '';
            if ($url === '') {
                throw new \RuntimeException('Stripe did not return a checkout URL');
            }

            return new RedirectResponse($url);
        } catch (\Throwable $e) {
            return new Response($this->renderView('cart', [
                'lines' => $this->cart()->lines(),
                'subtotal_cents' => $this->cart()->subtotalCents(),
                'stripe_ready' => $this->stripe()->isConfigured(),
                'error' => $e->getMessage(),
                'site' => $this->siteData(),
            ]), 502);
        }
    }

    private function success(Request $request): Response
    {
        return new Response($this->renderView('success', [
            'site' => $this->siteData(),
        ]));
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

    private function productsDir(): string
    {
        $rel = (string) ($this->config['config']['products_path'] ?? 'data/products');

        return __DIR__ . '/' . ltrim($rel, '/');
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
