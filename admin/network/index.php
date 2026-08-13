<?php
/**
 * Dawaam - Local Business Continuity Software
 * Admin - Local Network Operations & Onboarding Hub
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/qrcode.php';

require_permission('network.view');

$lan_ip = get_server_lan_ip();
$server_port = $_SERVER['SERVER_PORT'] ?? 8000;
$access_url = "http://{$lan_ip}:{$server_port}";

// Quick WAN check
$is_wan_online = false;
$sock = @fsockopen('8.8.8.8', 53, $errno, $errstr, 1);
if ($sock) {
    $is_wan_online = true;
    fclose($sock);
}

$page_title = "Local Network Operations Hub";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h2 class="fw-bold text-dark mb-1">
            <i class="bi bi-wifi text-primary me-2"></i> Local Network Operations Hub
        </h2>
        <p class="text-muted small mb-0">Local Wi-Fi server configuration, staff device pairing, and offline LAN diagnostics.</p>
    </div>
    <div>
        <button id="btnRefreshPing" onclick="checkLiveNetworkStatus();" class="btn btn-dw-primary btn-sm">
            <i id="iconRefreshPing" class="bi bi-arrow-repeat me-1"></i> Refresh Network Ping
        </button>
    </div>
</div>

<!-- Hero Server LAN Connection Banner -->
<div class="dw-hero-banner p-4 p-md-5 mb-4 rounded-3 shadow">
    <div class="row align-items-center">
        <div class="col-lg-8">
            <span class="badge bg-light text-dark fw-bold px-3 py-1.5 rounded-pill mb-3">
                <i class="bi bi-hdd-network-fill me-1 text-teal" style="color:#0f766e;"></i> Local LAN Server Host
            </span>
            <h3 class="display-6 fw-bold text-white mb-2">
                Pharmacy LAN Address: <code class="text-warning"><?php echo sanitize($lan_ip); ?>:<?php echo $server_port; ?></code>
            </h3>
            <p class="lead text-white-50 small mb-3">
                Staff devices inside Quetta Pharmacy can access Dawaam directly over local Wi-Fi without internet connectivity.
            </p>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <div class="input-group input-group-sm" style="max-width: 380px;">
                    <input type="text" class="form-control font-monospace" id="accessUrlInput" value="<?php echo sanitize($access_url); ?>" readonly>
                    <button class="btn btn-warning fw-bold" onclick="copyAccessUrl();">
                        <i class="bi bi-clipboard me-1"></i> Copy URL
                    </button>
                </div>
                <span id="copyMsg" class="small text-warning d-none"><i class="bi bi-check-circle me-1"></i> Copied!</span>
            </div>
        </div>

        <!-- Real 100% Offline Pure PHP Vector SVG QR Code Container -->
        <div class="col-lg-4 text-center mt-4 mt-lg-0">
            <div class="p-3 bg-dark bg-opacity-50 rounded-3 border border-secondary">
                <div class="d-inline-block bg-white p-2 rounded shadow mb-1" style="max-width: 175px;">
                    <?php echo DawaamQR::svg($access_url, 5); ?>
                </div>
                <div class="small text-white-50 mt-1">
                    <i class="bi bi-camera-fill me-1 text-warning"></i> Scan with phone camera to connect
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Network Indicators Grid -->
<div class="row g-3 mb-5">
    <div class="col-md-4">
        <div class="dw-card h-100 p-4 border-start border-4 border-success">
            <div class="d-flex align-items-center mb-2">
                <div class="p-2 bg-success bg-opacity-10 text-success rounded-circle me-3">
                    <i class="bi bi-router fs-3"></i>
                </div>
                <div>
                    <h6 class="fw-bold text-dark mb-0">Local LAN Uptime</h6>
                    <span class="badge bg-success px-2 py-1">
                        <i class="bi bi-check-circle-fill me-1"></i> LOCAL LAN ACTIVE
                    </span>
                </div>
            </div>
            <p class="small text-muted mb-0 mt-2">
                Local MySQL database and PHP web server are running cleanly on internal network.
            </p>
        </div>
    </div>

    <div class="col-md-4">
        <div class="dw-card h-100 p-4 border-start border-4 <?php echo $is_wan_online ? 'border-success' : 'border-warning'; ?>">
            <div class="d-flex align-items-center mb-2">
                <div class="p-2 <?php echo $is_wan_online ? 'bg-success bg-opacity-10 text-success' : 'bg-warning bg-opacity-10 text-warning'; ?> rounded-circle me-3">
                    <i class="bi bi-globe fs-3"></i>
                </div>
                <div>
                    <h6 class="fw-bold text-dark mb-0">Internet WAN Link</h6>
                    <?php if ($is_wan_online): ?>
                        <span class="badge bg-success px-2 py-1" id="badgeWanLink">
                            <i class="bi bi-wifi me-1"></i> WAN ONLINE
                        </span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark px-2 py-1" id="badgeWanLink">
                            <i class="bi bi-wifi-off me-1"></i> INTERNET OFFLINE (LOCAL CONTINUITY)
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <p class="small text-muted mb-0 mt-2" id="textWanLink">
                <?php echo $is_wan_online ? 'Cloud sync services connected.' : 'Quetta internet blackout active. All POS checkouts proceed 100% locally.'; ?>
            </p>
        </div>
    </div>

    <div class="col-md-4">
        <div class="dw-card h-100 p-4 border-start border-4 border-info">
            <div class="d-flex align-items-center mb-2">
                <div class="p-2 bg-info bg-opacity-10 text-info rounded-circle me-3">
                    <i class="bi bi-cpu fs-3"></i>
                </div>
                <div>
                    <h6 class="fw-bold text-dark mb-0">Server Engine</h6>
                    <span class="badge bg-info text-dark px-2 py-1">
                        PHP <?php echo PHP_VERSION; ?>
                    </span>
                </div>
            </div>
            <p class="small text-muted mb-0 mt-2">
                Host OS: Windows | Binding: <code>0.0.0.0:<?php echo $server_port; ?></code> (All Interface Access).
            </p>
        </div>
    </div>
</div>

<!-- Staff Device Onboarding Instructions -->
<div class="row g-4">
    <div class="col-lg-7">
        <div class="dw-card p-4">
            <h5 class="fw-bold text-dark mb-3 border-bottom pb-2">
                <i class="bi bi-phone text-primary me-2"></i> Staff Smartphone & Tablet Setup Guide
            </h5>

            <ol class="list-group list-group-numbered list-group-flush mb-0 small">
                <li class="list-group-item px-0 py-3">
                    <strong class="text-dark">Connect to Pharmacy Wi-Fi Router:</strong>
                    <span class="text-muted d-block mt-1">Ensure staff phone or register tablet is connected to the same local Wi-Fi network as the Dawaam server PC.</span>
                </li>
                <li class="list-group-item px-0 py-3">
                    <strong class="text-dark">Scan QR Code or Open Mobile Browser:</strong>
                    <span class="text-muted d-block mt-1">Point staff phone camera at the QR code above, or launch Chrome/Safari and enter <code><?php echo sanitize($access_url); ?></code>.</span>
                </li>
                <li class="list-group-item px-0 py-3">
                    <strong class="text-dark">Authenticate & Operate POS:</strong>
                    <span class="text-muted d-block mt-1">Log in with staff credentials (e.g. <code>tariq_pharm</code>). Staff can now record sales and check stock from anywhere inside the shop!</span>
                </li>
            </ol>
        </div>
    </div>

    <!-- Live Status API Poller Card -->
    <div class="col-lg-5">
        <div class="dw-card p-4">
            <h5 class="fw-bold text-dark mb-3 border-bottom pb-2">
                <i class="bi bi-activity text-teal me-2" style="color:#0f766e;"></i> Real-Time LAN Ping Monitor
            </h5>

            <div id="liveStatusContainer" class="p-3 bg-light rounded-3 border mb-3">
                <div class="d-flex justify-content-between mb-2 small border-bottom pb-2">
                    <span class="text-muted">LAN Address:</span>
                    <strong class="text-dark font-monospace" id="liveIp"><?php echo sanitize($lan_ip); ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2 small border-bottom pb-2">
                    <span class="text-muted">LAN Gateway Status:</span>
                    <span class="badge bg-success" id="liveLanStatus">ACTIVE</span>
                </div>
                <div class="d-flex justify-content-between mb-2 small border-bottom pb-2">
                    <span class="text-muted">Internet State:</span>
                    <span class="badge <?php echo $is_wan_online ? 'bg-success' : 'bg-warning text-dark'; ?>" id="liveWanStatus">
                        <?php echo $is_wan_online ? 'ONLINE' : 'OFFLINE'; ?>
                    </span>
                </div>
                <div class="d-flex justify-content-between mb-2 small border-bottom pb-2">
                    <span class="text-muted">Unsynced Change Logs:</span>
                    <span class="fw-bold text-dark" id="liveUnsynced">Loading...</span>
                </div>
                <div class="d-flex justify-content-between small">
                    <span class="text-muted">Last Ping Refreshed:</span>
                    <span class="text-muted" id="livePingTime"><?php echo date('H:i:s'); ?></span>
                </div>
            </div>

            <a href="status.php" target="_blank" class="btn btn-outline-secondary btn-sm w-100">
                <i class="bi bi-code-slash me-1"></i> View Raw JSON API Payload
            </a>
        </div>
    </div>
</div>

<script>
function copyAccessUrl() {
    const input = document.getElementById('accessUrlInput');
    input.select();
    document.execCommand('copy');
    const msg = document.getElementById('copyMsg');
    msg.classList.remove('d-none');
    setTimeout(() => msg.classList.add('d-none'), 3000);
}

function checkLiveNetworkStatus() {
    const icon = document.getElementById('iconRefreshPing');
    if (icon) icon.classList.add('spin-icon');

    fetch('status.php?t=' + new Date().getTime())
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('liveIp').textContent = data.lan_ip;
                document.getElementById('liveLanStatus').textContent = data.lan_status.toUpperCase();
                
                const wanBadge = document.getElementById('liveWanStatus');
                wanBadge.textContent = data.internet_status.toUpperCase();
                wanBadge.className = (data.internet_status === 'online') ? 'badge bg-success' : 'badge bg-warning text-dark';

                const mainWanBadge = document.getElementById('badgeWanLink');
                const mainWanText = document.getElementById('textWanLink');
                if (mainWanBadge && mainWanText) {
                    if (data.internet_status === 'online') {
                        mainWanBadge.className = 'badge bg-success px-2 py-1';
                        mainWanBadge.innerHTML = '<i class="bi bi-wifi me-1"></i> WAN ONLINE';
                        mainWanText.textContent = 'Cloud sync services connected.';
                    } else {
                        mainWanBadge.className = 'badge bg-warning text-dark px-2 py-1';
                        mainWanBadge.innerHTML = '<i class="bi bi-wifi-off me-1"></i> INTERNET OFFLINE (LOCAL CONTINUITY)';
                        mainWanText.textContent = 'Quetta internet blackout active. All POS checkouts proceed 100% locally.';
                    }
                }

                document.getElementById('liveUnsynced').textContent = data.unsynced_records + ' change logs';
                document.getElementById('livePingTime').textContent = new Date().toLocaleTimeString();
            }
        })
        .catch(err => console.error('Status fetch error:', err))
        .finally(() => {
            if (icon) icon.classList.remove('spin-icon');
        });
}

document.addEventListener('DOMContentLoaded', checkLiveNetworkStatus);
</script>

<style>
.spin-icon {
    display: inline-block;
    animation: spin 0.8s linear infinite;
}
@keyframes spin {
    100% { transform: rotate(360deg); }
}
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
