<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Arno D Clean</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admin.css">
    <!-- DateRangePicker CSS (Global because used in Dashboard & Reporting) -->
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
    
    <?php
    if (!isset($settings) && isset($pdo)) {
        $settings = $pdo->query("SELECT setting_key, setting_value FROM site_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    // CSRF Token Generation
    require_once __DIR__ . '/csrf.php'; 
    $csrfToken = generateCsrfToken();
    
    $admIcon = !empty($settings['site_icon']) ? '../' . $settings['site_icon'] : '../img/l2.png';
    $admLogo = !empty($settings['site_logo']) ? '../' . $settings['site_logo'] : '';
    ?>
    <link rel="icon" href="<?php echo htmlspecialchars($admIcon); ?>">
    <meta name="csrf-token" content="<?php echo $csrfToken; ?>">

    <!-- Core JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $.ajaxSetup({
            headers: {
                'X-CSRF-Token': $('meta[name="csrf-token"]').attr('content')
            }
        });
    </script>
    
    <!-- Analytics & UI -->

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

<!-- Mobile Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<!-- Mobile Header -->
<div class="mobile-header d-md-none justify-content-between">
    <div class="d-flex align-items-center">
        <button class="btn-toggle me-3" onclick="toggleSidebar()">
            <i class="fa-solid fa-bars"></i>
        </button>
        <span class="fs-5 fw-bold text-primary">Arno D Clean</span>
    </div>
    <div class="dropdown">
        <a href="#" class="d-flex align-items-center link-dark text-decoration-none" data-bs-toggle="dropdown">
             <img src="https://ui-avatars.com/api/?name=<?php echo $_SESSION['admin_username'] ?? 'Admin'; ?>&background=random" width="32" height="32" class="rounded-circle">
        </a>
        <ul class="dropdown-menu dropdown-menu-end shadow">
            <li><a class="dropdown-item" href="logout.php">Sign out</a></li>
        </ul>
    </div>
</div>

<div class="d-flex">
    <!-- Sidebar -->
    <nav class="sidebar d-flex flex-column p-3" id="sidebar">
        <a href="dashboard.php" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto link-dark text-decoration-none d-none d-md-flex justify-content-center w-100">
            <?php if($admLogo): ?>
                <img src="<?php echo htmlspecialchars($admLogo); ?>" alt="Logo" class="img-fluid" style="max-height: 60px; width: auto; max-width: 100%;">
            <?php else: ?>
                <i class="fa-solid fa-couch text-primary fs-4 me-2"></i>
                <span class="fs-4 fw-bold text-primary">Arno D Clean</span>
            <?php endif; ?>
        </a>
        <hr class="d-none d-md-block">
        
        <ul class="nav nav-pills flex-column mb-auto">
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-gauge"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a href="reporting.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reporting.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-chart-line"></i> Reporting
                </a>
            </li>

            <div class="sidebar-heading">Content</div>
            <li>
                <a href="services.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'services.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-list-check"></i> Layanan
                </a>
            </li>
            <li>
                <a href="benefits.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'benefits.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-star"></i> Keunggulan
                </a>
            </li>
            <li>
                <a href="testimonials.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'testimonials.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-comments"></i> Testimoni
                </a>
            </li>
            <li>
                <a href="locations.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'locations.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-map-location-dot"></i> Lokasi
                </a>
            </li>

            <div class="sidebar-heading">Blog & SEO</div>
            <li>
                <a href="articles.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'articles.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-newspaper"></i> Artikel
                </a>
            </li>
            <li>
                <a href="tags.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'tags.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-tags"></i> Tags
                </a>
            </li>
            <li>
                <a href="auto_content.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'auto_content.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-robot"></i> Auto Content
                </a>
            </li>

            <div class="sidebar-heading">Business</div>
            <li>
                <a href="leads.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'leads.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-address-card"></i> Leads / CRM
                </a>
            </li>

            <div class="sidebar-heading">System</div>
            <li>
                <a href="users.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-users-gear"></i> User Manager
                </a>
            </li>
            <li>
                <a href="activity.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'activity.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-clock-rotate-left"></i> Log Aktivitas
                </a>
            </li>
            <li>
                <a href="settings.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-gears"></i> Pengaturan
                </a>
            </li>
        </ul>
        <hr>
        <div class="dropdown d-none d-md-block">
            <a href="#" class="d-flex align-items-center link-dark text-decoration-none dropdown-toggle" id="dropdownUser2" data-bs-toggle="dropdown" aria-expanded="false">
                <img src="<?php echo $_SESSION['admin_avatar'] ?? "https://ui-avatars.com/api/?name=" . urlencode($_SESSION['admin_username'] ?? 'Admin') . "&background=random"; ?>" alt="" width="32" height="32" class="rounded-circle me-2">
                <strong><?php echo $_SESSION['admin_username'] ?? 'Admin'; ?></strong>
            </a>
            <ul class="dropdown-menu text-small shadow" aria-labelledby="dropdownUser2">
                <li><a class="dropdown-item" href="logout.php">Sign out</a></li>
            </ul>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="flex-grow-1 main-content">
    
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
            document.getElementById('sidebarOverlay').classList.toggle('show');
        }
    </script>
