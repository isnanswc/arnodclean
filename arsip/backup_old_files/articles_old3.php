<?php
// admin/articles.php
require_once '../db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
checkLogin();

// Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $img = $pdo->query("SELECT image_path FROM articles WHERE id=$id")->fetchColumn();
    if($img && file_exists("../$img")) unlink("../$img");
    
    $pdo->prepare("DELETE FROM articles WHERE id=?")->execute([$id]);
    logActivity("Delete Artikel", "Menghapus artikel ID: $id");
    header("Location: articles.php?deleted=1");
    exit;
}

// Handle Save
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'] ?? '';
    $title = $_POST['title'];
    $content = $_POST['content'];
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));
    $image_path = $_POST['current_image'] ?? '';
    
    $error = null;

    // Image Upload
    if (!empty($_FILES['image']['name'])) {
        try {
            $uploaded = uploadAndResize($_FILES['image'], "../uploads/articles/", "uploads/articles/");
            if ($uploaded) {
                 if ($id && $image_path && file_exists("../".$image_path)) unlink("../".$image_path);
                 $image_path = $uploaded;
            }
        } catch (Exception $e) {
             $error = $e->getMessage();
        }
    }

    $status = $_POST['status'] ?? 'published';
    
    if (!$error) {
        if ($id) {
            $seo_title = $_POST['seo_title'] ?? null;
            $seo_description = $_POST['seo_description'] ?? null;
            $focus_keyword = $_POST['focus_keyword'] ?? null;
            
            // Server-side SEO Calculation (Reliable)
            $seoResult = calculateSeoScoreLocal($title, $slug, $seo_title ?: $title, $seo_description, $focus_keyword);
            $seo_score = $seoResult['score'];
            $seo_audit_log = json_encode(['critique' => $seoResult['critique']]);
            
            $stmt = $pdo->prepare("UPDATE articles SET title=?, slug=?, content=?, image_path=?, status=?, seo_title=?, seo_description=?, focus_keyword=?, seo_score=?, seo_audit_log=? WHERE id=?");
            // Re-generate clean slug if title changes (optional, but good practice to clean existing)
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));
            $stmt->execute([$title, $slug, $content, $image_path, $status, $seo_title, $seo_description, $focus_keyword, $seo_score, $seo_audit_log, $id]);
            
            $link = "";
            if ($status == 'published') {
                $siteUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
                $baseUrl = rtrim(dirname($_SERVER['PHP_SELF'], 2), '/\\');
                $link = $siteUrl . $baseUrl . "/blog/" . $slug;
            }
            
            logActivity("Update Artikel", "Mengubah artikel: $title", $link);
            
            // Update tags
            $pdo->prepare("DELETE FROM article_tags WHERE article_id=?")->execute([$id]);
            $_SESSION['swal'] = ['title' => 'Berhasil!', 'text' => 'Artikel diperbarui.', 'icon' => 'success'];
        } else {
            $seo_title = $_POST['seo_title'] ?? null;
            $seo_description = $_POST['seo_description'] ?? null;
            $focus_keyword = $_POST['focus_keyword'] ?? null;
            
            // Server-side SEO Calculation
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));
            $seoResult = calculateSeoScoreLocal($title, $slug, $seo_title ?: $title, $seo_description, $focus_keyword);
            $seo_score = $seoResult['score'];
            $seo_audit_log = json_encode(['critique' => $seoResult['critique']]);
            
            $userSource = 'User (' . ($_SESSION['admin_username'] ?? 'Admin') . ')';

            $stmt = $pdo->prepare("INSERT INTO articles (title, slug, content, image_path, status, seo_title, seo_description, focus_keyword, seo_score, seo_audit_log, content_source) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $slug, $content, $image_path, $status, $seo_title, $seo_description, $focus_keyword, $seo_score, $seo_audit_log, $userSource]);
            $id = $pdo->lastInsertId();

            $link = "";
            if ($status == 'published') {
                $siteUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
                $baseUrl = rtrim(dirname($_SERVER['PHP_SELF'], 2), '/\\');
                $link = $siteUrl . $baseUrl . "/blog/" . $slug;
            }

            logActivity("Create Artikel", "Membuat artikel: $title", $link);
            $_SESSION['swal'] = ['title' => 'Berhasil!', 'text' => ($status == 'published' ? 'Artikel diterbitkan.' : 'Artikel disimpan ke Draft.'), 'icon' => 'success'];
        }

        // Insert Tags
        if (isset($_POST['tags'])) {
            foreach ($_POST['tags'] as $tag_id) {
                $pdo->prepare("INSERT INTO article_tags (article_id, tag_id) VALUES (?, ?)")->execute([$id, $tag_id]);
            }
        }
        
        header("Location: articles.php");
        exit;
    } else {
        $_SESSION['swal'] = ['title' => 'Gagal!', 'text' => $error, 'icon' => 'error'];
    }
}


// Fetch all tags for the modal
$stmt = $pdo->query("SELECT * FROM tags ORDER BY name ASC");
$all_tags = $stmt->fetchAll();

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Kelola Artikel</h1>
    <button class="btn btn-primary shadow-sm" onclick="showAddModal()">
        <i class="fa-solid fa-plus me-2"></i> Tulis Artikel Baru
    </button>
