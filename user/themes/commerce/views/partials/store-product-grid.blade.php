@php
    $catalog = $commerceCatalog ?? [];
@endphp
<section class="store-catalog" aria-labelledby="store-catalog-title">
    <div class="store-section-head">
        <h2 id="store-catalog-title" class="store-section-title">Featured catalog</h2>
        <a class="store-section-link" href="/commerce/shop">Full shop →</a>
    </div>
    @if(count($catalog) === 0)
        <p class="store-empty">Enable the commerce plugin and add products under <code>data/products/</code>.</p>
    @else
    <div class="store-product-grid">
        @foreach($catalog as $product)
            @php
                $slug = $product['slug'] ?? '';
                $title = $product['title'] ?? $slug;
                $cents = (int) ($product['price_cents'] ?? 0);
                $cur = strtoupper($product['currency'] ?? 'AUD');
                $price = number_format($cents / 100, 2);
                $desc = $product['description'] ?? '';
                $stock = $product['stock'] ?? null;
            @endphp
            <article class="store-product-card">
                <div class="store-product-visual" aria-hidden="true">
                    <span class="store-product-badge">{{ $cur }}</span>
                </div>
                <h3 class="store-product-name"><a href="/commerce/product/{{ $slug }}">{{ $title }}</a></h3>
                <p class="store-product-desc">{{ $desc }}</p>
                <div class="store-product-meta">
                    <span class="store-product-price">${{ $price }}</span>
                    @if($stock !== null && $stock !== '')
                    <span class="store-product-stock">{{ (int) $stock }} in stock</span>
                    @endif
                </div>
                <form method="POST" action="/commerce/cart/add" class="store-add-form">
                    <input type="hidden" name="slug" value="{{ $slug }}">
                    <input type="hidden" name="qty" value="1">
                    <input type="hidden" name="redirect" value="{{ e($_SERVER['REQUEST_URI'] ?? '/shop') }}">
                    <button type="submit" class="store-btn store-btn--primary">Add to cart</button>
                </form>
                <a class="store-btn store-btn--ghost" href="/commerce/product/{{ $slug }}">Details</a>
            </article>
        @endforeach
    </div>
    @endif
</section>
