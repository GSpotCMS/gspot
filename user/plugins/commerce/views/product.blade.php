<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $product['title'] ?? 'Product' }} — {{ $site['title'] }}</title>
    <link rel="stylesheet" href="{{ $commerceCss ?? '/plugin-assets/commerce/css/shop.css' }}">
</head>
<body class="commerce-page">
<header class="commerce-header">
    <a href="/commerce/shop">← Shop</a>
    <a href="/commerce/cart">Cart</a>
</header>
<main class="commerce-main commerce-product">
    @if(!empty($product['image']))
        <img src="{{ $product['image'] }}" alt="" class="commerce-hero-img">
    @endif
    <h1>{{ $product['title'] ?? '' }}</h1>
    <p>{{ $product['description'] ?? '' }}</p>
    @php
        $price = (int)($product['price_cents'] ?? 0);
        $cur = $product['currency'] ?? 'aud';
        $stock = array_key_exists('stock', $product) && $product['stock'] !== null ? (int)$product['stock'] : null;
        $soldOut = $stock !== null && $stock <= 0;
        $maxQty = $stock !== null ? min(99, max(1, $stock)) : 99;
    @endphp
    <p class="commerce-price">{{ strtoupper($cur) }} {{ number_format($price / 100, 2) }}</p>
        <form method="POST" action="/commerce/wishlist/add" style="margin-bottom:0.75rem">
            <input type="hidden" name="slug" value="{{ $product['slug'] ?? '' }}">
            <button type="submit">Save to wishlist</button>
        </form>
        @if($soldOut)
            <p class="commerce-sold-out">Out of stock</p>
        @else
            <form method="POST" action="/commerce/cart/add">
            <input type="hidden" name="slug" value="{{ $product['slug'] ?? '' }}">
            <label>Qty <input type="number" name="qty" value="1" min="1" max="{{ $maxQty }}"></label>
            <button type="submit">Add to cart</button>
        </form>
        @if($stock !== null)
            <p class="commerce-stock">{{ $stock }} in stock</p>
        @endif
    @endif
</main>
</body>
</html>
