/**
 * Interaksi seluruh halaman:
 * - dialog konfirmasi hapus (fokus awal di "Batal")
 * - notifikasi role=status hilang otomatis dalam 4 detik
 * - saklar "Sorot jalur kritis"
 * - slider progress + angka persen
 * - baris berulang (hari libur / urutan pengerjaan)
 * - fokus otomatis ke field error pertama
 */
document.addEventListener('DOMContentLoaded', function () {
    // ------------------------------------------------------------------
    // Dialog konfirmasi hapus (menggantikan window.confirm).
    // ------------------------------------------------------------------
    var dialog = document.getElementById('confirm-dialog');
    var dialogMessage = document.getElementById('confirm-dialog-message');
    var dialogCancel = document.getElementById('confirm-dialog-cancel');
    var dialogOk = document.getElementById('confirm-dialog-ok');
    var pendingForm = null;

    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (form.dataset.confirmed === '1') {
                form.dataset.confirmed = '';

                return;
            }
            event.preventDefault();
            pendingForm = form;
            if (dialog && typeof dialog.showModal === 'function') {
                dialogMessage.textContent = form.getAttribute('data-confirm');
                dialog.showModal();
                dialogCancel.focus(); // fokus awal pada tombol Batal
            } else {
                // Fallback bila <dialog> tidak didukung.
                if (window.confirm(form.getAttribute('data-confirm'))) {
                    form.dataset.confirmed = '1';
                    form.submit();
                }
            }
        });
    });

    if (dialog) {
        dialogCancel.addEventListener('click', function () {
            dialog.close();
            pendingForm = null;
        });
        dialogOk.addEventListener('click', function () {
            dialog.close();
            if (pendingForm) {
                pendingForm.dataset.confirmed = '1';
                pendingForm.submit();
                pendingForm = null;
            }
        });
        // Tombol Escape / klik area luar = batal.
        dialog.addEventListener('cancel', function () {
            pendingForm = null;
        });
    }

    // ------------------------------------------------------------------
    // Notifikasi: hilang otomatis dalam 4 detik.
    // ------------------------------------------------------------------
    document.querySelectorAll('.alert[role="status"]').forEach(function (alert) {
        setTimeout(function () {
            if (alert.parentNode) {
                alert.remove();
            }
        }, 4000);
    });

    document.querySelectorAll('.alert-close').forEach(function (button) {
        button.addEventListener('click', function () {
            var alert = button.closest('.alert');
            if (alert) {
                alert.remove();
            }
        });
    });

    // ------------------------------------------------------------------
    // Saklar "Sorot jalur kritis" (role="switch").
    // ------------------------------------------------------------------
    document.querySelectorAll('[data-switch]').forEach(function (button) {
        var target = document.getElementById(button.getAttribute('data-toggle-target'));
        if (!target) {
            return;
        }

        var initial = button.getAttribute('data-initial') === 'on';
        if (initial) {
            target.classList.add('active');
            button.setAttribute('aria-checked', 'true');
        }

        button.addEventListener('click', function () {
            var active = target.classList.toggle('active');
            button.setAttribute('aria-checked', active ? 'true' : 'false');
        });
    });

    // ------------------------------------------------------------------
    // Slider progress: tampilkan angka persen di sampingnya.
    // ------------------------------------------------------------------
    document.querySelectorAll('input[type="range"][list="progress-ticks"]').forEach(function (slider) {
        var output = document.getElementById('progress-output');
        if (!output) {
            return;
        }
        var sync = function () {
            output.textContent = slider.value + '%';
        };
        slider.addEventListener('input', sync);
        sync();
    });

    // ------------------------------------------------------------------
    // Tambah baris berulang dari <template> (placeholder __IDX__ diganti unik).
    // ------------------------------------------------------------------
    document.querySelectorAll('[data-add-row]').forEach(function (button) {
        button.addEventListener('click', function () {
            var template = document.querySelector(button.getAttribute('data-add-row'));
            var target = document.querySelector(button.getAttribute('data-target'));
            if (!template || !target) {
                return;
            }
            var html = template.innerHTML.replace(/__IDX__/g, 'new' + Date.now());
            target.insertAdjacentHTML('beforeend', html);
        });
    });

    // Hapus baris berulang.
    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-remove-row]');
        if (button) {
            var row = button.closest('.repeater-row');
            if (row) {
                row.remove();
            }
        }
    });

    // ------------------------------------------------------------------
    // Fokus otomatis ke field error pertama saat ada pesan validasi.
    // ------------------------------------------------------------------
    var firstError = document.querySelector('.field-error');
    if (firstError) {
        var field = firstError.closest('.field');
        var input = field ? field.querySelector('input, select, textarea') : null;
        if (input) {
            input.focus();
        }
    }
});
