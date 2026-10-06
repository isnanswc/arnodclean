<?php
// admin/reporting.php
require_once '../db.php';
require_once 'includes/auth.php';
checkLogin();

// Handle Settings Save
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_report_settings'])) {
    $reportTime = $_POST['tg_report_time'] ?? '';
    
    // Handle Checkboxes array
    $reportConfig = $_POST['json_arr_tg_report_config'] ?? [];
    $jsonConfig = json_encode($reportConfig);
    
    try {
        // Save Time
        $pdo->prepare("INSERT INTO auto_content_settings (setting_key, setting_value) VALUES ('tg_report_time', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")
            ->execute([$reportTime]);
            
        // Save Config
        $pdo->prepare("INSERT INTO auto_content_settings (setting_key, setting_value) VALUES ('tg_report_config', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")
            ->execute([$jsonConfig]);
            
        $_SESSION['swal'] = ['title' => 'Tersimpan', 'text' => 'Pengaturan laporan berhasil diperbarui.', 'icon' => 'success'];
        header("Location: reporting.php");
        exit;
    } catch (Exception $e) {
        $_SESSION['swal'] = ['title' => 'Error', 'text' => $e->getMessage(), 'icon' => 'error'];
    }
}

require_once 'includes/header.php';
?>

<style>
    .growth-indicator { font-size: 0.8rem; font-weight: 600; padding: 2px 6px; border-radius: 4px; display: inline-flex; align-items: center; gap: 4px; }
    .growth-up { background-color: #d1e7dd; color: #0f5132; }
    .growth-down { background-color: #f8d7da; color: #842029; }
    .growth-neutral { background-color: #e2e3e5; color: #41464b; }
    
    .ai-insight-box {
        background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
        border-left: 5px solid #0ea5e9;
        color: #0c4a6e;
    }
    
    .bg-soft-primary { background-color: #eff6ff; }
    .hover-shadow:hover { box-shadow: 0 .5rem 1rem rgba(0,0,0,.05)!important; border-color: #cbd5e1!important; }
    .transition-all { transition: all 0.2s ease-in-out; }
    .cursor-pointer { cursor: pointer; }
    .hover-lift:hover { transform: translateY(-1px); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); }
    .ls-1 { letter-spacing: 1px; }
    .text-xs { font-size: 0.75rem; }

    /* Heatmap Grid Custom Styles */
    .heatmap-grid {
        display: grid;
        grid-template-columns: 70px repeat(24, 1fr);
        gap: 3px;
        font-size: 0.75rem;
    }
    .heatmap-header {
        text-align: center;
        font-weight: 600;
        color: #64748b;
        padding: 2px 0;
    }
    .heatmap-day-label {
        font-weight: 600;
        color: #334155;
        display: flex;
        align-items: center;
    }
    .heatmap-cell {
        aspect-ratio: 1;
        border-radius: 4px;
        background: #f1f5f9;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 0.7rem;
        cursor: pointer;
        transition: transform 0.15s ease;
    }
    .heatmap-cell:hover {
        transform: scale(1.2);
        z-index: 10;
        box-shadow: 0 4px 6px rgba(0,0,0,0.15);
    }
</style>

<?php
// Fetch Current Settings
$currSettings = $pdo->query("SELECT setting_key, setting_value FROM auto_content_settings WHERE setting_key IN ('tg_report_time', 'tg_report_config')")->fetchAll(PDO::FETCH_KEY_PAIR);
$currTime = $currSettings['tg_report_time'] ?? '';
$currConfig = json_decode($currSettings['tg_report_config'] ?? '[]', true);
if(!is_array($currConfig)) $currConfig = ['leads', 'traffic', 'ai_insight', 'articles'];
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-3 mb-3 border-bottom">
    <div>
        <h1 class="h3 fw-bold mb-1"><i class="fa-solid fa-chart-pie text-primary me-2"></i>Laporan Lanjutan & Analytics</h1>
        <p class="text-muted mb-0">Analisa mendalam lalu lintas visitor, performa konversi WhatsApp, dan statistik harian.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <button class="btn btn-outline-secondary fw-semibold small" type="button" data-bs-toggle="modal" data-bs-target="#reportSettingsModal">
            <i class="fa-solid fa-gear me-1"></i> Konfigurasi Telegram
        </button>

        <div id="reportrange" class="bg-white border shadow-sm rounded px-3 py-2 cursor-pointer d-flex align-items-center" style="min-width: 240px;">
            <i class="fa-solid fa-calendar-days text-primary me-2"></i>
            <span class="fw-semibold small"></span> <i class="fa-solid fa-caret-down ms-auto text-muted"></i>
        </div>
        
        <div class="btn-group shadow-sm">
            <button class="btn btn-white border fw-semibold small" onclick="exportPDF()">
                <i class="fa-solid fa-file-pdf text-danger me-1"></i> PDF
            </button>
            <button class="btn btn-white border fw-semibold small" onclick="exportExcel()">
                <i class="fa-solid fa-file-excel text-success me-1"></i> Excel
            </button>
        </div>
    </div>
</div>

<!-- Report Settings Modal -->
<div class="modal fade" id="reportSettingsModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom-0 pb-0">
                <div>
                    <h5 class="modal-title fw-bold text-dark"><i class="fa-brands fa-telegram text-primary me-2"></i>Konfigurasi Laporan Otomatis</h5>
                    <p class="text-muted small mb-0 ms-4 ps-1">Atur jadwal dan konten laporan harian Telegram Anda.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="save_report_settings" value="1">
                <div class="modal-body p-4">
                    <div class="card border-0 bg-soft-primary mb-4 rounded-3 overflow-hidden">
                        <div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-3">
                            <div class="d-flex align-items-center">
                                <div class="bg-white p-3 rounded-circle shadow-sm me-3 text-primary">
                                    <i class="fa-solid fa-clock fa-lg"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-1">Jadwal Pengiriman</h6>
                                    <div class="small text-muted">Tentukan waktu pengiriman laporan harian.</div>
                                </div>
                            </div>
                            <div class="flex-grow-1" style="max-width: 200px;">
                                <div class="input-group shadow-sm">
                                    <span class="input-group-text bg-white border-end-0"><i class="fa-regular fa-clock text-secondary"></i></span>
                                    <input type="time" name="tg_report_time" class="form-control border-start-0 fw-bold text-center" value="<?php echo htmlspecialchars($currTime); ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <h6 class="fw-bold text-secondary text-uppercase small ls-1 mb-3"><i class="fa-solid fa-list-check me-2"></i>Konten Laporan</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="card h-100 border rounded-3 p-3 cursor-pointer hover-shadow transition-all d-flex flex-row align-items-center gap-3">
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" name="json_arr_tg_report_config[]" value="leads" <?php echo in_array('leads', $currConfig) ? 'checked' : ''; ?>>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark"><i class="fa-solid fa-envelope-open-text text-warning me-1"></i> Leads & Pesan</div>
                                    <div class="text-xs text-muted">Total pesan masuk, spam, dan genuine leads.</div>
                                </div>
                            </label>
                        </div>
                        <div class="col-md-6">
                            <label class="card h-100 border rounded-3 p-3 cursor-pointer hover-shadow transition-all d-flex flex-row align-items-center gap-3">
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" name="json_arr_tg_report_config[]" value="traffic" <?php echo in_array('traffic', $currConfig) ? 'checked' : ''; ?>>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark"><i class="fa-solid fa-chart-line text-success me-1"></i> Traffic Stats</div>
                                    <div class="text-xs text-muted">Total visitor, unique user, dan tren harian.</div>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light d-flex justify-content-between align-items-center px-4 py-3">
                    <button type="button" class="btn btn-white border fw-medium text-dark hover-lift" onclick="sendReportNow()">
                        <i class="fa-solid fa-paper-plane me-2 text-success"></i>Kirim Sekarang
                    </button>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-link text-muted text-decoration-none" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm">Simpan Perubahan</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="reportContent">
    
    <!-- AI Smart Insight -->
    <div class="card mb-4 border-0 shadow-sm ai-insight-box" id="aiBox" style="display:none;">
        <div class="card-body py-3 d-flex align-items-start gap-3">
            <div class="bg-white p-2 rounded-circle shadow-sm text-info">
                 <i class="fa-solid fa-robot fa-lg"></i>
            </div>
            <div>
                <h6 class="fw-bold mb-1">AI Executive Insight</h6>
                <p class="mb-0 small" id="aiSummaryText">Menganalisa data...</p>
            </div>
        </div>
    </div>

    <!-- Row 1: Key Metrics -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <small class="text-muted fw-bold text-uppercase" style="font-size: 0.7rem;">Total Views</small>
                        <span id="growth_views" class="growth-indicator"></span>
                    </div>
                    <h3 class="fw-bold mb-0 mt-2" id="val_total_views">-</h3>
                    <div class="small text-muted mt-2 border-top pt-2">
                        <i class="fa-solid fa-eye text-primary me-1"></i> Total Halaman Dilihat
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body p-3">
                     <div class="d-flex justify-content-between align-items-start">
                        <small class="text-muted fw-bold text-uppercase" style="font-size: 0.7rem;">Unique Visitor</small>
                        <span id="growth_unique" class="growth-indicator"></span>
                    </div>
                    <h3 class="fw-bold mb-0 mt-2" id="val_unique">-</h3>
                    <div class="small text-muted mt-2 border-top pt-2">
                        <i class="fa-solid fa-user-check text-success me-1"></i> Pengunjung Unik
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-6 col-md-3">
            <div class="card h-100 border-0 shadow-sm bg-gradient-success-soft">
                <div class="card-body p-3">
                    <small class="text-success fw-bold text-uppercase" style="font-size: 0.7rem;">Klik WhatsApp</small>
                    <h3 class="fw-bold mb-0 mt-2 text-success"><span id="val_total_wa_clicks">-</span></h3>
                    <div class="small text-muted mt-2 border-top pt-2">
                        <span class="text-success fw-bold" id="val_conversion">-</span>% Konversi WA
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body p-3">
                    <small class="text-muted fw-bold text-uppercase" style="font-size: 0.7rem;">Bounce Rate</small>
                    <h3 class="fw-bold mb-0 mt-2"><span id="val_bounce">-</span>%</h3>
                    <div class="small text-muted mt-2 border-top pt-2">
                         <i class="fa-solid fa-right-from-bracket text-warning me-1"></i> Keluar Cepat
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Row 2: Heatmap Hari & Jam (NEW FEATURE) -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h5 class="fw-bold mb-0"><i class="fa-solid fa-fire text-danger me-2"></i>Heatmap Aktivitas Hari & Jam (Peak Hours Matrix)</h5>
                <p class="text-muted small mb-0">Visualisasi jam & hari tersibuk dari kunjungan pengunjung dan konversi klik WhatsApp.</p>
            </div>
            <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-primary active" id="btnHeatmapTraffic" onclick="switchHeatmap('traffic')">
                    <i class="fa-solid fa-users me-1"></i> Traffic Visitor
                </button>
                <button type="button" class="btn btn-outline-success" id="btnHeatmapWa" onclick="switchHeatmap('wa')">
                    <i class="fa-brands fa-whatsapp me-1"></i> Klik WhatsApp
                </button>
            </div>
        </div>
        <div class="card-body">
            <!-- Heatmap Matrix Table -->
            <div class="table-responsive mb-3">
                <div class="heatmap-grid" id="heatmapGridContainer" style="min-width: 780px;">
                    <!-- Rendered dynamically by JS -->
                </div>
            </div>

            <!-- Heatmap Bar Chart alternative -->
            <div style="height: 220px;">
                <canvas id="heatmapChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Row 3: Traffic Trends -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0"><i class="fa-solid fa-chart-line text-primary me-2"></i>Traffic Trends & Forecast 7 Hari</h5>
            <span class="badge bg-light text-primary border"><i class="fa-solid fa-wand-magic-sparkles me-1"></i> AI Forecast</span>
        </div>
        <div class="card-body">
            <div style="height: 280px;">
                <canvas id="trafficChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Row 4: Detailed WA Click Logs (NEW FEATURE) -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h5 class="fw-bold mb-0 text-success"><i class="fa-brands fa-whatsapp me-2"></i>Detail Laporan Klik WhatsApp (Visitor & Form Leads)</h5>
                <p class="text-muted small mb-0">Rincian lengkap setiap tombol WA yang diklik pengunjung diseluruh halaman dan artikel.</p>
            </div>
            <div class="d-flex gap-2">
                <div class="input-group input-group-sm" style="width: 220px;">
                    <span class="input-group-text bg-white"><i class="fa-solid fa-search text-muted"></i></span>
                    <input type="text" id="waLogSearch" class="form-control" placeholder="Cari IP, artikel, tombol..." onkeyup="filterWaLogs()">
                </div>
                <select id="waDeviceFilter" class="form-select form-select-sm" style="width: 130px;" onchange="filterWaLogs()">
                    <option value="">Semua Perangkat</option>
                    <option value="Mobile">Mobile</option>
                    <option value="Desktop">Desktop</option>
                </select>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="waLogsReportTable">
                    <thead class="table-light">
                        <tr>
                            <th width="5%" class="ps-3">No</th>
                            <th width="15%">Tanggal & Jam</th>
                            <th width="35%">Tombol yang Diklik / Halaman / Artikel</th>
                            <th width="15%">No IP Address</th>
                            <th width="15%">Asal Wilayah</th>
                            <th width="15%">Perangkat</th>
                        </tr>
                    </thead>
                    <tbody id="waLogsReportBody">
                        <tr><td colspan="6" class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin me-1"></i>Memuat laporan WA...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white py-2 d-flex justify-content-between align-items-center flex-wrap">
            <small class="text-muted" id="waLogSummaryText">Menampilkan 0 log</small>
            <nav aria-label="Wa log navigation">
                <ul class="pagination pagination-sm mb-0" id="waLogNav"></ul>
            </nav>
        </div>
    </div>

    <!-- Row 5: Details (Top Pages, Cities, Devices, Sources) -->
    <div class="row g-4 mb-4">
        <div class="col-lg-4 col-md-6">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="fw-bold mb-0">Halaman Terpopuler (Top Pages)</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="topPagesTable">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-3">URL Halaman</th>
                                <th class="text-end pe-3">Views</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4 col-md-6">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="fw-bold mb-0">Kota Pengunjung (Top Cities)</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="topCitiesTable">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-3">Kota</th>
                                <th class="text-end pe-3">Visitor</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4 col-md-12">
             <div class="d-flex flex-column gap-4 h-100">
                <div class="card border-0 shadow-sm flex-grow-1">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="fw-bold mb-0">Perangkat (Devices)</h5>
                    </div>
                    <div class="card-body d-flex justify-content-center">
                        <div style="height: 140px; width: 100%;">
                            <canvas id="deviceChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm flex-grow-1">
                     <div class="card-header bg-white border-0 py-3">
                        <h5 class="fw-bold mb-0">Sumber Trafik (Referrer)</h5>
                    </div>
                    <div class="card-body d-flex justify-content-center">
                        <div style="height: 140px; width: 100%;">
                            <canvas id="sourceChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>

<script>
    let rawReportData = null;
    let currentHeatmapType = 'traffic';
    let currentWaLogsPage = 1;
    const waLogLimit = 10;
    let filteredWaLogs = [];

    let trafficChart, heatmapChart, deviceChart, sourceChart;

    $(function() {
        var start = moment().subtract(29, 'days');
        var end = moment();

        function cb(start, end) {
            $('#reportrange span').html(start.format('MMMM D, YYYY') + ' - ' + end.format('MMMM D, YYYY'));
            loadReportData(start.format('YYYY-MM-DD'), end.format('YYYY-MM-DD'));
        }

        $('#reportrange').daterangepicker({
            startDate: start,
            endDate: end,
            ranges: {
               'Hari Ini': [moment(), moment()],
               'Kemarin': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
               '7 Hari Terakhir': [moment().subtract(6, 'days'), moment()],
               '30 Hari Terakhir': [moment().subtract(29, 'days'), moment()],
               'Bulan Ini': [moment().startOf('month'), moment().endOf('month')],
               'Bulan Lalu': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
            }
        }, cb);

        cb(start, end);
    });

    function loadReportData(start, end) {
        $.ajax({
            url: 'api/get_report_data.php',
            data: { start_date: start, end_date: end },
            dataType: 'json',
            success: function(res) {
                if(res.error) {
                    Swal.fire('Error', res.error, 'error');
                    return;
                }
                rawReportData = res;
                updateUI(res);
            },
            error: function() {
                Swal.fire('Error', 'Gagal memuat data laporan.', 'error');
            }
        });
    }

    function updateUI(data) {
        if(data.smart_summary) {
            $('#aiBox').fadeIn();
            $('#aiSummaryText').html(data.smart_summary);
        }

        $('#val_total_views').text(data.summary.total_views.toLocaleString());
        $('#val_unique').text(data.summary.unique_visitors.toLocaleString());
        $('#val_bounce').text(data.summary.bounce_rate);
        $('#val_conversion').text(data.summary.conversion_rate);
        $('#val_total_wa_clicks').text(data.summary.total_wa_clicks || (data.wa_logs ? data.wa_logs.length : 0));

        renderGrowth('#growth_views', data.growth.total_views);
        renderGrowth('#growth_unique', data.growth.unique_visitors);

        // 1. Render Traffic Chart
        let dates = data.charts.traffic.map(d => moment(d.date).format('MMM D'));
        let totals = data.charts.traffic.map(d => d.total);
        let uniques = data.charts.traffic.map(d => d.unique_visits);
        let forecastData = new Array(totals.length).fill(null);

        if(data.forecast && data.forecast.length > 0) {
            const lastVal = totals[totals.length-1];
            forecastData[totals.length-1] = lastVal; 
            data.forecast.forEach(f => {
                dates.push(moment(f.date).format('MMM D'));
                totals.push(null);
                uniques.push(null);
                forecastData.push(f.val);
            });
        }

        if(trafficChart) trafficChart.destroy();
        trafficChart = new Chart(document.getElementById('trafficChart'), {
            type: 'line',
            data: {
                labels: dates,
                datasets: [
                    { label: 'Total Views', data: totals, borderColor: '#0d6efd', backgroundColor: 'rgba(13,110,253,0.1)', fill: true, tension: 0.3 },
                    { label: 'Forecast 7 Hari', data: forecastData, borderColor: '#0ea5e9', backgroundColor: 'transparent', borderDash: [5, 5], tension: 0.3 },
                    { label: 'Unique Visitors', data: uniques, borderColor: '#198754', backgroundColor: 'transparent', borderDash: [2, 2], tension: 0.3 }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { position: 'top' } }
            }
        });

        // 2. Render Heatmap Matrix Grid & Bar Chart
        renderHeatmapGrid();

        // 3. Render WA Detailed Logs Table
        filteredWaLogs = data.wa_logs || [];
        filterWaLogs();

        // 4. Top Pages
        const tbodyPages = $('#topPagesTable tbody').empty();
        data.top_pages.forEach(p => {
            tbodyPages.append(`
                <tr>
                    <td class="ps-3 small text-truncate" style="max-width: 180px;" title="${p.page_url}">${p.page_url}</td>
                    <td class="text-end pe-3 fw-bold">${p.views}</td>
                </tr>
            `);
        });

        // 5. Top Cities
        const tbodyCities = $('#topCitiesTable tbody').empty();
        if(data.top_cities && data.top_cities.length > 0){
             data.top_cities.forEach(c => {
                tbodyCities.append(`
                    <tr>
                        <td class="ps-3 small">${c.city}</td>
                        <td class="text-end pe-3 fw-bold">${c.visitors}</td>
                    </tr>
                `);
            });
        } else {
             tbodyCities.append(`<tr><td colspan="2" class="text-center text-muted small py-3">Belum ada data lokasi</td></tr>`);
        }

        // 6. Device Chart
        const devLabels = data.devices.map(d => d.device);
        const devData = data.devices.map(d => d.count);
        if(deviceChart) deviceChart.destroy();
        deviceChart = new Chart(document.getElementById('deviceChart'), {
            type: 'doughnut',
            data: { labels: devLabels, datasets: [{ data: devData, backgroundColor: ['#0d6efd', '#ffc107', '#198754', '#6c757d'] }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right' } } }
        });

        // 7. Source Chart
        if(data.top_sources) {
            const srcLabels = data.top_sources.map(s => s.source);
            const srcData = data.top_sources.map(s => s.visits);
            if(sourceChart) sourceChart.destroy();
            sourceChart = new Chart(document.getElementById('sourceChart'), {
                type: 'pie',
                data: { labels: srcLabels, datasets: [{ data: srcData, backgroundColor: ['#6610f2', '#d63384', '#fd7e14', '#20c997', '#6c757d'] }] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right' } } }
            });
        }
    }

    function switchHeatmap(type) {
        currentHeatmapType = type;
        if(type === 'traffic') {
            $('#btnHeatmapTraffic').addClass('active btn-primary').removeClass('btn-outline-primary');
            $('#btnHeatmapWa').removeClass('active btn-success').addClass('btn-outline-success');
        } else {
            $('#btnHeatmapWa').addClass('active btn-success').removeClass('btn-outline-success');
            $('#btnHeatmapTraffic').removeClass('active btn-primary').addClass('btn-outline-primary');
        }
        renderHeatmapGrid();
    }

    function renderHeatmapGrid() {
        if(!rawReportData) return;
        const container = $('#heatmapGridContainer').empty();
        const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        
        // Header (Hours 00 - 23)
        container.append('<div class="heatmap-header">Hari / Jam</div>');
        for(let h=0; h<24; h++) {
            container.append(`<div class="heatmap-header">${h < 10 ? '0' + h : h}</div>`);
        }

        // Prepare Matrix Data (7 x 24)
        const matrix = Array.from({length: 7}, () => new Array(24).fill(0));
        const rawList = (currentHeatmapType === 'wa') ? (rawReportData.charts.wa_heatmap || []) : (rawReportData.charts.heatmap || []);
        
        let maxVal = 1;
        rawList.forEach(item => {
            const d = parseInt(item.day_index);
            const h = parseInt(item.hour_index);
            const val = parseInt(item.clicks || item.visits || 0);
            if(d >= 0 && d < 7 && h >= 0 && h < 24) {
                matrix[d][h] = val;
                if(val > maxVal) maxVal = val;
            }
        });

        // Hourly totals for bar chart
        const hourlyTotals = new Array(24).fill(0);

        // Build Grid Rows
        for(let d=0; d<7; d++) {
            container.append(`<div class="heatmap-day-label">${days[d]}</div>`);
            for(let h=0; h<24; h++) {
                const count = matrix[d][h];
                hourlyTotals[h] += count;
                const ratio = count / maxVal;
                
                let bgStyle = '#f1f5f9';
                let textColor = '#64748b';
                if(count > 0) {
                    if(currentHeatmapType === 'wa') {
                        bgStyle = `rgba(25, 135, 84, ${Math.max(0.15, ratio)})`;
                        textColor = ratio > 0.5 ? '#ffffff' : '#0f5132';
                    } else {
                        bgStyle = `rgba(13, 110, 253, ${Math.max(0.15, ratio)})`;
                        textColor = ratio > 0.5 ? '#ffffff' : '#084298';
                    }
                }

                container.append(`
                    <div class="heatmap-cell" style="background: ${bgStyle}; color: ${textColor};" title="${days[d]} Jam ${h}:00 - ${count} ${currentHeatmapType === 'wa' ? 'Klik WA' : 'Visits'}">
                        ${count > 0 ? count : ''}
                    </div>
                `);
            }
        }

        // Render Bar Chart Below Matrix
        const hourLabels = hourlyTotals.map((_, i) => (i < 10 ? '0' + i : i) + ":00");
        if(heatmapChart) heatmapChart.destroy();
        heatmapChart = new Chart(document.getElementById('heatmapChart'), {
            type: 'bar',
            data: {
                labels: hourLabels,
                datasets: [{
                    label: currentHeatmapType === 'wa' ? 'Klik WhatsApp' : 'Kunjungan Visitor',
                    data: hourlyTotals,
                    backgroundColor: currentHeatmapType === 'wa' ? '#198754' : '#0dcaf0'
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: true, position: 'top' } }
            }
        });
    }

    function filterWaLogs() {
        if(!rawReportData || !rawReportData.wa_logs) return;
        const search = ($('#waLogSearch').val() || '').toLowerCase();
        const device = $('#waDeviceFilter').val();

        filteredWaLogs = rawReportData.wa_logs.filter(item => {
            const matchSearch = !search || 
                (item.ip_address && item.ip_address.toLowerCase().includes(search)) ||
                (item.button_label && item.button_label.toLowerCase().includes(search)) ||
                (item.page_url && item.page_url.toLowerCase().includes(search)) ||
                (item.location && item.location.toLowerCase().includes(search));
            
            const matchDevice = !device || item.device === device;
            return matchSearch && matchDevice;
        });

        currentWaLogsPage = 1;
        renderWaLogsTable();
    }

    function renderWaLogsTable() {
        const tbody = $('#waLogsReportBody').empty();
        const total = filteredWaLogs.length;

        if(total === 0) {
            tbody.html('<tr><td colspan="6" class="text-center py-4 text-muted">Tidak ada log klik WhatsApp ditemukan.</td></tr>');
            $('#waLogSummaryText').text('Menampilkan 0 log');
            $('#waLogNav').empty();
            return;
        }

        const startIdx = (currentWaLogsPage - 1) * waLogLimit;
        const pageData = filteredWaLogs.slice(startIdx, startIdx + waLogLimit);

        pageData.forEach((item, idx) => {
            const date = moment(item.clicked_at).format('YYYY-MM-DD HH:mm:ss');
            const deviceBadge = item.device === 'Mobile' ? 
                '<span class="badge bg-info text-dark"><i class="fa-solid fa-mobile-screen me-1"></i>Mobile</span>' : 
                '<span class="badge bg-secondary"><i class="fa-solid fa-desktop me-1"></i>Desktop</span>';

            tbody.append(`
                <tr>
                    <td class="ps-3 fw-bold text-muted">${startIdx + idx + 1}</td>
                    <td><small class="fw-semibold text-dark">${date}</small></td>
                    <td>
                        <div class="fw-bold text-success" style="font-size: 0.85rem;">${escapeHtml(item.button_label)}</div>
                        <div class="small text-muted text-truncate" style="max-width: 280px;" title="${escapeHtml(item.page_url)}">
                            <i class="fa-solid fa-link me-1"></i>${escapeHtml(item.page_url)}
                        </div>
                    </td>
                    <td><code class="text-dark small">${escapeHtml(item.ip_address)}</code></td>
                    <td><small><i class="fa-solid fa-location-dot text-danger me-1"></i>${escapeHtml(item.location)}</small></td>
                    <td>${deviceBadge}</td>
                </tr>
            `);
        });

        const endIdx = Math.min(startIdx + waLogLimit, total);
        $('#waLogSummaryText').text(`Menampilkan ${startIdx + 1} - ${endIdx} dari ${total} log klik WA`);
        renderWaLogPagination(Math.ceil(total / waLogLimit));
    }

    function renderWaLogPagination(totalPages) {
        const nav = $('#waLogNav').empty();
        if(totalPages <= 1) return;

        const prevDisabled = currentWaLogsPage === 1 ? 'disabled' : '';
        nav.append(`<li class="page-item ${prevDisabled}"><a class="page-link" href="#" onclick="changeWaLogReportPage(${currentWaLogsPage - 1}); return false;">Prev</a></li>`);

        for(let i=1; i<=totalPages; i++) {
            if(i === 1 || i === totalPages || (i >= currentWaLogsPage - 1 && i <= currentWaLogsPage + 1)) {
                nav.append(`<li class="page-item ${currentWaLogsPage === i ? 'active' : ''}"><a class="page-link" href="#" onclick="changeWaLogReportPage(${i}); return false;">${i}</a></li>`);
            }
        }

        const nextDisabled = currentWaLogsPage === totalPages ? 'disabled' : '';
        nav.append(`<li class="page-item ${nextDisabled}"><a class="page-link" href="#" onclick="changeWaLogReportPage(${currentWaLogsPage + 1}); return false;">Next</a></li>`);
    }

    function changeWaLogReportPage(page) {
        currentWaLogsPage = page;
        renderWaLogsTable();
    }

    function renderGrowth(selector, val) {
        const el = $(selector);
        el.removeClass('growth-up growth-down growth-neutral');
        if(val > 0) {
            el.addClass('growth-up').html(`<i class="fa-solid fa-arrow-trend-up"></i> +${val}%`);
        } else if (val < 0) {
            el.addClass('growth-down').html(`<i class="fa-solid fa-arrow-trend-down"></i> ${val}%`);
        } else {
            el.addClass('growth-neutral').html(`-`);
        }
    }

    function escapeHtml(text) {
        if (!text) return "";
        return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    function exportPDF() {
        const element = document.getElementById('reportContent');
        const opt = {
          margin:       0.3,
          filename:     'ArnoDClean_Report_' + moment().format('YYYY-MM-DD') + '.pdf',
          image:        { type: 'jpeg', quality: 0.98 },
          html2canvas:  { scale: 2 },
          jsPDF:        { unit: 'in', format: 'letter', orientation: 'landscape' }
        };
        html2pdf().set(opt).from(element).save();
    }

    function exportExcel() {
        if(!rawReportData) return;
        const wb = XLSX.utils.book_new();

        const summaryData = [
            ['Metric', 'Value'],
            ['Total Views', rawReportData.summary.total_views],
            ['Unique Visitors', rawReportData.summary.unique_visitors],
            ['Conversion Rate', rawReportData.summary.conversion_rate + '%'],
            ['Total WA Clicks', rawReportData.summary.total_wa_clicks],
            ['Total Leads', rawReportData.summary.total_leads]
        ];
        XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(summaryData), "Summary");

        if(rawReportData.wa_logs) {
            XLSX.utils.book_append_sheet(wb, XLSX.utils.json_to_sheet(rawReportData.wa_logs), "WA Clicks Log");
        }

        XLSX.writeFile(wb, 'ArnoDClean_WA_Report.xlsx');
    }
</script>

<?php
if (isset($_SESSION['swal'])) {
    $sw = $_SESSION['swal'];
    echo "<script>Swal.fire('{$sw['title']}', '{$sw['text']}', '{$sw['icon']}');</script>";
    unset($_SESSION['swal']);
}
require_once 'includes/footer.php';
?>
