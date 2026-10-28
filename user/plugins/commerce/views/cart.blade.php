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

        @if(!empty($checkout_currency) && $checkout_currency !== '—')
            <p class="commerce-note">Checkout currency: <strong>{{ $checkout_currency }}</strong> (one currency per order)</p>
        @endif

        <section class="commerce-coupon">
            @if(!empty($gift_card))
                <p>Gift card <code>{{ $gift_card['code'] ?? '' }}</code> (−{{ number_format(($gift_redemption_cents ?? 0) / 100, 2) }})</p>
                <form method="POST" action="/commerce/cart/gift-card/remove">
                    <button type="submit">Remove gift card</button>
                </form>
            @else
                <form method="POST" action="/commerce/cart/gift-card" class="commerce-coupon-form">
                    <label>Gift card <input name="code" placeholder="SPOT-GIFT-25" autocomplete="off"></label>
                    <button type="submit">Apply</button>
                </form>
            @endif
        </section>

        @if(($checkout_mode ?? 'payment') === 'payment' && empty($stripe_automatic_tax))
            <section class="commerce-coupon">
                <form method="POST" action="/commerce/cart/tax-country" class="commerce-coupon-form">
                    <label>Tax region
                        <select name="country">
                            @foreach($tax_regions ?? [] as $region)
                                <option value="{{ $region['code'] }}" @if(($tax_country ?? 'AU') === $region['code']) selected @endif>
                                    {{ $region['code'] }} ({{ $region['rate'] }}%)
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <button type="submit">Update tax</button>
                </form>
            </section>
        @endif

        @if(!empty($affiliate_code))
            <p class="commerce-note">Referred by affiliate <code>{{ $affiliate_code }}</code></p>
        @endif

        <section class="commerce-coupon">
            <form method="POST" action="/commerce/cart/b2b" class="commerce-coupon-form">
                <label>Company (B2B) <input name="company" value="{{ $b2b_company ?? '' }}"></label>
                <label><input type="checkbox" name="request_invoice" value="1" @if(!empty($request_invoice)) checked @endif> Stripe invoice checkout</label>
                <button type="submit">Save B2B options</button>
            </form>
        </section>

        <section class="commerce-coupon">
            <form method="POST" action="/commerce/cart/reminder-email" class="commerce-coupon-form">
                <label>Email for receipt &amp; cart reminder
                    <input type="email" name="email" value="{{ $reminder_email ?? '' }}" placeholder="you@example.com">
                </label>
                <button type="submit">Save</button>
            </form>
        </section>

        @if(!empty($shipping_cents))
            <p class="commerce-subtotal"><strong>Shipping:</strong> {{ number_format($shipping_cents / 100, 2) }}</p>
        @endif
        @if(!empty($stripe_automatic_tax))
            <p class="commerce-note">Stripe Tax is enabled — tax calculated at checkout from billing address.</p>
        @elseif(!empty($tax_cents))
            <p class="commerce-subtotal"><strong>Tax ({{ $tax_country ?? 'AU' }} {{ $tax_rate ?? 0 }}%):</strong> {{ number_format($tax_cents / 100, 2) }}</p>
        @endif
        @if(!empty($gift_redemption_cents))
            <p class="commerce-subtotal"><strong>Gift card:</strong> −{{ number_format($gift_redemption_cents / 100, 2) }}</p>
        @endif
        <p class="commerce-total"><strong>Total:</strong> {{ number_format(($total_cents ?? $subtotal_cents) / 100, 2) }}</p>

        @if($stripe_ready)
            <form method="POST" action="/commerce/checkout" style="margin-top:1rem">
                <button type="submit" class="commerce-checkout">
                    @if(($checkout_mode ?? 'payment') === 'subscription')
                        Subscribe with Stripe
                    @else
                        Checkout with Stripe
                    @endif
                </button>
            </form>
            @if(($checkout_mode ?? 'payment') === 'subscription')
                <p class="commerce-note">Subscription checkout uses Stripe recurring Price IDs.</p>
            @else
                <p class="commerce-note">Stripe promotion codes also work at checkout.</p>
            @endif
        @else
            <p class="commerce-note">Set <code>STRIPE_SECRET_KEY</code> in your environment to enable checkout.</p>
        @endif
    @endif
</main>
</body>
</html>
