<?php
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);
error_reporting(0); // Production Mode
require_once 'db.php';
require_once 'includes/tracker.php';
// Fetch Settings
$settings = $pdo->query("SELECT setting_key, setting_value FROM site_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$siteIcon = !empty($settings['site_icon']) ? $settings['site_icon'] : 'img/l2.png';
$siteLogo = !empty($settings['site_logo']) ? $settings['site_logo'] : '';

$slug = $_GET['slug'] ?? '';
$stmt = $pdo->prepare("SELECT * FROM articles WHERE slug = ? AND status = 'published'");
$stmt->execute([$slug]);
$article = $stmt->fetch();

// Smart Fallback: If not found, try trimming trailing hyphens (common issue)
if (!$article && substr($slug, -1) === '-') {
    $cleanSlug = rtrim($slug, '-');
    $stmt = $pdo->prepare("SELECT * FROM articles WHERE slug = ? AND status = 'published'");
    $stmt->execute([$cleanSlug]);
    $article = $stmt->fetch();
    
    // Optional: If found via fallback, we could doing a 301 redirect to the clean URL here
    // but serving the content is the immediate fix for the 404.
}

if (!$article) {
    die("Artikel tidak ditemukan.");
}

// --- Bot Detection & View Counting ---
$is_bot = 0;
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$bot_signatures = [
    'bot', 'crawl', 'spider', 'slurp', 'google', 'bing', 'msn', 'yandex', 'baidu', 'ahrefs', 
    'semrush', 'dotbot', 'exabot', 'screaming', 'facebook', 'twitter', 'linkedin', 'telegram', 
    'whatsapp', 'petal', 'pinterest', 'duckduckgo'
];
$ua_lower = strtolower($user_agent);
foreach ($bot_signatures as $sig) {
    if (strpos($ua_lower, $sig) !== false) {
        $is_bot = 1;
        break;
    }
}

if ($is_bot == 0) {
    // Increment Views
    $pdo->prepare("UPDATE articles SET views = views + 1 WHERE id = ?")->execute([$article['id']]);
    // Log Detailed View
    $stmt = $pdo->prepare("INSERT INTO article_views (article_id, ip_address, viewed_at) VALUES (?, ?, NOW())");
    $stmt->execute([$article['id'], $_SERVER['REMOTE_ADDR']]);
}

// Get Tags
$stmt = $pdo->prepare("SELECT t.name FROM tags t JOIN article_tags at ON t.id = at.tag_id WHERE at.article_id = ?");
$stmt->execute([$article['id']]);
$tags = $stmt->fetchAll(PDO::FETCH_COLUMN);

// SEO & Metadata Preparation
$pageTitle = !empty($article['seo_title']) ? $article['seo_title'] : $article['title'];
$metaDesc = !empty($article['seo_description']) ? $article['seo_description'] : substr(strip_tags(html_entity_decode($article['content'])), 0, 160) . '...';
$siteUrl = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    // Calculate Base URL for <base> tag to fix relative links with Friendly URLs
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
    $host = $_SERVER['HTTP_HOST'];
    $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
    // Ensure trailing slash
    $baseUrl = $protocol . "://" . $host . rtrim($scriptDir, '/\\') . '/';

    // Fix Image URL calculation to use correctly detected base
    $imageUrl = $article['image_path'] ? $baseUrl . $article['image_path'] : '';
    $publishedTime = date('c', strtotime($article['created_at']));

    // --- Fetch Contact Info ---
    $waNumber = $pdo->query("SELECT contact_value FROM contact_info WHERE contact_key = 'whatsapp'")->fetchColumn();
    // Sanitize
    if($waNumber) {
        $waNumber = preg_replace('/[^0-9]/', '', $waNumber);
        if(substr($waNumber, 0, 1) == '0') $waNumber = '62' . substr($waNumber, 1);
    } else {
        $waNumber = '6281280666659'; // Default Fallback
    }
    // Dynamic CTA Link
    $waMessage = "Halo, saya baca artikel '{$article['title']}' dan tertarik dengan layanan Anda.";
    $waLink = "https://wa.me/$waNumber?text=" . urlencode($waMessage);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <base href="<?php echo $baseUrl; ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title><?php echo htmlspecialchars($pageTitle); ?> - Arno D Clean</title>
    <link rel="icon" href="<?php echo htmlspecialchars($siteIcon); ?>">
    <meta name="description" content="<?php echo htmlspecialchars($metaDesc); ?>">

    <!-- Open Graph Tags -->
    <meta property="og:title" content="<?php echo htmlspecialchars($pageTitle); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($metaDesc); ?>">
    <meta property="og:url" content="<?php echo $siteUrl; ?>">
    <meta property="og:type" content="article">
    <?php if($imageUrl): ?>
        <meta property="og:image" content="<?php echo $imageUrl; ?>">
    <?php endif; ?>

    <!-- JSON-LD Structured Data for SEO -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Article",
      "headline": "<?php echo addslashes($article['title']); ?>",
      "image": [
        "<?php echo $imageUrl; ?>"
       ],
      "datePublished": "<?php echo $publishedTime; ?>",
      "dateModified": "<?php echo $publishedTime; ?>",
      "author": [{
          "@type": "Organization",
          "name": "Arno D Clean",
          "url": "<?php echo (isset($_SERVER['HTTPS']) ? "https" : "http") . "://" . $_SERVER['HTTP_HOST']; ?>"
        }]
    }
    </script>

    <!-- Fonts & CSS -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --font-body: 'Inter', sans-serif;
            --font-heading: 'Inter', sans-serif; /* Can use Playfair for stronger aesthetic */
            --color-text: #334155;
            --color-heading: #0f172a;
            --color-primary: #0d6efd;
            --page-bg: #f8fafc;
        }

        body { 
            font-family: var(--font-body); 
            background: var(--page-bg); 
            color: var(--color-text);
            line-height: 1.75;
            padding-top: 80px; 
        }

        /* Reading Width Container */
        .article-container {
            max-width: 760px; /* Optimal reading width */
            margin: 0 auto;
            position: relative;
        }

        /* Hero Section */
        .hero-section {
            padding: 2rem 0 2.5rem;
            text-align: center;
        }
        .hero-title {
            font-family: var(--font-heading);
            font-weight: 800;
            font-size: 2.5rem;
            line-height: 1.3;
            color: var(--color-heading);
            margin-bottom: 1rem;
            letter-spacing: -0.025em;
        }
        .meta-data {
            font-size: 0.9rem;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1.5rem;
        }
        .meta-data i { margin-right: 0.4rem; opacity: 0.8; }
        .tag-pill {
            background: #e2e8f0;
            color: #475569;
            padding: 0.25rem 0.75rem;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
        }
        .tag-pill:hover { background: #cbd5e1; color: #334155; }

        /* Article Image */
        .featured-image-wrapper {
            margin-bottom: 3rem;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 40px -10px rgba(0,0,0,0.1);
        }
        .article-img { width: 100%; height: auto; display: block; }

        /* Content Styling */
        .article-body {
            font-size: 1.125rem; /* ~18px */
            color: #334155;
        }
        .article-body h2 {
            font-family: var(--font-heading);
            font-weight: 700;
            font-size: 1.75rem;
            color: var(--color-heading);
            margin-top: 2.5rem;
            margin-bottom: 1.25rem;
            letter-spacing: -0.02em;
        }
        .article-body h3 {
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--color-heading);
            margin-top: 2rem;
            margin-bottom: 1rem;
        }
        .article-body p { margin-bottom: 1.5rem; }
        .article-body ul, .article-body ol { margin-bottom: 1.5rem; padding-left: 1.5rem; }
        .article-body li { margin-bottom: 0.5rem; }
        .article-body blockquote {
            border-left: 4px solid var(--color-primary);
            padding-left: 1.5rem;
            margin: 2rem 0;
            font-style: italic;
            font-size: 1.2rem;
            color: #475569;
            background: #f1f5f9;
            padding: 1.5rem;
            border-radius: 0 12px 12px 0;
        }
        .article-body img {
            max-width: 100%;
            height: auto;
            border-radius: 12px;
            margin: 1.5rem 0;
        }

        /* CTA */
        .cta-box {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border-radius: 16px;
            padding: 2.5rem;
            color: white;
            text-align: center;
            margin: 4rem 0;
            box-shadow: 0 20px 25px -5px rgba(16, 185, 129, 0.2);
        }
        .cta-btn {
            background: white;
            color: #059669;
            font-weight: 600;
            padding: 0.8rem 2rem;
            border-radius: 99px;
            display: inline-flex;
            align-items: center;
            transition: transform 0.2s;
            text-decoration: none;
            margin-top: 1rem;
        }
        .cta-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); color: #047857; }

        /* Related Articles */
        .related-card {
            border: none;
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            height: 100%;
        }
        .related-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
        }
        .related-img {
            height: 200px;
            object-fit: cover;
            width: 100%;
        }
        .related-body { padding: 1.5rem; }
        .related-title {
            font-weight: 700;
            font-size: 1.1rem;
            line-height: 1.4;
            margin-bottom: 0;
        }
        .related-title a { color: var(--color-heading); text-decoration: none; }
        .related-title a:hover { color: var(--color-primary); }
        .share-buttons .btn { width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center; margin: 0 4px; transition: all 0.2s; }
        .share-buttons .btn:hover { transform: translateY(-3px); }

        .toc-box {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2.5rem;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);
        }
        .toc-box ul { padding-left: 0; margin-bottom: 0; }
        .hover-primary:hover { color: var(--color-primary) !important; text-decoration: underline !important; }

        .navbar-brand img { max-height: 56px; width: auto; }
        @media (min-width: 992px) { .navbar-brand img { max-height: 64px; } }

        /* Sidebar & Floating CTA Styles */
        .sticky-promo { top: 100px; z-index: 10; }
        .promo-card {
            border: none;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 30px -5px rgba(0,0,0,0.1);
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
            color: white;
            text-align: center;
            padding: 2rem 1.5rem;
        }
        .promo-card .btn { box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        
        .floating-cta-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 1040;
            background: white;
            padding: 1rem;
            box-shadow: 0 -4px 20px rgba(0,0,0,0.1);
            transform: translateY(100%);
            animation: slideUp 0.5s ease-out forwards;
            animation-delay: 2s; /* Show after small delay */
        }
        @keyframes slideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }
        
        @media (max-width: 991.98px) {
            body { padding-bottom: 80px; } /* Space for floating CTA */
        }
        @media (min-width: 992px) {
            .article-container { margin: 0; max-width: 100%; } /* Reset max-width for grid layout */
        }
    </style>
    <!-- Ahrefs Web Analytics -->
    <script src="https://analytics.ahrefs.com/analytics.js" data-key="OHuYixrUYifEf8KuUmecHA" async></script>
