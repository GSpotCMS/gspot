<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ isset($page) ? $page->title() . ' — ' : '' }}{{ $site['title'] }}</title>
    @if(isset($theme) && $theme->cssVariables())
    <style id="gspot-theme-vars">{!! $theme->cssVariables() !!}</style>
    @endif
    <link rel="stylesheet" href="{{ $theme->assetUrl('css/main.css') }}">
    @if(!empty($commerceCss))
    <link rel="stylesheet" href="{{ $commerceCss }}">
    @endif
</head>
<body
@if(isset($page))
    class="theme-commerce @if(in_array($page->slug(), ['home', 'shop'], true)) is-storefront @endif"
@else
    class="theme-commerce"
@endif
>
    @include('partials.store-masthead')

    <main class="site-content">
        @yield('content')
    </main>

    <footer class="site-footer store-footer">
        <div class="site-footer-inner">
            <p>Powered by <a href="https://gspotcms.com">GSpot CMS</a> · Commerce theme</p>
            <p class="site-footer-note"><a href="/gspot-admin/">Admin</a> · <a href="/commerce/shop">Catalog</a> · swap to <a href="/docs/themes">editorial</a> or <a href="/">synthwave</a></p>
        </div>
    </footer>

    <script src="{{ $theme->assetUrl('js/main.js') }}"></script>
</body>
</html>
