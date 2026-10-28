<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop — {{ $site['title'] }}</title>
    <link rel="stylesheet" href="{{ $commerceCss ?? '/plugin-assets/commerce/css/shop.css' }}">
</head>
<body class="commerce-page">
<header class="commerce-header">
    <a href="/">{{ $site['title'] }}</a>
    <nav>
        <a href="/commerce/shop">Shop</a>
        <a href="/commerce/cart">Cart</a>
    </nav>
</header>
<main class="commerce-main">
    <h1>Shop</h1>
    <p class="commerce-lead">Flat-file products. Stripe Checkout. No database drama.</p>
    <div class="commerce-grid">
        @foreach($products as $product)
            @php
                $slug = $product['slug'] ?? '';
                $price = (int)($product['price_cents'] ?? 0);
                $cur = $product['currency'] ?? 'aud';
            @endphp
            <article class="commerce-card">
                @if(!empty($product['image']))
                    <img src="{{ $product['image'] }}" alt="" class="commerce-card-img">
                @endif
                <h2><a href="/commerce/product/{{ $slug }}">{{ $product['title'] ?? $slug }}</a></h2>
                <p>{{ $product['description'] ?? '' }}</p>
                <p class="commerce-price">{{ strtoupper($cur) }} {{ number_format($price / 100, 2) }}</p>
                <form method="POST" action="/commerce/cart/add">
                    <input type="hidden" name="slug" value="{{ $slug }}">
                    <input type="hidden" name="qty" value="1">
                    <button type="submit">Add to cart</button>
                </form>
            </article>
        @endforeach
    </div>
</main>
</body>
</html>
