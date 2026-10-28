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
    <p>Thanks for supporting the groundswell. Find the Spot. Hit it. Ship it.</p>
    <p><a href="/">Back to site</a> · <a href="/commerce/shop">Continue shopping</a></p>
</main>
</body>
</html>
