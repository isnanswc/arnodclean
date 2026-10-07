<?php
// ini_set('display_errors', 1);
// error_reporting(E_ALL);
error_reporting(0); // Production Mode
require_once 'db.php';
require_once 'includes/tracker.php';

// Navbar Fetch (Keep existing logic or include)
// Optimized: Fetch Top 20 Tags by usage count
// Optimized: Fetch Top 20 Tags by usage count
try {
    $tagsStmt = $pdo->query("
        SELECT t.id, t.name, COUNT(at.article_id) as count 
        FROM tags t 
        JOIN article_tags at ON t.id = at.tag_id 
        JOIN articles a ON at.article_id = a.id 
        WHERE a.status = 'published' 
        GROUP BY t.id, t.name 
        ORDER BY count DESC, t.name ASC 
        LIMIT 20
    ");
    $tags = $tagsStmt ? $tagsStmt->fetchAll() : [];
} catch (Exception $e) {
    // Fallback if strict mode or other error
    $tags = $pdo->query("SELECT * FROM tags ORDER BY name ASC LIMIT 20")->fetchAll();
}

// Fetch Settings & Contact
$settings = $pdo->query("SELECT setting_key, setting_value FROM site_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$waNumber = $pdo->query("SELECT contact_value FROM contact_info WHERE contact_key = 'whatsapp'")->fetchColumn();

// Defaults
$siteIcon = !empty($settings['site_icon']) ? $settings['site_icon'] : 'img/l2.png';
$siteLogo = !empty($settings['site_logo']) ? $settings['site_logo'] : '';

$waTemplate = $settings['wa_template_general'] ?? '';

// Sanitize/Format WA Number (Convert 08 to 628)
if($waNumber) {
    $waNumber = preg_replace('/[^0-9]/', '', $waNumber);
    if(substr($waNumber, 0, 1) == '0') $waNumber = '62' . substr($waNumber, 1);
} else {
    $waNumber = '6281234567890'; // Default
}
$waLink = "https://wa.me/$waNumber" . ($waTemplate ? "?text=" . urlencode($waTemplate) : "");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog & Tips Kebersihan - Arno D Clean</title>
    <meta name="description" content="Temukan tips kebersihan terbaik, panduan merawat furniture, dan solusi cuci sofa, springbed, serta karpet profesional dari Arno D Clean.">
    
    <?php
    $bHost = preg_replace('/^www\./i', '', $_SERVER['HTTP_HOST']);
    $bProto = ($bHost === 'arnod-clean.com') ? 'https' : (isset($_SERVER['HTTPS']) ? "https" : "http");
    $blogCanonical = $bProto . "://" . $bHost . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    ?>
    <!-- Robots Directive -->
    <?php if(!empty($_GET['search']) || !empty($_GET['tag'])): ?>
    <meta name="robots" content="noindex, follow">
    <?php else: ?>
    <meta name="robots" content="index, follow">
    <?php endif; ?>

    <!-- Canonical -->
    <link rel="canonical" href="<?php echo htmlspecialchars($blogCanonical); ?>">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo htmlspecialchars($blogCanonical); ?>">
    <meta property="og:title" content="Blog & Tips Kebersihan - Arno D Clean">
    <meta property="og:description" content="Temukan tips kebersihan terbaik, panduan merawat furniture, dan solusi cuci sofa, springbed, serta karpet profesional dari Arno D Clean.">
    <meta property="og:image" content="<?php echo $bProto . "://" . $bHost . '/' . ($siteLogo ?: 'img/l2.png'); ?>">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:title" content="Blog & Tips Kebersihan - Arno D Clean">
    <meta property="twitter:description" content="Temukan tips kebersihan terbaik, panduan merawat furniture, dan solusi cuci sofa, springbed, serta karpet profesional dari Arno D Clean.">
    <meta property="twitter:image" content="<?php echo (isset($_SERVER['HTTPS']) ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . '/' . ($siteLogo ?: 'img/l2.png'); ?>">

    <link rel="icon" href="<?php echo htmlspecialchars($siteIcon); ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { 
            --primary: #2563eb; 
            --primary-dark: #1e40af;
            --accent: #06b6d4;
            --bg-light: #f8fafc;
            --text-dark: #1e293b;
            --text-muted: #64748b;
        }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg-light); color: var(--text-dark); padding-top: 76px; }
        
        .navbar { background: rgba(255,255,255,0.95) !important; backdrop-filter: blur(10px); box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .navbar-brand { font-weight: 800; letter-spacing: -0.5px; display: flex; align-items: center; }
        .navbar-brand img { max-height: 56px; width: auto; }
        @media (min-width: 992px) { .navbar-brand img { max-height: 72px; } }

        /* Card Styles */
        .card-article { 
            border: 0; background: white; border-radius: 16px; overflow: hidden; 
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 2px 4px -1px rgba(0, 0, 0, 0.02);
            height: 100%;
            display: flex; flex-direction: column;
        }
        .card-article:hover { 
            transform: translateY(-5px); 
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); 
        }
        .card-img-top-wrapper { position: relative; padding-top: 56.25%; /* 16:9 Aspect Ratio */ overflow: hidden; }
        .card-img-top-wrapper img { 
            position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; 
            transition: transform 0.5s ease;
        }
        .card-article:hover .card-img-top-wrapper img { transform: scale(1.05); }
        
        .card-body { padding: 1.5rem; display: flex; flex-direction: column; flex: 1; }
        .card-meta { font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.5rem; }
        .card-title { font-weight: 700; line-height: 1.4; margin-bottom: 0.75rem; color: var(--text-dark); }
        .btn-read-more { margin-top: auto; font-weight: 600; color: var(--primary); text-decoration: none; }
        .btn-read-more:hover { color: var(--primary-dark); }
        
        /* Sidebar Widgets */
        .sidebar { position: sticky; top: 100px; }
        .widget { background: white; border-radius: 16px; padding: 1.5rem; margin-bottom: 1.5rem; border: 1px solid #f1f5f9; }
        .widget-title { font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem; position: relative; padding-bottom: 0.5rem; }
        .widget-title::after { content: ''; position: absolute; bottom: 0; left: 0; width: 40px; height: 3px; background: var(--primary); border-radius: 3px; }
        
        /* Search Input */
        .search-group input { border-radius: 50px; padding-left: 1.25rem; border: 2px solid #e2e8f0; height: 48px; }
        .search-group input:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1); }
        .search-group button { border-radius: 50px; padding-left: 1.5rem; padding-right: 1.5rem; }

        /* Tags */
        .tag-cloud { display: flex; flex-wrap: wrap; gap: 8px; }
        .tag-pill { 
            display: inline-flex; align-items: center;
            padding: 6px 14px; background: #f1f5f9; color: var(--text-muted); border-radius: 50px; 
            font-size: 0.85rem; font-weight: 500; text-decoration: none; transition: 0.2s; border: 1px solid transparent;
        }
        .tag-pill:hover, .tag-pill.active { background: var(--primary); color: white; border-color: var(--primary); }
        
        .tag-container-limited { max-height: auto; overflow: hidden; }
        .tag-hidden { display: none !important; }

        /* Pagination */
        .pagination .page-link { 
            border: none; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; 
            border-radius: 50%; margin: 0 4px; color: var(--text-dark); font-weight: 600;
        }
        .pagination .page-link:hover { background: #e2e8f0; color: var(--primary); }
        .pagination .active .page-link { background: var(--primary) !important; color: white !important; box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.4); }
        .pagination .disabled .page-link { background: transparent; color: #cbd5e1; }

        /* Loading */
        .skeleton { background: linear-gradient(90deg, #f0f0f0 25%, #f8f8f8 50%, #f0f0f0 75%); background-size: 200% 100%; animation: shimmer 1.5s infinite; border-radius: 8px; }
        @keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
    </style>
    <!-- Ahrefs Web Analytics -->
    <script src="https://analytics.ahrefs.com/analytics.js" data-key="OHuYixrUYifEf8KuUmecHA" async></script>
</head>
<body>

    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand text-primary" href="index.php">
                <?php if($siteLogo): ?>
                    <img src="<?php echo htmlspecialchars($siteLogo); ?>" alt="Arno D Clean">
                <?php else: ?>
                    <i class="fa-solid fa-sparkles me-2"></i>Arno D Clean
                <?php endif; ?>
            </a>
            <div class="d-flex align-items-center gap-2">
                <a href="index.php" class="btn btn-outline-secondary rounded-pill px-4 fw-medium"><i class="fa-solid fa-arrow-left me-2"></i>Beranda</a>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <div class="row g-5">
            <!-- Left Column: Articles -->
            <div class="col-lg-8">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <h2 class="fw-bold m-0 text-dark">Artikel Terbaru</h2>
                    <span class="text-muted small" id="resultCount">Memuat...</span>
                </div>

                <!-- Active Filter Badge -->
                <div id="activeFilter" class="mb-4 d-none">
                    <div class="alert alert-info border-0 d-flex align-items-center justify-content-between py-2 px-3 rounded-3 shadow-sm">
                        <span><i class="fa-solid fa-filter me-2"></i>Filter: <strong id="filterName">Tag</strong></span>
                        <button class="btn btn-sm btn-close" onclick="clearFilter()"></button>
                    </div>
                </div>

                <!-- Article Grid -->
                <div class="row g-4" id="articleList">
                    <!-- Skeleton Loading -->
                    <?php for($i=0; $i<4; $i++): ?>
                    <div class="col-md-6">
                        <div class="card card-article border-0">
                            <div class="card-img-top-wrapper skeleton" style="height: 200px; padding:0;"></div>
                            <div class="card-body">
                                <div class="skeleton mb-3" style="height: 10px; width: 30%;"></div>
                                <div class="skeleton mb-3" style="height: 20px; width: 80%;"></div>
                                <div class="skeleton" style="height: 15px; width: 100%;"></div>
                            </div>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>

                <!-- Pagination -->
                <nav class="mt-5">
                    <ul class="pagination justify-content-center" id="pagination"></ul>
                </nav>
            </div>

            <!-- Right Column: Sidebar -->
            <div class="col-lg-4">
                <div class="sidebar">
                    <!-- Search Widget -->
                    <div class="widget">
                        <h4 class="widget-title">Cari Artikel</h4>
                        <div class="input-group search-group">
                            <input type="text" id="searchInput" class="form-control" placeholder="Ketik kata kunci...">
                            <button class="btn btn-primary" type="button"><i class="fa-solid fa-magnifying-glass"></i></button>
                        </div>
                    </div>

                    <!-- Tags Widget (Dynamic & Expandable) -->
                    <div class="widget">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h4 class="widget-title mb-0">Topik Populer</h4>
                        </div>
                        <div class="tag-cloud" id="tagCloud">
                            <a href="#" class="tag-pill active" data-id="" onclick="filterTag(this, ''); return false;">
                                Semua
                            </a>
                            <?php 
                            $count = 0;
                            foreach($tags as $t): 
                                $count++;
                                $hiddenClass = $count > 10 ? 'tag-hidden' : '';
                            ?>
                                <a href="#" class="tag-pill <?php echo $hiddenClass; ?>" data-id="<?php echo $t['id']; ?>" onclick="filterTag(this, '<?php echo $t['id']; ?>'); return false;">
                                    <?php echo htmlspecialchars($t['name']); ?>
                                    <span class="badge bg-white text-secondary ms-2 rounded-pill shadow-sm" style="font-size: 0.7em;"><?php echo $t['count']; ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                        <?php if(count($tags) > 10): ?>
                            <button type="button" class="btn btn-link btn-sm text-decoration-none mt-3 p-0" id="btnToggleTags" onclick="toggleTags()">
                                <i class="fa-solid fa-chevron-down me-1"></i> Lihat lebih banyak
                            </button>
                        <?php endif; ?>
                    </div>

                    <!-- Info Widget -->
                    <div class="widget bg-primary text-white border-0" style="background: linear-gradient(135deg, var(--primary), var(--primary-dark));">
                        <h4 class="widget-title text-white">Butuh Layanan?</h4>
                        <p class="mb-4 opacity-75">Kami ahli membersihkan sofa, springbed, dan karpet Anda hingga bebas noda dan tungau.</p>
                        <a href="<?php echo htmlspecialchars($waLink); ?>" target="_blank" class="btn btn-light w-100 rounded-pill fw-bold text-primary">
                            <i class="fa-brands fa-whatsapp me-2"></i> Hubungi Kami
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="bg-white border-top py-4 mt-5">
        <div class="container text-center">
            <p class="text-muted mb-0">&copy; <?php echo date('Y'); ?> Arno D Clean. Solusi Kebersihan Profesional.</p>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        let currentState = {
            page: 1,
            limit: 6,
            search: '',
            tag: ''
        };
        let tagsExpanded = false;

        $(document).ready(function() {
            // Parse URL Params
            const urlParams = new URLSearchParams(window.location.search);
            const searchParam = urlParams.get('search');
            
            if(searchParam) {
                currentState.search = searchParam;
                $('#searchInput').val(searchParam);
            }

            loadArticles();

            // Search with Debounce
            let searchTimeout;
            $('#searchInput').on('input', function() {
                clearTimeout(searchTimeout);
                currentState.search = $(this).val();
                currentState.page = 1; 
                searchTimeout = setTimeout(() => {
                    loadArticles();
                    // Update URL for search
                    const newUrl = currentState.search ? `?search=${encodeURIComponent(currentState.search)}` : window.location.pathname;
                    window.history.pushState({path: newUrl}, '', newUrl);
                }, 500);
            });
        });

        function toggleTags() {
            tagsExpanded = !tagsExpanded;
            const btn = $('#btnToggleTags');
            if(tagsExpanded) {
                $('.tag-hidden').each(function() {
                    $(this).removeClass('tag-hidden').addClass('tag-was-hidden').hide().fadeIn();
                });
                btn.html('<i class="fa-solid fa-chevron-up me-1"></i> Tampilkan sedikit');
            } else {
                $('.tag-was-hidden').fadeOut(200, function(){ 
                    $(this).addClass('tag-hidden').removeClass('tag-was-hidden').removeAttr('style'); 
                });
                btn.html('<i class="fa-solid fa-chevron-down me-1"></i> Lihat lebih banyak');
            }
        }

        function filterTag(el, tagId) {
            $('.tag-pill').removeClass('active');
            $(el).addClass('active');
            currentState.tag = tagId;
            currentState.page = 1;
            
            // Show Active Filter Badge
            const tagName = $(el).text().trim().split('\n')[0]; // Remove count for display if needed, but text() gets all.
            // Cleaner way to get text only:
            let cleanTagName = $(el).contents().filter(function() { return this.nodeType == 3; }).text().trim();
            if(!cleanTagName) cleanTagName = $(el).text().trim();

            if(tagId) {
                $('#activeFilter').removeClass('d-none');
                $('#filterName').text(cleanTagName);
                // Update URL to match search param style so it's shareable
                // Actually, backend supports ?search=TagName, so let's use that for URL
                const newUrl = `?search=${encodeURIComponent(cleanTagName)}`;
                window.history.pushState({path: newUrl}, '', newUrl);
                
                // Also update search input
                $('#searchInput').val(cleanTagName);
                currentState.search = cleanTagName;
                currentState.tag = ''; // Clear ID if using search param, or keep both? 
                // Using search param is safer for the "get_articles" update we did.
                // Let's rely on 'search' since we updated get_articles to look at tags via search string.
            } else {
                $('#activeFilter').addClass('d-none');
                 window.history.pushState({path: window.location.pathname}, '', window.location.pathname);
                 $('#searchInput').val('');
                 currentState.search = '';
            }
            
            loadArticles();
        }
        
        function clearFilter() {
            filterTag($('.tag-pill[data-id=""]'), '');
        }

        function loadArticles() {
            // Only show loader if initial load or specific transitions (Optional)
            // $('#articleList').addClass('opacity-50');

            $.ajax({
                url: 'get_articles.php',
                data: currentState,
                dataType: 'json',
                success: function(response) {
                    renderArticles(response.data);
                    renderPagination(response.pagination);
                    
                    const min = (response.pagination.current_page - 1) * currentState.limit + 1;
                    const max = Math.min(response.pagination.current_page * currentState.limit, response.pagination.total_records);
                    if(response.pagination.total_records > 0)
                        $('#resultCount').text(`Menampilkan ${min}-${max} dari ${response.pagination.total_records} artikel`);
                    else
                        $('#resultCount').text('0 Artikel');
                },
                error: function(xhr, status, error) {
                    $('#articleList').html('<div class="col-12 text-center text-danger py-5"><i class="fa-solid fa-circle-exclamation fa-2x mb-3"></i><br>Gagal memuat artikel. Silakan coba lagi.</div>');
                },
                complete: function() {
                    $('#articleList').removeClass('opacity-50');
                }
            });
        }

        function renderArticles(articles) {
            const container = $('#articleList');
            container.empty();

            if (articles.length === 0) {
                container.html(`
                    <div class="col-12 text-center py-5">
                        <div class="mb-3"><i class="fa-solid fa-magnifying-glass fa-3x text-muted opacity-25"></i></div>
                        <h5 class="text-muted">Tidak ada artikel ditemukan</h5>
                        <p class="text-muted small">Coba kata kunci lain atau reset filter Anda.</p>
                        <button class="btn btn-outline-primary rounded-pill btn-sm mt-2" onclick="clearFilter(); $('#searchInput').val('').trigger('input');">Reset Pencarian</button>
                    </div>
                `);
                return;
            }

            articles.forEach(a => {
                const escape = (str) => {
                    if (!str) return '';
                    return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
                };

                let imageHtml = `<div class="bg-light d-flex align-items-center justify-content-center h-100 text-muted"><i class="fa-solid fa-image fa-2x opacity-25"></i></div>`;
                if (a.image_path) {
                    imageHtml = `<img src="${escape(a.image_path)}" alt="${escape(a.title)}">`;
                }

                // Format Content Snippet better
                let snippet = a.snippet || '';
                if(snippet.length > 100) snippet = snippet.substring(0, 100) + '...';

                const dateStr = new Date(a.created_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });

                const html = `
                <div class="col-md-6">
                    <article class="card card-article">
                        <div class="card-img-top-wrapper">
                            ${imageHtml}
                        </div>
                        <div class="card-body">
                            <div class="card-meta d-flex align-items-center mb-2">
                                <span><i class="fa-regular fa-calendar me-1"></i> ${dateStr}</span>
                            </div>
                            <h3 class="h5 card-title">
                                <a href="blog/${escape(a.slug)}" class="text-decoration-none text-dark stretched-link">${escape(a.title)}</a>
                            </h3>
                            <p class="card-text text-muted small mb-4 flex-grow-1">${escape(snippet)}</p>
                            <div class="mt-auto d-flex align-items-center justify-content-between">
                                <span class="btn-read-more small">Baca Selengkapnya <i class="fa-solid fa-arrow-right ms-1"></i></span>
                            </div>
                        </div>
                    </article>
                </div>
                `;
                container.append(html);
            });
        }

        function renderPagination(pagination) {
            const container = $('#pagination');
            container.empty();

            if (pagination.total_pages <= 1) return;

            const current = pagination.current_page;
            const total = pagination.total_pages;

            // Prev
            const prevDisabled = current === 1 ? 'disabled' : '';
            container.append(`<li class="page-item ${prevDisabled}"><a class="page-link" href="#" onclick="changePage(${current - 1}); return false;"><i class="fa-solid fa-chevron-left"></i></a></li>`);

            // Smart Pagination Logic (Ellipsis)
            const delta = 1;
            const range = [];
            for (let i = 1; i <= total; i++) {
                if (i === 1 || i === total || (i >= current - delta && i <= current + delta)) {
                    range.push(i);
                }
            }

            let l;
            for (let i of range) {
                if (l) {
                    if (i - l === 2) {
                        container.append(`<li class="page-item"><a class="page-link" href="#" onclick="changePage(${l + 1}); return false;">${l + 1}</a></li>`);
                    } else if (i - l !== 1) {
                        container.append(`<li class="page-item disabled"><span class="page-link border-0 bg-transparent">...</span></li>`);
                    }
                }
                const active = current === i ? 'active' : '';
                container.append(`<li class="page-item ${active}"><a class="page-link" href="#" onclick="changePage(${i}); return false;">${i}</a></li>`);
                l = i;
            }

            // Next
            const nextDisabled = current === total ? 'disabled' : '';
            container.append(`<li class="page-item ${nextDisabled}"><a class="page-link" href="#" onclick="changePage(${current + 1}); return false;"><i class="fa-solid fa-chevron-right"></i></a></li>`);
        }

        function changePage(page) {
            if (page < 1) return;
            currentState.page = page;
            loadArticles();
            if(window.innerWidth < 992) {
                // Mobile: scroll to list
                 $('html, body').animate({ scrollTop: $("#articleList").offset().top - 120 }, 500);
            } else {
                // Desktop: scroll to top
                 $('html, body').animate({ scrollTop: 0 }, 500);
            }
        }
    </script>
    <?php require_once 'includes/wa_tracker_script.php'; ?>
</body>
</html>
