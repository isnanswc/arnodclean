    </div> 
</div>

<!-- Scripts -->
<!-- Upload Loading Overlay -->
<div id="uploadOverlay" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.7); color:white; justify-content:center; align-items:center; flex-direction:column;">
    <div class="spinner-border text-light mb-3" style="width: 3rem; height: 3rem;" role="status"></div>
    <h5 id="uploadText">Memproses Data...</h5>
    <small class="text-white-50">Mohon tunggu, jangan tutup halaman ini.</small>
</div>



<script>
    // Auto-show loader on form submit
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function() {
            // Only show if it's a valid submit (simple check)
            if(this.checkValidity()) {
                const hasFile = this.querySelector('input[type="file"]');
                const overlay = document.getElementById('uploadOverlay');
                const text = document.getElementById('uploadText');
                
                if (hasFile && hasFile.value) {
                    text.textContent = "Sedang Mengupload Gambar...";
                } else {
                    text.textContent = "Menyimpan Data...";
                }
                overlay.style.display = 'flex';
            }
        });
    });

    // --- SYSTEM WORKER (Disabled: Server Cron Active) ---
    // Bot kini dijalankan via Cron Job Server.
    // Script browser-worker dihapus untuk efisiensi.
</script>
</body>
</html>
