@php
    $bpm = (string) ($page->meta('bpm') ?? '128');
    $energy = (string) ($page->meta('energy') ?? 'high');
@endphp
<div class="synth-arena">
    <div class="synth-marquee" aria-hidden="true">
        <div class="synth-marquee-track">
            <span>FIND THE SPOT · HIT IT · SHIP IT · MCP-NATIVE · FLAT-FILE · ZERO DRAMA · </span>
            <span>FIND THE SPOT · HIT IT · SHIP IT · MCP-NATIVE · FLAT-FILE · ZERO DRAMA · </span>
        </div>
    </div>

    <section class="synth-hero">
        <div class="synth-hero-copy">
            <p class="synth-eyebrow">/// COVER TRANSMISSION</p>
            <div class="page-content page-content--synth">
                {!! $page->content() !!}
            </div>
            <div class="synth-hero-actions">
                <a class="synth-btn synth-btn--primary" href="/pattern-demo">Enter pattern demo</a>
                <a class="synth-btn synth-btn--ghost" href="/docs">Read the docs</a>
            </div>
        </div>
        <div class="synth-hero-stage" aria-hidden="true">
            <div class="synth-sun"></div>
            <div class="synth-mountains"></div>
            <div class="synth-equalizer" data-bpm="{{ e($bpm) }}">
                @for ($i = 0; $i < 12; $i++)
                <span class="synth-eq-bar" style="--i: {{ $i }}"></span>
                @endfor
            </div>
            <div class="synth-holo-card">
                <p class="synth-holo-label">Vibe assurance</p>
                <p class="synth-holo-value">{{ strtoupper($energy) }} / {{ $bpm }} BPM</p>
                <p class="synth-holo-sub">Frontmatter → aura CSS</p>
            </div>
        </div>
    </section>

    <section class="synth-lanes" aria-label="Highlights">
        <a class="synth-lane synth-lane--cyan" href="/docs/mcp">
            <span class="synth-lane-id">01</span>
            <span class="synth-lane-title">MCP core</span>
            <span class="synth-lane-desc">Agents ship pages, themes, patterns</span>
        </a>
        <a class="synth-lane synth-lane--magenta" href="/pattern-demo">
            <span class="synth-lane-id">02</span>
            <span class="synth-lane-title">Pattern fences</span>
            <span class="synth-lane-desc">Try <code>synthwave-grid-hero</code></span>
        </a>
        <a class="synth-lane synth-lane--violet" href="/docs/themes">
            <span class="synth-lane-id">03</span>
            <span class="synth-lane-title">Four venues</span>
            <span class="synth-lane-desc">Editorial · Commerce · Starter</span>
        </a>
    </section>

    <section class="synth-deck" aria-label="Feature modules">
        <article class="synth-module">
            <h2 class="synth-module-title">Flat-file core</h2>
            <p>No database migrations at 2am. Markdown + YAML in git.</p>
        </article>
        <article class="synth-module">
            <h2 class="synth-module-title">Blade + plugins</h2>
            <p>Commerce, patterns, JavaBean admin skins.</p>
        </article>
        <article class="synth-module synth-module--wide">
            <h2 class="synth-module-title">What does the G stand for?</h2>
            <p><em>Guess.</em> Then open <code>/gspot-admin/</code> and ship anyway.</p>
        </article>
        <article class="synth-module">
            <h2 class="synth-module-title">Team DC</h2>
            <p>Groundswell not fork. NYE 2026 on the horizon.</p>
        </article>
    </section>
</div>
