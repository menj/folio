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
            frame.src = src;
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
        /* Esc clears the search field without losing focus. */
        searchInput.addEventListener("keydown", function (ev) {
            if (ev.key === "Escape" && searchInput.value !== "") {
                searchInput.value = "";
                activeSearch = "";
                applyFilter();
                ev.stopPropagation();
            }
        });
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

        function buildMedia(kind, url, title) {
            mediaBox.className = "hover-card-media kind-" + (kind || "other");
            if (kind === "image" && url) {
                var img = document.createElement("img");
                img.loading = "lazy";
                img.decoding = "async";
                img.alt = "";
                img.src = url;
                mediaBox.innerHTML = "";
                mediaBox.appendChild(img);
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
                buildMedia(kind, url, title);
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
})();
