document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.store-product-card, .product-card').forEach((card) => {
    card.addEventListener('mouseenter', () => card.classList.add('is-hovered'));
    card.addEventListener('mouseleave', () => card.classList.remove('is-hovered'));
  });

  const toggle = document.getElementById('store-cart-toggle');
  const panel = document.getElementById('store-mini-cart-panel');
  if (!toggle || !panel) {
    return;
  }

  toggle.addEventListener('click', (e) => {
    e.preventDefault();
    e.stopPropagation();
    const open = panel.hidden;
    panel.hidden = !open;
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  });

  document.addEventListener('click', (e) => {
    if (panel.hidden) {
      return;
    }
    if (e.target instanceof Node && !panel.contains(e.target) && !toggle.contains(e.target)) {
      panel.hidden = true;
      toggle.setAttribute('aria-expanded', 'false');
    }
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !panel.hidden) {
      panel.hidden = true;
      toggle.setAttribute('aria-expanded', 'false');
    }
  });
});