</head>
<body>
    <nav class="navbar navbar-expand-lg fixed-top bg-white/90 backdrop-blur shadow-sm" style="background: rgba(255,255,255,0.95); backdrop-filter: blur(10px);">
        <div class="container Article-maxWidth">
            <a class="navbar-brand fw-bold text-primary" href="index.php" style="letter-spacing: -0.5px;">
                <?php if($siteLogo): ?>
                    <img src="<?php echo htmlspecialchars($siteLogo); ?>" alt="Arno D Clean">
                <?php else: ?>
                    Arno D Clean
                <?php endif; ?>
            </a>
            <a href="blog.php" class="btn btn-sm btn-light rounded-pill px-3 fw-medium ms-auto"><i class="fa-solid fa-arrow-left me-1"></i> Kembali</a>
        </div>
    </nav>

    <main class="container py-5">
        <div class="row g-5">
            <!-- Left Column: Article Content -->
            <div class="col-lg-8">
                <article class="article-container" itemscope itemtype="http://schema.org/Article">
                    
                    <!-- Hero Header -->
                    <header class="hero-section text-start">
                        <!-- Breadcrumb -->
                        <nav aria-label="breadcrumb" class="mb-4">
                            <ol class="breadcrumb mb-0 small" itemscope itemtype="https://schema.org/BreadcrumbList">
                                <li class="breadcrumb-item" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                                    <a href="index.php" itemprop="item" class="text-decoration-none text-muted"><span itemprop="name">Home</span></a>
                                    <meta itemprop="position" content="1" />
                                </li>
                                <li class="breadcrumb-item" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                                    <a href="blog.php" itemprop="item" class="text-decoration-none text-muted"><span itemprop="name">Blog</span></a>
                                    <meta itemprop="position" content="2" />
                                </li>
                                <li class="breadcrumb-item active" aria-current="page"><span itemprop="name">Artikel</span></li>
                            </ol>
                        </nav>

                        <h1 class="hero-title text-start" itemprop="headline"><?php echo htmlspecialchars($article['title']); ?></h1>
                        
                        <div class="meta-data justify-content-start">
                            <span><i class="fa-regular fa-calendar"></i> <time itemprop="datePublished" datetime="<?php echo $publishedTime; ?>"><?php echo date('d M Y', strtotime($article['created_at'])); ?></time></span>
                            <span><i class="fa-regular fa-eye"></i> <?php echo number_format($article['views']); ?> Reads</span>
                        </div>
                        
                        <?php if(!empty($tags)): ?>
                        <div class="mt-3">
                            <?php foreach($tags as $t): ?>
                                <a href="blog.php?search=<?php echo urlencode(trim($t)); ?>" class="tag-pill me-1 text-decoration-none badge bg-light text-secondary border hover-primary"><?php echo htmlspecialchars(trim($t)); ?></a>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Social Share -->
                        <div class="share-buttons mt-4 text-start">
                            <span class="text-muted small me-2 d-none d-md-inline">Bagikan:</span>
                            <a href="https://wa.me/?text=<?php echo urlencode($article['title'] . ' ' . $siteUrl); ?>" target="_blank" class="btn btn-sm btn-outline-success rounded-circle" title="Share ke WhatsApp"><i class="fa-brands fa-whatsapp"></i></a>
                            <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($siteUrl); ?>" target="_blank" class="btn btn-sm btn-outline-primary rounded-circle" title="Share ke Facebook"><i class="fa-brands fa-facebook-f"></i></a>
                            <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode($siteUrl); ?>&text=<?php echo urlencode($article['title']); ?>" target="_blank" class="btn btn-sm btn-outline-dark rounded-circle" title="Share ke Twitter"><i class="fa-brands fa-x-twitter"></i></a>
                            <button onclick="copyLink()" class="btn btn-sm btn-outline-secondary rounded-circle" title="Salin Link"><i class="fa-solid fa-link"></i></button>
                        </div>
                    </header>

                    <!-- Featured Image -->
                    <?php if($article['image_path']): ?>
                    <figure class="featured-image-wrapper mt-4">
                        <img src="<?php echo $article['image_path']; ?>" class="article-img" alt="<?php echo htmlspecialchars($article['title']); ?>" itemprop="image">
                    </figure>
                    <?php endif; ?>

                    <!-- Main Content -->
                    
                    <!-- Table of Contents -->
                    <div id="toc-container" class="toc-box d-none">
                        <h5 class="fw-bold mb-3 h6 text-uppercase text-muted ls-1"><i class="fa-solid fa-list-ul me-2"></i>Daftar Isi</h5>
                        <nav id="toc-nav"></nav>
                    </div>

                    <div class="article-body" itemprop="articleBody">
                        <?php echo html_entity_decode($article['content']); ?>
                    </div>

                    <!-- CTA Section (In-Content) -->
                    <div class="cta-box">
                        <h3 class="fw-bold text-white mb-2">Butuh Bantuan Layanan Kebersihan?</h3>
                        <p class="mb-4 opacity-90">Tim profesional kami siap membantu membereskan masalah kebersihan Anda hari ini.</p>
                        <a href="<?php echo $waLink; ?>" class="cta-btn shadow-sm" target="_blank">
                            <i class="fa-brands fa-whatsapp me-2 fs-5"></i> Chat via WhatsApp
                        </a>
                    </div>

                </article>
            </div>

            <!-- Right Column: Sticky Sidebar -->
            <div class="col-lg-4 d-none d-lg-block">
                <div class="sticky-top sticky-promo">
                    <!-- Promo Card -->
                    <div class="card promo-card mb-4 text-white">
                        <div class="mb-3">
                            <i class="fa-solid fa-sparkles fa-3x opacity-75"></i>
                        </div>
                        <h4 class="fw-bold mb-2">Rumah Bersih, Hati Tenang</h4>
                        <p class="small mb-4 opacity-90">Arno D Clean menyediakan layanan cuci sofa, springbed, dan karpet dengan teknologi terkini. Diskon khusus untuk pelanggan baru!</p>
                        <a href="<?php echo $waLink; ?>" target="_blank" class="btn btn-light rounded-pill fw-bold text-primary w-100 stretched-link">
                            <i class="fa-brands fa-whatsapp me-2"></i> Reservasi Sekarang
                        </a>
                    </div>
                    
                    <!-- Quick Links / Popular Tags -->
                    <div class="card border-0 shadow-sm rounded-4 p-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h5 class="fw-bold mb-0">Topik Populer</h5>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                             <?php
                                // Fetch Popular Tags (Cached/Optimized)
                                try {
                                    $popTagsStmt = $pdo->query("
                                        SELECT t.id, t.name, COUNT(at.article_id) as count 
                                        FROM tags t 
                                        JOIN article_tags at ON t.id = at.tag_id 
                                        JOIN articles a ON at.article_id = a.id 
                                        WHERE a.status = 'published' 
                                        GROUP BY t.id, t.name 
                                        ORDER BY count DESC, t.name ASC 
                                        LIMIT 10
                                    ");
                                    $popTags = $popTagsStmt ? $popTagsStmt->fetchAll() : [];
                                } catch (Exception $e) {
                                    $popTags = [];
                                }

                                if(empty($popTags)) {
                                     echo '<span class="text-muted small">Belum ada topik populer.</span>';
                                } else {
                                    foreach($popTags as $pt) {
                                        echo '<a href="blog.php?search='.urlencode($pt['name']).'" class="tag-pill text-decoration-none d-flex align-items-center">';
                                        echo htmlspecialchars($pt['name']);
                                        echo '<span class="badge bg-white text-secondary ms-2 rounded-pill shadow-sm" style="font-size: 0.7em;">'.$pt['count'].'</span>';
                                        echo '</a>';
                                    }
                                }
                             ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Related Articles -->
        <?php
        $related = [];
        if (!empty($tags)) {
            $tids = $pdo->prepare("SELECT tag_id FROM article_tags WHERE article_id = ?");
            $tids->execute([$article['id']]);
            $tagIds = $tids->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($tagIds)) {
                $placeholders = implode(',', array_fill(0, count($tagIds), '?'));
                $sql = "SELECT a.id, a.title, a.slug, a.image_path, COUNT(at.tag_id) as matches 
                        FROM articles a 
                        JOIN article_tags at ON a.id = at.article_id 
                        WHERE at.tag_id IN ($placeholders) 
                        AND a.id != ? 
                        AND a.status = 'published'
                        GROUP BY a.id 
                        ORDER BY matches DESC, a.created_at DESC 
                        LIMIT 3";
                
                $params = array_merge($tagIds, [$article['id']]);
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $related = $stmt->fetchAll();
            }
        }

        if (count($related) < 3) {
            $limit = 3 - count($related);
            $excludeIds = array_column($related, 'id');
            $excludeIds[] = $article['id'];
            $excludePlaceholders = implode(',', array_fill(0, count($excludeIds), '?'));

            $sql = "SELECT id, title, slug, image_path FROM articles 
                    WHERE id NOT IN ($excludePlaceholders) 
                    AND status = 'published' 
                    ORDER BY created_at DESC 
                    LIMIT $limit";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($excludeIds);
            $fallback = $stmt->fetchAll();
            $related = array_merge($related, $fallback);
        }
        ?>

        <?php if (!empty($related)): ?>
        <aside class="mt-5 pt-5 pb-5">
            <div class="text-center mb-5">
                <span class="text-primary fw-bold small text-uppercase ls-1">Rekomendasi</span>
                <h3 class="fw-bold mt-1">Artikel Lainnya</h3>
            </div>
            
            <div class="row g-4 justify-content-center">
                <?php foreach($related as $r): ?>
                <div class="col-md-4">
                    <div class="related-card h-100">
                        <a href="blog/<?php echo $r['slug']; ?>" class="d-block overflow-hidden position-relative">
                            <?php if($r['image_path']): ?>
                                <img src="<?php echo $r['image_path']; ?>" class="related-img" alt="<?php echo htmlspecialchars($r['title']); ?>">
                            <?php else: ?>
                                <div class="related-img bg-light d-flex align-items-center justify-content-center">
                                    <i class="fa-solid fa-image text-secondary fs-1 opacity-25"></i>
                                </div>
                            <?php endif; ?>
                        </a>
                        <div class="related-body">
                            <h5 class="related-title">
                                <a href="blog/<?php echo $r['slug']; ?>"><?php echo htmlspecialchars($r['title']); ?></a>
                            </h5>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </aside>
        <?php endif; ?>

    </main>
    
    <!-- Mobile Floating CTA -->
    <div class="floating-cta-bar d-lg-none">
        <div class="container">
            <a href="<?php echo $waLink; ?>" target="_blank" class="btn btn-success w-100 rounded-pill fw-bold shadow-sm py-2">
                <i class="fa-brands fa-whatsapp me-2 fs-5"></i> Chat via WhatsApp
            </a>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function copyLink() {
            navigator.clipboard.writeText(window.location.href);
            alert('Link artikel berhasil disalin!');
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Generate Table of Contents
            const articleBody = document.querySelector('.article-body');
            const tocContainer = document.getElementById('toc-container');
            const tocNav = document.getElementById('toc-nav');
            
            if (articleBody && tocContainer) {
                const headings = articleBody.querySelectorAll('h2, h3');
                
                if (headings.length >= 2) {
                    tocContainer.classList.remove('d-none');
                    const ul = document.createElement('ul');
                    ul.className = 'list-unstyled mb-0';
                    
                    headings.forEach((heading, index) => {
                        const id = 'heading-' + index;
                        // Only set ID if not present
                        if (!heading.id) heading.id = id;
                        
                        const li = document.createElement('li');
                        li.className = heading.tagName === 'H2' ? 'mb-2 fw-medium' : 'ps-3 mb-1 small text-muted';
                        
                        const a = document.createElement('a');
                        a.href = '#' + heading.id;
                        a.textContent = heading.textContent;
                        a.className = 'text-decoration-none text-body hover-primary transition-all';
                        a.style.transition = 'color 0.2s';
                        
                        // Smooth scroll
                        a.onclick = function(e) {
                            e.preventDefault();
                            document.getElementById(heading.id).scrollIntoView({behavior: 'smooth', block: 'start'});
                        };
                        
                        li.appendChild(a);
                        ul.appendChild(li);
                    });
                    
                    tocNav.appendChild(ul);
                }
            }
        });
    </script>
</body>
</html>
