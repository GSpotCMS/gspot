document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.product-card').forEach((card) => {
    card.addEventListener('mouseenter', () => card.classList.add('is-hovered'));
    card.addEventListener('mouseleave', () => card.classList.remove('is-hovered'));
  });
});
