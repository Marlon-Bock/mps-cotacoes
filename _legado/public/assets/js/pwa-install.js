(function () {
  var basePath = document.querySelector('meta[name="base-path"]')?.content || '';

  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register(basePath + '/sw.js', { scope: basePath + '/' });
  }

  var STORAGE_KEY = 'mps_pwa_banner_hide_until';
  var DAYS = 7;
  var deferredPrompt = null;
  var banner = null;

  function buildBanner() {
    var el = document.createElement('div');
    el.id = 'pwa-banner';
    el.style.cssText = 'display:none;position:fixed;bottom:24px;right:24px;z-index:99999;max-width:300px;width:calc(100% - 48px);background:#fff;border-radius:16px;box-shadow:0 8px 32px rgba(0,0,0,.18);border-left:4px solid #7F1B1B;padding:16px 16px 14px;font-family:inherit;';
    el.innerHTML =
      '<button id="pwa-banner-close" aria-label="Fechar" style="position:absolute;top:8px;right:10px;background:none;border:none;cursor:pointer;color:#999;font-size:18px;line-height:1;padding:0;">&times;</button>' +
      '<div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">' +
      '<img src="' + basePath + '/assets/img/logo-mark.png" alt="MP" width="40" height="40" style="border-radius:10px;flex-shrink:0;">' +
      '<div>' +
      '<div style="font-weight:700;font-size:.92rem;color:#222;line-height:1.2;">Baixe o app da MPS</div>' +
      '<div style="font-size:.78rem;color:#666;margin-top:2px;">Acesso rápido direto na tela inicial</div>' +
      '</div>' +
      '</div>' +
      '<div style="display:flex;gap:8px;">' +
      '<button id="pwa-banner-install" style="flex:1;background:#7F1B1B;color:#fff;border:none;border-radius:8px;padding:8px 0;font-size:.85rem;font-weight:600;cursor:pointer;">Instalar agora</button>' +
      '<button id="pwa-banner-later" style="flex:1;background:#f1f1f1;color:#555;border:none;border-radius:8px;padding:8px 0;font-size:.85rem;cursor:pointer;">Agora não</button>' +
      '</div>';
    document.body.appendChild(el);
    return el;
  }

  function hideBannerFor(days) {
    var until = Date.now() + days * 24 * 60 * 60 * 1000;
    localStorage.setItem(STORAGE_KEY, String(until));
    if (banner) banner.style.display = 'none';
  }

  function shouldShow() {
    var until = parseInt(localStorage.getItem(STORAGE_KEY) || '0', 10);
    return Date.now() > until;
  }

  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    deferredPrompt = e;
    if (!shouldShow()) return;

    banner = buildBanner();

    document.getElementById('pwa-banner-install').addEventListener('click', function () {
      banner.style.display = 'none';
      if (!deferredPrompt) return;
      deferredPrompt.prompt();
      deferredPrompt.userChoice.then(function (result) {
        if (result.outcome === 'accepted') hideBannerFor(365);
        deferredPrompt = null;
      });
    });

    document.getElementById('pwa-banner-later').addEventListener('click', function () { hideBannerFor(DAYS); });
    document.getElementById('pwa-banner-close').addEventListener('click', function () { hideBannerFor(DAYS); });

    setTimeout(function () { banner.style.display = 'block'; }, 2000);
  });

  window.addEventListener('appinstalled', function () {
    if (banner) banner.style.display = 'none';
    hideBannerFor(365);
  });
})();
