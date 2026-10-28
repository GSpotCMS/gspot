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
            <p class="commerce-subtotal"><strong>Subtotal:</strong> AUD {{ number_format($subtotal_cents / 100, 2) }}</p>
            <button type="submit">Update quantities</button>
        </form>
        @if($stripe_ready)
            <form method="POST" action="/commerce/checkout" style="margin-top:1rem">
                <button type="submit" class="commerce-checkout">Checkout with Stripe</button>
            </form>
        @else
            <p class="commerce-note">Set <code>STRIPE_SECRET_KEY</code> in your environment to enable checkout.</p>
        @endif
    @endif
</main>
</body>
</html>
