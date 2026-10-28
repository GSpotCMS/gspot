@php
    $mini = $commerceCartMini ?? ['count' => 0, 'lines' => [], 'subtotal_cents' => 0, 'currency' => 'AUD'];
    $count = (int) ($mini['count'] ?? 0);
    $subtotal = number_format(((int) ($mini['subtotal_cents'] ?? 0)) / 100, 2);
    $cur = $mini['currency'] ?? 'AUD';
@endphp
<div class="store-cart-wrap">
    <button type="button" class="store-cart" id="store-cart-toggle" aria-expanded="false" aria-controls="store-mini-cart-panel" aria-haspopup="true">
        Cart
        @if($count > 0)
        <span class="store-cart-count">{{ $count }}</span>
        @endif
    </button>
    <div class="store-mini-cart" id="store-mini-cart-panel" hidden>
        <p class="store-mini-cart-title">Your cart</p>
        @if($count === 0)
            <p class="store-mini-cart-empty">Cart is empty.</p>
        @else
            <ul class="store-mini-cart-lines">
                @foreach($mini['lines'] ?? [] as $line)
                    @php
                        $p = $line['product'] ?? [];
                        $title = $p['title'] ?? '';
                        $slug = $p['slug'] ?? '';
                        $qty = (int) ($line['qty'] ?? 0);
                        $lineTotal = number_format(((int) ($line['line_cents'] ?? 0)) / 100, 2);
                    @endphp
                    <li>
                        <a href="/commerce/product/{{ $slug }}">{{ $title }}</a>
                        <span class="store-mini-qty">×{{ $qty }}</span>
                        <span class="store-mini-price">${{ $lineTotal }}</span>
                    </li>
                @endforeach
            </ul>
            <p class="store-mini-subtotal"><strong>Subtotal</strong> {{ $cur }} ${{ $subtotal }}</p>
        @endif
        <div class="store-mini-actions">
            <a class="store-btn store-btn--ghost store-btn--block" href="/commerce/cart">View full cart</a>
            <a class="store-btn store-btn--primary store-btn--block" href="/commerce/shop">Continue shopping</a>
        </div>
    </div>
</div>
