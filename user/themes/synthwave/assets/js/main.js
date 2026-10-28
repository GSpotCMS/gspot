document.addEventListener('DOMContentLoaded', () => {
  const bpm = document.body.dataset.bpm;
  if (bpm && !Number.isNaN(Number(bpm))) {
    document.documentElement.style.setProperty('--pulse-ms', `${Math.round(60000 / Number(bpm))}ms`);
  }
});
