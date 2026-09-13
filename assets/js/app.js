/**
 * E-Pilketos v2.0 - Client-side Utilities
 * SMK SIG (Sekolah Menengah Kejuruan SIG)
 */

document.addEventListener('DOMContentLoaded', () => {
    // Quick search filter for student list
    const studentSearch = document.getElementById('studentSearch');
    if (studentSearch) {
        studentSearch.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase().trim();
            const items = document.querySelectorAll('.student-item');
            items.forEach(item => {
                const name = item.getAttribute('data-name')?.toLowerCase() || '';
                const absen = item.getAttribute('data-absen')?.toLowerCase() || '';
                if (name.includes(query) || absen.includes(query)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    }

    // Modal Visi Misi detail handler
    const detailButtons = document.querySelectorAll('[data-bs-target="#modalVisiMisi"]');
    detailButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const num = btn.getAttribute('data-candidate-number');
            const name = btn.getAttribute('data-candidate-name');
            const cls = btn.getAttribute('data-candidate-class');
            const vision = btn.getAttribute('data-candidate-vision');
            const mission = btn.getAttribute('data-candidate-mission');
            const photo = btn.getAttribute('data-candidate-photo');

            document.getElementById('modalCandNum').textContent = num;
            document.getElementById('modalCandName').textContent = name;
            document.getElementById('modalCandClass').textContent = cls;
            document.getElementById('modalCandVision').textContent = vision;
            
            const missionEl = document.getElementById('modalCandMission');
            missionEl.innerHTML = '';
            const missionLines = mission.split('\n');
            missionLines.forEach(line => {
                if (line.trim()) {
                    const li = document.createElement('li');
                    li.textContent = line.trim().replace(/^[0-9]+\.\s*/, '');
                    missionEl.appendChild(li);
                }
            });

            const photoEl = document.getElementById('modalCandPhoto');
            if (photoEl) {
                photoEl.src = photo || 'assets/images/logo-smk-sig.png';
            }
        });
    });

    // Confirmation dialog for voting button
    const voteCandidateButtons = document.querySelectorAll('.btn-trigger-vote');
    voteCandidateButtons.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const candId = btn.getAttribute('data-candidate-id');
            const candNum = btn.getAttribute('data-candidate-number');
            const candName = btn.getAttribute('data-candidate-name');
            
            const confirmNumberEl = document.getElementById('confirmCandNumber');
            const confirmNameEl = document.getElementById('confirmCandName');
            const hiddenCandInput = document.getElementById('inputCandidateId');

            if (confirmNumberEl && confirmNameEl && hiddenCandInput) {
                confirmNumberEl.textContent = candNum;
                confirmNameEl.textContent = candName;
                hiddenCandInput.value = candId;

                const confirmModal = new bootstrap.Modal(document.getElementById('modalKonfirmasiPilihan'));
                confirmModal.show();
            }
        });
    });
});
