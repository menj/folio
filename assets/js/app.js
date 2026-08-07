/* Folio. Copyright (C) 2026 Mohd Elfie Nieshaem Juferi. SPDX-License-Identifier: GPL-3.0-or-later */
/* Folio — preview, print, themes */
(function () {
    "use strict";

    var pane = document.getElementById("preview-pane");
    var body = document.getElementById("preview-body");
    var nameEl = document.getElementById("preview-name");
    var printFrame = document.getElementById("print-frame");
    var current = null; // { file, kind, url }

    function openPreview(file, kind, label, rawUrl, renderUrl) {
        current = { file: file, kind: kind, url: rawUrl, renderUrl: renderUrl };
        nameEl.textContent = label || file.split("/").pop().replace(/\.[^.]+$/, "");
        body.innerHTML = "";

        if (kind === "image") {
            var img = document.createElement("img");
            img.src = current.url;
            img.alt = nameEl.textContent;
            body.appendChild(img);
        } else {
            /* An empty or relative src resolves against the current page and
               would load the library inside its own preview pane. Refuse
               rather than render that. */
            var src = kind === "md" ? current.renderUrl : current.url;
            if (!src) {
                body.innerHTML = "<p class=\"preview-empty\">This file has no preview.</p>";
                pane.hidden = false;
                return;
            }
            var frame = document.createElement("iframe");
            /* Chrome and Edge honour these PDF Open Parameters and drop their
               own viewer chrome — the download, print and menu buttons that
               otherwise sit on top of Folio's preview and duplicate the
               actions already beside it.

               This is presentation, not protection. Firefox and Safari ignore
               the parameters, and the file's URL is reachable regardless. A
               document that must not be downloaded needs pdf_access set to
               viewer or hidden, which is enforced on the server. */
            frame.src = current.kind === "pdf"
                ? src + (src.indexOf("#") === -1 ? "#" : "&")
                        + "toolbar=0&navpanes=0&statusbar=0&view=FitH"
                : src;
            frame.title = nameEl.textContent;
            body.appendChild(frame);
        }
        pane.hidden = false;
    }

    function printCurrent() {
        if (!current) {
            return;
        }
        if (current.kind === "image") {
            printFrame.onload = function () {
                printFrame.onload = null;
                try {
                    printFrame.contentWindow.focus();
                    printFrame.contentWindow.print();
                } catch (e) {
                    window.open(current.url, "_blank", "noopener");
                }
            };
            printFrame.src = current.url;
        } else {
            // PDF: print via the visible preview iframe
            var frame = body.querySelector("iframe");
            if (frame) {
                try {
                    frame.contentWindow.focus();
                    frame.contentWindow.print();
                } catch (e) {
                    window.open(current.url, "_blank");
                }
            }
        }
    }

    document.addEventListener("click", function (ev) {
        var link = ev.target.closest(".file-link");
        if (link) {
            ev.preventDefault();
            var row = link.closest("tr");
            var titleEl = row ? row.querySelector(".file-title") : null;
            openPreview(
                link.getAttribute("data-file"),
                link.getAttribute("data-kind"),
                titleEl ? titleEl.textContent : "",
                link.getAttribute("data-raw-url"),
                link.getAttribute("data-render-url")
            );
        }
    });

    /* Edit title / description */
    document.addEventListener("click", function (ev) {
        var editBtn = ev.target.closest(".meta-edit");
        if (editBtn) {
            var row = editBtn.closest("tr");
            row.querySelector(".file-meta").hidden = true;
            row.querySelector(".meta-form").hidden = false;
            row.querySelector('.meta-form input[name="title"]').focus();
            return;
        }
        var cancelBtn = ev.target.closest(".meta-cancel");
        if (cancelBtn) {
            var r = cancelBtn.closest("tr");
            r.querySelector(".meta-form").hidden = true;
            r.querySelector(".file-meta").hidden = false;
        }
    });

    document.addEventListener("submit", function (ev) {
        var form = ev.target.closest(".meta-form");
        if (!form) {
            return;
        }
        ev.preventDefault();
        fetch(window.location.pathname, { method: "POST", body: new FormData(form) })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data.ok) {
                    alert(data.error || "Could not save.");
                    return;
                }
                var row = form.closest("tr");
                var meta = row.querySelector(".file-meta");
                var titleEl = meta.querySelector(".file-title");
                var file = row.getAttribute("data-file");
                var fallback = file.split("/").pop().replace(/\.[^.]+$/, "");
                titleEl.textContent = data.title || fallback;

                var descEl = meta.querySelector(".file-desc");
                if (data.desc) {
                    if (!descEl) {
                        descEl = document.createElement("span");
                        descEl.className = "file-desc";
                        meta.appendChild(descEl);
                    }
                    descEl.textContent = data.desc;
                } else if (descEl) {
                    descEl.remove();
                }

                /* Rebuild chips */
                row.setAttribute("data-category", data.category || "");
                row.setAttribute("data-tags", (data.tags || []).join(","));
                var chips = meta.querySelector(".file-chips");
                if (chips) {
                    chips.remove();
                }
                if (data.category || (data.tags && data.tags.length)) {
                    chips = document.createElement("span");
                    chips.className = "file-chips";
                    if (data.category) {
                        var cb = document.createElement("a");
                        cb.className = "chip chip-cat chip-mini";
                        cb.setAttribute("data-filter-cat", data.category);
                        cb.href = data.category_url;
                        cb.textContent = data.category;
                        chips.appendChild(cb);
                    }
                    (data.tags || []).forEach(function (t) {
                        var tb = document.createElement("button");
                        tb.className = "chip chip-tag chip-mini";
                        tb.setAttribute("data-filter-tag", t);
                        tb.textContent = "#" + t;
                        chips.appendChild(tb);
                    });
                    meta.appendChild(chips);
                }

                form.hidden = true;
                meta.hidden = false;
            })
            .catch(function () { alert("Could not save."); });
    });

    /* Copy hotlink */
    document.addEventListener("click", function (ev) {
        var btn = ev.target.closest(".copy-link");
        if (!btn) {
            return;
        }
        var url = btn.getAttribute("data-hotlink");
        function flash() {
            var old = btn.textContent;
            btn.textContent = "Copied";
            setTimeout(function () { btn.textContent = old; }, 1200);
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(flash, function () {
                window.prompt("Direct link:", url);
            });
        } else {
            window.prompt("Direct link:", url);
        }
    });

    /* Filtering by category or tag, plus a free-text search */
    var activeFilter = null; // { type: "cat"|"tag", value: string }
    var activeSearch = "";   // lowercased needle

    function rowMatchesSearch(row) {
        if (activeSearch === "") { return true; }
        var hay = (
            (row.textContent || "") + " " +
            (row.getAttribute("data-file") || "") + " " +
            (row.getAttribute("data-category") || "") + " " +
            (row.getAttribute("data-tags") || "")
        ).toLowerCase();
        return hay.indexOf(activeSearch) !== -1;
    }

    function applyFilter() {
        var clearBtn = document.getElementById("filter-clear");
        var shown = 0;
        document.querySelectorAll("tr.row-file").forEach(function (row) {
            var show = true;
            if (activeFilter) {
                if (activeFilter.type === "cat") {
                    show = row.getAttribute("data-category") === activeFilter.value;
                } else {
                    var tags = (row.getAttribute("data-tags") || "").split(",");
                    show = tags.indexOf(activeFilter.value) !== -1;
                }
            }
            if (show) {
                show = rowMatchesSearch(row);
            }
            row.hidden = !show;
            if (show) { shown++; }
        });
        document.querySelectorAll(".chip[data-filter-cat], .chip[data-filter-tag]").forEach(function (c) {
            var val = c.getAttribute("data-filter-cat") || c.getAttribute("data-filter-tag");
            var type = c.hasAttribute("data-filter-cat") ? "cat" : "tag";
            c.classList.toggle("chip-active",
                !!activeFilter && activeFilter.type === type && activeFilter.value === val);
        });
        if (clearBtn) {
            clearBtn.hidden = !activeFilter;
        }
        var emptyRow = document.getElementById("search-empty");
        if (emptyRow) {
            var haveRows = document.querySelectorAll("tr.row-file").length > 0;
            emptyRow.hidden = !(haveRows && shown === 0);
        }
    }

    document.addEventListener("click", function (ev) {
        var chip = ev.target.closest(".chip[data-filter-cat], .chip[data-filter-tag]");
        if (!chip) {
            return;
        }
        /* Category chips are also links to their archive page. Filtering in
           place is the default, but a modified click should still open that
           page in a tab the way any other link would. */
        if (chip.tagName === "A" && (ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey || ev.button !== 0)) {
            return;
        }
        ev.preventDefault();
        var type = chip.hasAttribute("data-filter-cat") ? "cat" : "tag";
        var value = chip.getAttribute("data-filter-cat") || chip.getAttribute("data-filter-tag");
        if (!value) {
            return;
        }
        if (activeFilter && activeFilter.type === type && activeFilter.value === value) {
            activeFilter = null; // toggle off
        } else {
            activeFilter = { type: type, value: value };
        }
        applyFilter();
    });

    var clearBtn = document.getElementById("filter-clear");
    if (clearBtn) {
        clearBtn.addEventListener("click", function () {
            activeFilter = null;
            applyFilter();
        });
    }

    var searchInput = document.getElementById("listing-search");
    if (searchInput) {
        var searchTimer = null;
        searchInput.addEventListener("input", function () {
            if (searchTimer) { clearTimeout(searchTimer); }
            searchTimer = setTimeout(function () {
                activeSearch = searchInput.value.trim().toLowerCase();
                applyFilter();
            }, 90);
        });
        /* Escape is handled by the overlay, where it means "close". It used
           to clear the field, which made sense while the field sat inline —
           but with a panel, dismissing it and losing the results at the same
           time is not what a reader is asking for. Clearing is done by
           deleting the text. */
    }

    document.getElementById("btn-print").addEventListener("click", printCurrent);
    document.getElementById("btn-close").addEventListener("click", function () {
        pane.hidden = true;
        body.innerHTML = "";
        current = null;
    });

    /* Colour schemes */
    var saved = null;
    try { saved = localStorage.getItem("folio-theme"); } catch (e) {}
    if (saved) {
        document.documentElement.setAttribute("data-theme", saved);
    }

    function markActive() {
        var active = document.documentElement.getAttribute("data-theme");
        document.querySelectorAll(".theme-picker button").forEach(function (b) {
            b.classList.toggle("active", b.getAttribute("data-set-theme") === active);
        });
    }
    markActive();

    document.querySelectorAll(".theme-picker button").forEach(function (b) {
        b.addEventListener("click", function () {
            var theme = b.getAttribute("data-set-theme");
            document.documentElement.setAttribute("data-theme", theme);
            try { localStorage.setItem("folio-theme", theme); } catch (e) {}
            markActive();
        });
    });

    /* Hover preview cards (desktop, hover-capable pointers only) */
    (function () {
        var card = document.getElementById("hover-card");
        if (!card) { return; }
        var canHover = window.matchMedia
            && window.matchMedia("(hover: hover) and (min-width: 900px)").matches;
        if (!canHover) { return; }

        var mediaBox = document.getElementById("hover-card-media");
        var titleEl = document.getElementById("hover-card-title");
        var metaEl = document.getElementById("hover-card-meta");
        var rows = document.querySelectorAll(".row-file");
        var hideTimer = null;
        var lastKey = null;

        function buildMedia(kind, url, title, isServerThumb) {
            mediaBox.className = "hover-card-media kind-" + (kind || "other");
            if (kind === "image" && url) {
                var img = document.createElement("img");
                img.loading = "lazy";
                img.decoding = "async";
                img.alt = "";
                img.src = url;
                mediaBox.innerHTML = "";
                mediaBox.appendChild(img);
            } else if (kind === "pdf" && url && isServerThumb) {
                /* The server rendered page one already, so this is a small
                   cached image. Fetching it costs a few kilobytes instead of
                   pulling down the whole document — a 6 MB scan took seconds
                   to appear when the browser had to render it itself. */
                var pimg = document.createElement("img");
                pimg.loading = "lazy";
                pimg.decoding = "async";
                pimg.alt = "";
                /* A page Folio could not render — an encrypted or damaged
                   document, or one the server ran out of time on — answers
                   404 here. Without this the browser paints its broken-image
                   icon, which looks like Folio is broken rather than like a
                   document that has no preview. */
                pimg.addEventListener("error", function () {
                    mediaBox.innerHTML = "";
                    var g = document.createElement("span");
                    g.className = "hover-card-glyph";
                    g.textContent = "\u25A4";
                    mediaBox.appendChild(g);
                });
                pimg.src = url;
                mediaBox.innerHTML = "";
                mediaBox.appendChild(pimg);
            } else if (kind === "pdf" && url) {
                /* Render page one to a canvas rather than framing the file.
                   An <iframe> here pulls the entire PDF down on hover, depends
                   on the browser having a PDF plugin, and — if the URL is ever
                   empty — resolves the fragment against the current page and
                   displays the library inside itself. A thumbnail has none of
                   those failure modes. */
                mediaBox.innerHTML = "";
                var pcanvas = document.createElement("canvas");
                pcanvas.className = "hover-card-canvas";
                mediaBox.appendChild(pcanvas);
                renderPdfThumb(url, pcanvas, mediaBox);
            } else {
                // Text / markdown / other: a clean titled tile, no invented image.
                mediaBox.innerHTML = "";
                var glyph = document.createElement("span");
                glyph.className = "hover-card-glyph";
                glyph.textContent = (kind === "md") ? "\u201C \u201D" : "\u25A4";
                mediaBox.appendChild(glyph);
            }
        }

        var thumbCache = {};

        function renderPdfThumb(url, canvas, box) {
            var base = card.getAttribute("data-pdfjs-base");
            if (!base || !url) {
                showGlyph(box, "pdf");
                return;
            }
            if (thumbCache[url] === "failed") {
                showGlyph(box, "pdf");
                return;
            }
            import(/* webpackIgnore: true */ base + "pdf.min.mjs").then(function (pdfjsLib) {
                pdfjsLib.GlobalWorkerOptions.workerSrc = base + "pdf.worker.min.mjs";
                return pdfjsLib.getDocument({ url: url, disableAutoFetch: true, disableStream: false }).promise;
            }).then(function (doc) {
                return doc.getPage(1);
            }).then(function (page) {
                if (!canvas.isConnected) { return; }
                var boxW  = box.getBoundingClientRect().width || 320;
                var nat   = page.getViewport({ scale: 1 });
                var dpr   = Math.min(window.devicePixelRatio || 1, 2);
                var fit   = boxW / nat.width;
                var cssVp = page.getViewport({ scale: fit });
                var pixVp = page.getViewport({ scale: fit * dpr });
                canvas.width  = Math.ceil(pixVp.width);
                canvas.height = Math.ceil(pixVp.height);
                canvas.style.width  = Math.ceil(cssVp.width) + "px";
                canvas.style.height = Math.ceil(cssVp.height) + "px";
                return page.render({ canvasContext: canvas.getContext("2d"), viewport: pixVp }).promise;
            }).catch(function () {
                thumbCache[url] = "failed";
                showGlyph(box, "pdf");
            });
        }

        function showGlyph(box, kind) {
            box.innerHTML = "";
            var glyph = document.createElement("span");
            glyph.className = "hover-card-glyph";
            glyph.textContent = (kind === "md") ? "\u201C \u201D" : "\u25A4";
            box.appendChild(glyph);
        }

        function show(row) {
            if (hideTimer) { clearTimeout(hideTimer); hideTimer = null; }
            var key = row.getAttribute("data-file");
            if (key !== lastKey) {
                lastKey = key;
                var kind = row.getAttribute("data-hover-kind") || "other";
                var url = row.getAttribute("data-hover-url") || "";
                var title = row.getAttribute("data-hover-title") || "";
                var meta = row.getAttribute("data-hover-meta") || "";
                var served = row.getAttribute("data-hover-thumb") === "1";
                buildMedia(kind, url, title, served);
                titleEl.textContent = title;
                metaEl.innerHTML = meta;
            }
            card.classList.add("is-visible");
            card.setAttribute("aria-hidden", "false");
        }

        function scheduleHide() {
            if (hideTimer) { clearTimeout(hideTimer); }
            hideTimer = setTimeout(function () {
                card.classList.remove("is-visible");
                card.setAttribute("aria-hidden", "true");
                lastKey = null;
            }, 180);
        }

        rows.forEach(function (row) {
            row.addEventListener("mouseenter", function () { show(row); });
            row.addEventListener("mouseleave", scheduleHide);
            // Keyboard access: focusing a link inside the row previews it too.
            row.addEventListener("focusin", function () { show(row); });
            row.addEventListener("focusout", scheduleHide);
        });
        card.addEventListener("mouseenter", function () {
            if (hideTimer) { clearTimeout(hideTimer); hideTimer = null; }
        });
        card.addEventListener("mouseleave", scheduleHide);
    })();

    /* ---- Column sorting -----------------------------------------------
     *
     * Sorting reorders rows; it does not decide which are visible. The chip
     * filter and the search box own that, and both work by hiding rows, so
     * moving rows around leaves their decisions intact.
     *
     * Every column sorts on a value carried in a data attribute rather than
     * on what is displayed: "1.5 MB" and "9 B" do not compare as strings, and
     * "Oktober 1998" has no order without its machine form.
     */
    (function () {
        var table = document.querySelector(".listing table");
        if (!table) { return; }
        var tbody = table.querySelector("tbody");
        var heads = table.querySelectorAll("th[data-sort-key]");
        if (!tbody || !heads.length) { return; }

        var collator = window.Intl && Intl.Collator
            ? new Intl.Collator(document.documentElement.lang || "en",
                                { numeric: true, sensitivity: "base" })
            : null;

        function keyFor(row, which) {
            if (which === "size") {
                var s = row.querySelector("[data-sort-size]");
                return s ? parseInt(s.getAttribute("data-sort-size"), 10) || 0 : 0;
            }
            if (which === "date") {
                var d = row.querySelector("[data-sort-date]");
                return d ? d.getAttribute("data-sort-date") || "" : "";
            }
            if (which === "dated") {
                var f = row.querySelector("[data-sort-dated]");
                return f && f.getAttribute("data-sort-dated") === "1" ? 1 : 0;
            }
            var n = row.querySelector("[data-sort-name]");
            return n ? n.getAttribute("data-sort-name") || "" : "";
        }

        function compare(a, b, which) {
            // A document with no date of its own always sorts after those
            // that have one, in both directions. Its fallback value is a file
            // timestamp — a different kind of fact — and letting it mix in
            // among real dates would imply an ordering that is not real.
            if (which === "date") {
                var da = keyFor(a, "dated"), db = keyFor(b, "dated");
                if (da !== db) { return da === 1 ? -1 : 1; }
            }
            var x = keyFor(a, which), y = keyFor(b, which);
            if (which === "size") { return x - y; }
            if (collator) { return collator.compare(String(x), String(y)); }
            return String(x) < String(y) ? -1 : (String(x) > String(y) ? 1 : 0);
        }

        function apply(which, dir) {
            var rows = Array.prototype.slice.call(tbody.querySelectorAll("tr.row-file"));
            rows.sort(function (a, b) {
                if (which === "date") {
                    var da = keyFor(a, "dated"), db = keyFor(b, "dated");
                    if (da !== db) { return da === 1 ? -1 : 1; }
                }
                var r = compare(a, b, which);
                return dir === "descending" ? -r : r;
            });
            var frag = document.createDocumentFragment();
            rows.forEach(function (r) { frag.appendChild(r); });
            tbody.appendChild(frag);

            Array.prototype.forEach.call(heads, function (h) {
                h.setAttribute("aria-sort",
                    h.getAttribute("data-sort-key") === which ? dir : "none");
            });
            try {
                window.sessionStorage.setItem("folio-sort", which + ":" + dir);
            } catch (e) { /* private browsing; sorting still works */ }
        }

        Array.prototype.forEach.call(heads, function (h) {
            var btn = h.querySelector(".col-sort");
            if (!btn) { return; }
            btn.addEventListener("click", function () {
                var which = h.getAttribute("data-sort-key");
                var now = h.getAttribute("aria-sort");
                apply(which, now === "ascending" ? "descending" : "ascending");
            });
        });

        // Restore the previous choice, so moving between folders does not
        // silently reset an order the reader deliberately chose.
        try {
            var saved = window.sessionStorage.getItem("folio-sort");
            if (saved) {
                var parts = saved.split(":");
                if (parts.length === 2 && table.querySelector('th[data-sort-key="' + parts[0] + '"]')) {
                    apply(parts[0], parts[1]);
                }
            }
        } catch (e) { /* nothing saved, or storage unavailable */ }
    })();

    /* ---- Search overlay ------------------------------------------------
     *
     * The field itself is unchanged and still filters on input; this only
     * governs when it is on screen. Closing does not clear the search, so a
     * reader can dismiss the panel and keep looking at the results.
     */
    (function () {
        var trigger = document.getElementById("nav-search-open");
        var overlay = document.getElementById("search-overlay");
        var input = document.getElementById("listing-search");
        var closeBtn = document.getElementById("search-overlay-close");
        if (!trigger || !overlay || !input) { return; }

        var lastFocus = null;

        function open() {
            lastFocus = document.activeElement;
            overlay.hidden = false;
            trigger.setAttribute("aria-expanded", "true");
            input.focus();
            input.select();
        }

        function close() {
            overlay.hidden = true;
            trigger.setAttribute("aria-expanded", "false");
            // Focus goes back where it came from, so dismissing the panel does
            // not strand a keyboard user at the top of the document.
            if (lastFocus && typeof lastFocus.focus === "function") {
                lastFocus.focus();
            } else {
                trigger.focus();
            }
        }

        trigger.addEventListener("click", function () {
            if (overlay.hidden) { open(); } else { close(); }
        });
        if (closeBtn) { closeBtn.addEventListener("click", close); }

        // Clicking the backdrop dismisses; clicking inside the panel does not.
        overlay.addEventListener("mousedown", function (ev) {
            if (ev.target === overlay) { close(); }
        });

        /* Escape while the field has focus closes the panel and keeps the
           results. Handled on the input so it runs before anything else and
           cannot be mistaken for a request to clear the field. */
        input.addEventListener("keydown", function (ev) {
            if (ev.key === "Escape") {
                ev.preventDefault();
                ev.stopPropagation();
                close();
            }
        });

        document.addEventListener("keydown", function (ev) {
            if (ev.key === "Escape" && !overlay.hidden) {
                close();
                return;
            }
            // "/" is the long-standing shortcut for search, but not while the
            // reader is typing into something else.
            if (ev.key === "/" && overlay.hidden) {
                var t = ev.target;
                var tag = t && t.tagName ? t.tagName.toLowerCase() : "";
                if (tag === "input" || tag === "textarea" || tag === "select"
                    || (t && t.isContentEditable)) {
                    return;
                }
                ev.preventDefault();
                open();
            }
        });

        // Keep focus inside the panel while it is open.
        overlay.addEventListener("keydown", function (ev) {
            if (ev.key !== "Tab") { return; }
            var focusable = overlay.querySelectorAll("input, button");
            if (!focusable.length) { return; }
            var first = focusable[0], last = focusable[focusable.length - 1];
            if (ev.shiftKey && document.activeElement === first) {
                ev.preventDefault(); last.focus();
            } else if (!ev.shiftKey && document.activeElement === last) {
                ev.preventDefault(); first.focus();
            }
        });
    })();
})();
