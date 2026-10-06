<?php
// admin/activity.php
session_start();
require_once '../db.php';
require_once 'includes/auth.php';
checkLogin();

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Log Aktivitas</h1>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-6">
                <input type="text" id="searchInput" class="form-control" placeholder="Cari user, aksi, atau detail...">
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>User</th>
                        <th>Aksi</th>
                        <th>Detail</th>
                        <th>IP Address</th>
                        <th>Waktu</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <tr><td colspan="5" class="text-center">Memuat data...</td></tr>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <nav aria-label="Page navigation" class="mt-3">
            <ul class="pagination justify-content-center" id="pagination"></ul>
        </nav>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    let currentPage = 1;
    const limit = 15;
    
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
            data: { type: 'activity_logs', page: currentPage, limit: limit, search: search },
            dataType: 'json',
            success: function(response) {
                renderTable(response.data);
                renderPagination(response.pagination);
            },
            error: function() {
                $('#tableBody').html('<tr><td colspan="5" class="text-center text-danger">Gagal memuat data.</td></tr>');
            }
        });
    }

    function renderTable(data) {
        const tbody = $('#tableBody');
        tbody.empty();
        
        if (data.length === 0) {
            tbody.html('<tr><td colspan="5" class="text-center">Tidak ada data ditemukan.</td></tr>');
            return;
        }

        data.forEach(item => {
            const isLogin = item.log_type === 'login';
            const badgeClass = isLogin ? 'bg-success' : 'bg-primary';
            const avatar = item.avatar 
                ? `<img src="${escapeHtml(item.avatar)}" class="rounded-circle me-2" width="28" height="28">` 
                : '<i class="fa-solid fa-user-circle me-2 text-secondary fs-5"></i>';
            
            const date = new Date(item.created_at);
            const formattedDate = date.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) 
                                + ' ' + date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });

            const row = `
                <tr>
                    <td>
                        <div class="d-flex align-items-center">
                            ${avatar}
                            <span class="fw-medium">${escapeHtml(item.username || 'Unknown')}</span>
                        </div>
                    </td>
                    <td><span class="badge ${badgeClass}">${escapeHtml(item.action)}</span></td>
                    <td><small class="text-muted">${escapeHtml(item.details || '-')}</small></td>
                    <td><code class="small">${escapeHtml(item.ip_address || '-')}</code></td>
                    <td><small class="text-muted">${formattedDate}</small></td>
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

        // Smart Pagination Logic
        const total = pagination.total_pages;
        const current = pagination.current_page;
        const delta = 2; 
        
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

<?php require_once 'includes/footer.php'; ?>
