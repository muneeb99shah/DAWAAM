<?php
/**
 * Dawaam - Local Business Continuity Software
 * Admin - Gateway Settings & Real Health Check Control Panel
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/notification_service.php';

require_permission('sms.manage');

$errors = [];
$test_results = [];
$settings = get_gateway_settings();

// Handle Post Actions (Save Settings, Test WhatsApp Connection, Test SMS Gateway, Send Test Payloads)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $errors[] = 'Security token validation failed.';
    }

    $action = $_POST['action'] ?? 'save_settings';

    if (empty($errors)) {
        if ($action === 'save_settings') {
            $new_settings = [
                'whatsapp_enabled' => isset($_POST['whatsapp_enabled']) ? '1' : '0',
                'whatsapp_provider' => trim($_POST['whatsapp_provider'] ?? 'whatsapp_cloud_api'),
                'whatsapp_graph_version' => trim($_POST['whatsapp_graph_version'] ?? 'v20.0'),
                'whatsapp_phone_number_id' => trim($_POST['whatsapp_phone_number_id'] ?? ''),
                'whatsapp_access_token' => trim($_POST['whatsapp_access_token'] ?? ''),
                'whatsapp_account_id' => trim($_POST['whatsapp_account_id'] ?? ''),
                'whatsapp_sender_number' => trim($_POST['whatsapp_sender_number'] ?? ''),
                'whatsapp_webhook_url' => trim($_POST['whatsapp_webhook_url'] ?? ''),
                
                'sms_enabled' => isset($_POST['sms_enabled']) ? '1' : '0',
                'sms_provider' => trim($_POST['sms_provider'] ?? 'android_app'),
                'sms_api_url' => trim($_POST['sms_api_url'] ?? ''),
                'sms_api_token' => trim($_POST['sms_api_token'] ?? ''),
                'sms_sender_id' => trim($_POST['sms_sender_id'] ?? ''),
                'sms_webhook_url' => trim($_POST['sms_webhook_url'] ?? '')
            ];

            save_gateway_settings($new_settings);
            log_audit_action('UPDATE_GATEWAY_SETTINGS', 'sms', null, 'Updated SMS & WhatsApp gateway configurations');
            set_flash_message('success', 'Gateway configuration saved successfully.');
            redirect('admin/sms/settings.php');
        } elseif ($action === 'test_whatsapp_conn') {
            $test_results['whatsapp_conn'] = test_whatsapp_api_connection();
        } elseif ($action === 'test_sms_conn') {
            $test_results['sms_conn'] = test_sms_gateway_connection();
        } elseif ($action === 'test_whatsapp_send') {
            $test_phone = trim($_POST['test_recipient_phone'] ?? '');
            if (empty($test_phone)) {
                $errors[] = 'Please provide a test recipient phone number.';
            } else {
                $msg = "[DAWAAM GATEWAY TEST]\nWhatsApp channel connectivity test executed at " . date('Y-m-d H:i:s');
                $wa_res = dispatch_whatsapp_message($test_phone, $msg, $settings);
                $test_results['whatsapp_send'] = $wa_res;
            }
        } elseif ($action === 'test_sms_send') {
            $test_phone = trim($_POST['test_recipient_phone'] ?? '');
            if (empty($test_phone)) {
                $errors[] = 'Please provide a test recipient phone number.';
            } else {
                $msg = "[DAWAAM GATEWAY TEST]\nSMS channel connectivity test executed at " . date('Y-m-d H:i:s');
                $sms_res = dispatch_sms_message($test_phone, $msg, $settings);
                $test_results['sms_send'] = $sms_res;
            }
        }
    }
}

// Execute On-Load Backend Health Checks
$wa_health = test_whatsapp_api_connection();
$sms_health = test_sms_gateway_connection();
$computed_endpoint = get_whatsapp_endpoint_url($settings);

$page_title = "SMS & WhatsApp Gateway Settings";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="fw-bold text-dark mb-1">
            <i class="bi bi-gear-fill text-warning me-2"></i> SMS & WhatsApp Gateway Settings
        </h2>
        <p class="text-muted small mb-0">Configure Graph API credentials, REST endpoints, and perform real-time backend health checks.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-chat-left-text me-1"></i> Gateway Dashboard
        </a>
        <a href="numbers.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-telephone-outbound me-1"></i> Notification Numbers
        </a>
    </div>
</div>

<?php if (count($errors) > 0): ?>
    <div class="alert alert-danger shadow-sm mb-4">
        <ul class="mb-0 ps-3">
            <?php foreach ($errors as $err): ?>
                <li><?php echo sanitize($err); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- Top System Health Status Badges -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="dw-card p-3 bg-white border-start border-4 <?php echo $wa_health['success'] ? 'border-success' : 'border-danger'; ?>">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="fw-bold text-dark"><i class="bi bi-whatsapp text-success me-1"></i> WHATSAPP CLOUD API STATUS</span>
                <?php if ($wa_health['success']): ?>
                    <span class="badge bg-success px-2.5 py-1"><i class="bi bi-circle-fill fs-6 me-1"></i> CONNECTED</span>
                <?php else: ?>
                    <span class="badge bg-danger px-2.5 py-1"><i class="bi bi-x-circle me-1"></i> FAILED</span>
                <?php endif; ?>
            </div>
            <div class="small text-muted mb-2">
                Graph Version: <code><?php echo sanitize($settings['whatsapp_graph_version'] ?? 'v20.0'); ?></code> | Phone ID: <code><?php echo sanitize($settings['whatsapp_phone_number_id'] ?? 'Not Set'); ?></code>
            </div>
            <?php if (!$wa_health['success']): ?>
                <div class="p-2 bg-danger bg-opacity-10 text-danger rounded border border-danger border-opacity-25 small font-monospace">
                    ERROR: <?php echo sanitize($wa_health['error'] ?? 'API Connection Failed'); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-md-6">
        <div class="dw-card p-3 bg-white border-start border-4 <?php echo $sms_health['success'] ? 'border-primary' : 'border-warning'; ?>">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="fw-bold text-dark"><i class="bi bi-chat-text text-primary me-1"></i> SMS GATEWAY STATUS</span>
                <?php if ($sms_health['success']): ?>
                    <span class="badge bg-primary px-2.5 py-1"><i class="bi bi-circle-fill fs-6 me-1"></i> ONLINE</span>
                <?php else: ?>
                    <span class="badge bg-warning text-dark px-2.5 py-1"><i class="bi bi-exclamation-triangle me-1"></i> OFFLINE</span>
                <?php endif; ?>
            </div>
            <div class="small text-muted mb-2">
                Provider: <code><?php echo strtoupper(sanitize($settings['sms_provider'] ?? 'android_app')); ?></code> | REST URL: <code><?php echo sanitize($settings['sms_api_url'] ?? 'Not Set'); ?></code>
            </div>
            <?php if (!$sms_health['success']): ?>
                <div class="p-2 bg-warning bg-opacity-10 text-dark rounded border border-warning border-opacity-25 small font-monospace">
                    REASON: <?php echo sanitize($sms_health['reason'] ?? 'Socket Connection Refused'); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Test Action Results Banner -->
<?php if (count($test_results) > 0): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="dw-card p-3 bg-dark text-white">
                <h6 class="fw-bold text-warning mb-2"><i class="bi bi-cpu me-1"></i> Test Diagnostics Output:</h6>
                <pre class="mb-0 text-light font-monospace small" style="white-space: pre-wrap;"><?php echo sanitize(json_encode($test_results, JSON_PRETTY_PRINT)); ?></pre>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Main Configuration Form -->
<form action="settings.php" method="POST">
    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
    <input type="hidden" name="action" value="save_settings">

    <div class="row g-4 mb-4">
        <!-- WhatsApp Settings Panel -->
        <div class="col-lg-6">
            <div class="dw-card h-100 p-4 bg-white border-success">
                <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
                    <div class="d-flex align-items-center">
                        <div class="bg-success bg-opacity-10 text-success p-2.5 rounded-circle me-3">
                            <i class="bi bi-whatsapp fs-4"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold text-dark mb-0">WhatsApp Settings</h5>
                            <span class="text-muted small">Primary notification channel</span>
                        </div>
                    </div>
                    <div class="form-check form-switch fs-5">
                        <input class="form-check-input" type="checkbox" name="whatsapp_enabled" id="waEnabledSwitch" value="1" <?php echo ($settings['whatsapp_enabled'] ?? '1') === '1' ? 'checked' : ''; ?>>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">WhatsApp API Provider</label>
                        <select name="whatsapp_provider" class="form-select">
                            <option value="whatsapp_cloud_api" <?php echo ($settings['whatsapp_provider'] ?? '') === 'whatsapp_cloud_api' ? 'selected' : ''; ?>>Meta WhatsApp Cloud API</option>
                            <option value="ultramsg" <?php echo ($settings['whatsapp_provider'] ?? '') === 'ultramsg' ? 'selected' : ''; ?>>UltraMsg Gateway API</option>
                            <option value="baileys_node" <?php echo ($settings['whatsapp_provider'] ?? '') === 'baileys_node' ? 'selected' : ''; ?>>Local Baileys Node Gateway</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Graph API Version</label>
                        <input type="text" class="form-control font-monospace" name="whatsapp_graph_version" value="<?php echo sanitize($settings['whatsapp_graph_version'] ?? 'v20.0'); ?>" placeholder="v20.0">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">WhatsApp Phone Number ID <span class="text-danger">*</span></label>
                        <input type="text" class="form-control font-monospace" name="whatsapp_phone_number_id" value="<?php echo sanitize($settings['whatsapp_phone_number_id'] ?? ''); ?>" placeholder="109823471092837" required>
                        <div class="form-text small">Required for Meta messages endpoint (`/{PHONE_NUMBER_ID}/messages`).</div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Business Account ID (WABA ID)</label>
                        <input type="text" class="form-control font-monospace" name="whatsapp_account_id" value="<?php echo sanitize($settings['whatsapp_account_id'] ?? ''); ?>" placeholder="109823471092837">
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Sender WhatsApp Number</label>
                        <input type="text" class="form-control font-monospace" name="whatsapp_sender_number" value="<?php echo sanitize($settings['whatsapp_sender_number'] ?? ''); ?>" placeholder="Example: +1234567890">
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">API Access Token / Bearer Key</label>
                        <input type="password" class="form-control font-monospace" name="whatsapp_access_token" value="<?php echo sanitize($settings['whatsapp_access_token'] ?? ''); ?>" placeholder="••••••••••••••••">
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Computed Messages Endpoint URL</label>
                        <input type="text" class="form-control font-monospace bg-light" value="<?php echo sanitize($computed_endpoint); ?>" readonly>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Delivery Status Webhook URL</label>
                        <input type="text" class="form-control font-monospace bg-light" name="whatsapp_webhook_url" value="<?php echo sanitize($settings['whatsapp_webhook_url'] ?? ''); ?>" readonly>
                        <div class="form-text text-warning small"><i class="bi bi-info-circle me-1"></i> Note: For Meta Cloud API webhooks in local development, proxy this URL via a public HTTPS tunnel (e.g. ngrok).</div>
                    </div>
                </div>

                <div class="pt-3 border-top mt-3 d-flex gap-2">
                    <button type="submit" name="action" value="test_whatsapp_conn" class="btn btn-outline-success btn-sm w-100">
                        <i class="bi bi-activity me-1"></i> Test WhatsApp Connection
                    </button>
                </div>
            </div>
        </div>

        <!-- SMS Gateway Settings Panel -->
        <div class="col-lg-6">
            <div class="dw-card h-100 p-4 bg-white border-primary">
                <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
                    <div class="d-flex align-items-center">
                        <div class="bg-primary bg-opacity-10 text-primary p-2.5 rounded-circle me-3">
                            <i class="bi bi-chat-text fs-4"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold text-dark mb-0">SMS Gateway Settings</h5>
                            <span class="text-muted small">Automatic fallback channel</span>
                        </div>
                    </div>
                    <div class="form-check form-switch fs-5">
                        <input class="form-check-input" type="checkbox" name="sms_enabled" id="smsEnabledSwitch" value="1" <?php echo ($settings['sms_enabled'] ?? '1') === '1' ? 'checked' : ''; ?>>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">SMS Provider Architecture</label>
                        <select name="sms_provider" class="form-select">
                            <option value="android_app" <?php echo ($settings['sms_provider'] ?? '') === 'android_app' ? 'selected' : ''; ?>>Android SIM Gateway App</option>
                            <option value="simulated_cellular" <?php echo ($settings['sms_provider'] ?? '') === 'simulated_cellular' ? 'selected' : ''; ?>>Simulated GSM Cellular SIM</option>
                            <option value="twilio" <?php echo ($settings['sms_provider'] ?? '') === 'twilio' ? 'selected' : ''; ?>>Twilio REST API</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Sender ID / Brand Header</label>
                        <input type="text" class="form-control font-monospace" name="sms_sender_id" value="<?php echo sanitize($settings['sms_sender_id'] ?? ''); ?>" placeholder="DAWAAM_SMS">
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Gateway REST API URL</label>
                        <input type="url" class="form-control font-monospace" name="sms_api_url" value="<?php echo sanitize($settings['sms_api_url'] ?? ''); ?>" placeholder="http://192.168.108.55:8080/send">
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">API Authorization Token</label>
                        <input type="password" class="form-control font-monospace" name="sms_api_token" value="<?php echo sanitize($settings['sms_api_token'] ?? ''); ?>" placeholder="••••••••••••••••">
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Delivery Report Webhook Callback</label>
                        <input type="text" class="form-control font-monospace bg-light" name="sms_webhook_url" value="<?php echo sanitize($settings['sms_webhook_url'] ?? ''); ?>" readonly>
                    </div>
                </div>

                <div class="pt-3 border-top mt-4 d-flex gap-2">
                    <button type="submit" name="action" value="test_sms_conn" class="btn btn-outline-primary btn-sm w-100">
                        <i class="bi bi-broadcast me-1"></i> Test SMS Gateway Connection
                    </button>
                </div>
            </div>
        </div>

        <!-- Manual Dispatch Test Bar -->
        <div class="col-12">
            <div class="dw-card p-4 bg-white border">
                <h5 class="fw-bold text-dark mb-3"><i class="bi bi-send-check text-primary me-2"></i> Manual Live Notification Test</h5>
                <div class="row g-2 align-items-center">
                    <div class="col-md-6">
                        <input type="text" class="form-control font-monospace" name="test_recipient_phone" placeholder="Example: +1234567890">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" name="action" value="test_whatsapp_send" class="btn btn-success btn-sm w-100">
                            <i class="bi bi-whatsapp me-1"></i> Send Test WhatsApp
                        </button>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" name="action" value="test_sms_send" class="btn btn-primary btn-sm w-100">
                            <i class="bi bi-chat-text me-1"></i> Send Test SMS
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 text-end">
            <button type="submit" name="action" value="save_settings" class="btn btn-dw-primary px-4 py-2">
                <i class="bi bi-check-circle me-1"></i> Save Gateway Configuration
            </button>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