</div>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold text-dark">Daftar Artikel</h5>
        <div class="input-group w-auto">
            <span class="input-group-text bg-light border-0"><i class="fa-solid fa-search text-muted"></i></span>
            <input type="text" id="searchInput" class="form-control bg-light border-0" placeholder="Cari artikel..." style="min-width: 250px;">
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light text-muted small text-uppercase">
                    <tr>
                        <th class="ps-4" style="width: 40%;">Artikel Info</th>
                        <th class="d-none d-md-table-cell" style="width: 15%;">Status</th>
                        <th class="d-none d-md-table-cell" style="width: 15%;">Stats</th>
                        <th class="d-none d-md-table-cell" style="width: 10%;">Links</th>
                        <th class="d-none d-md-table-cell" style="width: 15%;">SEO</th>
                        <th class="text-end pe-4" style="width: 15%;">Aksi</th>
                    </tr>
                </thead>
                <tbody id="tableBody" class="border-top-0">
                     <tr><td colspan="5" class="text-center py-5 text-muted">Memuat data...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white border-0 py-3">
        <nav aria-label="Page navigation" class="d-flex justify-content-center">
            <ul class="pagination pagination-sm mb-0 shadow-sm" id="pagination"></ul>
        </nav>
    </div>
</div>

<script>
    let currentPage = 1;
    const limit = 10;
    let articleCache = {};
    
    $(document).ready(function() {
        loadData();
        
        let timeout;
        $('#searchInput').on('input', function() {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                currentPage = 1;
                loadData();
            }, 500);
        });
    });

    function loadData() {
        const search = $('#searchInput').val();
        $.ajax({
            url: 'api/get_data.php',
            data: { type: 'articles', page: currentPage, limit: limit, search: search },
            dataType: 'json',
            success: function(response) {
                // Populate Cache
                articleCache = {};
                response.data.forEach(item => {
                    articleCache[item.id] = item;
                });

                renderTable(response.data);
                renderPagination(response.pagination);
            },
            error: function() {
                $('#tableBody').html('<tr><td colspan="4" class="text-center text-danger">Gagal memuat data.</td></tr>');
            }
        });
    }

    function renderTable(data) {
        const tbody = $('#tableBody');
        tbody.empty();
        
        if (data.length === 0) {
            tbody.html('<tr><td colspan="4" class="text-center">Tidak ada data ditemukan.</td></tr>');
            return;
        }

        data.forEach(item => {
            const date = new Date(item.created_at).toLocaleDateString('id-ID', {day: 'numeric', month: 'short', year:'numeric'});
            const img = item.image_path ? `../${item.image_path}` : '';
            let imgHtml = '<div class="bg-secondary bg-opacity-10 rounded d-flex align-items-center justify-content-center text-muted" style="width:50px; height:50px;"><i class="fa-regular fa-image"></i></div>';
            if (img) {
                let source = item.image_source || 'Unknown';
                let color = 'bg-secondary';
                
                // Fallback logic for legacy images
                if (!item.image_source) {
                    if (item.image_path.includes('nano_')) { source = 'Google'; color = 'bg-success'; }
                    else if (item.image_path.includes('ai_')) { source = 'AI Gen'; color = 'bg-primary'; }
                    else { source = 'Manual'; color = 'bg-info'; }
                } else {
                    // Modern Source Logic
                    if (source.toLowerCase().includes('pollinations')) color = 'bg-pink-subtle text-pink'; // Custom or just bg-danger
                    else if (source.toLowerCase().includes('google')) color = 'bg-success';
                    else if (source.toLowerCase().includes('pexels')) color = 'bg-info';
                    else if (source.toLowerCase().includes('hugging')) color = 'bg-warning text-dark';
                    else color = 'bg-primary';
                }

                imgHtml = `
                    <div class="position-relative overflow-hidden rounded" style="width: 50px; height: 50px;">
                        <img src="${img}" class="w-100 h-100" style="object-fit:cover;">
                        <span class="position-absolute bottom-0 start-0 w-100 text-center text-white small py-0" style="font-size: 8px; background: rgba(0,0,0,0.6); backdrop-filter: blur(2px);">
                            ${source.length > 8 ? source.substring(0,8) : source}
                        </span>
                    </div>`;
            }
            const views = item.views || 0;
            
            const tags = item.tag_ids ? item.tag_ids.toString().split(',') : [];
            const jsonItem = JSON.stringify(item).replace(/'/g, "&#39;").replace(/`/g, "\\`").replace(/\\/g, "\\\\");
            const jsonTags = JSON.stringify(tags);

            // Mobile Condensed Info
            const mobileInfo = `
                <div class="d-md-none mt-2 d-flex flex-wrap gap-2 align-items-center" style="font-size: 0.75rem;">
                    <span class="badge rounded-pill ${item.status === 'published' ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning'} px-2 py-0 border">
                        ${item.status === 'published' ? 'Pub' : 'Draft'}
                    </span>
                    <span class="text-muted"><i class="fa-solid fa-eye me-1"></i>${item.human_views + item.bot_views}</span>
                    ${item.seo_score ? `<span class="text-${item.seo_score >= 80 ? 'success' : (item.seo_score >= 50 ? 'warning' : 'danger')} fw-bold">SEO ${parseInt(item.seo_score)}</span>` : ''}
                </div>
            `;

            // Creator Badge Logic
            let creatorBadge = '';
            const cSource = item.content_source || '';
            if (cSource.includes('User')) {
                // Format: User (username) -> user: username
                const match = cSource.match(/User\s*\((.*?)\)/);
                const user = match ? match[1] : 'Manual';
                creatorBadge = `<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary-subtle small ms-2 fw-normal" style="font-size: 0.65rem; padding-bottom: 2px;">user: ${user}</span>`;
            } else if (cSource.includes('AI')) {
                // Format: AI (Provider) -> ai: provider
                const match = cSource.match(/AI\s*\((.*?)\)/);
                const provider = match ? match[1].toLowerCase() : 'ai';
                let aiColor = 'primary';
                if(provider.includes('groq')) aiColor = 'danger'; // differentiate groq
                creatorBadge = `<span class="badge bg-${aiColor} bg-opacity-10 text-${aiColor} border border-${aiColor}-subtle small ms-2 fw-normal" style="font-size: 0.65rem; padding-bottom: 2px;">ai: ${provider}</span>`;
            }

            const row = `
                <tr>
                    <td class="ps-4">
                        <div class="d-flex align-items-center">
                            <div class="me-3 position-relative flex-shrink-0">
                                ${imgHtml}
                            </div>
                            <div style="min-width: 0;">
                                <div class="article-title-responsive">${escapeHtml(item.title)}</div>
                                <div class="d-flex align-items-center text-muted small">
                                    <i class="fa-regular fa-calendar me-1"></i> ${date}
                                    ${creatorBadge}
                                </div>
                                ${mobileInfo}
                            </div>
                        </div>
                    </td>
                    <td class="d-none d-md-table-cell">
                        <span class="badge rounded-pill ${item.status === 'published' ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-warning-subtle text-warning border border-warning-subtle'} px-3 py-2 fw-normal">
                             ${item.status === 'published' ? '<i class="fa-solid fa-check me-1"></i> Published' : '<i class="fa-solid fa-file-pen me-1"></i> Draft'}
                        </span>
                    </td>
                    <td class="d-none d-md-table-cell">
                        <div class="d-flex flex-column small">
                            <div class="d-flex align-items-center mb-1" title="Human Visitors">
                                <i class="fa-solid fa-user text-primary me-2" style="width:16px"></i> 
                                <span class="fw-bold text-dark">${item.human_views}</span>
                            </div>
                            <div class="d-flex align-items-center text-muted" title="Bot Crawlers">
                                <i class="fa-solid fa-robot me-2" style="width:16px"></i> 
                                <span>${item.bot_views}</span>
                            </div>
                        </div>
                    </td>
                    <td class="d-none d-md-table-cell">
                        <span class="badge rounded-pill bg-light text-dark border">
                            <i class="fa-solid fa-link me-1"></i> ${item.internal_link_count || 0}
                        </span>
                    </td>
                    <td class="d-none d-md-table-cell">
                        ${(() => {
                            const score = item.seo_score ? parseInt(item.seo_score) : 0;
                            const color = score >= 100 ? 'success' : (score >= 80 ? 'info' : (score >= 60 ? 'warning' : 'danger'));
                            return `<div class="d-flex align-items-center cursor-pointer" onclick="showAnalytics(${item.id}, 'seo')">
                                <div class="progress w-100 me-2" style="height: 6px; width: 60px !important; background-color: #e9ecef;">
                                    <div class="progress-bar bg-${color}" role="progressbar" style="width: ${score}%"></div>
                                </div>
                                <span class="fw-bold text-${color} small">${score}</span>
                            </div>`;
                        })()}
                    </td>
                    <td class="text-end pe-4 text-nowrap">
                        <!-- Mobile Action Trigger -->
                        <div class="d-md-none">
                            <button class="btn btn-sm btn-light border" onclick="showMobileActions(${item.id}, '${escapeHtml(item.title).replace(/'/g, "\\'")}')">
                                <i class="fa-solid fa-ellipsis-vertical text-muted"></i>
                            </button>
                        </div>

                        <!-- Desktop Buttons: Made more visible -->
                        <div class="d-none d-md-flex justify-content-end gap-1">
                            <button class="btn btn-sm btn-outline-warning" data-bs-toggle="tooltip" title="Auto Link" onclick="autoLink(${item.id})">
                                <i class="fa-solid fa-wand-magic-sparkles"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-info" data-bs-toggle="tooltip" title="Analytic" onclick="showAnalytics(${item.id})">
                                <i class="fa-solid fa-chart-simple"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="Preview" onclick="showPreview(${item.id})">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="Edit" onclick="editArticle(${item.id})">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" data-bs-toggle="tooltip" title="Hapus" onclick="confirmDelete(${item.id})">
                                <i class="fa-regular fa-trash-can"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
            tbody.append(row);
        });
    }
    
    function escapeHtml(text) {
        if (!text) return "";
        return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    function renderPagination(pagination) {
        const nav = $('#pagination');
        nav.empty();
        if (pagination.total_pages <= 1) return;

        // Prev
        const prevDisabled = pagination.current_page == 1 ? 'disabled' : '';
        nav.append(`<li class="page-item ${prevDisabled}">
            <a class="page-link" href="#" onclick="changePage(${pagination.current_page - 1}); return false;">Prev</a>
        </li>`);

        // Smart Pagination Logic (1 ... 4 5 6 ... 100)
        const total = pagination.total_pages;
        const current = pagination.current_page;
        const delta = 2; // Number of pages around current
        
        let range = [];
        for (let i = 1; i <= total; i++) {
            if (i == 1 || i == total || (i >= current - delta && i <= current + delta)) {
                range.push(i);
            }
        }

        let l;
        for (let i of range) {
            if (l) {
                if (i - l === 2) {
                    nav.append(`<li class="page-item"><a class="page-link" href="#" onclick="changePage(${l + 1}); return false;">${l + 1}</a></li>`);
                } else if (i - l !== 1) {
                    nav.append(`<li class="page-item disabled"><span class="page-link">...</span></li>`);
                }
            }
            const active = current == i ? 'active' : '';
            nav.append(`<li class="page-item ${active}">
                <a class="page-link" href="#" onclick="changePage(${i}); return false;">${i}</a>
            </li>`);
            l = i;
        }

        // Next
        const nextDisabled = pagination.current_page == total ? 'disabled' : '';
        nav.append(`<li class="page-item ${nextDisabled}">
            <a class="page-link" href="#" onclick="changePage(${pagination.current_page + 1}); return false;">Next</a>
        </li>`);

        // Jump to Page Input
        nav.append(`
            <li class="page-item ms-2">
                <div class="input-group" style="width: 130px;">
                    <input type="number" class="form-control" id="pageJump" min="1" max="${total}" placeholder="Page">
                    <button class="btn btn-outline-secondary" type="button" onclick="jumpToPage()">Go</button>
                </div>
            </li>
        `);
    }

    function jumpToPage() {
        const p = parseInt($('#pageJump').val());
        if(p && p > 0) changePage(p);
    }

    function changePage(page) {
        if(page < 1) return;
        currentPage = page;
        loadData();
    }
</script>

<!-- Modal -->
<div class="modal fade" id="articleModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalTitle">Tulis Artikel</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="id" id="article_id">
                    <input type="hidden" name="current_image" id="current_image">
                    <input type="hidden" name="live_seo_score" id="live_seo_score">
                    
                    <div class="mb-3">
                        <label class="form-label">Judul Artikel</label>
                        <input type="text" class="form-control" name="title" id="title" required onkeyup="syncFocusKeyword(); updateSeoScore()">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Focus Keyword (Utama)</label>
                        <input type="text" class="form-control" name="focus_keyword" id="focus_keyword" placeholder="Contoh: Cara Membuat Website" onkeyup="updateSeoScore()">
                        <div class="form-text text-muted">Kata kunci utama untuk penilaian SEO.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Isi Konten</label>
                        <textarea class="form-control" name="content" id="summernote"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Status Artikel</label>
                        <select name="status" id="status" class="form-select">
                            <option value="published">Terbitkan (Publish)</option>
                            <option value="draft">Simpan sebagai Draft</option>
                        </select>
                    </div>

                    <!-- SEO Settings Section -->
                    <div class="card bg-light border-0 mb-3 shadow-none">
                        <div class="card-body p-3">
                            <h6 class="fw-bold mb-3 mt-0 text-primary"><i class="fa-solid fa-search me-2"></i>SEO Meta Settings (Opsional)</h6>
                            
                            <!-- Live Score Badge -->
                            <div class="d-flex align-items-center mb-3 p-2 bg-white rounded border">
                                <span class="badge bg-secondary fs-5 me-2" id="liveScoreBadge">0</span>
                                <div>
                                    <div class="fw-bold small">Live SEO Score</div>
                                    <small class="text-muted" id="liveScoreText">Isi Focus Keyword untuk menilai.</small>
                                </div>
                            </div>

                            <div class="mb-2">
                                <label class="form-label small mb-1">SEO Title Tag</label>
                                <input type="text" class="form-control form-control-sm" name="seo_title" id="seo_title" placeholder="Akan menggunakan Judul Artikel jika kosong" onkeyup="updateSeoScore()">
                                <div id="seoTitleFeedback" class="form-text small"></div>
                            </div>
                            <div>
                                <label class="form-label small mb-1">SEO Meta Description</label>
                                <textarea class="form-control form-control-sm" name="seo_description" id="seo_description" rows="2" placeholder="Ringkasan singkat untuk hasil pencarian Google" onkeyup="updateSeoScore()"></textarea>
                                <div id="seoDescFeedback" class="form-text small"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Summernote CSS/JS -->
                    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
                    <style>
                        /* Fix Summernote Fullscreen over Bootstrap Modal */
                        .note-editor.note-frame.fullscreen {
                            z-index: 9999 !important; /* Higher than Bootstrap Modal (1055) */
                            position: fixed;
                            top: 0; left: 0; width: 100%; height: 100%;
                            background: #fff;
                        }
                        
                        /* Mobile Responsive Title */
                        .article-title-responsive {
                            font-size: 0.95rem;
                            font-weight: bold;
                            color: #212529;
                            margin-bottom: 0.25rem;
                            /* Desktop: Allow Wrap */
                            white-space: normal; 
                            line-height: 1.4;
                        }
                        
                        @media (max-width: 768px) {
                            .article-title-responsive {
                                font-size: 0.85rem !important; /* Smaller text */
                            }
                        }
                    </style>
                    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
                    <script>
                        $(document).ready(function() {
                            $('#summernote').summernote({
                                placeholder: 'Tulis artikel menarik di sini...',
                                tabsize: 2,
                                height: 300,
                                toolbar: [
                                    ['style', ['style']],
                                    ['font', ['bold', 'underline', 'clear']],
                                    ['color', ['color']],
                                    ['para', ['ul', 'ol', 'paragraph']],
                                    ['table', ['table']],
                                    ['insert', ['link', 'picture', 'video']],
                                    ['view', ['fullscreen', 'codeview', 'help']]
                                ],
                                callbacks: {
                                    onImageUpload: function(files) {
                                        for(let i=0; i < files.length; i++) {
                                            uploadImage(files[i]);
                                        }
                                    }
                                }
                            });
                        });

                        function uploadImage(file) {
                            let data = new FormData();
                            data.append("file", file);
                            $.ajax({
                                url: 'upload_image.php',
                                cache: false,
                                contentType: false,
                                processData: false,
                                data: data,
                                type: "post",
                                success: function(url) {
                                    // Verify if it looks like a path (basic check)
                                    if(url.includes('uploads/')) {
                                        var image = $('<img>').attr('src', url);
                                        $('#summernote').summernote("insertNode", image[0]);
                                    } else {
                                        Swal.fire('Gagal', 'Respon server tidak valid: ' + url, 'error');
                                    }
                                },
                                error: function(jqXHR, textStatus, errorThrown) {
                                    console.error(textStatus + ": " + errorThrown);
                                    let msg = jqXHR.responseText || "Terjadi kesalahan saat upload.";
                                    Swal.fire('Gagal Upload', msg, 'error');
                                }
                            });
                        }

                        // Manual Validation on Submit
                        $('form').on('submit', function(e) {
                            if ($('#summernote').summernote('isEmpty')) {
                                Swal.fire('Gagal', 'Konten artikel tidak boleh kosong!', 'warning');
                                e.preventDefault();
                            }
                        });
                    </script>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Gambar Utama</label>
                            <input type="file" class="form-control" name="image" accept="image/*">
                            <div id="image_preview" class="mt-2 text-center"></div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tags</label>
                            <div class="card p-2" style="max-height: 150px; overflow-y: auto;">
                                <?php foreach($all_tags as $t): ?>
                                <div class="form-check">
                                    <input class="form-check-input tag-checkbox" type="checkbox" name="tags[]" value="<?php echo $t['id']; ?>" id="tag<?php echo $t['id']; ?>">
                                    <label class="form-check-label" for="tag<?php echo $t['id']; ?>">
                                        <?php echo htmlspecialchars($t['name']); ?>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" id="btnSave">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function resetForm() {
        document.getElementById('modalTitle').innerText = 'Tulis Artikel Baru';
        document.getElementById('btnSave').innerText = 'Terbitkan';
        document.getElementById('article_id').value = '';
        document.getElementById('current_image').value = '';
        document.getElementById('title').value = '';
        document.getElementById('status').value = 'published';
        document.getElementById('seo_title').value = '';
        document.getElementById('seo_title').value = '';
        document.getElementById('seo_description').value = '';
        document.getElementById('focus_keyword').value = '';
        document.getElementById('liveScoreBadge').innerText = '0';
        document.getElementById('liveScoreBadge').className = 'badge bg-secondary fs-5 me-2';
        document.getElementById('liveScoreText').innerText = 'Isi Focus Keyword untuk menilai.';
        $('#summernote').summernote('code', ''); // Reset Summernote instrument
        document.getElementById('image_preview').innerHTML = '';
        // Uncheck all tags
        document.querySelectorAll('.tag-checkbox').forEach(cb => cb.checked = false);
    }

    function showAddModal() {
        resetForm();
        new bootstrap.Modal(document.getElementById('articleModal')).show();
    }

    function editArticle(id) {
        const data = articleCache[id];
        if (!data) return;

        const tags = data.tag_ids ? data.tag_ids.toString().split(',') : [];

        resetForm();
        document.getElementById('modalTitle').innerText = 'Edit Artikel';
        document.getElementById('btnSave').innerText = 'Simpan Perubahan';
        
        document.getElementById('article_id').value = data.id;
        document.getElementById('current_image').value = data.image_path;
        document.getElementById('title').value = data.title;
        document.getElementById('status').value = data.status || 'published';
        document.getElementById('seo_title').value = data.seo_title || '';
        document.getElementById('seo_description').value = data.seo_description || '';
        document.getElementById('focus_keyword').value = data.focus_keyword || '';
        $('#summernote').summernote('code', data.content);

        // Trigger Score Update
        updateSeoScore();
        
        if (data.image_path) {
            document.getElementById('image_preview').innerHTML = '<img src="../'+data.image_path+'" height="80" class="rounded">';
        }
        
        // Check tags
        tags.forEach(tagId => {
            let el = document.getElementById('tag' + tagId);
            if(el) el.checked = true;
        });
        
        new bootstrap.Modal(document.getElementById('articleModal')).show();
    }

    function confirmDelete(id) {
        Swal.fire({
            title: 'Hapus Artikel?',
            text: "Tidak dapat dikembalikan!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Ya, Hapus!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `articles.php?delete=${id}`;
            }
        })
    }

    function autoLink(id) {
        Swal.fire({
            title: 'Generate Internal Links?',
            text: "Akan menambahkan 5 link artikel lain secara random ke bagian bawah konten.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Ya, Tambahkan!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({title: 'Memproses...', didOpen: () => Swal.showLoading()});
                $.ajax({
                    url: 'api/auto_internal_link.php',
                    method: 'POST',
                    data: {id: id},
                    dataType: 'json',
                    success: function(res) {
                        if(res.status === 'success') {
                            Swal.fire('Berhasil!', res.message, 'success');
                            loadData(); // Reload table to see new count
                        } else {
                            Swal.fire('Gagal', res.message, 'error');
                        }
                    },
                    error: function(err) {
                        Swal.fire('Error', 'Terjadi kesalahan sistem.', 'error');
                    }
                });
            }
        });
    }
    
    // --- Analytics & Preview Functions ---
    let analyticChart = null;

    function showAnalytics(id, tab = 'traffic') {
        const item = articleCache[id];
        
        // Show Loading
        $('#analyticModal').modal('show');
        $('#analyticContent').addClass('d-none');
        $('#analyticLoading').removeClass('d-none');

        // Switch to requested tab
        if(tab === 'seo') {
             $('#tab-seo-link').tab('show');
        } else {
             $('#tab-traffic-link').tab('show');
        }

        // --- LOAD SEO DATA (Instant from Cache) ---
        if(item) {
            $('#anlTitle').text(item.title);
            
            const score = item.seo_score ? parseInt(item.seo_score) : 0;
            let audit = {};
            try { audit = typeof item.seo_audit_log === 'string' ? JSON.parse(item.seo_audit_log) : item.seo_audit_log; } catch(e) {}
            const issues = audit && audit.critique ? audit.critique.split('\n') : [];
            const keyword = item.focus_keyword || '(Tidak diset)';

            // Circle Color
            const color = score >= 100 ? 'text-success' : (score >= 80 ? 'text-info' : (score >= 60 ? 'text-warning' : 'text-danger'));
            const circleColor = score >= 100 ? '#198754' : (score >= 80 ? '#0dcaf0' : (score >= 60 ? '#ffc107' : '#dc3545'));
            
            // Render SEO Tab
            $('#seoScoreDisplay').text(score).attr('class', 'display-3 fw-bold ' + color);
            $('#seoScoreLabel').text(score >= 80 ? 'Excellent' : (score >= 60 ? 'Good' : 'Needs Work')).attr('class', 'h5 mb-0 ' + color);
            $('#seoKeyword').text(keyword);
            $('#seoTitleText').text(item.seo_title || item.title);
            $('#seoDescText').text(item.seo_description || '-');

            let issueHtml = '';
            if (issues.length > 0 && issues[0] !== "") {
                issues.forEach(iss => {
                    if(iss) issueHtml += `<li class="list-group-item border-0 bg-transparent px-0 py-2 d-flex text-danger"><i class="fa-solid fa-circle-xmark me-2 mt-1"></i> <span>${escapeHtml(iss)}</span></li>`;
                });
            } else {
                issueHtml = `<li class="list-group-item border-0 bg-transparent px-0 py-2 d-flex text-success"><i class="fa-solid fa-check-circle me-2 mt-1"></i> <span>Sempurna! Tidak ada masalah ditemukan.</span></li>`;
            }
            $('#seoIssuesList').html(issueHtml);
        }

        // --- LOAD TRAFFIC DATA (Async) ---
        $.ajax({
            url: 'api/get_article_analytics.php',
            data: { id: id },
            dataType: 'json',
            success: function(res) {
                if(res.status === 'success') {
                    $('#analyticLoading').addClass('d-none');
                    $('#analyticContent').removeClass('d-none');
                    
                    // Stats
                    $('#anlTotal').text(res.article.total_views);
                    $('#anlWeek').html(`<span class="text-success fw-bold">${res.period_stats.week_human}</span> <small class="text-muted ms-1">Unique</small>`);
                    $('#anlMonth').html(`<span class="text-info fw-bold">${res.period_stats.month_human}</span> <small class="text-muted ms-1">Unique</small>`);
                    
                    // Chart
                    const ctx = document.getElementById('viewChart').getContext('2d');
                    if(analyticChart) analyticChart.destroy();
                    
                    analyticChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: res.chart.labels,
                            datasets: [
                                {
                                    label: 'Manusia',
                                    data: res.chart.human,
                                    borderColor: '#0d6efd',
                                    backgroundColor: 'rgba(13, 110, 253, 0.05)',
                                    fill: true,
                                    tension: 0.4,
                                    pointRadius: 3,
                                    pointHoverRadius: 5
                                },
                                {
                                    label: 'Bot/Crawler',
                                    data: res.chart.bot,
                                    borderColor: '#adb5bd',
                                    backgroundColor: 'transparent',
                                    borderDash: [4, 4],
                                    tension: 0.4,
                                    pointRadius: 0,
                                    pointHoverRadius: 0
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: { display: true, position: 'top', align: 'end', labels: { usePointStyle: true, boxWidth: 8 } },
                                tooltip: { mode: 'index', intersect: false }
                            },
                            scales: {
                                x: { grid: { display: false } },
                                y: { beginAtZero: true, grid: { borderDash: [2, 2] } }
                            }
                        }
                    });

                } else {
                    $('#analyticContent').html('<div class="alert alert-danger">Gagal memuat data analitik.</div>').removeClass('d-none');
                    $('#analyticLoading').addClass('d-none');
                }
            }
        });
    }

    function showPreview(id) {
        $('#previewModal').modal('show');
        $('#previewContent').html('<div class="text-center py-5"><i class="fas fa-spinner fa-spin"></i> Memuat...</div>');
        
        // Re-use analytic API to get content is fine, or simple get_data
        $.ajax({
            url: 'api/get_article_analytics.php', // reuse for convenience as it returns content
            data: { id: id },
            dataType: 'json',
            success: function(res) {
                 if(res.status === 'success') {
                     const a = res.article;
                     const img = a.image ? `<img src="../${a.image}" class="img-fluid rounded mb-3 w-100" style="max-height:300px; object-fit:cover">` : '';
                     const html = `
                        ${img}
                        <h2 class="fw-bold mb-3">${a.title}</h2>
                        <div class="article-body">${a.content}</div>
                     `;
                     $('#previewContent').html(html);
                 }
            }
        });
    }

    // --- SEO Logic ---


    function updateSeoScore() {
        const title = $('#title').val();
        const seoTitle = $('#seo_title').val() || title;
        const seoDesc = $('#seo_description').val() || '';
        const keyword = $('#focus_keyword').val().trim();

        if (!keyword) {
            $('#liveScoreBadge').attr('class', 'badge bg-secondary fs-5 me-2').text('?');
            $('#liveScoreText').text('Masukkan Focus Keyword untuk memulai penilaian.');
            $('#live_seo_score').val(0);
            return;
        }

        let score = 100;
        let issues = [];

        // Helper: Fuzzy Match (Simple Token check)
        // Note: This is a simplified JS version of the PHP logic
        function checkKeyword(text, kw) {
            if (!text) return false;
            text = text.toLowerCase();
            kw = kw.toLowerCase();
            if (text.includes(kw)) return true;
            
            // Token match
            const tokens = kw.split(' ');
            let found = 0;
            tokens.forEach(t => {
                if (text.includes(t)) found++;
            });
            return (found / tokens.length) >= 0.7;
        }

        // 1. Keyword in Title
        if (!checkKeyword(title, keyword)) {
            score -= 20;
            issues.push("Keyword tidak ada di Judul Artikel utama.");
        }

        // 2. SEO Title Length & Keyword
        const stLen = seoTitle.length;
        let stMsg = `${stLen} chars`;
        let stClass = 'text-success';
        
        if (stLen < 30 || stLen > 70) {
            score -= 10;
            stClass = 'text-danger';
            issues.push(`SEO Title panjangnya kritis (${stLen}). Ideal: 40-60.`);
        } else if (stLen > 60) {
            score -= 5;
            stClass = 'text-warning';
            issues.push(`SEO Title agak panjang (${stLen}). Ideal 40-60.`);
        }
        $('#seoTitleFeedback').attr('class', 'form-text small ' + stClass).text(stMsg);

        // 3. Meta Desc Length & Keyword
        const sdLen = seoDesc.length;
        let sdMsg = `${sdLen} chars`;
        let sdClass = 'text-success';
        
        if (sdLen < 100 || sdLen > 170) {
            score -= 10;
            sdClass = 'text-danger';
            issues.push(`Meta Desc panjangnya kritis (${sdLen}). Ideal: 100-160.`);
        } else if (sdLen > 160) {
            score -= 5;
            sdClass = 'text-warning';
            issues.push(`Meta Desc agak panjang (${sdLen}). Ideal 100-160.`);
        }
        
        if (!checkKeyword(seoDesc, keyword)) {
            score -= 20;
            issues.push("Keyword tidak ditemukan di Meta Description.");
        }
        
        $('#seoDescFeedback').attr('class', 'form-text small ' + sdClass).text(sdMsg);

        // Update UI
        let color = score >= 100 ? 'bg-success' : (score >= 80 ? 'bg-info' : (score >= 60 ? 'bg-warning' : 'bg-danger'));
        $('#liveScoreBadge').attr('class', `badge ${color} fs-5 me-2`).text(score);
        $('#live_seo_score').val(score);

        if (issues.length > 0) {
            $('#liveScoreText').html('<ul class="mb-0 ps-3 text-danger small" style="list-style-type:disc;">' + issues.map(i => `<li>${i}</li>`).join('') + '</ul>');
        } else {
            $('#liveScoreText').html('<span class="text-success fw-bold">Sempurna! Semua kriteria terpenuhi.</span>');
        }
    }

    // Auto-fill Focus Keyword from Title if empty
    function syncFocusKeyword() {
        // Only auto-fill if user hasn't typed their own keyword yet (or if it's currently matching the title)
        // For simplicity: If focus keyword is empty, copy title.
        const title = $('#title').val();
        const keyword = $('#focus_keyword').val();
        
        // Logic: If keyword is empty OR keyword starts with previous title
        if (!keyword || title.startsWith(keyword.substring(0, keyword.length-1))) {
             $('#focus_keyword').val(title);
        }
    }
</script>

<!-- Modal Analytics -->
<!-- Modal Analytics & SEO -->
<div class="modal fade" id="analyticModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-white border-bottom-0 pb-0">
                <div class="d-flex flex-column w-100">
                    <div class="d-flex justify-content-between align-items-start">
                        <h5 class="modal-title fw-bold text-dark pe-3 text-wrap" id="anlTitle" style="word-break: break-word;">Judul Artikel</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <!-- Tabs -->
                    <ul class="nav nav-pills mt-3 mb-2 gap-2" role="tablist">
                         <li class="nav-item">
                            <button class="nav-link active px-3 py-1 rounded-pill small fw-bold" id="tab-traffic-link" data-bs-toggle="tab" data-bs-target="#tab-traffic" type="button" style="font-size: 0.85rem;">
                                <i class="fa-solid fa-chart-area me-2"></i>Traffic
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link px-3 py-1 rounded-pill small fw-bold" id="tab-seo-link" data-bs-toggle="tab" data-bs-target="#tab-seo" type="button" style="font-size: 0.85rem;">
                                <i class="fa-solid fa-wand-magic-sparkles me-2"></i>SEO Health
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="modal-body bg-light p-4">
                <div id="analyticLoading" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
                <div id="analyticContent" class="d-none">
                    <div class="tab-content">
                        <!-- Tab Traffic -->
                        <div class="tab-pane fade show active" id="tab-traffic">
                            <div class="row g-3 mb-4">
                                <div class="col-4">
                                    <div class="card border-0 shadow-sm h-100 p-3 text-center rounded-4">
                                        <div class="text-muted small text-uppercase fw-bold mb-1" style="font-size: 0.65rem; letter-spacing: 1px;">Total Views</div>
                                        <h3 class="fw-bold text-dark mb-0" id="anlTotal">0</h3>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="card border-0 shadow-sm h-100 p-3 text-center rounded-4">
                                        <div class="text-muted small text-uppercase fw-bold mb-1" style="font-size: 0.65rem; letter-spacing: 1px;">7 Days</div>
                                        <div class="h4 mb-0" id="anlWeek">0</div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="card border-0 shadow-sm h-100 p-3 text-center rounded-4">
                                        <div class="text-muted small text-uppercase fw-bold mb-1" style="font-size: 0.65rem; letter-spacing: 1px;">30 Days</div>
                                        <div class="h4 mb-0" id="anlMonth">0</div>
                                    </div>
                                </div>
                            </div>
                            <div class="card border-0 shadow-sm rounded-4 p-3">
                                <canvas id="viewChart" style="max-height: 250px;"></canvas>
                            </div>
                        </div>

                        <!-- Tab SEO -->
                        <div class="tab-pane fade" id="tab-seo">
                            <div class="row">
                                <div class="col-md-5 text-center d-flex flex-column justify-content-center align-items-center mb-4 mb-md-0 border-end-md">
                                    <div class="position-relative d-inline-flex justify-content-center align-items-center mb-2" style="width: 120px; height: 120px;">
                                        <!-- Simple CSS Circle Background -->
                                        <div class="rounded-circle bg-white shadow-sm position-absolute w-100 h-100"></div>
                                        <div class="position-relative z-1">
                                            <div id="seoScoreDisplay">0</div>
                                        </div>
                                    </div>
                                    <div id="seoScoreLabel" class="text-muted">Unrated</div>
                                </div>
                                <div class="col-md-7 ps-md-4">
                                    <div class="mb-3">
                                        <div class="text-uppercase text-muted fw-bold small mb-1" style="font-size: 0.7rem; letter-spacing: 0.5px;">Focus Keyword</div>
                                        <div class="badge bg-light text-dark border px-3 py-2 fw-normal fs-6 text-wrap text-break" id="seoKeyword"></div>
                                    </div>
                                    
                                    <h6 class="fw-bold mb-2 text-dark"><i class="fa-solid fa-stethoscope me-2 text-primary"></i>Diagnosis</h6>
                                    <div class="card border-0 bg-white shadow-sm rounded-3">
                                        <ul class="list-group list-group-flush" id="seoIssuesList">
                                            <!-- Issues Populated Here -->
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-4 pt-3 border-top">
                                <div class="row g-3 small text-muted">
                                    <div class="col-md-6">
                                        <strong class="d-block text-dark">SEO Title</strong>
                                        <span id="seoTitleText" class="d-block text-wrap text-break"></span>
                                    </div>
                                    <div class="col-md-6">
                                        <strong class="d-block text-dark">Meta Description</strong>
                                        <span id="seoDescText" class="d-block text-wrap text-break" style="max-width: 100%;"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Preview -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Preview Artikel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="previewContent">
                <!-- Content here -->
            </div>
             <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>


        </div>
    </div>
</div>

<!-- Mobile Action Modal (Bottom Sheet Style) -->
<div class="modal fade" id="mobileActionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom-0 pb-0 justify-content-center">
                <div class="bg-secondary rounded-pill mb-2" style="width: 40px; height: 4px; opacity: 0.3;"></div>
            </div>
            <div class="modal-body pt-0 pb-4">
                <h6 class="text-center fw-bold mb-4 text-truncate" id="actionArticleTitle">Pilih Aksi</h6>
                
                <div class="d-grid gap-2">
                    <button class="btn btn-light border py-2 text-start px-3" onclick="triggerMobileAction('preview')">
                        <i class="fa-solid fa-eye me-3 text-secondary" style="width:20px"></i> Preview
                    </button>
                    <button class="btn btn-light border py-2 text-start px-3" onclick="triggerMobileAction('analytics')">
                        <i class="fa-solid fa-chart-line me-3 text-info" style="width:20px"></i> Analytics
                    </button>
                    <button class="btn btn-light border py-2 text-start px-3" onclick="triggerMobileAction('edit')">
                        <i class="fa-solid fa-pen me-3 text-primary" style="width:20px"></i> Edit Artikel
                    </button>
                    <hr class="my-1">
                    <button class="btn btn-light border py-2 text-start px-3 text-danger" onclick="triggerMobileAction('delete')">
                        <i class="fa-solid fa-trash me-3" style="width:20px"></i> Hapus Artikel
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let selectedArticleId = 0;
    
    function showMobileActions(id, title) {
        selectedArticleId = id;
        $('#actionArticleTitle').text(title);
        new bootstrap.Modal(document.getElementById('mobileActionModal')).show();
    }

    function triggerMobileAction(action) {
        // Hide modal first
        $('#mobileActionModal').modal('hide');
        const id = selectedArticleId;
        
        setTimeout(() => {
            if(action === 'preview') showPreview(id);
            if(action === 'analytics') showAnalytics(id);
            if(action === 'edit') editArticle(id);
            if(action === 'delete') confirmDelete(id);
        }, 300); // Wait for modal close transition
    }
</script>

<?php 
if (isset($_SESSION['swal'])) {
    $s = $_SESSION['swal'];
    echo "<script>Swal.fire('{$s['title']}', '{$s['text']}', '{$s['icon']}');</script>";
    unset($_SESSION['swal']);
}
if (isset($_GET['deleted'])) {
    echo "<script>Swal.fire('Terhapus!', 'Artikel berhasil dihapus.', 'success');</script>";
}
require_once 'includes/footer.php'; 
?>
