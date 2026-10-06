<?php
// admin/tags.php
require_once '../db.php';
require_once 'includes/auth.php';
checkLogin();

// Add or Update
if (isset($_POST['save'])) {
    $id = $_POST['id'] ?? '';
    $name = trim($_POST['name']);
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
    
    if (!empty($name)) {
        if ($id) {
            $pdo->prepare("UPDATE tags SET name=?, slug=? WHERE id=?")->execute([$name, $slug, $id]);
            logActivity("Update Tag", "Mengubah tag: $name");
            $_SESSION['swal'] = ['title' => 'Berhasil!', 'text' => 'Tag diperbarui.', 'icon' => 'success'];
        } else {
            $pdo->prepare("INSERT INTO tags (name, slug) VALUES (?, ?)")->execute([$name, $slug]);
            logActivity("Create Tag", "Menambah tag baru: $name");
            $_SESSION['swal'] = ['title' => 'Berhasil!', 'text' => 'Tag ditambahkan.', 'icon' => 'success'];
        }
    }
    header("Location: tags.php");
    exit;
}

// Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $pdo->prepare("DELETE FROM tags WHERE id = ?")->execute([$id]);
    logActivity("Delete Tag", "Menghapus tag ID: $id");
    header("Location: tags.php?deleted=1");
    exit;
}

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Kelola Tags</h1>
    <button class="btn btn-primary shadow-sm" onclick="showAddModal()">
        <i class="fa-solid fa-plus me-2"></i> Tambah Tag
    </button>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-6">
                <input type="text" id="searchInput" class="form-control" placeholder="Cari tags...">
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="bg-light">
                    <tr>
                        <th>Nama Tag</th>
                        <th>Slug</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                     <tr><td colspan="3" class="text-center">Memuat data...</td></tr>
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
    const limit = 10;
    
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
            data: { type: 'tags', page: currentPage, limit: limit, search: search },
            dataType: 'json',
            success: function(response) {
                renderTable(response.data);
                renderPagination(response.pagination);
            },
            error: function() {
                $('#tableBody').html('<tr><td colspan="3" class="text-center text-danger">Gagal memuat data.</td></tr>');
            }
        });
    }

    function renderTable(data) {
        const tbody = $('#tableBody');
        tbody.empty();
        
        if (data.length === 0) {
            tbody.html('<tr><td colspan="3" class="text-center">Tidak ada data ditemukan.</td></tr>');
            return;
        }

        data.forEach(item => {
            const jsonItem = JSON.stringify(item).replace(/'/g, "&#39;");

            const row = `
                <tr>
                    <td><span class="badge bg-secondary fs-6">${escapeHtml(item.name)}</span></td>
                    <td class="text-muted">${escapeHtml(item.slug)}</td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary me-1" onclick='editTag(${jsonItem})'>
                            <i class="fa-solid fa-pen"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="confirmDelete(${item.id})">
                            <i class="fa-solid fa-trash"></i>
                        </button>
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

        nav.append(`<li class="page-item ${pagination.current_page == 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="changePage(${pagination.current_page - 1})">Prev</a>
        </li>`);

        for (let i = 1; i <= pagination.total_pages; i++) {
             nav.append(`<li class="page-item ${pagination.current_page == i ? 'active' : ''}">
                <a class="page-link" href="#" onclick="changePage(${i})">${i}</a>
            </li>`);
        }

        nav.append(`<li class="page-item ${pagination.current_page == pagination.total_pages ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="changePage(${pagination.current_page + 1})">Next</a>
        </li>`);
    }

    function changePage(page) {
        if(page < 1) return;
        currentPage = page;
        loadData();
    }
</script>

<!-- Modal -->
<div class="modal fade" id="tagModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalTitle">Tambah Tag</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="id" id="tag_id">
                    <div class="mb-3">
                        <label class="form-label">Nama Tag</label>
                        <input type="text" class="form-control" name="name" id="name" required placeholder="Contoh: Tips Kebersihan">
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="save" class="btn btn-primary" id="btnSave">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function resetForm() {
        document.getElementById('modalTitle').innerText = 'Tambah Tag';
        document.getElementById('btnSave').innerText = 'Simpan';
        document.getElementById('tag_id').value = '';
        document.getElementById('name').value = '';
    }

    function showAddModal() {
        resetForm();
        new bootstrap.Modal(document.getElementById('tagModal')).show();
    }

    function editTag(data) {
        resetForm();
        document.getElementById('modalTitle').innerText = 'Edit Tag';
        document.getElementById('btnSave').innerText = 'Simpan Perubahan';
        document.getElementById('tag_id').value = data.id;
        document.getElementById('name').value = data.name;
        new bootstrap.Modal(document.getElementById('tagModal')).show();
    }

    function confirmDelete(id) {
        Swal.fire({
            title: 'Hapus Tag?',
            text: "Akan hilang dari artikel terkait!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Ya, Hapus!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `tags.php?delete=${id}`;
            }
        })
    }
</script>

<?php 
if (isset($_SESSION['swal'])) {
    $s = $_SESSION['swal'];
    echo "<script>Swal.fire('{$s['title']}', '{$s['text']}', '{$s['icon']}');</script>";
    unset($_SESSION['swal']);
}
if (isset($_GET['deleted'])) {
    echo "<script>Swal.fire('Terhapus!', 'Tag berhasil dihapus.', 'success');</script>";
}
require_once 'includes/footer.php'; 
?>
