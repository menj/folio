/* Folio — preview, print, themes */
(function () {
    "use strict";

    var pane = document.getElementById("preview-pane");
    var body = document.getElementById("preview-body");
    var nameEl = document.getElementById("preview-name");
    var printFrame = document.getElementById("print-frame");
    var current = null; // { file, kind, url }

    function rawUrl(file) {
        // Files live in the uploads folder and are served directly.
        return "uploads/" + file.split("/").map(encodeURIComponent).join("/");
    }

    function renderUrl(file) {
        return "?action=render&file=" + encodeURIComponent(file);
    }

    function openPreview(file, kind, label) {
        current = { file: file, kind: kind, url: rawUrl(file) };
        nameEl.textContent = label || file.split("/").pop().replace(/\.[^.]+$/, "");
        body.innerHTML = "";

        if (kind === "image") {
            var img = document.createElement("img");
            img.src = current.url;
            img.alt = nameEl.textContent;
            body.appendChild(img);
        } else {
            var frame = document.createElement("iframe");
            frame.src = kind === "md" ? renderUrl(file) : current.url;
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
            var doc = printFrame.contentDocument;
            doc.open();
            doc.write(
                "<html><head><title>" + nameEl.textContent + "</title>" +
                "<style>body{margin:0}img{max-width:100%}</style></head><body>" +
                '<img src="' + current.url + '" onload="window.print()">' +
                "</body></html>"
            );
            doc.close();
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
                titleEl ? titleEl.textContent : ""
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
                        var cb = document.createElement("button");
                        cb.className = "chip chip-cat chip-mini";
                        cb.setAttribute("data-filter-cat", data.category);
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

    /* Filtering by category or tag */
    var activeFilter = null; // { type: "cat"|"tag", value: string }

    function applyFilter() {
        var clearBtn = document.getElementById("filter-clear");
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
            row.hidden = !show;
        });
        document.querySelectorAll(".chip").forEach(function (c) {
            var val = c.getAttribute("data-filter-cat") || c.getAttribute("data-filter-tag");
            var type = c.hasAttribute("data-filter-cat") ? "cat" : "tag";
            c.classList.toggle("chip-active",
                !!activeFilter && activeFilter.type === type && activeFilter.value === val);
        });
        if (clearBtn) {
            clearBtn.hidden = !activeFilter;
        }
    }

    document.addEventListener("click", function (ev) {
        var chip = ev.target.closest(".chip");
        if (!chip || chip.id === "filter-clear") {
            return;
        }
        ev.preventDefault();
        var type = chip.hasAttribute("data-filter-cat") ? "cat" : "tag";
        var value = chip.getAttribute("data-filter-cat") || chip.getAttribute("data-filter-tag");
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
})();
