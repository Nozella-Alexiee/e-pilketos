/**
 * E-Pilketos v2.0 - PWA Installer & Service Worker Manager
 * Mendukung instalasi di Tablet, Smartphone, & Laptop
 */

(function () {
  'use strict';

  // 1. Registrasi Service Worker Dinamis
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      // Hitung path sw.js secara dinamis berdasarkan letak folder
      const getSwPath = () => {
        const pathParts = window.location.pathname.split('/').filter(Boolean);
        // Jika ada di dalam subfolder face-poc atau admin, arahkan ke root
        if (pathParts.includes('face-poc') || pathParts.includes('admin')) {
          return '../sw.js';
        }
        return './sw.js';
      };

      navigator.serviceWorker
        .register(getSwPath())
        .then((reg) => {
          console.log('[PWA] Service Worker terdaftar:', reg.scope);
        })
        .catch((err) => {
          console.warn('[PWA] Gagal mendaftarkan Service Worker:', err);
        });
    });
  }

  // 2. Tangani Prompt Instalasi PWA (Tombol "Pasang Aplikasi")
  let deferredPrompt = null;

  window.addEventListener('beforeinstallprompt', (e) => {
    // Mencegah mini-infobar default browser agar tidak mengganggu
    e.preventDefault();
    deferredPrompt = e;

    // Tampilkan tombol install jika elemen tersedia di halaman
    const installBtn = document.getElementById('pwaInstallBtn');
    const installBanner = document.getElementById('pwaInstallBanner');

    if (installBtn) {
      installBtn.style.display = 'inline-flex';
      installBtn.addEventListener('click', promptPwaInstall);
    }
    if (installBanner) {
      installBanner.style.display = 'flex';
      const bannerBtn = installBanner.querySelector('.pwa-btn-action');
      if (bannerBtn) bannerBtn.addEventListener('click', promptPwaInstall);
    }
  });

  function promptPwaInstall() {
    if (!deferredPrompt) return;

    deferredPrompt.prompt();
    deferredPrompt.userChoice.then((choiceResult) => {
      if (choiceResult.outcome === 'accepted') {
        console.log('[PWA] Pengguna menyetujui instalasi aplikasi');
      } else {
        console.log('[PWA] Pengguna menolak instalasi aplikasi');
      }
      deferredPrompt = null;
      // Sembunyikan tombol setelah aksi
      const installBtn = document.getElementById('pwaInstallBtn');
      const installBanner = document.getElementById('pwaInstallBanner');
      if (installBtn) installBtn.style.display = 'none';
      if (installBanner) installBanner.style.display = 'none';
    });
  }

  window.addEventListener('appinstalled', () => {
    console.log('[PWA] E-Pilketos berhasil terpasang di perangkat');
    deferredPrompt = null;
    const installBtn = document.getElementById('pwaInstallBtn');
    const installBanner = document.getElementById('pwaInstallBanner');
    if (installBtn) installBtn.style.display = 'none';
    if (installBanner) installBanner.style.display = 'none';
  });
})();
