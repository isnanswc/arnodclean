<!-- Global WhatsApp Tracker & Form Direct Redirect Script -->
<script>
(function() {
    // Function to track WA Click and open URL
    window.trackWaClick = function(source = 'button', serviceId = null, customText = '', targetUrl = '') {
        const pageUrl = window.location.pathname + window.location.search;
        const formData = new FormData();
        formData.append('source', source);
        formData.append('page_url', pageUrl);
        if (serviceId) formData.append('service_id', serviceId);
        if (customText) formData.append('custom_text', customText);

        // Navigate to WA URL
        if (targetUrl) {
            // URL sudah diketahui — kirim tracking via Beacon (fire-and-forget) lalu buka langsung
            if (navigator.sendBeacon) {
                navigator.sendBeacon('api/track_wa_click.php', formData);
            } else {
                fetch('api/track_wa_click.php', { method: 'POST', body: formData }).catch(() => {});
            }
            window.open(targetUrl, '_blank');
        } else {
            // URL belum diketahui — gunakan fetch untuk tracking + ambil wa_url sekaligus
            fetch('api/track_wa_click.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.wa_url) {
                        window.open(data.wa_url, '_blank');
                    }
                })
                .catch(() => {
                    const phone = '6281280666659';
                    window.open(`https://wa.me/${phone}?text=${encodeURIComponent(customText || 'Halo Admin')}`, '_blank');
                });
        }
    };

    document.addEventListener('DOMContentLoaded', function() {
        // 1. Auto-bind all WA Links on the page (including inside article contents)
        document.body.addEventListener('click', function(e) {
            const waTarget = e.target.closest('a[href*="wa.me"], a[href*="api.whatsapp.com"], .wa-track-btn, [data-wa-source]');
            if (waTarget) {
                // If it's part of form submit button, ignore here
                if (waTarget.type === 'submit' || waTarget.closest('.lead-form')) return;

                e.preventDefault();

                let source = waTarget.dataset.waSource;
                if (!source) {
                    if (waTarget.closest('.article-body') || waTarget.closest('.article-content') || waTarget.closest('article')) {
                        source = 'article_body';
                    } else if (waTarget.id === 'waFloat' || waTarget.closest('#chatPopup')) {
                        source = 'floating_widget';
                    } else if (waTarget.closest('.hero')) {
                        source = 'hero_button';
                    } else if (waTarget.closest('.service-card') || waTarget.closest('.card')) {
                        source = 'service_card';
                    } else if (waTarget.closest('header') || waTarget.closest('.navbar')) {
                        source = 'header_button';
                    } else if (waTarget.closest('footer')) {
                        source = 'footer_button';
                    } else {
                        source = 'wa_button';
                    }
                }

                const serviceId = waTarget.dataset.serviceId || null;
                let hrefUrl = waTarget.getAttribute('href') || '';
                let customText = waTarget.dataset.waText || '';

                // If href has text parameter, extract it
                if (hrefUrl.includes('text=')) {
                    try {
                        const urlObj = new URL(hrefUrl.startsWith('http') ? hrefUrl : 'https://wa.me' + hrefUrl);
                        customText = urlObj.searchParams.get('text') || customText;
                    } catch (err) {}
                }

                trackWaClick(source, serviceId, customText, hrefUrl);
            }
        });

        // 2. Handle Lead Form Submit with Direct WA Redirect & CRM storage
        document.querySelectorAll('.lead-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const submitBtn = this.querySelector('button[type="submit"]');
                const btnText = submitBtn ? submitBtn.querySelector('.btn-text') : null;
                const spinner = submitBtn ? submitBtn.querySelector('.spinner-border') : null;

                if (submitBtn) submitBtn.disabled = true;
                if (btnText) btnText.classList.add('d-none');
                if (spinner) spinner.classList.remove('d-none');

                const formData = new FormData(this);
                formData.append('page_url', window.location.pathname + window.location.search);
                formData.append('referrer', document.referrer || '');

                fetch('api/submit_lead.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        const waUrl = data.wa_url;
                        
                        // Direct Redirect to WhatsApp
                        let opened = window.open(waUrl, '_blank');
                        if (!opened) {
                            // Fallback if popup blocked
                            window.location.href = waUrl;
                        }

                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                title: 'Pesan Terkirim!',
                                text: 'Data Anda telah tersimpan di CRM. Anda langsung dihubungkan ke WhatsApp...',
                                icon: 'success',
                                showCancelButton: true,
                                confirmButtonText: '<i class="fa-brands fa-whatsapp me-1"></i> Buka WhatsApp Sekarang',
                                cancelButtonText: 'Tutup',
                                timer: 3000,
                                timerProgressBar: true
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    window.open(waUrl, '_blank');
                                }
                            });
                        }

                        this.reset();
                        // Close modal if open
                        const modalEl = document.getElementById('bookingModal');
                        if (modalEl && typeof bootstrap !== 'undefined') {
                            const modal = bootstrap.Modal.getInstance(modalEl);
                            if (modal) modal.hide();
                        }
                    } else {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire('Gagal', data.message || 'Terjadi kesalahan.', 'error');
                        } else {
                            alert(data.message || 'Terjadi kesalahan.');
                        }
                    }
                })
                .catch(err => {
                    console.error(err);
                    if (typeof Swal !== 'undefined') {
                        Swal.fire('Error', 'Gagal menghubungkan ke server.', 'error');
                    } else {
                        alert('Gagal menghubungkan ke server.');
                    }
                })
                .finally(() => {
                    if (submitBtn) submitBtn.disabled = false;
                    if (btnText) btnText.classList.remove('d-none');
                    if (spinner) spinner.classList.add('d-none');
                });
            });
        });
    });
})();
</script>
