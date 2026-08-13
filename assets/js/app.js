/**
 * Dawaam - Local-First Business Continuity Software
 * Client-Side JavaScript Runtime & Offline Status Monitor
 */

document.addEventListener('DOMContentLoaded', function () {
    console.log('Dawaam Local Continuity System Initialized.');

    const statusBadge = document.getElementById('dw-network-status');

    function updateNetworkStatus() {
        if (!statusBadge) return;

        if (navigator.onLine) {
            // Check if local server is reachable
            fetch(window.location.origin + window.location.pathname, { method: 'HEAD', cache: 'no-store' })
                .then(() => {
                    statusBadge.className = 'dw-badge-lan';
                    statusBadge.innerHTML = '<span class="dw-status-pulse"></span> Local LAN Active';
                })
                .catch(() => {
                    statusBadge.className = 'dw-badge-offline';
                    statusBadge.innerHTML = '<span class="dw-status-pulse"></span> LAN Standalone';
                });
        } else {
            statusBadge.className = 'dw-badge-offline';
            statusBadge.innerHTML = '<span class="dw-status-pulse"></span> Internet Blackout (Local-First Active)';
        }
    }

    window.addEventListener('online', updateNetworkStatus);
    window.addEventListener('offline', updateNetworkStatus);
    updateNetworkStatus();

    // Auto-dismiss alert messages after 6 seconds
    const autoAlerts = document.querySelectorAll('.alert-dismissible');
    autoAlerts.forEach(function (alert) {
        setTimeout(function () {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 6000);
    });
});
