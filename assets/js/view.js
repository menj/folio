/* Folio. Copyright (C) 2026 Mohd Elfie Nieshaem Juferi. SPDX-License-Identifier: GPL-3.0-or-later */
/* Folio — file detail page */
(function () {
    "use strict";

    /* ---- PDF preview -----------------------------------------------------
     *
     * Preferred: embed the PDF so the reader can scroll and search it in
     * place using the browser's own viewer. Desktop Chrome, Firefox, Edge,
     * and Safari 16.4+ all have one.
     *
     * Chrome on Android and older Safari on iOS do not, and an <iframe>
     * there renders a "content blocked" placeholder. navigator.pdfViewerEnabled
     * reports which case we are in, and where the answer is no (or the
     * property is missing on an older engine) we fall back to rendering page
     * one to a <canvas> with the pdf.js copy already shipped for the flip
     * reader. Either way the action row below offers flip view and download.
     */
    var pdfPreview = document.querySelector(".pdf-preview");
    if (pdfPreview) {
        if (navigator.pdfViewerEnabled === true) {
            embedPdfInline(pdfPreview);
        } else {
            renderPdfPreview(pdfPreview);
        }
    }

    /* Hand the file to the browser's built-in viewer: scrollable, searchable,
       printable, and free of any pdf.js download on capable browsers. */
    function embedPdfInline(host) {
        var wrap   = host.querySelector(".pdf-preview-canvas-wrap");
        var pdfUrl = host.getAttribute("data-pdf-url");
        if (!wrap || !pdfUrl) {
            return;
        }
        var frame = document.createElement("iframe");
        frame.className = "pdf-frame";
        /* Drop the browser's own PDF toolbar where it is honoured, so its
           download and print buttons do not sit on top of the ones Folio
           already provides. Presentation only: the parameters are ignored by
           some browsers, and access is governed by pdf_access on the server. */
        frame.src = pdfUrl + (pdfUrl.indexOf("#") === -1 ? "#" : "&")
                  + "toolbar=0&navpanes=0&statusbar=0&view=FitH";
        frame.title = host.getAttribute("data-pdf-title") || "PDF preview";
        while (wrap.firstChild) {
            wrap.removeChild(wrap.firstChild);
        }
        wrap.appendChild(frame);
        host.classList.add("pdf-preview-inline");
    }

    function renderPdfPreview(host) {
        var wrap    = host.querySelector(".pdf-preview-canvas-wrap");
        var canvas  = host.querySelector(".pdf-preview-canvas");
        var status  = host.querySelector(".pdf-preview-status");
        var base    = host.getAttribute("data-pdfjs-base");
        var pdfUrl  = host.getAttribute("data-pdf-url");
        var flipUrl = host.getAttribute("data-flip-url");
        if (!wrap || !canvas || !base || !pdfUrl) {
            return;
        }

        var failed = function (msg) {
            /* Never leave the reader with a blank space. If pdf.js cannot
               render the preview for any reason, the flip reader and download
               button are still present in the action row below. */
            wrap.classList.add("pdf-preview-failed");
            canvas.remove();
            if (status) {
                status.textContent = msg + " Use the buttons below to read or download the file.";
                status.classList.add("pdf-preview-error");
            }
        };

        /* Nothing below should be able to hang. If the module never loads or
           never resolves — a wrong MIME type on .mjs, a blocked request, a
           stalled network — say so rather than leaving "Loading" on screen
           forever. Cleared as soon as page one paints. */
        var watchdog = window.setTimeout(function () {
            failed("The preview timed out.");
        }, 15000);
        var settled = function () { window.clearTimeout(watchdog); };

        import(/* webpackIgnore: true */ base + "pdf.min.mjs").then(function (pdfjsLib) {
            pdfjsLib.GlobalWorkerOptions.workerSrc = base + "pdf.worker.min.mjs";
            return pdfjsLib.getDocument({
                url: pdfUrl,
                wasmUrl: base + "wasm/",
                isEvalSupported: false
            }).promise;
        }).then(function (doc) {
            return doc.getPage(1);
        }).then(function (page) {
            var natural = page.getViewport({ scale: 1 });
            var boxW    = wrap.getBoundingClientRect().width || 600;
            /* Cap the buffer so a Retina screen does not force a giant canvas. */
            var dpr     = Math.min(window.devicePixelRatio || 1, 2);
            var fit     = Math.min(boxW / natural.width, 1.4);
            var cssVp   = page.getViewport({ scale: fit });
            var pixVp   = page.getViewport({ scale: fit * dpr });
            canvas.width  = Math.ceil(pixVp.width);
            canvas.height = Math.ceil(pixVp.height);
            canvas.style.width  = Math.ceil(cssVp.width) + "px";
            canvas.style.height = Math.ceil(cssVp.height) + "px";
            return page.render({
                canvasContext: canvas.getContext("2d"),
                viewport: pixVp
            }).promise;
        }).then(function () {
            settled();
            if (status) { status.remove(); }
            /* Tapping the preview enters the flip reader — a clear affordance
               that "the preview is a doorway to the reader". */
            if (flipUrl) {
                canvas.style.cursor = "pointer";
                canvas.addEventListener("click", function () {
                    window.location.href = flipUrl;
                });
            }
        }).catch(function (err) {
            settled();
            var name = err && err.name;
            if (name === "PasswordException") {
                failed("This PDF is password-protected and cannot be previewed here.");
            } else if (name === "InvalidPDFException") {
                failed("This PDF appears to be damaged or incomplete.");
            } else {
                failed("The preview could not be loaded.");
            }
        });
    }

    /* ---- Print button ---------------------------------------------------- */

    var frameEl = document.getElementById("print-frame");
    var btn = document.getElementById("btn-print");
    if (!frameEl || !btn) {
        return;
    }
    var cfg = { kind: frameEl.getAttribute("data-kind"), url: frameEl.getAttribute("data-url") };

    btn.addEventListener("click", function () {
        if (cfg.kind === "md") {
            window.print();
            return;
        }
        if (cfg.kind === "image") {
            frameEl.onload = function () {
                frameEl.onload = null;
                try {
                    frameEl.contentWindow.focus();
                    frameEl.contentWindow.print();
                } catch (e) {
                    window.open(cfg.url, "_blank", "noopener");
                }
            };
            frameEl.src = cfg.url;
        } else {
            /* PDFs no longer have an inline iframe to print, so hand the file
               to the browser's own viewer. Desktop opens the PDF in a new tab
               with print controls; mobile downloads it. Both are what the
               person meant by "print". */
            window.open(cfg.url, "_blank", "noopener");
        }
    });
}());
