/* Folio — file detail page */
(function () {
    "use strict";

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
            var doc = frameEl.contentDocument;
            doc.open();
            doc.write(
                "<html><head><style>body{margin:0}img{max-width:100%}</style></head><body>" +
                '<img src="' + cfg.url + '" onload="window.print()">' +
                "</body></html>"
            );
            doc.close();
        } else {
            var pdf = document.querySelector(".detail-media iframe");
            if (pdf) {
                try {
                    pdf.contentWindow.focus();
                    pdf.contentWindow.print();
                } catch (e) {
                    window.open(cfg.url, "_blank");
                }
            }
        }
    });
})();
