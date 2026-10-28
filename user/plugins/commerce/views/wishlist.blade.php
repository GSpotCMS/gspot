<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Wishlist — {{ $site['title'] }}</title>
    <link rel="stylesheet" href="{{ $commerceCss ?? '/plugin-assets/commerce/css/shop.css' }}">
</head>
<body class="commerce-page">
<header class="commerce-header">
    <a href="/commerce/shop">← Shop</a>
    <a href="/commerce/cart">Cart</a>
</header>
<main class="commerce-main">
    <h1>Wishlist</h1>
    @if(empty($products))
        <p>Nothing saved yet. <a href="/commerce/shop">Browse the shop</a></p>
    @else
        <ul class="commerce-wishlist">
            @foreach($products as $product)
                @php $slug = $product['slug'] ?? ''; @endphp
                <li>
                    <strong>{{ $product['title'] ?? $slug }}</strong>
                    <form method="POST" action="/commerce/cart/add" style="display:inline">
                        <input type="hidden" name="slug" value="{{ $slug }}">
                        <input type="hidden" name="qty" value="1">
                        <button type="submit">Add to cart</button>
                    </form>
                    <form method="POST" action="/commerce/wishlist/remove" style="display:inline">
                        <input type="hidden" name="slug" value="{{ $slug }}">
                        <button type="submit">Remove</button>
                    </form>
                </li>
            @endforeach
        </ul>
        <form method="POST" action="/commerce/wishlist/to-cart">
            <button type="submit">Move all to cart</button>
        </form>
    @endif
</main>
</body>
</html>
