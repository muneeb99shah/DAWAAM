<?php
/**
 * Dawaam - Local Business Continuity Software
 * Contact Page - Inquiry & Server-Side Form Processing
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// If user is already logged in, redirect directly to their Operational Dashboard
if (is_logged_in()) {
    redirect('admin/index.php');
}

$page_title = "Contact & Local Business Support";

$errors = [];
$form_data = [
    'name' => '',
    'email' => '',
    'subject' => '',
    'message' => ''
];

// Pre-fill subject if inquiring about a specific service package
if (isset($_GET['service_id'])) {
    $service_id = (int)$_GET['service_id'];
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT title FROM services WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $service_id]);
    $srv = $stmt->fetch();
    if ($srv) {
        $form_data['subject'] = "Inquiry regarding package: " . $srv['title'];
    }
}

// Handle Form POST Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Verify CSRF Token
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $errors[] = "Security check failed. Please refresh the page and try again.";
    }

    // 2. Extract & Sanitize Inputs
    $form_data['name'] = trim($_POST['name'] ?? '');
    $form_data['email'] = trim($_POST['email'] ?? '');
    $form_data['subject'] = trim($_POST['subject'] ?? '');
    $form_data['message'] = trim($_POST['message'] ?? '');

    // 3. Server-Side Validation
    if (empty($form_data['name'])) {
        $errors[] = "Please enter your name.";
    } elseif (mb_strlen($form_data['name']) > 100) {
        $errors[] = "Name cannot exceed 100 characters.";
    }

    if (empty($form_data['email'])) {
        $errors[] = "Please enter your email address.";
    } elseif (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address (e.g. name@domain.com).";
    }

    if (empty($form_data['message'])) {
        $errors[] = "Please enter your message.";
    } elseif (mb_strlen($form_data['message']) < 10) {
        $errors[] = "Message must be at least 10 characters long.";
    }

    // 4. If No Validation Errors, Insert into Database via PDO
    if (empty($errors)) {
        try {
            $pdo = get_db_connection();

            // Append subject to message body if provided
            $full_message = $form_data['message'];
            if (!empty($form_data['subject'])) {
                $full_message = "SUBJECT: " . $form_data['subject'] . "\n\n" . $full_message;
            }

            $stmt = $pdo->prepare("
                INSERT INTO contact_messages (name, email, message, submitted_at)
                VALUES (:name, :email, :message, NOW())
            ");
            $stmt->execute([
                ':name' => $form_data['name'],
                ':email' => $form_data['email'],
                ':message' => $full_message
            ]);

            $msg_id = $pdo->lastInsertId();

            // Queue for offline cloud sync & record audit log
            queue_sync_record('contact_messages', $msg_id, 'INSERT');
            log_audit_action('SUBMIT_CONTACT_MESSAGE', 'contact_messages', $msg_id, "Inquiry from {$form_data['name']} ({$form_data['email']})");

            set_flash_message('success', "Thank you, {$form_data['name']}! Your message has been successfully recorded on the local server. Our team will contact you shortly.");
            redirect('contact.php');
        } catch (Exception $e) {
            error_log('Contact Form Error: ' . $e->getMessage());
            $errors[] = "An unexpected error occurred while saving your message. Please try again.";
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Banner -->
<div class="row mb-5">
    <div class="col-12">
        <div class="dw-hero-banner">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <span class="badge bg-warning text-dark fw-bold px-3 py-2 rounded-pill mb-3 d-inline-flex align-items-center gap-1">
                        <i class="bi bi-headset fs-6"></i> Local Technical Assistance
                    </span>
                    <h1 class="display-5 fw-bold mb-3 text-white">
                        Get In Touch With Dawaam Team
                    </h1>
                    <p class="lead text-white-50 mb-0" style="font-size: 1.1rem; line-height: 1.6;">
                        Have questions about configuring local LAN server continuity or Android SMS gateway hardware for your business in Quetta? Reach out to our local deployment team.
                    </p>
                </div>
                <div class="col-lg-4 d-none d-lg-block text-center">
                    <div class="p-4 bg-dark bg-opacity-40 border border-light border-opacity-25 rounded-4 backdrop-blur">
                        <i class="bi bi-envelope-check text-success display-1 d-block mb-2"></i>
                        <h5 class="fw-bold text-white mb-1">Local Business Support</h5>
                        <p class="small text-white-50 mb-0">Quetta, Balochistan, Pakistan</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-5">
    <!-- Contact Info Cards -->
    <div class="col-lg-4">
        <div class="dw-card h-100 p-4">
            <h4 class="fw-bold text-dark mb-4 border-bottom pb-2">Business Information</h4>

            <div class="d-flex align-items-start mb-4">
                <div class="bg-light text-teal p-3 rounded-circle me-3" style="color:#0f766e;">
                    <i class="bi bi-geo-alt-fill fs-4 text-danger"></i>
                </div>
                <div>
                    <strong class="text-dark d-block">Location Address</strong>
                    <span class="text-muted small">MA Jinnah Road, Quetta, Balochistan, Pakistan</span>
                </div>
            </div>

            <div class="d-flex align-items-start mb-4">
                <div class="bg-light text-teal p-3 rounded-circle me-3" style="color:#0f766e;">
                    <i class="bi bi-telephone-fill fs-4 text-success"></i>
                </div>
                <div>
                    <strong class="text-dark d-block">Local Phone / Mobile</strong>
                    <span class="text-muted small">+92 (81) 2840911 / +92 300 1234567</span>
                </div>
            </div>

            <div class="d-flex align-items-start mb-4">
                <div class="bg-light text-teal p-3 rounded-circle me-3" style="color:#0f766e;">
                    <i class="bi bi-envelope-fill fs-4 text-info"></i>
                </div>
                <div>
                    <strong class="text-dark d-block">Email Support</strong>
                    <span class="text-muted small">support@dawaam.local</span>
                </div>
            </div>

            <div class="p-3 bg-light rounded-3 border">
                <h6 class="fw-bold text-dark mb-1"><i class="bi bi-clock-history me-1 text-warning"></i> Operational Hours</h6>
                <p class="small text-muted mb-0">Monday – Saturday: 9:00 AM – 9:00 PM</p>
            </div>
        </div>
    </div>

    <!-- Contact Form -->
    <div class="col-lg-8">
        <div class="dw-card h-100 p-4">
            <h4 class="fw-bold text-dark mb-3 border-bottom pb-2">Send Message / Request Consultation</h4>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger shadow-sm border-0 mb-4" role="alert">
                    <h6 class="fw-bold mb-2"><i class="bi bi-exclamation-triangle-fill me-2"></i> Please fix the following errors:</h6>
                    <ul class="mb-0 ps-3 small">
                        <?php foreach ($errors as $err): ?>
                            <li><?php echo sanitize($err); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form action="contact.php" method="POST">
                <?php csrf_field(); ?>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="name" class="form-label fw-semibold text-dark">Your Name <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" class="form-control" id="name" name="name" value="<?php echo sanitize($form_data['name']); ?>" placeholder="e.g. Ahmed Khan" required>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label for="email" class="form-label fw-semibold text-dark">Email Address <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo sanitize($form_data['email']); ?>" placeholder="e.g. ahmed@example.com" required>
                        </div>
                    </div>

                    <div class="col-12">
                        <label for="subject" class="form-label fw-semibold text-dark">Subject / Service Inquiry</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-chat-left-text"></i></span>
                            <input type="text" class="form-control" id="subject" name="subject" value="<?php echo sanitize($form_data['subject']); ?>" placeholder="e.g. Local Server Installation Inquiry">
                        </div>
                    </div>

                    <div class="col-12">
                        <label for="message" class="form-label fw-semibold text-dark">Message / Business Details <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="message" name="message" rows="5" placeholder="Describe your store type, number of registers, or continuity requirements..." required><?php echo sanitize($form_data['message']); ?></textarea>
                    </div>

                    <div class="col-12 pt-2">
                        <button type="submit" class="btn btn-dw-primary px-4 py-2">
                            <i class="bi bi-send-fill me-2"></i> Submit Inquiry Message
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
