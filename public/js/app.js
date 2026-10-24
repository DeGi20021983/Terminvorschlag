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
})();
