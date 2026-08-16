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

    /* Video access-control preflight (Crawlers screen).
       Fetches the video probe file directly and expects it to be REFUSED with
       403, which proves the webserver honours the deny block Folio writes into
       uploads/.htaccess. Anonymous on purpose: the public is who must be
       refused. On success it flags probe_result=forbidden so the server can
       enforce even where it cannot reach its own public URL. */
    (function () {
        var host = document.getElementById("video-gate-preflight");
        if (!host) {
            return;
        }
        var btn    = document.getElementById("video-gate-test-btn");
        var result = document.getElementById("video-gate-result");
        var form   = document.getElementById("video-gate-form");
        if (!btn || !result || !form) {
            return;
        }
        btn.addEventListener("click", function () {
            result.textContent = "Testing…";
            result.classList.remove("rewrite-ok", "rewrite-bad");
            fetch(host.dataset.probe, { credentials: "omit", cache: "no-store" })
                .then(function (r) {
                    if (r.status === 403) {
                        result.textContent = "The server refuses direct video access. Safe to verify and enforce.";
                        result.classList.add("rewrite-ok");
                        form.classList.add("is-visible");
                        var input = form.querySelector('input[name="probe_result"]');
                        if (input) { input.value = "forbidden"; }
                    } else {
                        result.textContent = "The server served the video probe (HTTP " + r.status + ") instead of refusing it. The uploads/.htaccess rules are not being applied here. Do not enforce.";
                        result.classList.add("rewrite-bad");
                    }
                })
                .catch(function () {
                    result.textContent = "Could not reach the probe. Try again.";
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

    /* ---- Compress a PDF ----------------------------------------------
     *
     * Produces a smaller copy and offers it for download. Nothing on the
     * server is replaced, so the button reports a result rather than
     * announcing a change.
     */
    (function () {
        var buttons = document.querySelectorAll(".pdf-compress");
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
                    btn.textContent = "Compressing… "
                        + Math.round((Date.now() - started) / 1000) + "s";
                }, 1000);
                btn.textContent = "Compressing…";

                var body = new URLSearchParams();
                body.append("action", "compress");
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
                        btn.disabled = false;
                        if (data && data.ok && data.download) {
                            btn.textContent = "\u2212" + data.saved_pct + "%";
                            btn.classList.add("ocr-done");
                            window.alert(data.message
                                + "\n\nYour file has not been changed. The smaller copy will "
                                + "download now; replace the original over FTP if you want to "
                                + "keep it.");
                            window.location.href = data.download;
                        } else {
                            btn.textContent = original;
                            window.alert((data && data.error) || "Could not compress this document.");
                        }
                    })
                    .catch(function () {
                        window.clearInterval(ticker);
                        btn.disabled = false;
                        btn.textContent = original;
                        window.alert("The server could not be reached. Your file has not been changed.");
                    });
            });
        });
    }());
}());

/* ---- Diagnostics tabs ------------------------------------------------
 * Progressive enhancement: without JavaScript every panel stays visible and
 * the page reads as it always did, just in labelled sections.
 */
