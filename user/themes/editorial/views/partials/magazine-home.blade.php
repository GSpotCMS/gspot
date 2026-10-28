<div class="magazine-shell magazine-shell--home">
    <section class="magazine-hero">
        <div class="magazine-hero-main">
            <p class="page-kicker">Cover story</p>
            <div class="magazine-hero-body page-content page-content--magazine">
                {!! $page->content() !!}
            </div>
        </div>
        <aside class="magazine-rail" aria-label="In this issue">
            <h2 class="magazine-rail-title">In this issue</h2>
            <ul class="magazine-rail-list">
                <li><a href="/docs/vibe-check"><span class="rail-kicker">Assurance</span> Vibe check your pages</a></li>
                <li><a href="/docs/themes"><span class="rail-kicker">Design</span> Four starter themes</a></li>
                <li><a href="/docs/mcp"><span class="rail-kicker">Agents</span> MCP-native core</a></li>
                <li><a href="/docs/commerce"><span class="rail-kicker">Store</span> Flat-file commerce</a></li>
            </ul>
            <div class="magazine-rail-box">
                <p class="rail-box-kicker">Editorial theme</p>
                <p>Theme-layer patterns: <code>editorial-lede</code>, <code>editorial-pullquote</code></p>
            </div>
        </aside>
    </section>

    <section class="magazine-grid" aria-label="Featured sections">
        <article class="magazine-card magazine-card--span2">
            <p class="magazine-card-kicker">Feature</p>
            <h2 class="magazine-card-title"><a href="/pattern-demo">Pattern fences, three layers deep</a></h2>
            <p class="magazine-card-deck">Core blocks, theme packs, and site overrides. Swap synthwave for editorial without forking HTML.</p>
            <a class="magazine-card-link" href="/pattern-demo">Open pattern demo →</a>
        </article>
        <article class="magazine-card">
            <p class="magazine-card-kicker">Profile</p>
            <h2 class="magazine-card-title"><a href="/about">Team DC</a></h2>
            <p class="magazine-card-deck">Groundswell not fork. Village brain, shipped receipts.</p>
        </article>
        <article class="magazine-card">
            <p class="magazine-card-kicker">Commerce</p>
            <h2 class="magazine-card-title"><a href="/shop">Storefront ready</a></h2>
            <p class="magazine-card-deck">Stripe checkout, products in folders, Pro when you need multivendor.</p>
        </article>
        <article class="magazine-card">
            <p class="magazine-card-kicker">Install</p>
            <h2 class="magazine-card-title"><a href="/docs/installation">Get started</a></h2>
            <p class="magazine-card-deck"><code>composer serve</code> · admin at <code>/gspot-admin/</code></p>
        </article>
        <article class="magazine-card magazine-card--accent">
            <p class="magazine-card-kicker">Opinion</p>
            <h2 class="magazine-card-title">What does the G stand for?</h2>
            <p class="magazine-card-deck"><em>Guess.</em> (Then ship anyway.)</p>
        </article>
    </section>
</div>
