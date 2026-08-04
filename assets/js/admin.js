/* Folio. Copyright (C) 2026 Mohd Elfie Nieshaem Juferi. SPDX-License-Identifier: GPL-3.0-or-later */
/* Folio — small admin-only behaviours */
(function () {
    "use strict";

    /* Confirm before deleting an account. */
    document.addEventListener("submit", function (event) {
        var form = event.target.closest(".account-delete-form");
        if (!form) {
            return;
        }
        var username = form.getAttribute("data-username") || "this account";
        if (!window.confirm("Delete the account " + username + "? Existing sessions for it will be revoked.")) {
            event.preventDefault();
        }
    });

    /* Confirm before removing an IndexNow key. */
    document.addEventListener("submit", function (event) {
        var form = event.target.closest(".indexnow-clear-form");
        if (!form) {
            return;
        }
        if (!window.confirm("Remove the IndexNow key? Search engines that verified against it will need to re-verify.")) {
            event.preventDefault();
        }
    });

    /* Rewrite preflight (Crawlers screen).
       Fetches /__probe__/?action=rewrite_probe. If mod_rewrite is active the
       probe route arrives at the JSON handler and we reveal the Enable button. */
    (function () {
        var host = document.getElementById("rewrite-preflight");
        if (!host) {
            return;
        }
        var btn    = document.getElementById("rewrite-test-btn");
        var result = document.getElementById("rewrite-result");
        var form   = document.getElementById("pretty-form");
        if (!btn || !result || !form) {
            return;
        }
        btn.addEventListener("click", function () {
            result.textContent = "Testing…";
            result.classList.remove("rewrite-ok", "rewrite-bad");
            fetch(host.dataset.probe, { credentials: "same-origin" })
                .then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); })
                .then(function (data) {
                    if (data && data.ok && String(data.route || "").indexOf("__probe__") === 0) {
                        result.textContent = "Rewrite is active. Clean URLs can be enabled safely.";
                        result.classList.add("rewrite-ok");
                        form.classList.add("is-visible");
                        var input = form.querySelector('input[name="probe_result"]');
                        if (input) { input.value = "ok"; }
                    } else {
                        result.textContent = "The server did not route through Folio. The rewrite block in .htaccess is either commented out, missing, or ignored by your host. Do not enable clean URLs.";
                        result.classList.add("rewrite-bad");
                    }
                })
                .catch(function () {
                    result.textContent = "The server returned an error instead of routing through Folio. The rewrite rules are not active. Do not enable clean URLs.";
                    result.classList.add("rewrite-bad");
                });
        });
    }());

    /* PDF access-control preflight (Crawlers screen).
       Fetches uploads/.folio-pdf-probe.pdf directly (not via ?action=raw)
       so the test exercises the exact same path a real PDF request takes.
       If the PDF rewrite rule is active, that request lands in the raw
       action's admin-only probe branch and returns JSON; the button then
       reveals the confirm form. The server independently re-verifies this
       itself before actually enforcing anything — this button is just
       feedback so the admin isn't confirming blind. */
    (function () {
        var host = document.getElementById("pdf-gate-preflight");
        if (!host) {
            return;
        }
        var btn    = document.getElementById("pdf-gate-test-btn");
        var result = document.getElementById("pdf-gate-result");
        var form   = document.getElementById("pdf-gate-form");
        if (!btn || !result || !form) {
            return;
        }
        btn.addEventListener("click", function () {
            result.textContent = "Testing…";
            result.classList.remove("rewrite-ok", "rewrite-bad");
            fetch(host.dataset.probe, { credentials: "same-origin", cache: "no-store" })
                .then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); })
                .then(function (data) {
                    if (data && data.ok && data.gate === "pdf") {
                        result.textContent = "PDF requests reach Folio. Safe to confirm and enforce.";
                        result.classList.add("rewrite-ok");
                        form.classList.add("is-visible");
                        var input = form.querySelector('input[name="probe_result"]');
                        if (input) { input.value = "ok"; }
                    } else {
                        result.textContent = "The server did not route the PDF request through Folio. Check that the PDF rule is present in .htaccess and mod_rewrite is active. Do not confirm.";
                        result.classList.add("rewrite-bad");
                    }
                })
                .catch(function () {
                    result.textContent = "The server returned an error instead of routing through Folio. Do not confirm.";
                    result.classList.add("rewrite-bad");
                });
        });
    }());

    /* ---- Run OCR on a scanned PDF ------------------------------------
     *
     * OCR takes tens of seconds to minutes on a long document, so the button
     * has to say so and keep saying so. A control that sits silent for two
     * minutes reads as broken, and an impatient second click would start the
     * whole job again.
     */
    (function () {
        var buttons = document.querySelectorAll(".ocr-run");
        if (!buttons.length) { return; }

        var tokenField = document.querySelector('input[name="csrf"]');
        var token = tokenField ? tokenField.value : "";

        Array.prototype.forEach.call(buttons, function (btn) {
            btn.addEventListener("click", function () {
                if (btn.disabled) { return; }
                var file = btn.getAttribute("data-file") || "";
                if (!file) { return; }

                var original = btn.textContent;
                btn.disabled = true;

                var started = Date.now();
                var ticker = window.setInterval(function () {
                    var secs = Math.round((Date.now() - started) / 1000);
                    btn.textContent = "Reading… " + secs + "s";
                }, 1000);
                btn.textContent = "Reading…";

                var body = new URLSearchParams();
                body.append("action", "ocr");
                body.append("csrf", token);
                body.append("file", file);

                window.fetch(window.location.pathname, {
                    method: "POST",
                    credentials: "same-origin",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: body.toString()
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        window.clearInterval(ticker);
                        if (data && data.ok && data.skipped) {
                            btn.textContent = "Already searchable";
                        } else if (data && data.ok) {
                            btn.textContent = "Done · " + (data.chars || 0) + " chars";
                            btn.classList.add("ocr-done");
                        } else {
                            btn.disabled = false;
                            btn.textContent = original;
                            window.alert("OCR did not finish.\n\n"
                                + ((data && data.error) || "The server did not explain why.")
                                + "\n\nYour file has not been changed.");
                        }
                    })
                    .catch(function () {
                        window.clearInterval(ticker);
                        btn.disabled = false;
                        btn.textContent = original;
                        window.alert("OCR could not be reached. On a long document the server "
                            + "may have cut the request short before it finished. "
                            + "Your file has not been changed.");
                    });
            });
        });
    }());
}());
