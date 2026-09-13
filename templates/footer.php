<?php
/**
 * Footer Template - Public & Student Facing
 * SMK SIG (Sekolah Menengah Kejuruan SIG)
 */
?>
    </div>
</main>

<!-- Modal Detail Visi & Misi Kandidat -->
<div class="modal fade" id="modalVisiMisi" tabindex="-1" aria-labelledby="modalVisiMisiLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light border-bottom py-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-danger text-white fs-6 px-2.5 py-1" id="modalCandNum">01</span>
                    <h5 class="modal-title fw-bold mb-0 text-dark" id="modalCandName">Nama Kandidat</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-4">
                    <div class="col-md-4 text-center">
                        <div class="rounded border p-1 bg-white shadow-sm mb-2" style="height: 220px; overflow: hidden;">
                            <img id="modalCandPhoto" src="assets/images/logo-smk-sig.png" class="w-100 h-100 object-fit-cover" alt="Foto Kandidat">
                        </div>
                        <span class="badge bg-secondary-subtle text-secondary border px-3 py-1" id="modalCandClass">Kelas</span>
                    </div>
                    <div class="col-md-8">
                        <div class="mb-3">
                            <h6 class="text-uppercase text-muted fw-bold small mb-1" style="letter-spacing: 0.05em;">Visi</h6>
                            <p class="text-dark bg-light p-3 rounded border" id="modalCandVision" style="line-height: 1.6;"></p>
                        </div>
                        <div>
                            <h6 class="text-uppercase text-muted fw-bold small mb-2" style="letter-spacing: 0.05em;">Misi</h6>
                            <ul class="ps-3 text-dark mb-0" id="modalCandMission" style="line-height: 1.7;"></ul>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-top py-2.5">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- Institutional Footer -->
<footer class="school-footer">
    <div class="container">
        <div class="row g-4 align-items-center justify-content-between">
            <div class="col-md-6">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <img src="assets/images/logo-smk-sig.png" alt="SMK SIG" height="28">
                    <span class="school-footer-brand">SMK SIG (Sekolah Menengah Kejuruan SIG)</span>
                </div>
                <p class="text-muted small mb-0">
                    Semen Indonesia Foundation — Jl. Arief Rachman Hakim No. 90, Gresik, Jawa Timur.<br>
                    Sistem Pemilihan Ketua OSIS Digital (E-Pilketos v2.0).
                </p>
            </div>
            <div class="col-md-6 text-md-end">
                <div class="small text-muted mb-1">
                    Prinsip: <strong>LUBER JURDIL</strong> (Langsung, Umum, Bebas, Rahasia, Jujur, dan Adil).
                </div>
                <div class="text-muted small">
                    &copy; <?= date('Y') ?> SMK Semen Gresik. Seluruh hak cipta dilindungi.
                </div>
            </div>
        </div>
    </div>
</footer>

<!-- Floating PWA Install Banner for Mobile & Tablet -->
<div id="pwaInstallBanner" class="pwa-floating-banner" style="display: none;">
    <div class="pwa-banner-content">
        <img src="assets/images/icons/icon-192x192.png" alt="E-Pilketos" class="pwa-banner-icon">
        <div>
            <div class="pwa-banner-title">Pasang E-Pilketos di Layar Utama</div>
            <div class="pwa-banner-sub">Akses instan bilik suara full-screen tanpa browser bar</div>
        </div>
    </div>
    <div class="pwa-banner-actions">
        <button type="button" class="btn btn-sm btn-primary pwa-btn-action" style="background: var(--maroon-900); border: none;">
            <i class="bi bi-download me-1"></i>Pasang
        </button>
        <button type="button" class="btn-close pwa-banner-close" onclick="document.getElementById('pwaInstallBanner').style.display='none'" aria-label="Close"></button>
    </div>
</div>

<!-- Bootstrap 5.3.3 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Client Custom JS -->
<script src="assets/js/app.js"></script>
<!-- PWA Service Worker & Installer JS -->
<script src="assets/js/pwa.js"></script>
</body>
</html>

