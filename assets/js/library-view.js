/* Folio. Copyright (C) 2026 Mohd Elfie Nieshaem Juferi. SPDX-License-Identifier: GPL-3.0-or-later */
/* Folio — human-readable rendering of library.yaml in the browser.
 *
 * Fetches the site's library.yaml, parses it with the bundled js-yaml
 * (window.jsyaml, served from lib/js-yaml/, allowed by script-src 'self'),
 * and renders the resources, AI usage policy, and document list as HTML.
 * Progressive enhancement: the page ships a link to the raw YAML, so a
 * reader without JavaScript still reaches the data. */
(function () {
    "use strict";

    var root = document.getElementById("library-view");
    if (!root) {
        return;
    }
    var src = root.getAttribute("data-yaml-url");
    if (!src || typeof window.jsyaml === "undefined") {
        return; // link to the raw file remains visible
    }

    function el(tag, className, text) {
        var n = document.createElement(tag);
        if (className) { n.className = className; }
        if (text !== undefined && text !== null) { n.textContent = String(text); }
        return n;
    }

    function renderPermissions(perms, into) {
        if (!perms || typeof perms !== "object") { return; }
        var sec = el("section", "lv-section");
        sec.appendChild(el("h2", null, "AI usage policy"));
        var dl = el("dl", "lv-perms");
        ["quote", "summarise", "train", "commercial"].forEach(function (k) {
            if (!(k in perms)) { return; }
            dl.appendChild(el("dt", null, k));
            dl.appendChild(el("dd", "lv-" + (perms[k] ? "yes" : "no"), perms[k] ? "allowed" : "not allowed"));
        });
        sec.appendChild(dl);
        if (perms.note) {
            sec.appendChild(el("p", "lv-note", perms.note));
        }
        into.appendChild(sec);
    }

    function renderResources(res, into) {
        if (!res || typeof res !== "object") { return; }
        var sec = el("section", "lv-section");
        sec.appendChild(el("h2", null, "Related resources"));
        var ul = el("ul", "lv-resources");
        Object.keys(res).forEach(function (k) {
            var li = el("li");
            li.appendChild(el("span", "lv-res-key", k + ": "));
            var a = el("a", null, res[k]);
            a.href = res[k];
            li.appendChild(a);
            ul.appendChild(li);
        });
        sec.appendChild(ul);
        into.appendChild(sec);
    }

    function renderDocuments(docs, into) {
        var sec = el("section", "lv-section");
        sec.appendChild(el("h2", null, "Documents" + (docs && docs.length ? " (" + docs.length + ")" : "")));
        if (!docs || !docs.length) {
            sec.appendChild(el("p", "lv-note", "No public documents."));
            into.appendChild(sec);
            return;
        }
        docs.forEach(function (d) {
            var item = el("article", "lv-doc");
            var h = el("h3", "lv-doc-title");
            if (d.url) {
                var a = el("a", null, d.title || d.url);
                a.href = d.url;
                h.appendChild(a);
            } else {
                h.textContent = d.title || "Untitled";
            }
            item.appendChild(h);
            var meta = el("p", "lv-doc-meta");
            var bits = [];
            if (d.category) { bits.push(d.category); }
            if (d.modified) { bits.push(String(d.modified).slice(0, 10)); }
            if (d.language) { bits.push(d.language); }
            if (bits.length) { meta.textContent = bits.join(" · "); item.appendChild(meta); }
            if (d.description) { item.appendChild(el("p", "lv-doc-desc", d.description)); }
            if (d.tags && d.tags.length) {
                var tags = el("p", "lv-doc-tags");
                d.tags.forEach(function (t) { tags.appendChild(el("span", "lv-tag", "#" + t)); });
                item.appendChild(tags);
            }
            sec.appendChild(item);
        });
        into.appendChild(sec);
    }

    root.textContent = "Loading…";
    fetch(src, { headers: { "Accept": "application/yaml, text/plain" } })
        .then(function (r) {
            if (!r.ok) { throw new Error("HTTP " + r.status); }
            return r.text();
        })
        .then(function (text) {
            var data;
            try {
                data = window.jsyaml.load(text);
            } catch (e) {
                root.textContent = "Could not parse library.yaml.";
                return;
            }
            root.textContent = "";
            var site = (data && data.site) || {};
            if (site.name) { root.appendChild(el("h1", "lv-title", site.name)); }
            if (site.description) { root.appendChild(el("p", "lv-desc", site.description)); }
            renderResources(site.resources, root);
            renderPermissions(site.permissions, root);
            renderDocuments(data && data.documents, root);
        })
        .catch(function () {
            root.textContent = "Could not load library.yaml.";
        });
})();
