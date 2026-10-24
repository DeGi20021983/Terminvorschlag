/* Kleine Verbesserungen ohne Abhängigkeiten. Die Seiten funktionieren auch ohne dieses Skript. */
(function () {
    'use strict';

    document.documentElement.classList.add('js');

    // Bestätigung vor Aktionen, die einen Platz freigeben oder etwas löschen.
    document.addEventListener('submit', function (event) {
        var message = event.target.getAttribute('data-confirm');

        if (message && !window.confirm(message)) {
            event.preventDefault();
        }
    });

    // Umschalter Liste/Kalender auf der Buchungsseite.
    // Tabellen mit horizontalem Scrollen: per Tastatur erreichbar und mit der Tabellenbeschriftung benannt.
    document.querySelectorAll('.table-wrap').forEach(function (wrap) {
        var caption = wrap.querySelector('caption');

        wrap.setAttribute('role', 'region');

        if (caption) {
            wrap.setAttribute('aria-label', caption.textContent.trim());
        }
    });

    var toggles = document.querySelectorAll('[data-view-target]');

    toggles.forEach(function (toggle) {
        toggle.addEventListener('click', function () {
            var target = toggle.getAttribute('data-view-target');

            toggles.forEach(function (other) {
                var active = other === toggle;
                other.setAttribute('aria-pressed', active ? 'true' : 'false');

                var panel = document.getElementById(other.getAttribute('data-view-target'));
                if (panel) {
                    panel.hidden = !active;
                }
            });

            var panel = document.getElementById(target);
            if (panel) {
                panel.hidden = false;
            }
        });
    });
    // Teilnehmerliste: Tabelle alle 15 Sekunden neu laden, nur wenn die Seite sichtbar ist.
    var live = document.querySelector('[data-live-url]');

    if (live) {
        var stand = document.querySelector('[data-live-status]');
        var url = live.getAttribute('data-live-url');

        var refresh = function () {
            if (document.hidden) {
                return;
            }

            fetch(url, { credentials: 'same-origin', headers: { Accept: 'text/html' } })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status);
                    }

                    var time = response.headers.get('X-Stand');

                    return response.text().then(function (html) {
                        live.innerHTML = html;

                        if (stand && time) {
                            stand.textContent = 'Stand: ' + time + ' Uhr · aktualisiert sich automatisch';
                        }
                    });
                })
                .catch(function () {
                    if (stand) {
                        stand.textContent = 'Die Aktualisierung ist fehlgeschlagen. Bitte laden Sie die Seite neu.';
                    }
                });
        };

        window.setInterval(refresh, 15000);
    }

    // Drucken über den Button auf der Anwesenheitsliste (ohne Inline-Skript, wegen CSP).
    document.querySelectorAll('[data-print]').forEach(function (button) {
        button.addEventListener('click', function () {
            window.print();
        });
    });
})();
