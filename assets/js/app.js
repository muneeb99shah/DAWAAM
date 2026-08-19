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
});
