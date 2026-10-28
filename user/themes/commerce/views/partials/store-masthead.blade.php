<div class="store-promo" role="region" aria-label="Promotion">
    <p>Digital goods ship instantly · Stripe Checkout · Flat-file catalog in <code>user/plugins/commerce/data/products/</code></p>
</div>
<header class="store-header">
    <div class="store-header-inner">
        <a href="/" class="store-logo">{{ $site['title'] }}</a>
        <nav class="store-nav" aria-label="Store">
            <a href="/">Home</a>
            <a href="/shop">Shop page</a>
            <a href="/commerce/shop">Catalog</a>
            <a href="/docs/commerce">Docs</a>
            <a href="/pattern-demo">Patterns</a>
        </nav>
        <div class="store-actions">
            @include('partials.store-mini-cart')
            <a class="store-cta" href="/commerce/shop">Shop all</a>
        </div>
    </div>
</header>
