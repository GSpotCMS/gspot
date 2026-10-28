document.addEventListener('DOMContentLoaded', () => {
  const bpm = document.body.dataset.bpm;
  if (bpm && !Number.isNaN(Number(bpm))) {
    const ms = Math.round(60000 / Number(bpm));
    document.documentElement.style.setProperty('--pulse-ms', `${ms}ms`);
  }

  document.querySelectorAll('.synth-equalizer').forEach((eq) => {
    const raw = eq.getAttribute('data-bpm');
    if (raw && !Number.isNaN(Number(raw))) {
      eq.style.setProperty('--pulse-ms', `${Math.round(60000 / Number(raw))}ms`);
    }
  });
});