(function () {
    var host = document.getElementById("diag-tabs");
    if (!host) { return; }
    host.classList.add("diag-tabs-live");

    var tabs = host.querySelectorAll("[data-diag-tab]");
    var panels = host.querySelectorAll("[data-diag-panel]");

    function select(index) {
        Array.prototype.forEach.call(tabs, function (t) {
            var on = t.getAttribute("data-diag-tab") === index;
            t.classList.toggle("is-active", on);
            t.setAttribute("aria-selected", on ? "true" : "false");
        });
        Array.prototype.forEach.call(panels, function (p) {
            p.classList.toggle("is-active", p.getAttribute("data-diag-panel") === index);
        });
    }

    Array.prototype.forEach.call(tabs, function (t) {
        t.addEventListener("click", function () {
            select(t.getAttribute("data-diag-tab"));
        });
        t.addEventListener("keydown", function (ev) {
            if (ev.key !== "ArrowRight" && ev.key !== "ArrowLeft") { return; }
            ev.preventDefault();
            var list = Array.prototype.slice.call(tabs);
            var at = list.indexOf(t);
            var next = list[(at + (ev.key === "ArrowRight" ? 1 : list.length - 1)) % list.length];
            next.focus();
            select(next.getAttribute("data-diag-tab"));
        });
    });
})();

    /* ---------------------------------------------------------------- */
    /* PDF redaction editor.                                             */
    /* Draws opaque boxes over a rendered page; stores each as fractional*/
    /* {page,x,y,w,h} in a hidden input the meta form submits. Enforcement*/
    /* is entirely server-side — this is only an authoring surface.      */
    /* ---------------------------------------------------------------- */
    (function () {
        var overlay = null;
        var state = null;   // { fieldset, input, file, previewBase, pages, page, regions, imgW, imgH }

        function parseRegions(input) {
            try {
                var v = JSON.parse(input.value || "[]");
                return Array.isArray(v) ? v : [];
            } catch (e) { return []; }
        }

        function updateCount(fieldset, regions) {
            var span = fieldset.querySelector(".redact-count");
            if (span) {
                span.textContent = regions.length + " region" + (regions.length === 1 ? "" : "s");
            }
        }

        function pageMeta(base, cb) {
            var url = base + "&meta=1";
            var x = new XMLHttpRequest();
            x.open("GET", url, true);
            x.onreadystatechange = function () {
                if (x.readyState !== 4) { return; }
                var pages = 1;
                try {
                    var r = JSON.parse(x.responseText);
                    if (r && r.pages) { pages = r.pages; }
                } catch (e) {}
                cb(pages);
            };
            x.send();
        }

        function closeEditor() {
            if (overlay && overlay.parentNode) { overlay.parentNode.removeChild(overlay); }
            overlay = null;
            state = null;
            document.removeEventListener("keydown", onKey);
        }

        function onKey(ev) {
            if (ev.key === "Escape") { closeEditor(); }
        }

        function save() {
            if (!state) { return; }
            state.input.value = JSON.stringify(state.regions);
            updateCount(state.fieldset, state.regions);
            closeEditor();
        }

        function drawRegions(canvasWrap) {
            /* remove existing boxes, redraw current page's */
            var old = canvasWrap.querySelectorAll(".redact-box");
            Array.prototype.forEach.call(old, function (b) { b.parentNode.removeChild(b); });
            state.regions.forEach(function (r, i) {
                if (r.page !== state.page) { return; }
                var box = document.createElement("div");
                box.className = "redact-box";
                box.style.position = "absolute";
                box.style.left = (r.x * 100) + "%";
                box.style.top = (r.y * 100) + "%";
                box.style.width = (r.w * 100) + "%";
                box.style.height = (r.h * 100) + "%";
                box.style.background = "rgba(0,0,0,0.85)";
                box.style.outline = "1px solid #fff";
                box.style.cursor = "pointer";
                box.title = "Click to remove";
                box.setAttribute("data-idx", String(i));
                box.addEventListener("click", function (ev) {
                    ev.stopPropagation();
                    var idx = parseInt(box.getAttribute("data-idx"), 10);
                    state.regions.splice(idx, 1);
                    drawRegions(canvasWrap);
                });
                canvasWrap.appendChild(box);
            });
        }

        function loadPage(canvasWrap, img, label) {
            img.src = state.previewBase + "&page=" + state.page + "&_=" + Date.now();
            label.textContent = "Page " + state.page + " of " + state.pages;
            drawRegions(canvasWrap);
        }

        function buildEditor() {
            overlay = document.createElement("div");
            overlay.className = "redact-overlay";
            overlay.style.position = "fixed";
            overlay.style.inset = "0";
            overlay.style.background = "rgba(0,0,0,0.6)";
            overlay.style.zIndex = "9999";
            overlay.style.display = "flex";
            overlay.style.alignItems = "center";
            overlay.style.justifyContent = "center";

            var panel = document.createElement("div");
            panel.className = "redact-panel";
            panel.style.background = "#fff";
            panel.style.maxWidth = "min(900px, 95vw)";
            panel.style.maxHeight = "95vh";
            panel.style.overflow = "auto";
            panel.style.padding = "1rem";
            panel.style.borderRadius = "6px";

            var bar = document.createElement("div");
            bar.style.display = "flex";
            bar.style.gap = "0.5rem";
            bar.style.alignItems = "center";
            bar.style.marginBottom = "0.5rem";
            bar.style.flexWrap = "wrap";

            var prev = mkBtn("‹ Prev");
            var next = mkBtn("Next ›");
            var label = document.createElement("span");
            label.style.fontWeight = "bold";
            var spacer = document.createElement("span");
            spacer.style.flex = "1";
            var saveBtn = mkBtn("Save redactions");
            var cancelBtn = mkBtn("Cancel");
            var hint = document.createElement("p");
            hint.textContent = "Drag on the page to add a box. Click a box to remove it.";
            hint.style.margin = "0 0 0.5rem";
            hint.style.fontSize = "0.85em";
            hint.style.opacity = "0.8";

            bar.appendChild(prev);
            bar.appendChild(next);
            bar.appendChild(label);
            bar.appendChild(spacer);
            bar.appendChild(saveBtn);
            bar.appendChild(cancelBtn);

            var canvasWrap = document.createElement("div");
            canvasWrap.className = "redact-canvas";
            canvasWrap.style.position = "relative";
            canvasWrap.style.userSelect = "none";
            canvasWrap.style.lineHeight = "0";
            canvasWrap.style.border = "1px solid #ccc";

            var img = document.createElement("img");
            img.alt = "Document page";
            img.style.display = "block";
            img.style.width = "100%";
            img.style.height = "auto";
            img.draggable = false;
            canvasWrap.appendChild(img);

            panel.appendChild(bar);
            panel.appendChild(hint);
            panel.appendChild(canvasWrap);
            overlay.appendChild(panel);
            document.body.appendChild(overlay);

            prev.addEventListener("click", function () {
                if (state.page > 1) { state.page--; loadPage(canvasWrap, img, label); }
            });
            next.addEventListener("click", function () {
                if (state.page < state.pages) { state.page++; loadPage(canvasWrap, img, label); }
            });
            saveBtn.addEventListener("click", save);
            cancelBtn.addEventListener("click", closeEditor);
            overlay.addEventListener("click", function (ev) {
                if (ev.target === overlay) { closeEditor(); }
            });
            document.addEventListener("keydown", onKey);

            /* Drag-to-draw. */
            var dragging = false, sx = 0, sy = 0, ghost = null;
            canvasWrap.addEventListener("pointerdown", function (ev) {
                if (ev.target.classList.contains("redact-box")) { return; }
                dragging = true;
                var rect = canvasWrap.getBoundingClientRect();
                sx = (ev.clientX - rect.left) / rect.width;
                sy = (ev.clientY - rect.top) / rect.height;
                ghost = document.createElement("div");
                ghost.style.position = "absolute";
                ghost.style.background = "rgba(0,0,0,0.4)";
                ghost.style.outline = "1px dashed #000";
                ghost.style.left = (sx * 100) + "%";
                ghost.style.top = (sy * 100) + "%";
                canvasWrap.appendChild(ghost);
                try { canvasWrap.setPointerCapture(ev.pointerId); } catch (e) {}
            });
            canvasWrap.addEventListener("pointermove", function (ev) {
                if (!dragging || !ghost) { return; }
                var rect = canvasWrap.getBoundingClientRect();
                var cx = (ev.clientX - rect.left) / rect.width;
                var cy = (ev.clientY - rect.top) / rect.height;
                var x = Math.max(0, Math.min(sx, cx));
                var y = Math.max(0, Math.min(sy, cy));
                var w = Math.min(1, Math.abs(cx - sx));
                var h = Math.min(1, Math.abs(cy - sy));
                ghost.style.left = (x * 100) + "%";
                ghost.style.top = (y * 100) + "%";
                ghost.style.width = (w * 100) + "%";
                ghost.style.height = (h * 100) + "%";
            });
            function endDrag(ev) {
                if (!dragging) { return; }
                dragging = false;
                var rect = canvasWrap.getBoundingClientRect();
                var cx = (ev.clientX - rect.left) / rect.width;
                var cy = (ev.clientY - rect.top) / rect.height;
                var x = Math.max(0, Math.min(sx, cx));
                var y = Math.max(0, Math.min(sy, cy));
                var w = Math.min(1 - x, Math.abs(cx - sx));
                var h = Math.min(1 - y, Math.abs(cy - sy));
                if (ghost && ghost.parentNode) { ghost.parentNode.removeChild(ghost); }
                ghost = null;
                if (w > 0.005 && h > 0.005) {
                    state.regions.push({
                        page: state.page,
                        x: +x.toFixed(5), y: +y.toFixed(5),
                        w: +w.toFixed(5), h: +h.toFixed(5)
                    });
                    drawRegions(canvasWrap);
                }
            }
            canvasWrap.addEventListener("pointerup", endDrag);
            canvasWrap.addEventListener("pointercancel", function () {
                dragging = false;
                if (ghost && ghost.parentNode) { ghost.parentNode.removeChild(ghost); }
                ghost = null;
            });

            loadPage(canvasWrap, img, label);
        }

        function mkBtn(text) {
            var b = document.createElement("button");
            b.type = "button";
            b.className = "btn-small btn-ghost";
            b.textContent = text;
            return b;
        }

        document.addEventListener("click", function (ev) {
            var openBtn = ev.target.closest && ev.target.closest(".redact-open");
            var clearBtn = ev.target.closest && ev.target.closest(".redact-clear");
            if (!openBtn && !clearBtn) { return; }
            var fieldset = ev.target.closest(".meta-redact-fields");
            if (!fieldset) { return; }
            var input = fieldset.querySelector(".redact-regions-input");
            if (!input) { return; }

            if (clearBtn) {
                input.value = "[]";
                updateCount(fieldset, []);
                return;
            }
            state = {
                fieldset: fieldset,
                input: input,
                file: fieldset.getAttribute("data-redact-file"),
                previewBase: fieldset.getAttribute("data-redact-preview"),
                pages: 1,
                page: 1,
                regions: parseRegions(input)
            };
            pageMeta(state.previewBase, function (pages) {
                state.pages = pages || 1;
                buildEditor();
            });
        });
    })();
