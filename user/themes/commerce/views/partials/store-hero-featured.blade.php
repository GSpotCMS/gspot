@php
    $featured = array_slice($commerceCatalog ?? [], 0, 3);
@endphp
<aside class="store-hero-featured" aria-labelledby="store-featured-rail-title">
    <h2 id="store-featured-rail-title" class="store-featured-rail-title">Featured picks</h2>
    @if($featured === [])
        <p class="store-featured-empty">Add products in the commerce plugin catalog.</p>
    @else
        <ul class="store-featured-list">
            @foreach($featured as $product)
                @php
                    $slug = $product['slug'] ?? '';
                    $title = $product['title'] ?? $slug;
                    $price = number_format(((int) ($product['price_cents'] ?? 0)) / 100, 2);
                @endphp
                <li class="store-featured-item">
                    <a href="/commerce/product/{{ $slug }}" class="store-featured-link">
                        <span class="store-featured-name">{{ $title }}</span>
                        <span class="store-featured-price">${{ $price }}</span>
                    </a>
                    <form method="POST" action="/commerce/cart/add" class="store-featured-add">
                        <input type="hidden" name="slug" value="{{ $slug }}">
                        <input type="hidden" name="qty" value="1">
                        <input type="hidden" name="redirect" value="{{ e($_SERVER['REQUEST_URI'] ?? '/') }}">
                        <button type="submit" class="store-btn store-btn--primary store-btn--sm">Add</button>
                    </form>
                </li>
            @endforeach
        </ul>
    @endif
    <a class="store-featured-all" href="/shop">View full catalog →</a>
</aside>
