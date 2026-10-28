(function () {
  const SNIPPETS = {
    'hero-split': `<section class="hero hero--split hero--align-center">
  <div class="hero-split-grid">
    <div class="hero-split-media"><div class="hero-split-media-fallback" aria-hidden="true"></div></div>
    <div class="hero-split-copy hero-inner">
      <p class="hero-eyebrow">Eyebrow</p>
      <h1 class="hero-title">Headline <span class="hero-accent">accent</span></h1>
      <p class="hero-lead">Supporting line.</p>
      <div class="hero-actions">
        <a class="hero-btn hero-btn--primary" href="#">Primary</a>
        <a class="hero-btn hero-btn--ghost" href="#">Secondary</a>
      </div>
    </div>
  </div>
</section>`,
    'hero-fullscreen': `<section class="hero hero--fullscreen hero--align-center">
  <div class="hero-media" aria-hidden="true"></div>
  <div class="hero-scrim"></div>
  <div class="hero-inner">
    <p class="eyebrow">Eyebrow</p>
    <h1>Fullscreen <span class="accent">hero</span></h1>
    <p class="lead">One big statement above the fold.</p>
    <div class="hero-actions">
      <a class="btn primary" href="#">Get started</a>
    </div>
  </div>
</section>`,
    'cards-three': `<section class="cards cards--three">
  <article class="card"><h3>One</h3><p>Body copy.</p></article>
  <article class="card"><h3>Two</h3><p>Body copy.</p></article>
  <article class="card"><h3>Three</h3><p>Body copy.</p></article>
</section>`,
    callout: `<section class="callout callout--accent">
  <div class="callout-inner">
    <h2 class="callout-title">Callout title</h2>
    <p class="callout-body">Short pitch.</p>
    <a class="callout-btn" href="#">Action</a>
  </div>
</section>`,
    'mud-callout-info': `<aside class="mud-callout mud-callout--info">
  <strong>Note</strong>
  <p>Helpful info for readers.</p>
</aside>`,
    quote: `<section class="quote-block">
  <blockquote>Pull quote goes here.</blockquote>
  <cite>— Name, role</cite>
</section>`,
    manifesto: `<section class="manifesto">
  <h2>Manifesto title</h2>
  <p>Why we build this way.</p>
  <p class="sign-off">— Team sign-off</p>
</section>`,
    'pricing-card': `<section class="pricing">
  <div class="pricing-grid">
    <article class="pricing-card">
      <h3>Plan</h3>
      <p class="pricing-price">$0</p>
      <ul class="pricing-features"><li>Feature one</li></ul>
      <a class="btn primary" href="#">Choose</a>
    </article>
  </div>
</section>`,
  };

  const form = document.getElementById('mud-pattern-form');
  if (!form) return;

  const source = document.getElementById('mud-html-source');
  const quillHost = document.getElementById('mud-quill-editor');
  const previewFrame = document.getElementById('mud-preview-frame');
  const sourceTa = document.getElementById('mud-html-source-editor');
  const mudCss = form.dataset.mudCss || '/plugin-assets/gspot-mud/css/gspot-mud-fences.css';
  const tabs = document.querySelectorAll('[data-mud-tab]');
  const panes = document.querySelectorAll('[data-mud-pane]');

  const quill = new Quill('#mud-quill-editor', {
    theme: 'snow',
    modules: {
      toolbar: [
        [{ header: [1, 2, 3, false] }],
        ['bold', 'italic', 'underline', 'link'],
        [{ list: 'ordered' }, { list: 'bullet' }],
        ['blockquote', 'code-block'],
        ['clean'],
      ],
    },
  });

  function currentHtml() {
    const active = document.querySelector('[data-mud-pane].is-active');
    if (active && active.dataset.mudPane === 'source') {
      return sourceTa.value;
    }
    return quill.getSemanticHTML ? quill.getSemanticHTML() : quill.root.innerHTML;
  }

  function setHtml(html) {
    source.value = html;
    sourceTa.value = html;
    quill.clipboard.dangerouslyPasteHTML(html || '<p><br></p>');
    refreshPreview(html);
  }

  function refreshPreview(html) {
    if (!previewFrame) return;
    const doc =
      '<!DOCTYPE html><html><head><meta charset="utf-8">' +
      '<link rel="stylesheet" href="' +
      mudCss +
      '"></head><body class="mud-spec-body">' +
      '<div class="mud-content"><div class="mud-page">' +
      (html || '') +
      '</div></div></body></html>';
    previewFrame.srcdoc = doc;
  }

  let previewTimer;
  function schedulePreview() {
    clearTimeout(previewTimer);
    previewTimer = setTimeout(function () {
      refreshPreview(currentHtml());
    }, 280);
  }

  if (source.value) {
    setHtml(source.value);
  } else {
    refreshPreview('');
  }

  tabs.forEach(function (btn) {
    btn.addEventListener('click', function () {
      const tab = btn.dataset.mudTab;
      tabs.forEach(function (b) {
        b.classList.toggle('is-active', b === btn);
      });
      panes.forEach(function (pane) {
        const on = pane.dataset.mudPane === tab;
        pane.classList.toggle('is-active', on);
      });
      if (tab === 'source') {
        sourceTa.value = quill.getSemanticHTML ? quill.getSemanticHTML() : quill.root.innerHTML;
      }
      if (tab === 'visual') {
        quill.clipboard.dangerouslyPasteHTML(sourceTa.value || '<p><br></p>');
      }
      if (tab === 'preview') {
        refreshPreview(currentHtml());
      }
    });
  });

  quill.on('text-change', schedulePreview);
  sourceTa.addEventListener('input', schedulePreview);

  document.getElementById('mud-snippet-insert')?.addEventListener('click', function () {
    const sel = document.getElementById('mud-snippet-select');
    const key = sel && sel.value;
    if (!key || !SNIPPETS[key]) return;
    const block = SNIPPETS[key];
    const active = document.querySelector('[data-mud-pane].is-active');
    if (active && active.dataset.mudPane === 'source') {
      sourceTa.value = sourceTa.value + '\n' + block;
    } else {
      const html = quill.getSemanticHTML ? quill.getSemanticHTML() : quill.root.innerHTML;
      setHtml(html + '\n' + block);
    }
    schedulePreview();
  });

  form.addEventListener('submit', function () {
    source.value = currentHtml();
  });
})();
