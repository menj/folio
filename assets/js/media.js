/* Folio. Copyright (C) 2026 Mohd Elfie Nieshaem Juferi. SPDX-License-Identifier: GPL-3.0-or-later */
/* Folio — themed transport for audio and video.
 *
 * Progressive enhancement. The page ships a plain <audio>/<video> element
 * with the native `controls` attribute, so a reader with JavaScript off still
 * gets a working player. This replaces those controls with a transport styled
 * to Folio's themes. If anything here throws, the native controls are put
 * back, so a failure degrades to a working player rather than a broken one.
 */
(function () {
    "use strict";

    var SVG = "http://www.w3.org/2000/svg";

    function icon(paths) {
        var svg = document.createElementNS(SVG, "svg");
        svg.setAttribute("viewBox", "0 0 24 24");
        svg.setAttribute("aria-hidden", "true");
        svg.setAttribute("focusable", "false");
        for (var i = 0; i < paths.length; i++) {
            var p = document.createElementNS(SVG, "path");
            p.setAttribute("d", paths[i]);
            svg.appendChild(p);
        }
        return svg;
    }

    var ICONS = {
        play: ["M8 5v14l11-7z"],
        pause: ["M6 5h4v14H6z", "M14 5h4v14h-4z"],
        volume: ["M4 9v6h4l5 5V4L8 9H4z", "M16 8a5 5 0 0 1 0 8"],
        muted: ["M4 9v6h4l5 5V4L8 9H4z", "M22 9l-6 6", "M16 9l6 6"],
        full: ["M4 9V4h5", "M20 9V4h-5", "M4 15v5h5", "M20 15v5h-5"],
        exitfull: ["M9 4v5H4", "M15 4v5h5", "M9 20v-5H4", "M15 20v-5h5"]
    };

    function fmtTime(t) {
        if (!isFinite(t) || t < 0) {
            t = 0;
        }
        t = Math.floor(t);
        var s = t % 60;
        var m = Math.floor(t / 60) % 60;
        var h = Math.floor(t / 3600);
        var mm = (h && m < 10) ? "0" + m : String(m);
        var ss = s < 10 ? "0" + s : String(s);
        return (h ? h + ":" : "") + mm + ":" + ss;
    }

    function button(cls, label, iconPaths) {
        var b = document.createElement("button");
        b.type = "button";
        b.className = "fm-btn " + cls;
        b.setAttribute("aria-label", label);
        b.appendChild(icon(iconPaths));
        return b;
    }

    function mount(wrap) {
        if (!wrap || wrap.getAttribute("data-fm-ready") === "1") {
            return;
        }
        var el = wrap.querySelector(".fm-el");
        if (!el) {
            return;
        }
        var isVideo = el.tagName.toLowerCase() === "video";

        try {
            el.removeAttribute("controls");
            wrap.setAttribute("data-fm-ready", "1");

            var bar = document.createElement("div");
            bar.className = "fm-bar";

            var playBtn = button("fm-play", "Play", ICONS.play);

            var scrub = document.createElement("div");
            scrub.className = "fm-scrub";
            scrub.setAttribute("role", "slider");
            scrub.setAttribute("tabindex", "0");
            scrub.setAttribute("aria-label", "Seek");
            scrub.setAttribute("aria-valuemin", "0");
            var buffered = document.createElement("div");
            buffered.className = "fm-buffered";
            var played = document.createElement("div");
            played.className = "fm-played";
            var knob = document.createElement("div");
            knob.className = "fm-knob";
            scrub.appendChild(buffered);
            scrub.appendChild(played);
            scrub.appendChild(knob);

            var time = document.createElement("span");
            time.className = "fm-time";
            time.textContent = "0:00 / 0:00";

            var volBtn = button("fm-mute", "Mute", ICONS.volume);
            var vol = document.createElement("input");
            vol.type = "range";
            vol.className = "fm-vol";
            vol.min = "0";
            vol.max = "1";
            vol.step = "0.05";
            vol.value = "1";
            vol.setAttribute("aria-label", "Volume");

            bar.appendChild(playBtn);
            bar.appendChild(scrub);
            bar.appendChild(time);
            bar.appendChild(volBtn);
            bar.appendChild(vol);

            var fullBtn = null;
            if (isVideo) {
                fullBtn = button("fm-full", "Full screen", ICONS.full);
                bar.appendChild(fullBtn);
            }

            wrap.appendChild(bar);

            /* ---- wiring ---- */

            function setPlayIcon(playing) {
                playBtn.innerHTML = "";
                playBtn.appendChild(icon(playing ? ICONS.pause : ICONS.play));
                playBtn.setAttribute("aria-label", playing ? "Pause" : "Play");
                wrap.classList.toggle("fm-playing", playing);
            }

            function toggle() {
                if (el.paused) {
                    el.play();
                } else {
                    el.pause();
                }
            }

            playBtn.addEventListener("click", toggle);
            if (isVideo) {
                el.addEventListener("click", toggle);
            }

            el.addEventListener("play", function () { setPlayIcon(true); });
            el.addEventListener("pause", function () { setPlayIcon(false); });
            el.addEventListener("ended", function () { setPlayIcon(false); });

            function refreshProgress() {
                var d = el.duration;
                var c = el.currentTime;
                var pct = (d > 0) ? (c / d) * 100 : 0;
                played.style.width = pct + "%";
                knob.style.left = pct + "%";
                time.textContent = fmtTime(c) + " / " + fmtTime(d);
                scrub.setAttribute("aria-valuemax", String(Math.floor(d || 0)));
                scrub.setAttribute("aria-valuenow", String(Math.floor(c || 0)));
                scrub.setAttribute("aria-valuetext", fmtTime(c));
            }

            function refreshBuffered() {
                try {
                    if (el.buffered && el.buffered.length && el.duration > 0) {
                        var end = el.buffered.end(el.buffered.length - 1);
                        buffered.style.width = ((end / el.duration) * 100) + "%";
                    }
                } catch (e) { /* buffered can throw before metadata */ }
            }

            el.addEventListener("loadedmetadata", refreshProgress);
            el.addEventListener("timeupdate", refreshProgress);
            el.addEventListener("progress", refreshBuffered);
            el.addEventListener("durationchange", refreshProgress);

            // Once the video's real dimensions are known, size its box to match
            // so it is not letterboxed into the default 16:9. A portrait clip
            // gets a portrait box, capped by height so it does not run off the
            // screen; the box stays centred either way.
            if (isVideo) {
                el.addEventListener("loadedmetadata", function () {
                    var vw = el.videoWidth;
                    var vh = el.videoHeight;
                    if (!vw || !vh) { return; }
                    wrap.style.setProperty("--fm-ar", vw + " / " + vh);
                    wrap.style.setProperty("--fm-maxw", vh > vw ? (vw / vh * 80) + "vh" : "100%");
                });
            }

            function seekFromPointer(clientX) {
                var rect = scrub.getBoundingClientRect();
                if (rect.width <= 0 || !(el.duration > 0)) {
                    return;
                }
                var ratio = (clientX - rect.left) / rect.width;
                ratio = Math.min(1, Math.max(0, ratio));
                el.currentTime = ratio * el.duration;
                refreshProgress();
            }

            var dragging = false;
            scrub.addEventListener("pointerdown", function (ev) {
                dragging = true;
                try { scrub.setPointerCapture(ev.pointerId); } catch (e) {}
                seekFromPointer(ev.clientX);
            });
            scrub.addEventListener("pointermove", function (ev) {
                if (dragging) {
                    seekFromPointer(ev.clientX);
                }
            });
            scrub.addEventListener("pointerup", function () { dragging = false; });
            scrub.addEventListener("pointercancel", function () { dragging = false; });

            scrub.addEventListener("keydown", function (ev) {
                if (!(el.duration > 0)) {
                    return;
                }
                var step = ev.shiftKey ? 30 : 5;
                if (ev.key === "ArrowRight") {
                    el.currentTime = Math.min(el.duration, el.currentTime + step);
                    ev.preventDefault();
                } else if (ev.key === "ArrowLeft") {
                    el.currentTime = Math.max(0, el.currentTime - step);
                    ev.preventDefault();
                } else if (ev.key === "Home") {
                    el.currentTime = 0;
                    ev.preventDefault();
                } else if (ev.key === "End") {
                    el.currentTime = el.duration;
                    ev.preventDefault();
                }
            });

            function setVolIcon() {
                volBtn.innerHTML = "";
                var m = el.muted || el.volume === 0;
                volBtn.appendChild(icon(m ? ICONS.muted : ICONS.volume));
                volBtn.setAttribute("aria-label", m ? "Unmute" : "Mute");
                wrap.classList.toggle("fm-muted", m);
            }

            volBtn.addEventListener("click", function () {
                el.muted = !el.muted;
                if (!el.muted && el.volume === 0) {
                    el.volume = 1;
                    vol.value = "1";
                }
                setVolIcon();
            });
            vol.addEventListener("input", function () {
                el.volume = parseFloat(vol.value);
                el.muted = el.volume === 0;
                setVolIcon();
            });

            if (fullBtn) {
                fullBtn.addEventListener("click", function () {
                    var d = document;
                    if (d.fullscreenElement || d.webkitFullscreenElement) {
                        (d.exitFullscreen || d.webkitExitFullscreen).call(d);
                    } else {
                        var r = wrap.requestFullscreen || wrap.webkitRequestFullscreen
                            || el.webkitEnterFullscreen;
                        if (r) { r.call(wrap.requestFullscreen ? wrap : el); }
                    }
                });
                document.addEventListener("fullscreenchange", function () {
                    var on = document.fullscreenElement === wrap;
                    wrap.classList.toggle("fm-fs", on);
                    fullBtn.innerHTML = "";
                    fullBtn.appendChild(icon(on ? ICONS.exitfull : ICONS.full));
                });
            }

            /* Video: fade the bar out while playing and the pointer is idle. */
            if (isVideo) {
                var idle = null;
                function wake() {
                    wrap.classList.remove("fm-idle");
                    if (idle) { clearTimeout(idle); }
                    idle = setTimeout(function () {
                        if (!el.paused) {
                            wrap.classList.add("fm-idle");
                        }
                    }, 2500);
                }
                wrap.addEventListener("pointermove", wake);
                el.addEventListener("play", wake);
                el.addEventListener("pause", function () {
                    wrap.classList.remove("fm-idle");
                    if (idle) { clearTimeout(idle); }
                });
            }

            /* ---- playlist ----
             *
             * Present only when the container carries a data-playlist queue
             * (the document page builds it server-side; the listing preview
             * builds it from the folder's audio rows). The queue swaps the
             * element's src in place, so all the transport wiring above keeps
             * working unchanged.
             */
            var queue = null;
            try {
                var raw = wrap.getAttribute("data-playlist");
                if (raw) {
                    queue = JSON.parse(raw);
                }
            } catch (e) { queue = null; }

            if (queue && queue.length > 1) {
                var index = parseInt(wrap.getAttribute("data-playlist-index"), 10);
                if (!(index >= 0 && index < queue.length)) {
                    index = 0;
                }
                var repeat = false;
                var shuffle = false;

                var prevBtn = button("fm-prev", "Previous track",
                    ["M6 5v14", "M20 5v14l-11-7z"]);
                var nextBtn = button("fm-next", "Next track",
                    ["M18 5v14", "M4 5v14l11-7z"]);
                bar.insertBefore(prevBtn, playBtn);
                bar.insertBefore(nextBtn, playBtn.nextSibling);

                var repeatBtn = button("fm-repeat", "Repeat",
                    ["M17 2l4 4-4 4", "M3 11V9a4 4 0 0 1 4-4h14",
                     "M7 22l-4-4 4-4", "M21 13v2a4 4 0 0 1-4 4H3"]);
                var shuffleBtn = button("fm-shuffle", "Shuffle",
                    ["M16 3h5v5", "M4 20L21 3", "M21 16v5h-5", "M15 15l6 6", "M4 4l5 5"]);
                bar.appendChild(repeatBtn);
                bar.appendChild(shuffleBtn);

                function fmtDur(t) {
                    if (!isFinite(t) || t <= 0) { return ""; }
                    t = Math.floor(t);
                    var s = t % 60;
                    return Math.floor(t / 60) + ":" + (s < 10 ? "0" + s : s);
                }

                var list = document.createElement("ol");
                list.className = "fm-list";
                var durCells = [];
                queue.forEach(function (track, i) {
                    var li = document.createElement("li");
                    li.className = "fm-track";
                    li.setAttribute("role", "button");
                    li.setAttribute("tabindex", "0");

                    var mark = document.createElement("span");
                    mark.className = "fm-mark";
                    var num = document.createElement("span");
                    num.className = "fm-num";
                    num.textContent = i + 1;
                    mark.appendChild(num);

                    var title = document.createElement("span");
                    title.className = "fm-track-title";
                    title.textContent = track.title || ("Track " + (i + 1));

                    var dur = document.createElement("span");
                    dur.className = "fm-dur";
                    durCells.push(dur);

                    li.appendChild(mark);
                    li.appendChild(title);
                    li.appendChild(dur);
                    li.addEventListener("click", function () { load(i, true); });
                    li.addEventListener("keydown", function (ev) {
                        if (ev.key === "Enter" || ev.key === " ") {
                            ev.preventDefault();
                            load(i, true);
                        }
                    });
                    list.appendChild(li);
                });
                wrap.appendChild(list);

                /* Durations fill in on their own as each track's metadata
                   loads. To keep them from competing with playback for
                   bandwidth and connections, they load one at a time, they do
                   not start until the current track can already play, and they
                   pause whenever the player is actively buffering (a "waiting"
                   event) and resume once it can play again. Nothing is stored;
                   these are metadata-only reads. */
                (function loadDurations() {
                    var next = 0;
                    var busy = false;      // a probe is in flight
                    var blocked = true;    // hold until the player is ready
                    var probe = null;

                    function pump() {
                        if (busy || blocked || next >= queue.length) {
                            return;
                        }
                        var i = next++;
                        if (durCells[i].textContent) { pump(); return; }
                        busy = true;
                        probe = document.createElement(el.tagName.toLowerCase() === "video" ? "video" : "audio");
                        probe.preload = "metadata";
                        probe.src = queue[i].url;
                        var done = function () {
                            durCells[i].textContent = fmtDur(probe.duration);
                            cleanup();
                        };
                        var fail = function () { cleanup(); };
                        function cleanup() {
                            if (!probe) { return; }
                            probe.removeAttribute("src");
                            probe.load();
                            probe = null;
                            busy = false;
                            pump();
                        }
                        probe.addEventListener("loadedmetadata", done);
                        probe.addEventListener("error", fail);
                    }

                    // Give way while the player is stalled for data; resume when
                    // it can play again.
                    el.addEventListener("waiting", function () { blocked = true; });
                    el.addEventListener("canplay", function () { blocked = false; pump(); });
                    el.addEventListener("playing", function () { blocked = false; pump(); });
                    el.addEventListener("pause", function () { blocked = false; pump(); });
                    // If the first track never gets played, still fill durations
                    // shortly after load.
                    setTimeout(function () { blocked = false; pump(); }, 2000);
                })();

                function eqNode() {
                    var eq = document.createElement("span");
                    eq.className = "fm-eq";
                    eq.setAttribute("aria-hidden", "true");
                    eq.appendChild(document.createElement("span"));
                    eq.appendChild(document.createElement("span"));
                    eq.appendChild(document.createElement("span"));
                    return eq;
                }

                function highlight() {
                    var items = list.querySelectorAll(".fm-track");
                    for (var i = 0; i < items.length; i++) {
                        var isCur = i === index;
                        items[i].classList.toggle("fm-current", isCur);
                        var mark = items[i].querySelector(".fm-mark");
                        mark.innerHTML = "";
                        if (isCur) {
                            mark.appendChild(eqNode());
                            items[i].setAttribute("aria-current", "true");
                        } else {
                            var n = document.createElement("span");
                            n.className = "fm-num";
                            n.textContent = i + 1;
                            mark.appendChild(n);
                            items[i].removeAttribute("aria-current");
                        }
                    }
                }

                function load(i, play) {
                    if (i < 0 || i >= queue.length) {
                        return;
                    }
                    index = i;
                    el.src = queue[i].url;
                    el.load();
                    highlight();
                    if (play) {
                        var p = el.play();
                        if (p && p.catch) { p.catch(function () {}); }
                    }
                }

                function step(dir) {
                    if (shuffle && queue.length > 2) {
                        var next;
                        do {
                            next = Math.floor(Math.random() * queue.length);
                        } while (next === index);
                        load(next, true);
                        return;
                    }
                    var i = index + dir;
                    if (i >= queue.length) {
                        i = repeat ? 0 : -1;
                    } else if (i < 0) {
                        i = repeat ? queue.length - 1 : 0;
                    }
                    if (i >= 0) {
                        load(i, true);
                    }
                }

                prevBtn.addEventListener("click", function () {
                    if (el.currentTime > 3) { el.currentTime = 0; } else { step(-1); }
                });
                nextBtn.addEventListener("click", function () { step(1); });
                el.addEventListener("ended", function () { step(1); });

                repeatBtn.addEventListener("click", function () {
                    repeat = !repeat;
                    repeatBtn.classList.toggle("fm-on", repeat);
                    repeatBtn.setAttribute("aria-pressed", repeat ? "true" : "false");
                });
                shuffleBtn.addEventListener("click", function () {
                    shuffle = !shuffle;
                    shuffleBtn.classList.toggle("fm-on", shuffle);
                    shuffleBtn.setAttribute("aria-pressed", shuffle ? "true" : "false");
                });

                wrap.classList.add("fm-has-list");
                highlight();
            }

            setPlayIcon(!el.paused);
            setVolIcon();
            refreshProgress();
            refreshBuffered();
        } catch (e) {
            /* Enhancement failed: restore a usable player. */
            try {
                el.setAttribute("controls", "");
                wrap.removeAttribute("data-fm-ready");
                var stray = wrap.querySelector(".fm-bar");
                if (stray) { stray.parentNode.removeChild(stray); }
            } catch (e2) { /* nothing more to do */ }
        }
    }

    function mountAll(root) {
        var list = (root || document).querySelectorAll(".folio-media");
        for (var i = 0; i < list.length; i++) {
            mount(list[i]);
        }
    }

    window.FolioMedia = { mount: mount, mountAll: mountAll };

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", function () { mountAll(); });
    } else {
        mountAll();
    }
})();

/* Colour-scheme switching. Mirrors the handler in app.js so pages that load
   only media.js — the standalone audio and video playlist pages — honour the
   reader's saved theme and carry a working picker. Idempotent: safe even if
   app.js has already wired the same controls on a page that loads both. */
(function () {
    "use strict";
    var root = document.documentElement;
    var saved = null;
    try { saved = localStorage.getItem("folio-theme"); } catch (e) {}
    if (saved) {
        root.setAttribute("data-theme", saved);
    }
    function markActive() {
        var active = root.getAttribute("data-theme");
        document.querySelectorAll(".theme-picker button").forEach(function (b) {
            b.classList.toggle("active", b.getAttribute("data-set-theme") === active);
        });
    }
    function wire() {
        markActive();
        document.querySelectorAll(".theme-picker button").forEach(function (b) {
            if (b.dataset.themeWired) { return; }
            b.dataset.themeWired = "1";
            b.addEventListener("click", function () {
                var theme = b.getAttribute("data-set-theme");
                root.setAttribute("data-theme", theme);
                try { localStorage.setItem("folio-theme", theme); } catch (e) {}
                markActive();
            });
        });
    }
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", wire);
    } else {
        wire();
    }
})();
