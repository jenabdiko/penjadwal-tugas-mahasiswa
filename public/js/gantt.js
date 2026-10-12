/**
 * Gantt Chart:
 * 1) Mengatur lebar kolom hari (--col-width) agar timeline mengisi lebar kartu
 *    dengan minimum 36px; bila kurang, area timeline di-scroll horizontal.
 * 2) Menggambar panah dependency (lapisan SVG). Posisi dihitung dari batang:
 *    FS = ujung kanan ke awal batang berikutnya, SS = awal ke awal,
 *    FF = ujung ke ujung, SF = awal ke ujung. Jenis selain FS diberi label.
 */
document.addEventListener('DOMContentLoaded', function () {
    var chart = document.getElementById('gantt-chart');
    var svg = document.getElementById('gantt-arrows');
    if (!chart || !svg) {
        return;
    }

    var NS = 'http://www.w3.org/2000/svg';
    var MIN_COL = 36;

    // 1) Lebar kolom hari menyesuaikan lebar yang tersedia (min 36px).
    function fitColumns() {
        var scroll = document.getElementById('gantt-scroll');
        if (!scroll) {
            return;
        }
        var days = parseInt(chart.getAttribute('data-days'), 10) || 1;
        var available = scroll.clientWidth;
        var col = Math.max(MIN_COL, Math.floor(available / days));
        chart.style.setProperty('--col-width', col + 'px');
    }

    // 2) Panah dependency antar batang.
    function draw() {
        if (typeof window.GANTT_ARROWS === 'undefined') {
            return;
        }

        svg.querySelectorAll('.gantt-arrow-group').forEach(function (node) {
            node.remove();
        });

        var body = svg.parentElement;
        if (!body) {
            return;
        }
        var bodyRect = body.getBoundingClientRect();
        svg.setAttribute('width', body.scrollWidth);
        svg.setAttribute('height', body.scrollHeight);

        var group = document.createElementNS(NS, 'g');
        group.setAttribute('class', 'gantt-arrow-group');

        function barRect(id) {
            var bar = document.getElementById('gantt-bar-' + id);
            if (!bar) {
                return null;
            }
            var rect = bar.getBoundingClientRect();
            return {
                left: rect.left - bodyRect.left,
                right: rect.right - bodyRect.left,
                top: rect.top - bodyRect.top,
                height: rect.height
            };
        }

        window.GANTT_ARROWS.forEach(function (dep) {
            var from = barRect(dep.from);
            var to = barRect(dep.to);
            if (!from || !to) {
                return;
            }

            var y1 = from.top + from.height / 2;
            var y2 = to.top + to.height / 2;
            var x1;
            var x2;

            if (dep.type === 'SS') {
                x1 = from.left;
                x2 = to.left;
            } else if (dep.type === 'FF') {
                x1 = from.right;
                x2 = to.right;
            } else if (dep.type === 'SF') {
                x1 = from.left;
                x2 = to.right;
            } else { // FS
                x1 = from.right;
                x2 = to.left;
            }

            var dx = Math.abs(x2 - x1);
            var midX = dx < 6 ? x1 : (x1 + x2) / 2;
            var path = document.createElementNS(NS, 'path');
            path.setAttribute('class', 'gantt-arrow-path');
            path.setAttribute('d', 'M ' + x1 + ' ' + y1 + ' H ' + midX + ' V ' + y2 + ' H ' + x2);
            path.setAttribute('marker-end', 'url(#gantt-arrowhead)');
            group.appendChild(path);

            if (dep.type !== 'FS' || dep.lag > 0) {
                var label = document.createElementNS(NS, 'text');
                label.setAttribute('class', 'gantt-arrow-label');
                label.setAttribute('x', midX);
                label.setAttribute('y', (y1 + y2) / 2 - 5);
                label.setAttribute('text-anchor', 'middle');
                label.textContent = dep.type + (dep.lag > 0 ? '+' + dep.lag : '');
                group.appendChild(label);
            }
        });

        svg.appendChild(group);
    }

    function relayout() {
        fitColumns();
        draw();
    }

    relayout();

    // Gambar ulang saat ukuran jendela berubah (lebar kolom ikut menyesuaikan).
    var resizeTimer;
    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(relayout, 150);
    });
});
