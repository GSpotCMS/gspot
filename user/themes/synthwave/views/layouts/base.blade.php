<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ isset($page) ? $page->title() . ' — ' : '' }}{{ $site['title'] }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700;800&display=swap" rel="stylesheet">
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
    class="theme-synthwave @if($page->slug() === 'home') is-synth-home @endif @if($page->meta('vibe')) gspot-aura gspot-vibe-{{ $page->meta('vibe') }} @endif @if($page->meta('energy')) gspot-energy-{{ $page->meta('energy') }} @endif"
@else
    class="theme-synthwave"
@endif
>
    <div class="synth-grid" aria-hidden="true"></div>
    @include('partials.synth-masthead')

    <main class="site-content">
        @yield('content')
    </main>

    <footer class="site-footer synth-footer">
        <p>Powered by <a href="https://gspotcms.com">GSpot CMS</a> · Synthwave theme</p>
        <p class="synth-footer-sub"><a href="/gspot-admin/">Admin</a> · swap to <a href="/docs/themes">editorial</a> for the broadsheet vibe</p>
    </footer>

    <script src="{{ $theme->assetUrl('js/main.js') }}"></script>
</body>
</html>
