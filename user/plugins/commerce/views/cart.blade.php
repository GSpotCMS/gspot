<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Cart — {{ $site['title'] }}</title>
    <link rel="stylesheet" href="{{ $commerceCss ?? '/plugin-assets/commerce/css/shop.css' }}">
</head>
<body class="commerce-page">
<header class="commerce-header">
    <a href="/commerce/shop">← Shop</a>
</header>
<main class="commerce-main">
    <h1>Your cart</h1>
    @if(!empty($error))
        <p class="commerce-error">{{ $error }}</p>
    @endif
    @if(empty($lines))
        <p>Cart is empty. <a href="/commerce/shop">Browse products</a></p>
    @else
        <form method="POST" action="/commerce/cart/update">
            <table class="commerce-table">
                <thead><tr><th>Product</th><th>Qty</th><th>Line</th></tr></thead>
                <tbody>
                @foreach($lines as $line)
                    @php
                        $p = $line['product'];
                        $slug = $p['slug'] ?? '';
                        $cur = $p['currency'] ?? 'aud';
                    @endphp
                    <tr>
                        <td>{{ $p['title'] ?? $slug }}</td>
                        <td><input type="number" name="qty[{{ $slug }}]" value="{{ $line['qty'] }}" min="0" max="99"></td>
                        <td>{{ strtoupper($cur) }} {{ number_format($line['line_cents'] / 100, 2) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <p class="commerce-subtotal"><strong>Subtotal:</strong> {{ number_format(($subtotal_cents ?? 0) / 100, 2) }}</p>
            <button type="submit">Update quantities</button>
        </form>

        <section class="commerce-coupon">
            @if(!empty($coupon))
                <p>Coupon <code>{{ $coupon['code'] ?? '' }}</code> applied
                    (−{{ number_format(($discount_cents ?? 0) / 100, 2) }})</p>
                <form method="POST" action="/commerce/cart/coupon/remove">
                    <button type="submit" class="commerce-coupon-remove">Remove coupon</button>
                </form>
            @else
                <form method="POST" action="/commerce/cart/coupon" class="commerce-coupon-form">
                    <label>Coupon <input name="code" placeholder="TEAMDC10" autocomplete="off"></label>
                    <button type="submit">Apply</button>
                </form>
            @endif
        </section>

        <p class="commerce-total"><strong>Total:</strong> {{ number_format(($total_cents ?? $subtotal_cents) / 100, 2) }}</p>

        @if($stripe_ready)
            <form method="POST" action="/commerce/checkout" style="margin-top:1rem">
                <button type="submit" class="commerce-checkout">Checkout with Stripe</button>
            </form>
            <p class="commerce-note">Stripe promotion codes also work at checkout.</p>
        @else
            <p class="commerce-note">Set <code>STRIPE_SECRET_KEY</code> in your environment to enable checkout.</p>
        @endif
    @endif
</main>
</body>
</html>
