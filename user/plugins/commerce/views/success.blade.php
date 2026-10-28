<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Thank you — {{ $site['title'] }}</title>
    <link rel="stylesheet" href="{{ $commerceCss ?? '/plugin-assets/commerce/css/shop.css' }}">
</head>
<body class="commerce-page">
<main class="commerce-main commerce-success">
    <h1>Payment received</h1>
    @if(!empty($order))
        <p>Order <code>{{ $order['id'] ?? '' }}</code> — {{ strtoupper($order['currency'] ?? 'aud') }} {{ number_format(((int)($order['amount_total'] ?? 0)) / 100, 2) }}</p>
        @if(!empty($order['email']))
            <p>Receipt email: {{ $order['email'] }}</p>
        @endif
        @if(!empty($order['downloads']))
            <div class="commerce-downloads">
                <h2>Your downloads</h2>
                <ul>
                @foreach($order['downloads'] as $dl)
                    @if(is_array($dl))
                        <li><a href="{{ $dl['url'] ?? '#' }}">{{ $dl['title'] ?? 'Download' }}</a></li>
                    @endif
                @endforeach
                </ul>
            </div>
        @endif
    @elseif(!empty($session_id))
        <p>Session {{ $session_id }} — webhook will file the order receipt shortly.</p>
    @endif
    <p>Thanks for supporting the groundswell. Find the Spot. Hit it. Ship it.</p>
    <p><a href="/">Back to site</a> · <a href="/commerce/shop">Continue shopping</a></p>
</main>
</body>
</html>
