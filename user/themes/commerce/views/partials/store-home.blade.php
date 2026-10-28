<div class="store-arena">
    <section class="store-hero">
        <div class="store-hero-copy">
            <p class="store-eyebrow">Storefront edition</p>
            <div class="page-content page-content--store page-content--store-hero">
                {!! $page->content() !!}
            </div>
        </div>
        @include('partials.store-hero-featured')
    </section>

    @include('partials.store-product-grid')

    <section class="store-home-details page-content page-content--store" aria-label="About GSpot CMS">
        {!! $page->content() !!}
    </section>

    <section class="store-trust" aria-label="Trust and integrations">
        <div class="store-trust-item"><strong>Stripe</strong><span>Checkout &amp; portal</span></div>
        <div class="store-trust-item"><strong>Flat-file</strong><span>YAML products in git</span></div>
        <div class="store-trust-item"><strong>MCP</strong><span>Agent product tools</span></div>
        <div class="store-trust-item"><strong>Pro</strong><span>Affiliates &amp; multivendor</span></div>
    </section>
</div>
