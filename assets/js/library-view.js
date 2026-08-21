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

    /* A stable, readable anchor id from a category name, so the index at the
     * top of the sitemap can jump to each group. */
    function anchorId(name) {
        return "cat-" + String(name).toLowerCase()
            .replace(/[^a-z0-9]+/g, "-")
            .replace(/^-+|-+$/g, "");
    }

    function renderDocItem(d, into) {
        var item = el("li", "lv-doc");
        var title = el("div", "lv-doc-title");
        if (d.url) {
            var a = el("a", null, d.title || d.url);
            a.href = d.url;
            title.appendChild(a);
        } else {
            title.textContent = d.title || "Untitled";
        }
        item.appendChild(title);
        var meta = el("p", "lv-doc-meta");
        var bits = [];
        if (d.modified) { bits.push(String(d.modified).slice(0, 10)); }
        if (d.language) { bits.push(d.language); }
        if (bits.length) { meta.textContent = bits.join(" · "); item.appendChild(meta); }
        if (d.description) { item.appendChild(el("p", "lv-doc-desc", d.description)); }
        if (d.tags && d.tags.length) {
            var tags = el("p", "lv-doc-tags");
            d.tags.forEach(function (t) { tags.appendChild(el("span", "lv-tag", "#" + t)); });
            item.appendChild(tags);
        }
        into.appendChild(item);
    }

    /* The document list is laid out as an HTML sitemap: one group per category,
     * each a heading over a list of links, with an index of the groups at the
     * top. Every field the flat list carried (link, date, language, description,
     * tags) is kept inside each entry, so nothing is lost in the regrouping. */
    function renderDocuments(docs, into) {
        var sec = el("section", "lv-section lv-sitemap");
        sec.appendChild(el("h2", null, "Documents" + (docs && docs.length ? " (" + docs.length + ")" : "")));
        if (!docs || !docs.length) {
            sec.appendChild(el("p", "lv-note", "No public documents."));
            into.appendChild(sec);
            return;
        }

        var groups = {};
        var order = [];
        docs.forEach(function (d) {
            var cat = (d.category && String(d.category).trim()) || "Uncategorised";
            if (!groups[cat]) { groups[cat] = []; order.push(cat); }
            groups[cat].push(d);
        });
        order.sort(function (a, b) {
            if (a === "Uncategorised") { return 1; }
            if (b === "Uncategorised") { return -1; }
            return a.localeCompare(b);
        });

        // Index of the groups: the table of contents an HTML sitemap opens with.
        if (order.length > 1) {
            var nav = el("nav", "lv-index");
            nav.setAttribute("aria-label", "Categories");
            order.forEach(function (cat) {
                var link = el("a", "lv-index-link", cat + " (" + groups[cat].length + ")");
                link.href = "#" + anchorId(cat);
                nav.appendChild(link);
            });
            sec.appendChild(nav);
        }

        order.forEach(function (cat) {
            var group = el("section", "lv-group");
            var h = el("h3", "lv-group-title", cat);
            h.id = anchorId(cat);
            group.appendChild(h);
            var ul = el("ul", "lv-doclist");
            groups[cat].forEach(function (d) { renderDocItem(d, ul); });
            group.appendChild(ul);
            sec.appendChild(group);
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
