<?php
/**
 * Dawaam - Local Business Continuity Software
 * Services Page - Dynamic Database Catalog
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// If user is already logged in, redirect directly to their Operational Dashboard
if (is_logged_in()) {
    redirect('admin/index.php');
}

$page_title = "Business Continuity Services Catalog";
require_once __DIR__ . '/includes/header.php';

// Fetch Categories and Services dynamically from MySQL via PDO
$pdo = get_db_connection();

$stmt_cat = $pdo->query("SELECT id, name FROM service_categories ORDER BY id ASC");
$service_categories = $stmt_cat->fetchAll();

$selected_cat = isset($_GET['category']) ? (int)$_GET['category'] : 0;

if ($selected_cat > 0) {
    $stmt_srv = $pdo->prepare("
        SELECT s.id, s.title, s.description, s.price, s.image_path, sc.name AS category_name, s.category_id
        FROM services s
        INNER JOIN service_categories sc ON s.category_id = sc.id
        WHERE s.category_id = :category_id
        ORDER BY s.id ASC
    ");
    $stmt_srv->execute([':category_id' => $selected_cat]);
} else {
    $stmt_srv = $pdo->query("
        SELECT s.id, s.title, s.description, s.price, s.image_path, sc.name AS category_name, s.category_id
        FROM services s
        INNER JOIN service_categories sc ON s.category_id = sc.id
        ORDER BY s.id ASC
    ");
}
$services = $stmt_srv->fetchAll();
?>

<!-- Hero Banner -->
<div class="row mb-5">
    <div class="col-12">
        <div class="dw-hero-banner">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <span class="badge bg-warning text-dark fw-bold px-3 py-2 rounded-pill mb-3 d-inline-flex align-items-center gap-1">
                        <i class="bi bi-gear-wide-connected fs-6"></i> Hardware & Software Deployment
                    </span>
                    <h1 class="display-5 fw-bold mb-3 text-white">
                        Business Continuity Services
                    </h1>
                    <p class="lead text-white-50 mb-0" style="font-size: 1.1rem; line-height: 1.6;">
                        Complete local infrastructure setup, Android SMS gateway integration, and database mirroring designed to protect your enterprise during regional blackout events.
                    </p>
                </div>
                <div class="col-lg-4 d-none d-lg-block text-center">
                    <div class="p-4 bg-dark bg-opacity-40 border border-light border-opacity-25 rounded-4 backdrop-blur">
                        <i class="bi bi-box-seam text-info display-1 d-block mb-2"></i>
                        <h5 class="fw-bold text-white mb-1">Local Installation Packages</h5>
                        <p class="small text-white-50 mb-0">Loaded dynamically from MySQL</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Service Category Filter Tabs -->
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex flex-wrap align-items-center gap-2 pb-3 border-bottom">
            <span class="fw-bold text-dark me-2 small uppercase"><i class="bi bi-funnel text-teal me-1" style="color:#0f766e;"></i> Filter Category:</span>
            <a href="services.php" class="btn btn-sm <?php echo $selected_cat === 0 ? 'btn-dw-primary' : 'btn-outline-secondary'; ?> rounded-pill px-3">
                All Services
            </a>
            <?php foreach ($service_categories as $cat): ?>
                <a href="services.php?category=<?php echo $cat['id']; ?>" class="btn btn-sm <?php echo $selected_cat === (int)$cat['id'] ? 'btn-dw-primary' : 'btn-outline-secondary'; ?> rounded-pill px-3">
                    <?php echo sanitize($cat['name']); ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Dynamic Services Grid -->
<div class="row g-4 mb-5">
    <?php if (count($services) > 0): ?>
        <?php foreach ($services as $srv): ?>
            <div class="col-md-6 col-lg-6">
                <div class="dw-card h-100 d-flex flex-column overflow-hidden shadow-sm">
                    <!-- Service Image Container -->
                    <div class="position-relative bg-dark text-center overflow-hidden" style="height: 220px;">
                        <?php if (!empty($srv['image_path']) && file_exists(__DIR__ . '/' . $srv['image_path'])): ?>
                            <img src="<?php echo BASE_URL . '/' . sanitize($srv['image_path']); ?>" alt="<?php echo sanitize($srv['title']); ?>" class="w-100 h-100" style="object-fit: cover; object-position: center;">
                        <?php else: ?>
                            <div class="d-flex align-items-center justify-content-center h-100 bg-secondary bg-opacity-20 text-white-50">
                                <i class="bi bi-hdd-network display-2"></i>
                            </div>
                        <?php endif; ?>
                        <span class="position-absolute top-0 end-0 m-3 badge bg-teal px-3 py-2 fs-6 shadow fw-bold" style="background-color:#0f766e;">
                            <?php echo format_currency($srv['price']); ?>
                        </span>
                    </div>

                    <div class="card-body p-4 d-flex flex-column flex-grow-1">
                        <div class="mb-2">
                            <span class="badge bg-light text-dark border me-2">
                                <i class="bi bi-tag-fill me-1 text-teal" style="color:#0f766e;"></i> <?php echo sanitize($srv['category_name']); ?>
                            </span>
                        </div>

                        <h4 class="fw-bold text-dark mb-2"><?php echo sanitize($srv['title']); ?></h4>
                        <p class="text-muted small flex-grow-1" style="line-height: 1.6;">
                            <?php echo sanitize($srv['description']); ?>
                        </p>

                        <div class="pt-3 mt-3 border-top d-flex justify-content-between align-items-center">
                            <span class="small text-muted fw-semibold">
                                <i class="bi bi-check2-circle text-success me-1"></i> Local Setup Included
                            </span>
                            <a href="contact.php?service_id=<?php echo $srv['id']; ?>" class="btn btn-dw-outline btn-sm">
                                <i class="bi bi-send me-1"></i> Inquire Package
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="col-12">
            <div class="alert alert-info text-center p-5">
                <i class="bi bi-info-circle fs-1 d-block mb-3"></i>
                <h5>No Services Found in Selected Category</h5>
                <p class="mb-0">Please select another category or return to the main catalog.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
