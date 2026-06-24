/**
 * assets/js/admin.js
 * Handles sidebar toggle, image preview, drag-drop upload,
 * table search filter, and confirm-delete guards.
 */

document.addEventListener('DOMContentLoaded', () => {

    // ── Sidebar toggle (mobile) ──────────────────────────────
    const sidebar  = document.getElementById('lmsSidebar');
    const toggle   = document.getElementById('sidebarToggle');
    const overlay  = document.getElementById('sidebarOverlay');

    function openSidebar()  { sidebar?.classList.add('open');  overlay?.classList.add('show'); }
    function closeSidebar() { sidebar?.classList.remove('open'); overlay?.classList.remove('show'); }

    toggle?.addEventListener('click', () => {
        sidebar?.classList.contains('open') ? closeSidebar() : openSidebar();
    });
    overlay?.addEventListener('click', closeSidebar);

    // ── Image upload preview ─────────────────────────────────
    const fileInput   = document.getElementById('courseImage');
    const previewWrap = document.getElementById('imagePreviewWrap');
    const previewImg  = document.getElementById('imagePreview');
    const uploadZone  = document.getElementById('uploadZone');

    fileInput?.addEventListener('change', () => {
        const file = fileInput.files[0];
        if (file && file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = e => {
                if (previewImg)  previewImg.src = e.target.result;
                if (previewWrap) previewWrap.style.display = 'block';
            };
            reader.readAsDataURL(file);
        }
    });

    // Drag-and-drop visual feedback
    uploadZone?.addEventListener('dragover', e => {
        e.preventDefault();
        uploadZone.classList.add('dragging');
    });
    uploadZone?.addEventListener('dragleave', () => uploadZone.classList.remove('dragging'));
    uploadZone?.addEventListener('drop', e => {
        e.preventDefault();
        uploadZone.classList.remove('dragging');
        if (e.dataTransfer.files.length && fileInput) {
            fileInput.files = e.dataTransfer.files;
            fileInput.dispatchEvent(new Event('change'));
        }
    });

    // ── Live table search ────────────────────────────────────
    const searchInput = document.getElementById('tableSearch');
    const tableBody   = document.getElementById('courseTableBody');

    searchInput?.addEventListener('input', () => {
        const q = searchInput.value.toLowerCase();
        tableBody?.querySelectorAll('tr').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
        // Show/hide empty state
        const visible = tableBody?.querySelectorAll('tr:not([style*="none"])').length;
        const emptyRow = document.getElementById('emptySearchRow');
        if (emptyRow) emptyRow.style.display = (visible === 0) ? '' : 'none';
    });

    // ── Delete confirmation ──────────────────────────────────
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', e => {
            const msg = el.dataset.confirm || 'Are you sure?';
            if (!confirm(msg)) e.preventDefault();
        });
    });

    // ── Auto-dismiss alerts ──────────────────────────────────
    document.querySelectorAll('.lms-alert[data-autohide]').forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity .5s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 4000);
    });

    // ── Prevent double submit ────────────────────────────────
    document.querySelectorAll('form[data-loading]').forEach(form => {
        form.addEventListener('submit', () => {
            const btn = form.querySelector('[type="submit"]');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing…';
            }
        });
    });

});