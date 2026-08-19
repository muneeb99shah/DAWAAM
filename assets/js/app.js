/**
 * Dawaam - Local-First Business Continuity Software
 * Client-Side JavaScript Runtime & Offline Status Monitor
 */

document.addEventListener('DOMContentLoaded', function () {
    console.log('Dawaam Local Continuity System Initialized.');

    const statusBadge = document.getElementById('dw-network-status');

    function updateNetworkStatus(skipFetch) {
        if (!statusBadge) return;

        if (navigator.onLine) {
            statusBadge.className = 'dw-badge-lan';
            statusBadge.innerHTML = '<span class="dw-status-pulse"></span> Local LAN Active';
        } else {
            statusBadge.className = 'dw-badge-offline';
            statusBadge.innerHTML = '<span class="dw-status-pulse"></span> Internet Blackout (Local-First Active)';
        }
    }

    window.addEventListener('online', function() { updateNetworkStatus(); });
    window.addEventListener('offline', function() { updateNetworkStatus(); });
    updateNetworkStatus();

    // Instant click visual response feedback for submit buttons
    const submitButtons = document.querySelectorAll('form button[type="submit"]:not(.no-instant-feedback)');
    submitButtons.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            const form = btn.closest('form');
            if (form && form.checkValidity()) {
                btn.style.opacity = '0.75';
                btn.style.pointerEvents = 'none';
                const originalText = btn.innerHTML;
                btn.setAttribute('data-orig-text', originalText);
            }
        });
    });

    // Auto-dismiss alert messages after 6 seconds
    const autoAlerts = document.querySelectorAll('.alert-dismissible');
    autoAlerts.forEach(function (alert) {
        setTimeout(function () {
            if (typeof bootstrap !== 'undefined' && bootstrap.Alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }
        }, 6000);
    });

    // Collapsible & Expandable Operational Sidebar Controller
    const sidebar = document.getElementById('dw-main-sidebar');
    const toggleBtn = document.getElementById('dw-sidebar-toggle-btn');
    const searchInput = document.getElementById('dw-sidebar-search-input');

    if (sidebar && toggleBtn) {
        // Read saved user preference from localStorage (default: collapsed)
        const savedState = localStorage.getItem('dw_sidebar_state');
        if (savedState === 'expanded') {
            expandSidebar();
        } else {
            collapseSidebar();
        }

        toggleBtn.addEventListener('click', function (e) {
            e.preventDefault();
            if (sidebar.classList.contains('dw-sidebar-expanded')) {
                collapseSidebar();
                localStorage.setItem('dw_sidebar_state', 'collapsed');
            } else {
                expandSidebar();
                localStorage.setItem('dw_sidebar_state', 'expanded');
            }
        });

        function expandSidebar() {
            sidebar.classList.remove('dw-sidebar-collapsed');
            sidebar.classList.add('dw-sidebar-expanded');
            toggleBtn.setAttribute('title', 'Collapse Sidebar Navigation');
            const icon = toggleBtn.querySelector('.dw-toggle-icon');
            if (icon) {
                icon.className = 'bi bi-chevron-double-left dw-toggle-icon';
            }
        }

        function collapseSidebar() {
            sidebar.classList.remove('dw-sidebar-expanded');
            sidebar.classList.add('dw-sidebar-collapsed');
            toggleBtn.setAttribute('title', 'Expand Sidebar Navigation');
            const icon = toggleBtn.querySelector('.dw-toggle-icon');
            if (icon) {
                icon.className = 'bi bi-chevron-double-right dw-toggle-icon';
            }
            if (searchInput) {
                searchInput.value = '';
                filterSidebarItems('');
            }
        }

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                filterSidebarItems(this.value.trim().toLowerCase());
            });
        }

        function filterSidebarItems(query) {
            const links = sidebar.querySelectorAll('.dw-sidebar-link');
            links.forEach(function (link) {
                const title = (link.getAttribute('data-title') || '').toLowerCase();
                if (!query || title.includes(query)) {
                    link.style.display = 'flex';
                } else {
                    link.style.display = 'none';
                }
            });
        }
    }
});
