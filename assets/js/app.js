/**
 * ==============================================================
 * app.js — global JS helpers shared across the whole system
 * (toasts, modal open/close, sidebar toggle, generic AJAX POST)
 * ==============================================================
 */

/* ---------------- TOASTS ---------------- */
function showToast(type, message) {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    const icons = { success: 'fa-circle-check', error: 'fa-circle-exclamation', info: 'fa-circle-info' };
    const icon = icons[type] || icons.info;

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `<i class="fa-solid ${icon}"></i><span class="toast-msg">${message}</span><span class="toast-close">&times;</span>`;
    container.appendChild(toast);

    toast.querySelector('.toast-close').addEventListener('click', () => toast.remove());
    setTimeout(() => toast.remove(), 5000);
}

/* ---------------- SIDEBAR TOGGLE (mobile) ---------------- */
document.addEventListener('DOMContentLoaded', () => {
    const toggleBtn = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', () => sidebar.classList.toggle('open'));
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 900 && sidebar.classList.contains('open')
                && !sidebar.contains(e.target) && e.target !== toggleBtn) {
                sidebar.classList.remove('open');
            }
        });
    }
});

/* ---------------- MODAL HELPERS ---------------- */
function openModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.add('show');
}
function closeModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('show');
}
// Close modal when clicking outside the box
document.addEventListener('click', (e) => {
    if (e.target.classList && e.target.classList.contains('modal-backdrop')) {
        e.target.classList.remove('show');
    }
});

/* ---------------- GENERIC AJAX (fetch wrapper) ---------------- */
async function ajaxPost(url, data) {
    const formData = new FormData();
    for (const key in data) formData.append(key, data[key]);
    const res = await fetch(url, { method: 'POST', body: formData });
    let json;
    try {
        json = await res.json();
    } catch (err) {
        throw new Error('Invalid server response');
    }
    return json;
}

async function ajaxGet(url) {
    const res = await fetch(url);
    return await res.json();
}

/* ---------------- DEBOUNCE (for live search boxes) ---------------- */
function debounce(fn, delay = 350) {
    let timer;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), delay);
    };
}

/* ---------------- CONFIRM DELETE HELPER ---------------- */
function confirmDelete(message = 'Are you sure you want to delete this record? This cannot be undone.') {
    return confirm(message);
}
