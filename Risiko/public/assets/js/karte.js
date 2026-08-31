/**
 * Karteninteraktion.
 *
 * Bewusst duenn: Auswahl hervorheben, gueltige Ziele markieren, die Formulare
 * fuellen. Es trifft keine Spielentscheidungen - welche Zuege erlaubt sind,
 * hat der Server bereits ausgerechnet und in "ziele" mitgeliefert. Ohne
 * JavaScript bleiben die Auswahlfelder bedienbar; das ist gleichzeitig der
 * einfachste Testpfad.
 */
(function () {
    'use strict';

    var daten = document.getElementById('spieldaten');
    if (!daten) {
        return;
    }

    var D       = JSON.parse(daten.textContent);
    var svg     = document.querySelector('.weltkarte');
    var vonSel  = document.querySelector('select[data-rolle="von"]');
    var nachSel = document.querySelector('select[data-rolle="nach"]');
    var wuerfel = document.querySelector('select[data-rolle="wuerfel"]');
    var anzahl  = document.getElementById('anzahl');

    // Vollstaendige Zielliste merken, bevor gefiltert wird.
    var alleZiele = nachSel ? Array.prototype.slice.call(nachSel.options) : [];

    function pfad(tid) {
        return svg ? svg.querySelector('[data-terr="' + tid + '"]') : null;
    }

    function zieleFuer(von) {
        return (D.ziele && D.ziele[von]) ? D.ziele[von] : [];
    }

    /** Zielliste auf das gewaehlte Ausgangsgebiet einschraenken. */
    function filtereZiele() {
        if (!nachSel || !vonSel) {
            return;
        }
        var von     = vonSel.value;
        var vorher  = nachSel.value;
        var sichtbar = [];

        nachSel.innerHTML = '';
        alleZiele.forEach(function (option) {
            if (option.dataset.von === von) {
                nachSel.appendChild(option);
                sichtbar.push(option.value);
            }
        });

        if (sichtbar.indexOf(vorher) >= 0) {
            nachSel.value = vorher;
        }
    }

    /** Obergrenzen nachziehen: Wuerfelzahl bzw. verschiebbare Einheiten. */
    function grenzenSetzen() {
        if (!vonSel) {
            return;
        }
        var staerke = D.einheiten[vonSel.value] || 1;

        if (wuerfel) {
            var moeglich = Math.max(1, Math.min(3, staerke - 1));
            Array.prototype.forEach.call(wuerfel.options, function (option) {
                option.disabled = parseInt(option.value, 10) > moeglich;
            });
            if (parseInt(wuerfel.value, 10) > moeglich) {
                wuerfel.value = String(moeglich);
            }
        }

        if (anzahl && D.phase === 'fortify') {
            var max = Math.max(1, staerke - 1);
            anzahl.max = String(max);
            if (parseInt(anzahl.value, 10) > max) {
                anzahl.value = String(max);
            }
        }
    }

    function markiere() {
        if (!svg) {
            return;
        }
        svg.querySelectorAll('.gebiet').forEach(function (el) {
            el.classList.remove('gewaehlt', 'ziel');
        });

        if (!vonSel) {
            return;
        }
        var von = pfad(vonSel.value);
        if (von) {
            von.classList.add('gewaehlt');
        }
        zieleFuer(vonSel.value).forEach(function (tid) {
            var el = pfad(tid);
            if (el) {
                el.classList.add('ziel');
            }
        });
        if (nachSel && nachSel.value) {
            var ziel = pfad(nachSel.value);
            if (ziel) {
                ziel.classList.add('gewaehlt');
            }
        }
    }

    function aktualisiere() {
        filtereZiele();
        grenzenSetzen();
        markiere();
    }

    if (vonSel) {
        vonSel.addEventListener('change', aktualisiere);
    }
    if (nachSel) {
        nachSel.addEventListener('change', markiere);
    }

    // Ein einziger Listener am <svg>, die Klicks kommen per Delegation an.
    if (svg && D.meinZug) {
        svg.addEventListener('click', function (event) {
            var el = event.target.closest('[data-terr]');
            if (!el || !vonSel) {
                return;
            }
            var tid = el.dataset.terr;

            if (nachSel && zieleFuer(vonSel.value).indexOf(parseInt(tid, 10)) >= 0) {
                nachSel.value = tid;
                markiere();

                return;
            }

            var waehlbar = Array.prototype.some.call(
                vonSel.options,
                function (option) { return option.value === tid; }
            );
            if (waehlbar) {
                vonSel.value = tid;
                aktualisiere();
            }
        });
    }

    aktualisiere();

    // Wer nicht am Zug ist, wartet - dann fragt die Seite gelegentlich nach,
    // ob sich etwas getan hat, statt dass man selbst F5 drueckt.
    if (!D.meinZug && D.statusUrl) {
        setInterval(function () {
            fetch(D.statusUrl, { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (json) {
                    if (json && json.token && json.token !== D.token) {
                        window.location.href = D.seiteUrl;
                    }
                })
                .catch(function () { /* Netzhaenger sind kein Grund zur Panik */ });
        }, 6000);
    }
})();
