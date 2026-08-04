/* Folio — PDF flip-view reader.
 *
 * Loaded only on ?action=flipbook, as an ES module. Renders real PDF pages
 * with the vendored, official pdf.js (lib/pdfjs/), one page at a time, with
 * a page-turn transition between them. No jQuery, no Turn.js: this reader
 * is written from scratch against pdf.js's public API.
 *
 * The reduced-motion and error paths are not afterthoughts — a PDF reader
 * that goes blank on a bad file, or that forces an animation on someone who
 * asked their system not to show them one, is worse than no reader at all.
 */
(function () {
    "use strict";

    var stage = document.getElementById("flip-stage");
    if (!stage) {
        return;
    }

    var pdfUrl    = stage.getAttribute("data-pdf-url") || "";
    var pdfjsBase = stage.getAttribute("data-pdfjs-base") || "";
    var statusEl  = null;
    var prevBtn   = document.getElementById("flip-prev");
    var nextBtn   = document.getElementById("flip-next");
    var pageInput = document.getElementById("flip-page-input");
    var totalEl   = document.getElementById("flip-page-total");

    var reduceMotion = window.matchMedia
        && window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    var pdfDoc      = null;
    var pageCount   = 0;
    var currentPage = 1;
    var renderToken = 0;      // guards against out-of-order async renders
    var cache       = new Map(); // page number -> canvas
    var CACHE_LIMIT = 8;
    var busy        = false;

    function setStatus(text, isError) {
        if (!statusEl) {
            return;
        }
        statusEl.hidden = !text;
        statusEl.textContent = text || "";
        statusEl.classList.toggle("flip-status-error", !!isError);
    }

    function showError(message, showDownloadHint) {
        setStatus(
            message + (showDownloadHint ? " Use Download in the header to open it another way." : ""),
            true
        );
        if (prevBtn) { prevBtn.disabled = true; }
        if (nextBtn) { nextBtn.disabled = true; }
        if (pageInput) { pageInput.disabled = true; }
    }

    /* Fit a page into the available stage area, capping device-pixel scale
       so a huge monitor does not force absurd canvas sizes. */
    function computeFitScale(viewport, boxW, boxH) {
        return Math.max(Math.min(boxW / viewport.width, boxH / viewport.height), 0.05);
    }

    function evictIfNeeded() {
        if (cache.size <= CACHE_LIMIT) {
            return;
        }
        var farthest = null;
        var farthestDist = -1;
        cache.forEach(function (_canvas, page) {
            var dist = Math.abs(page - currentPage);
            if (dist > farthestDist) {
                farthestDist = dist;
                farthest = page;
            }
        });
        if (farthest !== null) {
            cache.delete(farthest);
        }
    }

    function renderPageToCanvas(pageNumber) {
        if (cache.has(pageNumber)) {
            return Promise.resolve(cache.get(pageNumber));
        }
        return pdfDoc.getPage(pageNumber).then(function (page) {
            var box = stageInner.getBoundingClientRect();
            var boxW = box.width || 600;
            var boxH = box.height || 800;
            var nativeViewport = page.getViewport({ scale: 1 });
            var fitScale = computeFitScale(nativeViewport, boxW, boxH);
            var dpr = Math.min(window.devicePixelRatio || 1, 2.5);
            var cssViewport = page.getViewport({ scale: fitScale });
            var pixelViewport = page.getViewport({ scale: fitScale * dpr });

            var canvas = document.createElement("canvas");
            canvas.width = Math.ceil(pixelViewport.width);
            canvas.height = Math.ceil(pixelViewport.height);
            canvas.style.width = Math.ceil(cssViewport.width) + "px";
            canvas.style.height = Math.ceil(cssViewport.height) + "px";
            var ctx = canvas.getContext("2d");

            return page.render({ canvasContext: ctx, viewport: pixelViewport }).promise.then(function () {
                cache.set(pageNumber, canvas);
                evictIfNeeded();
                return canvas;
            });
        });
    }

    function prefetchAround(pageNumber) {
        [pageNumber + 1, pageNumber - 1].forEach(function (n) {
            if (n >= 1 && n <= pageCount && !cache.has(n)) {
                var idle = window.requestIdleCallback || function (fn) { return setTimeout(fn, 200); };
                idle(function () {
                    renderPageToCanvas(n).catch(function () { /* prefetch is best-effort */ });
                });
            }
        });
    }

    function updateControls() {
        if (pageInput) { pageInput.value = String(currentPage); }
        if (totalEl) { totalEl.textContent = String(pageCount); }
        if (prevBtn) { prevBtn.disabled = currentPage <= 1; }
        if (nextBtn) { nextBtn.disabled = currentPage >= pageCount; }
    }

    /* ---- DOM for the stage: an under-layer (the page being revealed) and
       a flipping leaf (the page animating away). ------------------------ */

    var stageInner = document.createElement("div");
    stageInner.className = "flip-inner";

    var underCanvasHolder = document.createElement("div");
    underCanvasHolder.className = "flip-under";

    var leaf = document.createElement("div");
    leaf.className = "flip-leaf";
    var leafCanvasHolder = document.createElement("div");
    leafCanvasHolder.className = "flip-leaf-face";
    leaf.appendChild(leafCanvasHolder);

    stageInner.appendChild(underCanvasHolder);
    stageInner.appendChild(leaf);

    function placeCanvas(holder, canvas) {
        holder.innerHTML = "";
        holder.appendChild(canvas);
    }

    /* Show a page with no transition at all — used for the first paint,
       for reduced-motion, and for jumps of more than one page. */
    function showInstant(pageNumber) {
        var myToken = ++renderToken;
        return renderPageToCanvas(pageNumber).then(function (canvas) {
            if (myToken !== renderToken) {
                return;
            }
            underCanvasHolder.innerHTML = "";
            leaf.classList.remove("is-flipping-next", "is-flipping-prev");
            leaf.style.transition = "none";
            leaf.style.transform = "rotateY(0deg)";
            placeCanvas(leafCanvasHolder, canvas);
            // Force layout so the next transition (if any) is not merged with this reset.
            void leaf.offsetWidth;
            leaf.style.transition = "";
            currentPage = pageNumber;
            updateControls();
            setStatus("");
            prefetchAround(currentPage);
        }).catch(function (err) {
            handleRenderError(err);
        });
    }

    function turnPage(direction) {
        if (busy) {
            return;
        }
        var target = currentPage + direction;
        if (target < 1 || target > pageCount) {
            return;
        }
        if (reduceMotion) {
            showInstant(target);
            return;
        }

        busy = true;
        var myToken = ++renderToken;
        renderPageToCanvas(target).then(function (nextCanvas) {
            if (myToken !== renderToken) {
                busy = false;
                return;
            }
            // Under-layer already shows the *current* page; put the target
            // beneath it so lifting the leaf reveals the right thing.
            placeCanvas(underCanvasHolder, nextCanvas);

            var flipClass = direction > 0 ? "is-flipping-next" : "is-flipping-prev";
            leaf.classList.add(flipClass);

            var onEnd = function () {
                leaf.removeEventListener("transitionend", onEnd);
                leaf.classList.remove(flipClass);
                leaf.style.transition = "none";
                leaf.style.transform = "rotateY(0deg)";
                placeCanvas(leafCanvasHolder, nextCanvas);
                void leaf.offsetWidth;
                leaf.style.transition = "";
                currentPage = target;
                updateControls();
                busy = false;
                prefetchAround(currentPage);
            };
            leaf.addEventListener("transitionend", onEnd);
        }).catch(function (err) {
            busy = false;
            handleRenderError(err);
        });
    }

    function handleRenderError(err) {
        var name = err && err.name;
        if (name === "PasswordException") {
            showError("This PDF is password-protected and cannot be shown here.", true);
        } else if (name === "InvalidPDFException") {
            showError("This file is not a readable PDF. It may be damaged or incomplete.", true);
        } else if (name === "MissingPDFException") {
            showError("This PDF could not be found on the server.", false);
        } else if (name === "UnexpectedResponseException") {
            showError("The server would not serve this PDF.", true);
        } else {
            showError("This page could not be rendered.", true);
        }
    }

    function jumpToPage(n) {
        n = Math.max(1, Math.min(pageCount, n | 0));
        if (n === currentPage) {
            updateControls();
            return;
        }
        if (Math.abs(n - currentPage) === 1 && !reduceMotion) {
            turnPage(n - currentPage);
        } else {
            showInstant(n);
        }
    }

    /* ---- Controls ------------------------------------------------------ */

    if (prevBtn) { prevBtn.addEventListener("click", function () { turnPage(-1); }); }
    if (nextBtn) { nextBtn.addEventListener("click", function () { turnPage(1); }); }

    if (pageInput) {
        pageInput.addEventListener("keydown", function (ev) {
            if (ev.key === "Enter") {
                jumpToPage(parseInt(pageInput.value, 10) || currentPage);
                pageInput.blur();
            }
        });
        pageInput.addEventListener("blur", function () {
            jumpToPage(parseInt(pageInput.value, 10) || currentPage);
        });
    }

    document.addEventListener("keydown", function (ev) {
        if (document.activeElement === pageInput) {
            return;
        }
        if (ev.key === "ArrowRight") { turnPage(1); }
        else if (ev.key === "ArrowLeft") { turnPage(-1); }
        else if (ev.key === "Home") { jumpToPage(1); }
        else if (ev.key === "End") { jumpToPage(pageCount); }
        else if (ev.key === "Escape") {
            var back = document.querySelector(".flip-back");
            if (back) { window.location.href = back.href; }
        }
    });

    /* Click the left/right thirds of the stage to turn pages, like a book. */
    stage.addEventListener("click", function (ev) {
        if (busy) {
            return;
        }
        var box = stage.getBoundingClientRect();
        var x = ev.clientX - box.left;
        if (x < box.width / 3) { turnPage(-1); }
        else if (x > (box.width * 2) / 3) { turnPage(1); }
    });

    var resizeTimer = null;
    window.addEventListener("resize", function () {
        if (resizeTimer) { clearTimeout(resizeTimer); }
        resizeTimer = setTimeout(function () {
            cache.clear();
            showInstant(currentPage);
        }, 200);
    });

    /* ---- Boot ------------------------------------------------------------ */

    stage.innerHTML = "";
    stage.appendChild(stageInner);
    var statusHolder = document.createElement("p");
    statusHolder.className = "flip-status";
    statusHolder.id = "flip-status";
    stage.appendChild(statusHolder);
    statusEl = statusHolder;

    if (!pdfUrl || !pdfjsBase) {
        showError("This viewer is missing its configuration.", false);
        return;
    }

    setStatus("Loading\u2026");

    import(/* webpackIgnore: true */ pdfjsBase + "pdf.min.mjs").then(function (pdfjsLib) {
        pdfjsLib.GlobalWorkerOptions.workerSrc = pdfjsBase + "pdf.worker.min.mjs";
        return pdfjsLib.getDocument({
            url: pdfUrl,
            wasmUrl: pdfjsBase + "wasm/",
            // No PDF in a document library should need runtime code generation.
            isEvalSupported: false,
        }).promise;
    }).then(function (doc) {
        pdfDoc = doc;
        pageCount = doc.numPages;
        updateControls();
        return showInstant(1);
    }).catch(function (err) {
        handleRenderError(err);
    });
}());
