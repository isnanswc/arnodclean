<?php
// admin/leads.php
require_once '../db.php';
require_once 'includes/auth.php';
require_once 'includes/csrf.php';
checkLogin();

// Generate Token
$csrfToken = generateCsrfToken();

$message = '';

// Handle Delete (POST)
if (isset($_POST['delete_id'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) die("CSRF Failure");
    
    $id = $_POST['delete_id'];
    $stmt = $pdo->prepare("DELETE FROM leads WHERE id = ?");
    if ($stmt->execute([$id])) {
        logActivity("Delete Lead", "Menghapus data lead ID: $id");
        $message = "Lead berhasil dihapus.";
    }
}

// Update Status (POST)
if (isset($_POST['update_status'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) die("CSRF Failure");

    $id = $_POST['id'];
    $status = $_POST['status'];
    $stmt = $pdo->prepare("UPDATE leads SET status = ? WHERE id = ?");
    if ($stmt->execute([$status, $id])) {
        logActivity("Update Lead Status", "Mengubah status lead ID: $id menjadi $status");
        $message = "Status lead diperbarui.";
    }
}

// Fetch Services for Filter/Form
$services = $pdo->query("SELECT id, title FROM services ORDER BY title ASC")->fetchAll();

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <div>
        <h1 class="h2 mb-1">Customer Leads & WhatsApp Tracker (CRM)</h1>
        <p class="text-muted small mb-0">Kelola prospek pelanggan dan analisa performa klik tombol WhatsApp secara real-time.</p>
    </div>
    <div class="btn-toolbar mb-2 mb-md-0 gap-2">
        <button type="button" class="btn btn-sm btn-outline-primary" id="btnScanAI">
            <i class="fa-solid fa-wand-magic-sparkles me-1"></i> Scan AI
        </button>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="loadAllAnalytics()">
            <i class="fa-solid fa-arrows-rotate me-1"></i> Refresh
        </button>
    </div>
</div>

<!-- KPI Summary Cards -->
<div class="row g-3 mb-4" id="kpiStatsContainer">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-primary text-white h-100">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="small fw-semibold text-white-50">Total Form Leads</span>
                    <i class="fa-solid fa-user-tag fs-4"></i>
                </div>
                <div class="fs-3 fw-bold" id="statTotalLeads">0</div>
                <div class="small text-white-50 mt-1">Data prospek di CRM</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-success text-white h-100">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="small fw-semibold text-white-50">Total Klik WhatsApp</span>
                    <i class="fa-brands fa-whatsapp fs-4"></i>
                </div>
                <div class="fs-3 fw-bold" id="statTotalWaClicks">0</div>
                <div class="small text-white-50 mt-1">Seluruh tombol WA disitus</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-info text-white h-100">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="small fw-semibold text-white-50">Klik WA Hari Ini</span>
                    <i class="fa-solid fa-calendar-day fs-4"></i>
                </div>
                <div class="fs-3 fw-bold" id="statTodayWaClicks">0</div>
                <div class="small text-white-50 mt-1">Respon visitor hari ini</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-dark text-white h-100">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="small fw-semibold text-white-50">Sumber Teratas</span>
                    <i class="fa-solid fa-chart-pie fs-4"></i>
                </div>
                <div class="fs-4 fw-bold text-truncate" id="statTopSource">-</div>
                <div class="small text-white-50 mt-1">Saluran konversi utama</div>
            </div>
        </div>
    </div>
</div>

<!-- Floating Alert for Unsorted Leads -->
<div id="aiAlert" class="alert alert-warning shadow-sm border-warning position-fixed bottom-0 end-0 m-4 d-none" style="z-index: 1050; max-width: 350px;">
    <div class="d-flex justify-content-between align-items-start">
        <div>
            <i class="fa-solid fa-triangle-exclamation me-2"></i>
            <span id="aiAlertText">Ada pesan belum disortir.</span>
        </div>
        <button type="button" class="btn-close btn-close-sm" onclick="$(this).closest('.alert').addClass('d-none')"></button>
    </div>
    <div class="mt-2 text-end">
        <button class="btn btn-sm btn-warning text-dark fw-bold" onclick="$('#btnScanAI').click()">Sortir Sekarang</button>
    </div>
</div>

<?php if ($message): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <?php echo $message; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Main Content Card with Navigation Tabs -->
<div class="card shadow-sm border-0">
    <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
        <ul class="nav nav-tabs card-header-tabs" id="mainTabs">
            <li class="nav-item">
                <a class="nav-link active fw-bold" href="#tab-leads" data-bs-toggle="tab" id="viewLeadsTab">
                    <i class="fa-solid fa-address-book me-1"></i> Data Lead CRM
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link fw-bold text-success" href="#tab-wa-tracker" data-bs-toggle="tab" id="viewWaTrackerTab">
                    <i class="fa-brands fa-whatsapp me-1"></i> Tracker Klik WhatsApp & Analisa
                </a>
            </li>
        </ul>
    </div>
    <div class="card-body">
        <div class="tab-content">
            <!-- TAB 1: CRM LEADS TABLE -->
            <div class="tab-pane fade show active" id="tab-leads">
                <!-- Sub Filter for AI Categories -->
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div class="btn-group btn-group-sm" id="leadTabs">
                        <button class="btn btn-outline-secondary active fw-bold" data-status="all">Semua Pesan</button>
                        <button class="btn btn-outline-success fw-bold" data-status="genuine"><i class="fa-solid fa-check-circle me-1"></i>Genuine</button>
                        <button class="btn btn-outline-danger fw-bold" data-status="spam"><i class="fa-solid fa-ban me-1"></i>Spam / Bot</button>
                    </div>

                    <div class="d-flex gap-2">
                        <div class="input-group input-group-sm" style="width: 250px;">
                            <span class="input-group-text bg-white border-end-0"><i class="fa-solid fa-search text-muted"></i></span>
                            <input type="text" id="searchInput" class="form-control border-start-0" placeholder="Cari nama, email, WA...">
                        </div>
                        <select id="statusFilter" class="form-select form-select-sm" style="width: 170px;" onchange="currentPage=1; loadData()">
                            <option value="">Filter Status</option>
                            <option value="new">Baru (New)</option>
                            <option value="contacted">Sudah Dihubungi</option>
                            <option value="closed">Selesai (Closed)</option>
                        </select>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th width="15%">Waktu</th>
                                <th width="20%">Pelanggan</th>
                                <th width="20%">Kontak</th>
                                <th width="25%">Pesan / Analisa AI</th>
                                <th width="10%">Status</th>
                                <th width="10%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <tr><td colspan="6" class="text-center py-4"><i class="fas fa-spinner fa-spin me-2"></i>Memuat data leads...</td></tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <nav aria-label="Page navigation" class="mt-3">
                    <ul class="pagination justify-content-center" id="pagination"></ul>
                </nav>
            </div>

            <!-- TAB 2: WA TRACKER & ANALYTICS -->
            <div class="tab-pane fade" id="tab-wa-tracker">
                <div class="row g-3 mb-4">
                    <!-- Source Breakdown Card -->
                    <div class="col-md-6">
                        <div class="card border shadow-sm h-100">
                            <div class="card-header bg-light py-2 fw-bold">
                                <i class="fa-solid fa-bullseye text-primary me-2"></i>Analisa Sumber Klik WA (Source)
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped mb-0 align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Saluran / Tombol WA</th>
                                                <th class="text-end">Jumlah Klik</th>
                                                <th class="text-end" width="30%">Persentase</th>
                                            </tr>
                                        </thead>
                                        <tbody id="waSourcesBody">
                                            <tr><td colspan="3" class="text-center py-3 text-muted"><i class="fas fa-spinner fa-spin me-1"></i>Memuat statistik...</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Top Pages Breakdown Card -->
                    <div class="col-md-6">
                        <div class="card border shadow-sm h-100">
                            <div class="card-header bg-light py-2 fw-bold">
                                <i class="fa-solid fa-file-lines text-success me-2"></i>Halaman Asal Pemicu WA (Top Pages)
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped mb-0 align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>URL Halaman / Artikel</th>
                                                <th class="text-end">Klik WA</th>
                                            </tr>
                                        </thead>
                                        <tbody id="waPagesBody">
                                            <tr><td colspan="2" class="text-center py-3 text-muted"><i class="fas fa-spinner fa-spin me-1"></i>Memuat statistik...</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Live Log Table -->
                <div class="card border shadow-sm">
                    <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                        <span class="fw-bold"><i class="fa-solid fa-list-check text-info me-2"></i>Log Aktivitas Klik WhatsApp Terbaru</span>
                        <div class="input-group input-group-sm" style="width: 220px;">
                            <input type="text" id="waLogSearchInput" class="form-control" placeholder="Cari IP, source, URL...">
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th width="15%">Waktu Klik</th>
                                        <th width="15%">Sumber (Source)</th>
                                        <th width="25%">Halaman Asal (Page URL)</th>
                                        <th width="15%">Referrer</th>
                                        <th width="15%">Perangkat / Lokasi</th>
                                        <th width="15%">IP Address</th>
                                    </tr>
                                </thead>
                                <tbody id="waLogTableBody">
                                    <tr><td colspan="6" class="text-center py-4"><i class="fas fa-spinner fa-spin me-2"></i>Memuat log aktivitas...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-white py-2">
                        <nav aria-label="Wa log pagination">
                            <ul class="pagination pagination-sm justify-content-center mb-0" id="waLogPagination"></ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal View Lead -->
<div class="modal fade" id="leadModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fa-solid fa-user-tag me-2"></i>Detail Lead CRM</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="leadDetailContent">
                <!-- Content via JS -->
            </div>
            <div class="modal-footer">
                <form method="POST" class="w-100 d-flex gap-2">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="id" id="modal_lead_id">
                    <input type="hidden" name="update_status" value="1">
                    <select name="status" id="modal_status" class="form-select form-select-sm">
                        <option value="new">Baru (New)</option>
                        <option value="contacted">Sudah Dihubungi</option>
                        <option value="closed">Selesai (Closed)</option>
                        <option value="spam">Spam</option>
                    </select>
                    <button type="submit" class="btn btn-sm btn-primary">Update Status</button>
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    let currentPage = 1;
    let currentWaLogPage = 1;
    const limit = 10;
    let currentAiTab = 'all';

    const serviceMap = <?php 
        $sMap = []; 
        foreach($services as $s) $sMap[$s['id']] = $s['title']; 
        echo json_encode($sMap); 
    ?>;
    
    $(document).ready(function() {
        loadAllAnalytics();
        
        // Tab Handlers
        $('#leadTabs button').on('click', function(e) {
            e.preventDefault();
            $('#leadTabs button').removeClass('active');
            $(this).addClass('active');
            currentAiTab = $(this).data('status');
            currentPage = 1;
            loadData();
        });

        $('#viewWaTrackerTab').on('click', function() {
            loadWaLogData();
        });

        // Search Debounce
        let timeout;
        $('#searchInput').on('input', function() {
            clearTimeout(timeout);
            timeout = setTimeout(() => { currentPage = 1; loadData(); }, 500);
        });

        let waTimeout;
        $('#waLogSearchInput').on('input', function() {
            clearTimeout(waTimeout);
            waTimeout = setTimeout(() => { currentWaLogPage = 1; loadWaLogData(); }, 500);
        });

        // Manual Scan AI Button
        $('#btnScanAI').on('click', function() {
            const btn = $(this);
            const originalText = btn.html();
            btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin me-1"></i> Scanning...');
            
            $.get('api/cron_classify_leads.php', function(res) {
                if(res.status === 'success') {
                    Swal.fire({
                        toast: true, position: 'top-end', icon: 'success', 
                        title: res.message, showConfirmButton: false, timer: 3000
                    });
                    loadData();
                } else {
                    Swal.fire('Info', res.message, 'info');
                }
            }, 'json').fail(function() {
                Swal.fire('Error', 'Gagal menghubungi server.', 'error');
            }).always(function() {
                btn.prop('disabled', false).html(originalText);
            });
        });
    });

    function loadAllAnalytics() {
        loadData();
        loadWaAnalytics();
        loadWaLogData();
    }

    function loadWaAnalytics() {
        $.ajax({
            url: 'api/get_data.php',
            data: { type: 'wa_analytics' },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success' && res.data) {
                    const d = res.data;
                    $('#statTotalLeads').text(d.total_crm_leads || 0);
                    $('#statTotalWaClicks').text(d.total_wa_clicks || 0);
                    $('#statTodayWaClicks').text(d.today_wa_clicks || 0);
                    
                    if (d.sources && d.sources.length > 0) {
                        $('#statTopSource').text(formatSourceLabel(d.sources[0].source));
                    } else {
                        $('#statTopSource').text('-');
                    }

                    // Render Sources Table
                    const sourcesBody = $('#waSourcesBody').empty();
                    if (!d.sources || d.sources.length === 0) {
                        sourcesBody.html('<tr><td colspan="3" class="text-center py-3 text-muted">Belum ada data klik WA.</td></tr>');
                    } else {
                        const total = d.total_wa_clicks || 1;
                        d.sources.forEach(s => {
                            const pct = Math.round((s.count / total) * 100);
                            sourcesBody.append(`
                                <tr>
                                    <td><span class="badge bg-light text-dark border me-1">${formatSourceLabel(s.source)}</span></td>
                                    <td class="text-end fw-bold">${s.count}</td>
                                    <td class="text-end">
                                        <div class="progress" style="height: 14px;">
                                            <div class="progress-bar bg-success" style="width: ${pct}%;">${pct}%</div>
                                        </div>
                                    </td>
                                </tr>
                            `);
                        });
                    }

                    // Render Pages Table
                    const pagesBody = $('#waPagesBody').empty();
                    if (!d.pages || d.pages.length === 0) {
                        pagesBody.html('<tr><td colspan="2" class="text-center py-3 text-muted">Belum ada data halaman.</td></tr>');
                    } else {
                        d.pages.forEach(p => {
                            pagesBody.append(`
                                <tr>
                                    <td class="text-truncate" style="max-width: 280px;" title="${escapeHtml(p.page_url)}">
                                        <a href="${escapeHtml(p.page_url)}" target="_blank" class="text-decoration-none text-dark">${escapeHtml(p.page_url)}</a>
                                    </td>
                                    <td class="text-end fw-bold text-success">${p.count}</td>
                                </tr>
                            `);
                        });
                    }
                }
            }
        });
    }

    function loadData() {
        const search = $('#searchInput').val();
        const status = $('#statusFilter').val();
        
        $.ajax({
            url: 'api/get_data.php',
            data: { 
                type: 'leads', 
                page: currentPage, 
                limit: limit, 
                search: search,
                ai_filter: currentAiTab 
            },
            dataType: 'json',
            success: function(response) {
                let data = response.data;
                if (status) data = data.filter(item => item.status === status);
                
                renderTable(data);
                renderPagination(response.pagination, '#pagination', 'changePage');
            },
            error: function() {
                $('#tableBody').html('<tr><td colspan="6" class="text-center text-danger">Gagal memuat data.</td></tr>');
            }
        });
    }

    function loadWaLogData() {
        const search = $('#waLogSearchInput').val();
        $.ajax({
            url: 'api/get_data.php',
            data: {
                type: 'wa_clicks',
                page: currentWaLogPage,
                limit: limit,
                search: search
            },
            dataType: 'json',
            success: function(response) {
                renderWaLogTable(response.data);
                renderPagination(response.pagination, '#waLogPagination', 'changeWaLogPage');
            },
            error: function() {
                $('#waLogTableBody').html('<tr><td colspan="6" class="text-center text-danger">Gagal memuat log.</td></tr>');
            }
        });
    }

    function renderTable(data) {
        const tbody = $('#tableBody').empty();
        let uncategorizedCount = 0;

        if (!data || data.length === 0) {
            tbody.html('<tr><td colspan="6" class="text-center py-4">Tidak ada data lead ditemukan.</td></tr>');
            $('#aiAlert').addClass('d-none');
            return;
        }

        data.forEach(item => {
            if (item.ai_status === 'uncategorized') uncategorizedCount++;

            const date = new Date(item.created_at).toLocaleDateString('id-ID', {day:'numeric', month:'short', hour:'2-digit', minute:'2-digit'});
            const statusBadge = getStatusBadge(item.status);
            const serviceName = serviceMap[item.service_id] || 'Umum';
            
            // AI Analysis Display
            let aiBadge = '';
            if (item.ai_status === 'genuine') aiBadge = `<span class="badge bg-success bg-opacity-10 text-success border border-success"><i class="fa-solid fa-check me-1"></i>Genuine ${item.ai_confidence}%</span>`;
            else if (item.ai_status === 'spam') aiBadge = `<span class="badge bg-danger bg-opacity-10 text-danger border border-danger"><i class="fa-solid fa-ban me-1"></i>Spam ${item.ai_confidence}%</span>`;
            else aiBadge = `<span class="badge bg-secondary bg-opacity-10 text-secondary border">Unsorted</span>`;

            const aiReason = item.ai_analysis ? `<div class="small text-muted mt-1 fst-italic border-start border-3 ps-2" style="font-size:0.75rem;">AI: "${escapeHtml(item.ai_analysis)}"</div>` : '';

            // Clean WA Chat link for Admin to follow-up directly
            const cleanWa = item.whatsapp ? item.whatsapp.replace(/[^0-9]/g, '') : '';
            const waChatMsg = encodeURIComponent(`Halo ${item.name}, terima kasih telah mengisi form di Arno D Clean. Ada yang bisa kami bantu mengenai pesanan Anda?`);
            const waChatUrl = cleanWa ? `https://wa.me/${cleanWa}?text=${waChatMsg}` : '#';

            const jsonItem = JSON.stringify(item).replace(/'/g, "&#39;");
            const row = `
                <tr>
                    <td><small class="text-muted">${date}</small></td>
                    <td>
                        <div class="fw-bold text-dark">${escapeHtml(item.name)}</div>
                        <div class="small text-muted">${serviceName}</div>
                    </td>
                    <td>
                        <div class="small">
                            ${item.whatsapp ? `<a href="${waChatUrl}" target="_blank" class="btn btn-xs btn-outline-success fw-bold py-0 px-2 rounded-pill"><i class="fa-brands fa-whatsapp me-1"></i>Chat WA</a> <span class="ms-1">${item.whatsapp}</span>` : ''}
                            ${item.email ? `<div class="text-muted mt-1"><i class="fa-solid fa-envelope me-1"></i>${item.email}</div>` : ''}
                        </div>
                    </td>
                    <td>
                        <div class="text-wrap" style="min-width: 220px;">
                            <div class="mb-1">${escapeHtml(item.message)}</div>
                            ${aiBadge} ${aiReason}
                        </div>
                    </td>
                    <td>${statusBadge}</td>
                    <td>
                        <div class="btn-group">
                            <button class="btn btn-sm btn-outline-primary" onclick='viewLead(${jsonItem})'><i class="fa-solid fa-eye"></i></button>
                            <button class="btn btn-sm btn-outline-danger" onclick="confirmDelete(${item.id})"><i class="fa-solid fa-trash"></i></button>
                        </div>
                    </td>
                </tr>
            `;
            tbody.append(row);
        });
        
        if (uncategorizedCount > 0 && currentAiTab === 'all') {
            $('#aiAlertText').text(`${uncategorizedCount} Pesan di halaman ini belum disortir.`);
            $('#aiAlert').removeClass('d-none');
        } else {
            $('#aiAlert').addClass('d-none');
        }
    }

    function renderWaLogTable(data) {
        const tbody = $('#waLogTableBody').empty();
        if (!data || data.length === 0) {
            tbody.html('<tr><td colspan="6" class="text-center py-4">Belum ada aktivitas klik WhatsApp tercatat.</td></tr>');
            return;
        }

        data.forEach(item => {
            const date = new Date(item.clicked_at).toLocaleDateString('id-ID', {day:'numeric', month:'short', hour:'2-digit', minute:'2-digit'});
            const deviceBadge = item.device === 'Mobile' ? '<span class="badge bg-info text-dark"><i class="fa-solid fa-mobile-screen me-1"></i>Mobile</span>' : '<span class="badge bg-secondary"><i class="fa-solid fa-desktop me-1"></i>Desktop</span>';
            const location = (item.city && item.city !== 'Unknown') ? `${item.city}, ${item.country}` : (item.country || 'Unknown');

            tbody.append(`
                <tr>
                    <td><small class="text-muted">${date}</small></td>
                    <td><span class="badge bg-success bg-opacity-10 text-success border border-success">${formatSourceLabel(item.source)}</span></td>
                    <td class="text-truncate" style="max-width: 220px;" title="${escapeHtml(item.page_url || '/')}">
                        <small>${escapeHtml(item.page_url || '/')}</small>
                    </td>
                    <td><small class="text-muted">${escapeHtml(item.referrer || 'Direct')}</small></td>
                    <td><small>${deviceBadge} ${escapeHtml(location)}</small></td>
                    <td><small class="text-muted">${escapeHtml(item.ip_address || '-')}</small></td>
                </tr>
            `);
        });
    }

    function formatSourceLabel(src) {
        switch(src) {
            case 'form_crm': return 'Form CRM Direct';
            case 'floating_widget': return 'Widget Melayang';
            case 'hero_button': return 'Tombol Hero';
            case 'service_card': return 'Kartu Layanan';
            case 'article_body': return 'Artikel CTA';
            case 'header_button': return 'Header Nav';
            case 'footer_button': return 'Footer Link';
            default: return src || 'Tombol WA';
        }
    }

    function getStatusBadge(status) {
        switch(status) {
            case 'new': return '<span class="badge bg-primary">Baru</span>';
            case 'contacted': return '<span class="badge bg-warning text-dark">Dihubungi</span>';
            case 'closed': return '<span class="badge bg-success">Selesai</span>';
            case 'spam': return '<span class="badge bg-secondary">Spam</span>';
            default: return '<span class="badge bg-light text-dark">'+status+'</span>';
        }
    }

    function viewLead(data) {
        const cleanWa = data.whatsapp ? data.whatsapp.replace(/[^0-9]/g, '') : '';
        const waChatMsg = encodeURIComponent(`Halo ${data.name}, terima kasih telah mengisi form di Arno D Clean.`);
        const waChatUrl = cleanWa ? `https://wa.me/${cleanWa}?text=${waChatMsg}` : '#';

        let content = `
            <div class="mb-3">
                <label class="small text-muted d-block">Nama Lengkap</label>
                <div class="fw-bold fs-5">${escapeHtml(data.name)}</div>
            </div>
            <div class="row mb-3">
                <div class="col-6">
                    <label class="small text-muted d-block">WhatsApp</label>
                    <div class="fw-bold">
                        ${data.whatsapp ? `<a href="${waChatUrl}" target="_blank" class="text-success text-decoration-none fw-bold"><i class="fa-brands fa-whatsapp me-1"></i>${data.whatsapp}</a>` : '-'}
                    </div>
                </div>
                <div class="col-6">
                    <label class="small text-muted d-block">Email</label>
                    <div class="fw-bold">${data.email || '-'}</div>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-6">
                    <label class="small text-muted d-block">Layanan Terkait</label>
                    <div class="fw-bold text-primary">${serviceMap[data.service_id] || 'Umum / Lainnya'}</div>
                </div>
                <div class="col-6">
                    <label class="small text-muted d-block">Halaman & Sumber Trafik</label>
                    <div class="small text-muted">${escapeHtml(data.page_url || '/')} (${escapeHtml(data.referrer || 'Direct')})</div>
                </div>
            </div>
            <div class="mb-3 p-3 bg-light rounded border">
                <label class="small text-muted d-block mb-1">Isi Pesan / Kebutuhan</label>
                <div style="white-space: pre-wrap;">${escapeHtml(data.message)}</div>
            </div>
            <div class="text-muted small text-end">Dikirim pada: ${data.created_at}</div>
        `;
        $('#leadDetailContent').html(content);
        $('#modal_lead_id').val(data.id);
        $('#modal_status').val(data.status);
        new bootstrap.Modal(document.getElementById('leadModal')).show();
    }

    function confirmDelete(id) {
        Swal.fire({
            title: 'Hapus Lead?',
            text: "Data akan dihapus permanen.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Hapus'
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'leads.php';
                
                const hiddenId = document.createElement('input');
                hiddenId.type = 'hidden';
                hiddenId.name = 'delete_id';
                hiddenId.value = id;
                
                const hiddenCsrf = document.createElement('input');
                hiddenCsrf.type = 'hidden';
                hiddenCsrf.name = 'csrf_token';
                hiddenCsrf.value = '<?php echo $csrfToken; ?>';

                form.appendChild(hiddenId);
                form.appendChild(hiddenCsrf);
                document.body.appendChild(form);
                form.submit();
            }
        });
    }

    function escapeHtml(text) {
        if (!text) return "";
        return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    function renderPagination(pagination, targetNav = '#pagination', callbackName = 'changePage') {
        const nav = $(targetNav).empty();
        if (!pagination || pagination.total_pages <= 1) return;
        const prevDisabled = pagination.current_page == 1 ? 'disabled' : '';
        nav.append(`<li class="page-item ${prevDisabled}"><a class="page-link" href="#" onclick="${callbackName}(${pagination.current_page - 1}); return false;">Prev</a></li>`);
        const total = pagination.total_pages; const current = pagination.current_page; const delta = 2; let range = [];
        for (let i = 1; i <= total; i++) { if (i == 1 || i == total || (i >= current - delta && i <= current + delta)) range.push(i); }
        let l;
        for (let i of range) {
            if (l) {
                if (i - l === 2) nav.append(`<li class="page-item"><a class="page-link" href="#" onclick="${callbackName}(${l + 1}); return false;">${l + 1}</a></li>`);
                else if (i - l !== 1) nav.append(`<li class="page-item disabled"><span class="page-link">...</span></li>`);
            }
            nav.append(`<li class="page-item ${current == i ? 'active' : ''}"><a class="page-link" href="#" onclick="${callbackName}(${i}); return false;">${i}</a></li>`);
            l = i;
        }
        const nextDisabled = pagination.current_page == total ? 'disabled' : '';
        nav.append(`<li class="page-item ${nextDisabled}"><a class="page-link" href="#" onclick="${callbackName}(${pagination.current_page + 1}); return false;">Next</a></li>`);
    }

    function changePage(page) {
        if(page < 1) return;
        currentPage = page;
        loadData();
    }

    function changeWaLogPage(page) {
        if(page < 1) return;
        currentWaLogPage = page;
        loadWaLogData();
    }
</script>

<?php require_once 'includes/footer.php'; ?>
