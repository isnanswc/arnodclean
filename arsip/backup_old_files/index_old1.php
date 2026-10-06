<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

// Prevent Caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require_once 'db.php';
require_once 'includes/tracker.php';

// Fetch Data
// 1. Services
$services = $pdo->query("SELECT * FROM services ORDER BY created_at DESC")->fetchAll();

// 2. Testimonials
$testimonials = $pdo->query("SELECT * FROM testimonials ORDER BY created_at DESC")->fetchAll();

// 2b. Benefits
$benefits = $pdo->query("SELECT * FROM benefits ORDER BY created_at ASC")->fetchAll();


// 3. Locations
$locations_db = $pdo->query("SELECT * FROM locations")->fetchAll();

// 4. Contact Info
$contacts_db = $pdo->query("SELECT * FROM contact_info")->fetchAll();
$contact = [];
foreach ($contacts_db as $c) {
    $contact[$c['contact_key']] = $c['contact_value'];
}

// 5. Site Settings (Hero & WA)
try {
    $settings_db = $pdo->query("SELECT * FROM site_settings")->fetchAll();
    $setting = [];
    foreach ($settings_db as $s) {
        $setting[$s['setting_key']] = $s['setting_value'];
    }
} catch (Exception $e) {
    // Fallback if table doesn't exist yet
    $setting = [
        'hero_title' => 'Sofa bersih, higienis, dan wangi tanpa repot.',
        'hero_description' => 'Kami membersihkan sofa Anda dengan bahan aman, alat modern, dan teknisi terlatih. Layanan panggilan ke rumah, hasil maksimal, garansi kepuasan.',
        'hero_btn_primary' => 'Lihat Layanan',
        'hero_btn_secondary' => 'Konsultasi Gratis',
        'wa_template' => 'Halo Arno D Clean, saya ingin tanya layanan & harga.'
    ];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <?php
        $siteTitle = $setting['site_meta_title'] ?? 'Arno D Clean - Jasa Cuci Kasur & Sofa Tangerang';
        $siteDesc = $setting['site_meta_description'] ?? 'Jasa cuci sofa, kasur, dan karpet profesional di Tangerang. Bersih, wangi, dan bebas tungau.';
        $baseUrl = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
    ?>
    <title><?php echo htmlspecialchars($siteTitle); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($siteDesc); ?>">
    <meta name="keywords" content="jasa cuci sofa, cuci sofa, cleaning service, cuci springbed, cuci karpet, kebersihan sofa, Jasa cuci sofa, cuci sofa Citra Raya, cuci sofa Pasar Kemis, cuci sofa Kota Bumi, cuci sofa Karawaci, cuci sofa Perum, cuci sofa Panongan, cuci sofa Poris, cuci sofa Cipondoh, cuci sofa BSD, cuci sofa Serpong, layanan cuci sofa profesional, pembersih sofa, cuci sofa terdekat, ahli cuci sofa, cuci spring bed, cuci karpet, cuci jok mobil, cuci sofa cepat, cuci sofa murah, jasa kebersihan sofa, cuci sofa panggil, cuci sofa rumah" />
    <link rel="canonical" href="<?php echo $baseUrl; ?>/" />

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo $baseUrl; ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($siteTitle); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($siteDesc); ?>">
    <meta property="og:image" content="<?php echo $baseUrl; ?>/img/og-image.jpg">

    <!-- Favicon -->
    <?php $favicon = !empty($setting['site_icon']) ? $setting['site_icon'] : 'img/l2.png'; ?>
    <link rel="icon" href="<?php echo htmlspecialchars($favicon); ?>" />

    <!-- Preload Hero Image (LCP Optimization) -->
    <?php if(!empty($setting['hero_bg_image'])): ?>
    <link rel="preload" as="image" href="<?php echo htmlspecialchars($setting['hero_bg_image']); ?>" />
    <?php endif; ?>

    <!-- Schema.org (LocalBusiness) -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "LocalBusiness",
      "name": "Arno D Clean",
      "image": "<?php echo $baseUrl; ?>/img/l2.png",
      "description": "<?php echo htmlspecialchars($siteDesc); ?>",
      "telephone": "<?php echo $contact['phone'] ?? '+6281280666659'; ?>",
      "address": {
        "@type": "PostalAddress",
        "streetAddress": "<?php echo $contact['address'] ?? 'Tangerang'; ?>",
        "addressLocality": "Tangerang",
        "addressRegion": "Banten",
        "addressCountry": "ID"
      },
      "url": "<?php echo $baseUrl; ?>",
      "priceRange": "$$",
      "openingHoursSpecification": {
        "@type": "OpeningHoursSpecification",
        "dayOfWeek": [
          "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"
        ],
        "opens": "00:00",
        "closes": "23:59"
      }
    }
    </script>

    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=AW-17621219370"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', 'AW-17621219370');
        
        // Conversion Event
        gtag('event', 'conversion', {'send_to': 'AW-17621219370/o7jCCI-q5KYbEKrwudJB'});
    </script>
    
    <!-- CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster/dist/MarkerCluster.Default.css" />

  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet" />
  <link href="https://unpkg.com/aos@2.3.4/dist/aos.css" rel="stylesheet" />

  <style>
    :root{
      --primary: #2C73D2;
      --secondary: #84DCCF;
      --accent: #4BB3FD;
      --ink: #1f2a37;
      --muted: #6b7280;
      --bg: #f6f9fc;
      --white: #ffffff;
      --card: #ffffff;
      --shadow: 0 10px 30px rgba(23,43,77,0.08);
      --radius: 14px;
    }
    html { scroll-behavior: smooth; }
    body {
      font-family: 'Poppins', sans-serif;
      color: var(--ink);
      background: var(--bg);
      line-height: 1.6;
      padding-top: 80px; /* Offset for sticky nav */
    }

    /* Navbar */
    .navbar {
      backdrop-filter: saturate(180%) blur(10px);
      background: rgba(255,255,255,0.85) !important;
      box-shadow: var(--shadow);
    }
    .navbar-brand img { max-height: 56px; width: auto; }
    @media (min-width: 992px) { .navbar-brand img { max-height: 72px; } }
    .navbar .nav-link { font-weight: 500; }

    /* Hero Parallax */
    .hero {
      position: relative;
      min-height: 92vh;
      display: grid;
      place-items: center;
      color: var(--white);
      text-align: center;
      overflow: hidden;
      background-position: center;
      background-size: cover;
      background-repeat: no-repeat;
      background-attachment: fixed;
    }
    .hero::before{
      content:"";
      position:absolute; inset:0;
      background: linear-gradient(180deg, rgba(31,42,55,0.55), rgba(31,42,55,0.25));
    }
    .hero-content { position: relative; z-index: 2; padding: 2rem; }
    .hero h1 { font-weight: 700; letter-spacing: 0.2px; }
    .hero p { max-width: 680px; margin: 0 auto; opacity: 0.95; }

    /* Sections */
    section.section { padding: clamp(60px, 8vw, 110px) 0; }
    .section-title { font-weight: 700; margin-bottom: 0.5rem; }
    .section-subtitle { color: var(--muted); max-width: 760px; margin-inline: auto; margin-bottom: 2rem; }

    /* Cards */
    .service-card, .feature-card {
      background: var(--card);
      border: 0;
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      overflow: hidden;
      transition: transform .25s ease, box-shadow .25s ease;
    }
    .service-card:hover, .feature-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 16px 40px rgba(23,43,77,0.12);
    }
    .service-card img {
      width:100%;
      height:220px;
      object-fit: cover;
    }

    /* Process timeline style */
    .step {
      background: var(--card);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      padding: 1.25rem;
      display: flex;
      gap: 14px;
      align-items: center;
    }
    .step-icon {
      width: 42px; height: 42px;
      display: grid; place-items: center;
      border-radius: 10px;
      color: var(--white);
      background: linear-gradient(135deg, var(--primary), var(--accent));
    }

    /* Testimonial */
    .testimonial { background: linear-gradient(180deg, #eaf5ff, #f6f9fc); }
    .testimonial .carousel-item { min-height: 280px; }
    .quote-card {
      background: var(--card);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      padding: 1.5rem;
    }

    /* Floating WhatsApp */
    .wa-float {
      position: fixed; right: 18px; bottom: 18px; z-index: 1080;
      width: 58px; height: 58px;
      border-radius: 50%;
      display: grid; place-items: center;
      color: #fff; background: #25D366;
      box-shadow: 0 12px 30px rgba(37,211,102,.35);
      transition: transform .2s ease;
    }
    .wa-float:hover { transform: translateY(-2px) scale(1.03); }

    /* Chat popup */
    .chat-popup {
      position: fixed; right: 18px; bottom: 86px; z-index: 1080;
      width: min(340px, calc(100vw - 36px));
      background: var(--card);
      border-radius: 16px;
      box-shadow: 0 18px 50px rgba(31,42,55,.18);
      overflow: hidden;
      opacity: 0; transform: translateY(14px) scale(.98);
      pointer-events: none;
      transition: opacity .22s ease, transform .22s ease;
    }
    .chat-popup.open { opacity: 1; transform: translateY(0) scale(1); pointer-events: auto; }
    .chat-header {
      display: flex; align-items:center; justify-content: space-between;
      gap: 12px; padding: 12px 14px; color:#fff;
      background: linear-gradient(135deg, #128C7E, #25D366);
    }
    .chat-body { padding: 14px; background: #f4f7f9; max-height: 300px; overflow: auto; }
    .chat-bubble {
      background: #fff; border-radius: 12px 12px 12px 4px;
      box-shadow: var(--shadow); padding: 10px 12px; margin-bottom: 10px;
      font-size: 0.95rem;
    }
    .chat-footer { padding: 12px; background: #fff; display: grid; gap: 8px; }
    .chat-footer textarea {
      width:100%; border:1px solid #e5e7eb; border-radius: 10px;
      padding: 10px 12px; resize: none; min-height: 54px;
    }

    /* Scroll in/out animations */
    .reveal { opacity: 0; transform: translateY(18px); transition: opacity .6s ease, transform .6s ease; }
    .reveal.in { opacity: 1; transform: translateY(0); }
    .reveal.out { opacity: 0.1; transform: translateY(12px) scale(.995); }

    /* Footer */
    .footer { background: #0f172a; color: #cbd5e1; }
    .footer a { color: #e2e8f0; text-decoration: none; }
    .footer a:hover { color: #93c5fd; }

    /* Helpers */
    .btn-primary {
      background: linear-gradient(135deg, var(--primary), var(--accent));
      border: 0;
      box-shadow: 0 10px 24px rgba(76,141,235,.25);
    }
    .btn-outline-primary { border-color: var(--primary); color: var(--primary); }
    .badge-soft {
      background: #e6f1ff; color: var(--primary);
      font-weight: 600; border-radius: 999px; padding: 6px 12px;
    }
    
    /* Responsive */
    html, body { max-width: 100%; overflow-x: hidden; }
    img { max-width: 100%; height: auto; display: block; }
    @media (max-width: 767.98px) {
      .hero { background-attachment: scroll !important; min-height: 70vh; }
      .service-card img { height: auto; }
      .navbar .nav-link { padding: 0.75rem 1rem; }
    }
    #map { height: 400px; width: 100%; border-radius: var(--radius); box-shadow: var(--shadow); }
    @media (min-width: 992px) { #map { height: 500px; } }
    .legend { background: white; padding: 6px 8px; font-size: 14px; }
  </style>
  
  <style>
    /* Preloader */
    #preloader {
        position: fixed; inset: 0; z-index: 9999;
        background: #ffffff;
        display: flex; justify-content: center; align-items: center;
        transition: opacity 0.5s ease;
    }
    .spinner {
        width: 50px; height: 50px;
        border: 5px solid #f3f3f3;
        border-top: 5px solid var(--primary);
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
  </style>

  <script>
    // Preloader Logic
    function hideLoader() {
        const loader = document.getElementById('preloader');
        if(loader && loader.style.display !== 'none') {
            loader.style.opacity = '0';
            setTimeout(() => loader.style.display = 'none', 500);
        }
    }
    window.addEventListener('load', hideLoader);
    setTimeout(hideLoader, 3000); // Force hide after 3s (safety)
  </script>
  <!-- Ahrefs Web Analytics -->
  <script src="https://analytics.ahrefs.com/analytics.js" data-key="OHuYixrUYifEf8KuUmecHA" async></script>
</head>
<body data-bs-spy="scroll" data-bs-target="#mainNav" data-bs-offset="80" tabindex="0">

  <!-- Preloader HTML -->
  <div id="preloader"><div class="spinner"></div></div>

  <!-- Development Mode Error Check -->
  <?php 
  if (!is_dir('img')) {
      echo '<div class="alert alert-danger fixed-top m-3 shadow-lg" role="alert" style="z-index: 10000;">
              <h4 class="alert-heading"><i class="fa-solid fa-triangle-exclamation"></i> Error: Folder Gambar Tidak Ditemukan!</h4>
              <p>Folder <code>img/</code> belum ada di hosting Anda. Website tidak dapat menampilkan gambar (Logo, Background, Icon).</p>
              <hr>
              <p class="mb-0"><strong>Solusi:</strong> Silahkan buat folder bernama <code>img</code> dan upload seluruh gambar aset (b2.png, l2.png, r1.png, dll) ke dalamnya.</p>
            </div>';
  }
  ?>

  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg fixed-top" id="mainNav">
    <div class="container">
      <a class="navbar-brand d-flex align-items-center" href="#">
        <?php if(!empty($setting['site_logo'])): ?>
            <img src="<?php echo htmlspecialchars($setting['site_logo']); ?>" width="150" height="50" alt="Arno D Clean - Jasa Cuci Sofa & Springbed Tangerang">
        <?php else: ?>
            <i class="fa-solid fa-couch text-primary me-2"></i> Arno D Clean
        <?php endif; ?>
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navContent">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div id="navContent" class="collapse navbar-collapse">
        <ul class="navbar-nav ms-auto gap-lg-2">
          <li class="nav-item"><a class="nav-link" href="#home">Beranda</a></li>
          <li class="nav-item"><a class="nav-link" href="#services">Produk & Jasa</a></li>
          <li class="nav-item"><a class="nav-link" href="#process">Alur Hubungi</a></li>
          <li class="nav-item"><a class="nav-link" href="#testimonials">Testimoni</a></li>
          <li class="nav-item"><a class="nav-link" href="#benefits">Keunggulan</a></li>
          <li class="nav-item"><a class="nav-link" href="#location">Lokasi</a></li>
          <li class="nav-item"><a class="nav-link" href="blog.php">Artikel</a></li>
          <li class="nav-item"><a class="nav-link" href="#contact">Kontak</a></li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Hero -->
  <?php 
    $hero_bg = !empty($setting['hero_bg_image']) ? $setting['hero_bg_image'] : '';
    $hero_style = !empty($hero_bg) ? "background-image: url('".htmlspecialchars($hero_bg)."');" : ""; 
  ?>
  <header id="home" class="hero" style="<?php echo $hero_style; ?>">
    <div class="hero-content container">
      <span class="badge-soft mb-3">Jasa Cuci Sofa Profesional</span>
      <br><br>
      <h1 class="display-5 mb-3" data-aos="fade-up"><?php echo htmlspecialchars($setting['hero_title']); ?></h1>
      <p class="mb-4" data-aos="fade-up" data-aos-delay="100">
        <?php echo htmlspecialchars($setting['hero_description']); ?>
      </p>
      <div class="d-flex justify-content-center gap-3" data-aos="fade-up" data-aos-delay="200">
        <a href="#services" class="btn btn-primary btn-lg"><i class="fa-solid fa-bolt me-2"></i><?php echo htmlspecialchars($setting['hero_btn_primary']); ?></a>
        <a href="#contact" class="btn btn-success btn-lg"><?php echo htmlspecialchars($setting['hero_btn_secondary']); ?></a>
      </div>
    </div>
  </header>
  
  <main>

  <!-- Services (Dynamic) -->
  <section id="services" class="section">
    <div class="container reveal">
      <div class="text-center mb-4">
        <h2 class="section-title">Produk & Jasa</h2>
        <p class="section-subtitle">Paket lengkap untuk kebutuhan rumah, apartemen, dan kantor.</p>
      </div>
      <div class="row g-4">
        <?php if(!empty($services)): ?>
          <?php foreach ($services as $service): ?>
          <div class="col-md-6 col-lg-4" data-aos="zoom-in">
            <div class="card service-card h-100">
              <?php 
                $img = !empty($service['image_path']) ? $service['image_path'] : 'img/placeholder.jpg'; 
                // Ensure image path is not broken if relative
                if ($img !== 'img/placeholder.jpg' && !file_exists($img)) {
                     // Check if it might be missing
                     // Keep as is for now, but user should ensure uploads exist
                }
              ?>
              <img src="<?php echo htmlspecialchars($img); ?>" loading="lazy" width="600" height="400" alt="Layanan <?php echo htmlspecialchars($service['title']); ?> - Arno D Clean" />
              <div class="card-body">
                <h3 class="h5 card-title"><?php echo htmlspecialchars($service['title']); ?></h3>
                <p class="card-text"><?php echo htmlspecialchars($service['description'] ?? ''); ?></p>
                <div class="d-flex align-items-center justify-content-between">
                  <span class="fw-semibold text-primary">Mulai dari Rp.<?php echo htmlspecialchars(number_format($service['price_start'] ?? 0, 0, '.', ',')); ?></span>
                  <button type="button" class="btn btn-sm btn-primary" aria-label="Pesan Layanan <?php echo htmlspecialchars($service['title']); ?>" onclick="openBookingModal(<?php echo $service['id']; ?>, '<?php echo addslashes($service['title']); ?>')">Pesan</button>
                </div>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="col-12 text-center text-muted">Belum ada layanan yang ditambahkan dari Admin Panel.</div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- Process (Static) -->
  <section id="process" class="section">
    <div class="container reveal">
      <div class="text-center mb-4">
        <h2 class="section-title">Alur proses pemesanan</h2>
        <p class="section-subtitle">Sederhana, transparan, dan cepat.</p>
      </div>
      <div class="row g-3 g-lg-4">
        <div class="col-md-6 col-lg-3" data-aos="fade-up">
          <div class="step h-100">
            <div class="step-icon"><i class="fa-solid fa-phone"></i></div>
            <div>
              <div class="fw-semibold">Konsultasi</div>
              <div class="text-muted small">Ceritakan kebutuhan Anda.</div>
            </div>
          </div>
        </div>
        <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="100">
          <div class="step h-100">
            <div class="step-icon"><i class="fa-solid fa-calendar-check"></i></div>
            <div>
              <div class="fw-semibold">Jadwalkan</div>
              <div class="text-muted small">Tentukan waktu & lokasi.</div>
            </div>
          </div>
        </div>
        <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="200">
          <div class="step h-100">
            <div class="step-icon"><i class="fa-solid fa-spray-can-sparkles"></i></div>
            <div>
              <div class="fw-semibold">Pengerjaan</div>
              <div class="text-muted small">Teknisi datang ke lokasi Anda.</div>
            </div>
          </div>
        </div>
        <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="300">
          <div class="step h-100">
            <div class="step-icon"><i class="fa-solid fa-thumbs-up"></i></div>
            <div>
              <div class="fw-semibold">Quality Check</div>
              <div class="text-muted small">Review hasil & garansi.</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Testimonials (Dynamic) -->
  <section id="testimonials" class="section testimonial">
    <div class="container reveal">
      <div class="text-center mb-4">
        <h2 class="section-title">Apa kata pelanggan</h2>
        <p class="section-subtitle">Ulasan asli dari pelanggan kami.</p>
      </div>

      <div id="testiCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="5000" data-aos="fade-up">
        <div class="carousel-inner">
          <?php if(!empty($testimonials)): ?>
            <?php foreach ($testimonials as $index => $testi): ?>
            <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
              <div class="row justify-content-center">
                <div class="col-lg-8">
                  <div class="quote-card">
                    <div class="d-flex align-items-center gap-3 mb-2">
                       <?php if(!empty($testi['image_path'])): ?>
                        <img src="<?php echo htmlspecialchars($testi['image_path']); ?>" alt="Testimoni Pelanggan - <?php echo htmlspecialchars($testi['name']); ?>" class="rounded-circle" width="56" height="56" loading="lazy">
                      <?php else: ?>
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($testi['name']); ?>&background=random" alt="Avatar <?php echo htmlspecialchars($testi['name']); ?>" class="rounded-circle" width="56" height="56">
                      <?php endif; ?>
                      <div>
                        <div class="fw-semibold"><?php echo htmlspecialchars($testi['name']); ?></div>
                        <div class="small text-muted"><?php echo htmlspecialchars($testi['location'] ?? ''); ?></div>
                      </div>
                      <div class="ms-auto text-warning"><i class="fa-solid fa-star"></i> <?php echo $testi['rating'] ?? '5.0'; ?></div>
                    </div>
                    <p class="mb-0"><?php echo htmlspecialchars($testi['content']); ?></p>
                  </div>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          <?php else: ?>
             <div class="carousel-item active">
               <div class="text-center p-4">Belum ada testimoni.</div>
             </div>
          <?php endif; ?>
        </div>
        <button class="carousel-control-prev" type="button" data-bs-target="#testiCarousel" data-bs-slide="prev" aria-label="Previous"><span class="carousel-control-prev-icon"></span></button>
        <button class="carousel-control-next" type="button" data-bs-target="#testiCarousel" data-bs-slide="next" aria-label="Next"><span class="carousel-control-next-icon"></span></button>
      </div>
    </div>
  </section>

  <!-- Benefits (Static) -->
  <section id="benefits" class="section">
    <div class="container reveal">
      <div class="text-center mb-4">
        <h2 class="section-title">Keunggulan & kelebihan</h2>
        <p class="section-subtitle">Kami fokus pada kualitas dan kepuasan.</p>
      </div>
      <div class="row g-4">
        <?php if(!empty($benefits)): ?>
            <?php foreach($benefits as $index => $benefit): ?>
            <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="<?php echo $index * 100; ?>">
              <div class="feature-card h-100">
                <?php if(!empty($benefit['image_path'])): ?>
                    <?php if(isset($benefit['media_type']) && $benefit['media_type'] === 'video'): ?>
                        <video src="<?php echo htmlspecialchars($benefit['image_path']); ?>" 
                               autoplay loop muted playsinline 
                               class="w-100 rounded-top"></video>
                    <?php else: ?>
                        <img src="<?php echo htmlspecialchars($benefit['image_path']); ?>" loading="lazy" width="600" height="400" alt="Keunggulan - <?php echo htmlspecialchars($benefit['title']); ?> Arno D Clean" />
                    <?php endif; ?>
                <?php endif; ?>
                <div class="p-3">
                  <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="fa-solid fa-check-circle text-primary"></i> <h3 class="h5 mb-0"><?php echo htmlspecialchars($benefit['title']); ?></h3>
                  </div>
                  <p class="mb-0"><?php echo htmlspecialchars($benefit['description']); ?></p>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <!-- Fallback Static if DB empty -->
            <div class="col-md-6 col-lg-4" data-aos="fade-up">
              <div class="feature-card h-100">
                <img src="img/r1.png" loading="lazy" width="600" height="400" alt="Ilustrasi Bahan Pembersih Aman - Eco Friendly" />
                <div class="p-3">
                  <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="fa-solid fa-shield-heart text-primary"></i> <h3 class="h5 mb-0">Bahan Aman</h3>
                  </div>
                  <p class="mb-0">Ramah lingkungan & aman bagi hewan peliharaan.</p>
                </div>
              </div>
            </div>
            <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="100">
              <div class="feature-card h-100">
                <img src="img/r2.png" loading="lazy" width="600" height="400" alt="Foto Teknisi Terlatih Arno D Clean" />
                <div class="p-3">
                  <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="fa-solid fa-user-check text-primary"></i> <h3 class="h5 mb-0">Teknisi Terlatih</h3>
                  </div>
                  <p class="mb-0">Profesional, sopan, dan berpengalaman.</p>
                </div>
              </div>
            </div>
            <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="200">
              <div class="feature-card h-100">
                <img src="img/r3.png" loading="lazy" width="600" height="400" alt="Badge Garansi Kepuasan Pelanggan" />
                <div class="p-3">
                  <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="fa-solid fa-award text-primary"></i> <h3 class="h5 mb-0">Garansi Kepuasan</h3>
                  </div>
                  <p class="mb-0">Layanan ulang jika hasil kurang maksimal.</p>
                </div>
              </div>
            </div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- Locations (Dynamic Map) -->
  <section id="location" class="section">
    <div class="container reveal">
      <div class="text-center mb-4">
        <h2 class="section-title">Lokasi</h2>
        <p class="section-subtitle">Kami tersedia di kota anda.</p>
      </div>
      <div class="row g-4">
          <div id="map"></div>
      </div>
    </div>
  </section>

  <!-- Latest Articles -->
  <section id="blog" class="section bg-light">
    <div class="container reveal">
      <div class="text-center mb-5" data-aos="fade-up">
        <h2 class="section-title">Artikel Terbaru</h2>
        <p class="section-subtitle">Tips dan informasi seputar kebersihan dan perawatan furniture.</p>
      </div>

      <div class="row g-4">
        <?php 
           // Fetch 4 latest articles
           $latest_articles = $pdo->query("SELECT * FROM articles WHERE status = 'published' ORDER BY created_at DESC LIMIT 4")->fetchAll();
           if(count($latest_articles) > 0):
               foreach($latest_articles as $art):
        ?>
        <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="100">
          <div class="card h-100 border-0 shadow-sm service-card">
            <div class="ratio ratio-4x3 overflow-hidden">
                <img src="<?php echo !empty($art['image_path']) ? $art['image_path'] : 'img/placeholder.jpg'; ?>" 
                     class="card-img-top object-fit-cover transition-scale" width="600" height="450" loading="lazy" alt="Artikel Blog: <?php echo htmlspecialchars($art['title']); ?>">
            </div>
            <div class="card-body d-flex flex-column p-4">
              <small class="text-muted mb-2"><i class="fa-regular fa-calendar me-1"></i> <?php echo date('d M Y', strtotime($art['created_at'])); ?></small>
              <h3 class="h5 card-title fw-bold mb-3">
                  <a href="blog/<?php echo $art['slug']; ?>" class="text-decoration-none text-dark stretched-link">
                      <?php echo htmlspecialchars($art['title']); ?>
                  </a>
              </h3>
              <div class="mt-auto">
                  <span class="text-primary fw-medium text-sm">Baca Selengkapnya <i class="fa-solid fa-arrow-right ms-1"></i></span>
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; else: ?>
        <div class="col-12 text-center py-5">
            <p class="text-muted">Belum ada artikel yang diterbitkan.</p>
        </div>
        <?php endif; ?>
      </div>
      
      <div class="text-center mt-5">
          <a href="blog.php" class="btn btn-outline-primary px-4 rounded-pill">Lihat Semua Artikel</a>
      </div>
    </div>
  </section>

  <!-- Contact (Dynamic) -->
  <section id="contact" class="section">
    <div class="container reveal">
      <div class="row g-4 align-items-center">
        <div class="col-lg-6" data-aos="fade-right">
          <h2 class="section-title">Hubungi kami</h2>
          <p class="section-subtitle mb-3">Siap jadwalkan pembersihan? Tim kami respons cepat.</p>

          <div class="d-flex align-items-start gap-3 mb-2">
            <i class="fa-solid fa-phone text-primary fs-5"></i>
            <div>
              <div class="fw-semibold">Telepon</div>
              <a href="tel:+<?php echo $contact['phone'] ?? ''; ?>"><?php echo $contact['phone'] ?? '-'; ?></a>
            </div>
          </div>

          <div class="d-flex align-items-start gap-3 mb-2">
            <i class="fa-solid fa-envelope text-primary fs-5"></i>
            <div>
              <div class="fw-semibold">Email</div>
              <a href="mailto:<?php echo $contact['email'] ?? ''; ?>"><?php echo $contact['email'] ?? '-'; ?></a>
            </div>
          </div>

          <div class="d-flex align-items-start gap-3">
            <i class="fa-solid fa-location-dot text-primary fs-5"></i>
            <div>
              <div class="fw-semibold">Alamat</div>
              <div><?php echo $contact['address'] ?? '-'; ?></div>
            </div>
          </div>

            <div class="mt-4 d-flex gap-2">
              <a class="btn btn-primary px-4" href="#services"><i class="fa-solid fa-list-check me-2"></i>Cek Layanan</a>
              <button class="btn btn-outline-primary px-4" onclick="openBookingModal()"><i class="fa-solid fa-calendar-check me-2"></i>Booking Sekarang</button>
            </div>
          </div>
          <div class="col-lg-6" data-aos="fade-left">
            <div class="card border-0 shadow-lg p-4 rounded-4">
              <h3 class="h4 fw-bold mb-4">Kirim Pesan Cepat</h3>
                <div class="row g-3">
                  <!-- Honeypot field (Anti-Bot) -->
                  <div style="display:none !important;">
                    <label>If you are human, leave this empty</label>
                    <input type="text" name="honeypot" tabindex="-1" autocomplete="off">
                  </div>
                  <div class="col-md-6">
                    <label for="inpName" class="form-label small fw-semibold">Nama Lengkap</label>
                    <input type="text" id="inpName" name="name" class="form-control" placeholder="Nama Anda" required>
                  </div>
                  <div class="col-md-6">
                    <label for="inpWa" class="form-label small fw-semibold">WhatsApp</label>
                    <input type="tel" id="inpWa" name="whatsapp" class="form-control" placeholder="Contoh: 0812..." required>
                  </div>
                  <div class="col-12">
                    <label for="inpService" class="form-label small fw-semibold">Pilih Layanan</label>
                    <select id="inpService" name="service_id" class="form-select">
                      <option value="">Umum / Tanya-tanya</option>
                      <?php foreach($services as $s): ?>
                        <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['title']); ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-12">
                    <label for="inpMsg" class="form-label small fw-semibold">Pesan / Alamat</label>
                    <textarea id="inpMsg" name="message" class="form-control" rows="3" placeholder="Apa yang bisa kami bantu?"></textarea>
                  </div>
                  <div class="col-12">
                    <button type="submit" class="btn btn-primary btn-lg w-100 mt-2">
                      <span class="btn-text">Kirim Sekarang</span>
                      <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                    </button>
                  </div>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </section>

  </main>

  <!-- Footer -->
  <footer class="footer py-5">
    <div class="container">
      <div class="row g-4">
        <div class="col-md-6 col-lg-4">
          <h5 class="text-white">Arno D Clean</h5>
          <p class="mb-2">Jasa cuci sofa profesional, aman, dan bergaransi.</p>
          <small> <span id="year"></span> Arno D Clean. All rights reserved.</small>
        </div>
        <div class="col-md-6 col-lg-4">
          <h6 class="text-white">Navigasi</h6>
          <ul class="list-unstyled">
             <li><a href="#services">Layanan</a></li>
             <li><a href="#testimonials">Testimoni</a></li>
             <li><a href="#contact">Kontak</a></li>
             
          </ul>
        </div>
        <div class="col-md-6 col-lg-4">
          <h6 class="text-white">Jam operasional</h6>
          <p class="mb-1">Senin-Minggu: 24 Jam</p>
        </div>
      </div>
    </div>
  </footer>

  <!-- Floating WA & Chat -->
  <button class="wa-float" id="waFloat" aria-label="Chat WhatsApp"><i class="fa-brands fa-whatsapp fs-3"></i></button>
  <div class="chat-popup" id="chatPopup">
    <div class="chat-header">
      <div class="d-flex align-items-center gap-2">
        <img src="https://ui-avatars.com/api/?name=Admin&background=random" class="rounded-circle" width="30" alt="Admin Support Avatar">
        <div><div class="fw-semibold">Admin</div><small>Online</small></div>
      </div>
      <button class="btn btn-sm btn-light" id="closeChat" aria-label="Close Chat"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="chat-body">
      <div class="chat-bubble">Halo! Ada yang bisa kami bantu? ??</div>
    </div>
    <div class="chat-footer">
      <textarea id="chatText" placeholder="Tulis pesan..."></textarea>
      <button class="btn btn-success w-100" id="sendWA"><i class="fa-brands fa-whatsapp me-2"></i>Kirim</button>
    </div>
  </div>

  <!-- Universal Booking Modal -->
  <div class="modal fade" id="bookingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow-lg rounded-4">
        <div class="modal-header bg-primary text-white border-0 py-3">
          <h5 class="modal-title fw-bold"><i class="fa-solid fa-calendar-check me-2"></i>Booking Layanan</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="modalLeadForm" class="lead-form">
          <div class="modal-body p-4">
            <!-- Honeypot field (Anti-Bot) -->
            <div style="display:none !important;">
                <label>If you are human, leave this empty</label>
                <input type="text" name="honeypot" tabindex="-1" autocomplete="off">
            </div>
            <p class="text-muted small mb-4">Lengkapi data berikut, tim kami akan segera menghubungi Anda melalui WhatsApp.</p>
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label small fw-semibold">Nama Lengkap</label>
                <input type="text" name="name" class="form-control bg-light border-0" placeholder="Nama Anda" required>
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-semibold">WhatsApp</label>
                <input type="tel" name="whatsapp" class="form-control bg-light border-0" placeholder="0812..." required>
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Email (Opsional)</label>
                <input type="email" name="email" class="form-control bg-light border-0" placeholder="email@contoh.com">
              </div>
              <div class="col-12">
                <label class="form-label small fw-semibold">Layanan</label>
                <select name="service_id" id="modal_service_id" class="form-select bg-light border-0">
                  <option value="">Umum / Lainnya</option>
                  <?php foreach($services as $s): ?>
                    <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['title']); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label small fw-semibold">Pesan Khusus / Alamat</label>
                <textarea name="message" class="form-control bg-light border-0" rows="3" placeholder="Tuliskan detail permintaan Anda..."></textarea>
              </div>
            </div>
          </div>
          <div class="modal-footer border-0 p-4 pt-0">
            <button type="submit" class="btn btn-primary btn-lg w-100 rounded-pill">
              <span class="btn-text">Kirim & Pesan Sekarang</span>
              <span class="spinner-border spinner-border-sm d-none" role="status"></span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
  <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
  <script src="https://unpkg.com/leaflet.markercluster/dist/leaflet.markercluster.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <script>
    AOS.init({ duration: 700, once: false, offset: 80 });
    document.getElementById('year').textContent = new Date().getFullYear();

    // CRM Lead Form Handle
    function openBookingModal(serviceId = '', serviceTitle = '') {
        if(serviceId) {
            document.getElementById('modal_service_id').value = serviceId;
        }
        const modal = new bootstrap.Modal(document.getElementById('bookingModal'));
        modal.show();
    }

    // Chat Logic
    const waBtn = document.getElementById('waFloat');
    const chat = document.getElementById('chatPopup');
    const closeBtn = document.getElementById('closeChat');
    const toggle = (o) => chat.classList.toggle('open', o);
    if (waBtn) waBtn.onclick = () => toggle();
    if (closeBtn) closeBtn.onclick = () => toggle(false);
    
    const sendWaBtn = document.getElementById('sendWA');
    if (sendWaBtn) {
        sendWaBtn.onclick = () => {
            const text = document.getElementById('chatText').value || <?php echo json_encode($setting['wa_template_general'] ?? 'Halo Admin, saya ingin bertanya seputar layanan Anda.'); ?>;
            trackWaClick('floating_widget', null, text);
        };
    }

    // Map Logic
    var locations = <?php 
        $loc_array = [];
        foreach($locations_db as $loc) {
            $loc_array[] = [
                'name' => $loc['name'],
                'coords' => [(float)$loc['latitude'], (float)$loc['longitude']],
                'area' => $loc['address']
            ];
        }
        echo json_encode($loc_array); 
    ?>;

    if (locations.length === 0) {
        locations = [{name: 'BSD City', coords: [-6.2976,106.6843], area: 'Tangerang Selatan'}];
    }

    var map = L.map('map', { scrollWheelZoom: false }).setView(locations[0].coords, 10);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(map);
    
    function iconFor(area){
        var c = 'blue';
        if(area.includes('Kabupaten')) c = 'green';
        if(area.includes('Selatan')) c = 'orange';
        if(area.includes('Jakarta')) c = 'red';
        return L.divIcon({className: 'custom-marker', html: `<div style="background:${c};width:14px;height:14px;border-radius:7px;border:2px solid white;"></div>`, iconSize: [18,18]});
    }

    var markers = L.markerClusterGroup();
    locations.forEach(loc => {
        var m = L.marker(loc.coords, {icon: iconFor(loc.area)});
        m.bindPopup(`<b>${loc.name}</b><br>${loc.area}`);
        markers.addLayer(m);
    });
    map.addLayer(markers);
    if(locations.length > 0) map.fitBounds(markers.getBounds());
  </script>
  <?php require_once 'includes/wa_tracker_script.php'; ?>
</body>
</html>
