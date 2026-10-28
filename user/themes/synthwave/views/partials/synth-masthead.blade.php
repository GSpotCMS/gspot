<div class="synth-scanlines" aria-hidden="true"></div>
<header class="synth-masthead">
    <div class="synth-masthead-bar">
        <span class="synth-chip">GSPOT · NIGHT DRIVE EDITION</span>
        <span class="synth-hud">
            @if(isset($page) && $page->meta('bpm'))
            <span class="synth-hud-item"><em>BPM</em> {{ e((string) $page->meta('bpm')) }}</span>
            @endif
            @if(isset($page) && $page->meta('energy'))
            <span class="synth-hud-item"><em>NRG</em> {{ e($page->meta('energy')) }}</span>
            @endif
            @if(isset($page) && $page->meta('vibe'))
            <span class="synth-hud-item synth-hud-item--vibe"><em>VIBE</em> {{ e($page->meta('vibe')) }}</span>
            @endif
        </span>
    </div>
    <div class="synth-masthead-core">
        <a href="/" class="synth-logo">{{ $site['title'] }}</a>
        <p class="synth-tagline">Find the spot · hit it · ship it</p>
    </div>
    <nav class="synth-nav" aria-label="Primary">
        <a href="/">Home</a>
        <a href="/pattern-demo">Patterns</a>
        <a href="/docs/mcp">MCP</a>
        <a href="/docs/vibe-check">Vibe check</a>
        <a href="/shop">Shop</a>
    </nav>
</header>
