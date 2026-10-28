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
</head>
<body
@if(isset($page))
    data-vibe="{{ e($page->meta('vibe', '')) }}"
    data-bpm="{{ e((string) $page->meta('bpm', '')) }}"
    data-energy="{{ e($page->meta('energy', '')) }}"
    class="theme-editorial @if($page->meta('vibe')) gspot-aura gspot-vibe-{{ $page->meta('vibe') }} @endif @if($page->meta('energy')) gspot-energy-{{ $page->meta('energy') }} @endif"
@else
    class="theme-editorial"
@endif
>
    <header class="site-header">
        <div class="site-header-inner">
            <a href="/" class="site-brand">{{ $site['title'] }}</a>
            <p class="site-tagline">Editorial</p>
        </div>
    </header>

    <main class="site-content">
        @yield('content')
    </main>

    <footer class="site-footer">
        <p>Powered by <a href="https://gspotcms.com">GSpot CMS</a> · Editorial theme</p>
    </footer>

    <script src="{{ $theme->assetUrl('js/main.js') }}"></script>
</body>
</html>
