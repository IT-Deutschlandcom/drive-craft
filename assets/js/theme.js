(() => {
  try {
    const theme = localStorage.getItem('dc-theme');
    document.documentElement.dataset.bsTheme = theme === 'light' ? 'light' : 'dark';
  } catch (_) {
    document.documentElement.dataset.bsTheme = 'dark';
  }
})();
