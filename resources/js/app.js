import Alpine from 'alpinejs';
import L from 'leaflet';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';

delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({ iconUrl: markerIcon, iconRetinaUrl: markerIcon2x, shadowUrl: markerShadow });

/* Scroll-reveal: elements with data-reveal fade/slide in once when they enter the viewport. */
document.documentElement.classList.add('js');
window.__revealReady = true;
const revealObserver = 'IntersectionObserver' in window
    ? new IntersectionObserver((entries) => {
        entries.forEach((e) => {
            if (e.isIntersecting) {
                e.target.classList.add('is-visible');
                revealObserver.unobserve(e.target);
            }
        });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' })
    : null;
const initReveal = () => document.querySelectorAll('[data-reveal]').forEach((el) => (revealObserver ? revealObserver.observe(el) : el.classList.add('is-visible')));
document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', initReveal) : initReveal();

/* Count up to a real number from the database when it scrolls into view. */
Alpine.data('countUp', (target) => ({
    shown: 0,
    init() {
        const run = () => {
            const start = performance.now();
            const tick = (t) => {
                const p = Math.min(1, (t - start) / 1200);
                this.shown = Math.round(target * (1 - Math.pow(1 - p, 3)));
                if (p < 1) requestAnimationFrame(tick);
            };
            requestAnimationFrame(tick);
        };
        if (!('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            this.shown = target;
            return;
        }
        const io = new IntersectionObserver(([e]) => { if (e.isIntersecting) { run(); io.disconnect(); } });
        io.observe(this.$el);
    },
}));

/* Hero demo: cycles through example journey stages (clearly labelled as an example). */
Alpine.data('journeyDemo', (stages) => ({
    stages,
    i: 0,
    init() {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        setInterval(() => { this.i = (this.i + 1) % this.stages.length; }, 2600);
    },
}));

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

async function api(url, options = {}) {
    const res = await fetch(url, {
        credentials: 'same-origin',
        ...options,
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest', ...(options.headers || {}) },
    });
    const body = await res.json().catch(() => ({}));
    if (!res.ok) {
        const msg = body.message || (body.errors && Object.values(body.errors)[0]?.[0]) || `Request failed (${res.status})`;
        throw Object.assign(new Error(msg), { status: res.status, body });
    }
    return body;
}

function baseMap(el, cfg) {
    const map = L.map(el, { scrollWheelZoom: false });
    L.tileLayer(cfg.tileUrl, { attribution: cfg.attribution, maxZoom: 19 }).addTo(map);
    return map;
}

/* Prevent double submission: disable submit buttons and show a spinner. */
document.addEventListener('submit', (e) => {
    const form = e.target;
    if (form.dataset.noLock !== undefined) return;
    if (form.dataset.submitting) {
        e.preventDefault();
        return;
    }
    form.dataset.submitting = '1';
    // Disabled buttons are excluded from form data, so carry the clicked button's value over.
    const submitter = e.submitter;
    if (submitter?.name) {
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = submitter.name;
        hidden.value = submitter.value;
        hidden.dataset.submitterCopy = '1';
        form.appendChild(hidden);
    }
    form.querySelectorAll('button[type="submit"]').forEach((b) => {
        b.disabled = true;
        b.setAttribute('aria-busy', 'true');
        b.insertAdjacentHTML('afterbegin', '<span class="spinner" aria-hidden="true"></span>');
    });
});
window.addEventListener('pageshow', () => {
    document.querySelectorAll('form[data-submitting]').forEach((f) => {
        delete f.dataset.submitting;
        f.querySelectorAll('[data-submitter-copy]').forEach((i) => i.remove());
        f.querySelectorAll('button[type="submit"]').forEach((b) => {
            b.disabled = false;
            b.removeAttribute('aria-busy');
            b.querySelector('.spinner')?.remove();
        });
    });
});

/* Branch / pickup-point map (list view is always rendered alongside for accessibility). */
Alpine.data('branchMap', (branches, cfg) => ({
    init() {
        const pts = branches.filter((b) => b.lat && b.lng);
        if (!pts.length) return;
        const map = baseMap(this.$refs.map, cfg);
        const bounds = [];
        pts.forEach((b) => {
            const m = L.marker([b.lat, b.lng], { title: b.name, alt: b.name }).addTo(map);
            const el = document.createElement('div');
            el.innerHTML = `<strong></strong><br><span></span><br><a target="_blank" rel="noopener">Directions</a>`;
            el.querySelector('strong').textContent = b.name;
            el.querySelector('span').textContent = b.address;
            el.querySelector('a').href = b.directions;
            m.bindPopup(el);
            bounds.push([b.lat, b.lng]);
        });
        map.fitBounds(bounds, { padding: [30, 30], maxZoom: 13 });
    },
}));

/* Operations map of rider last-known locations. Labels stale data honestly. */
Alpine.data('riderMap', (endpoint, cfg) => {
    let map = null;
    let layer = null;
    let fitted = false;
    return {
    riders: [],
    error: null,
    updatedAt: null,
    init() {
        map = baseMap(this.$refs.map, cfg);
        map.setView(cfg.center || [6.5244, 3.3792], 11);
        layer = L.layerGroup().addTo(map);
        this.refresh();
        setInterval(() => this.refresh(), 30000);
    },
    async refresh() {
        try {
            const data = await api(endpoint);
            this.riders = data.riders;
            this.updatedAt = new Date().toLocaleTimeString();
            this.error = null;
            layer.clearLayers();
            const b = [];
            data.riders.filter((r) => r.lat !== null).forEach((r) => {
                const color = r.freshness === 'live' ? '#059669' : '#94a3b8';
                const m = L.circleMarker([r.lat, r.lng], { radius: 9, color, fillColor: color, fillOpacity: 0.8 }).addTo(layer);
                if (r.accuracy_m) L.circle([r.lat, r.lng], { radius: r.accuracy_m, color, weight: 1, fillOpacity: 0.05 }).addTo(layer);
                m.bindTooltip(`${r.name} — ${r.freshness === 'live' ? 'Live' : 'Last seen'} ${r.last_seen_human}`);
                b.push([r.lat, r.lng]);
            });
            if (b.length && !fitted) {
                map.fitBounds(b, { padding: [40, 40], maxZoom: 14 });
                fitted = true;
            }
        } catch (e) {
            this.error = e.message;
        }
    },
    };
});

/* Customer-facing approximate location (coarsened server-side). */
Alpine.data('approxMap', (point, cfg) => ({
    init() {
        if (!point) return;
        const map = baseMap(this.$refs.map, cfg);
        map.setView([point.lat, point.lng], 13);
        L.circle([point.lat, point.lng], { radius: 1200, color: '#1447e6', fillOpacity: 0.15 }).addTo(map);
    },
}));

/*
 * Rider location sharing. Uses the browser Geolocation API only after explicit
 * consent; sends updates while this page is open and the rider is on duty.
 * Browsers may pause location when the screen locks or the tab is backgrounded.
 */
Alpine.data('locationSharing', (cfg) => {
    let watchId = null;
    let latest = null;
    let timer = null;
    return {
    sharing: cfg.sharing,
    state: cfg.sharing ? 'starting' : 'off', // off | starting | active | denied | unavailable | error
    message: '',
    lastSentAt: null,
    lastAccuracy: null,
    init() {
        if (!('geolocation' in navigator)) {
            this.state = 'unavailable';
            this.message = 'This browser does not support location sharing.';
            return;
        }
        if (this.sharing) this.start();
        setInterval(() => {
            if (this.state === 'active' && this.lastSentAt && Date.now() - this.lastSentAt > cfg.staleMs) {
                this.message = 'No location update recently — your position may be out of date.';
            }
        }, 10000);
    },
    async enable() {
        try {
            await api(cfg.toggleUrl, { method: 'POST', body: JSON.stringify({ sharing: true, consent: true }) });
            this.sharing = true;
            this.start();
        } catch (e) {
            this.state = 'error';
            this.message = e.message;
        }
    },
    async disable() {
        this.stop();
        this.sharing = false;
        this.state = 'off';
        this.message = '';
        await api(cfg.toggleUrl, { method: 'POST', body: JSON.stringify({ sharing: false }) }).catch(() => {});
    },
    start() {
        this.state = 'starting';
        watchId = navigator.geolocation.watchPosition(
            (pos) => {
                latest = pos;
                if (this.state !== 'active') {
                    this.state = 'active';
                    this.send();
                }
            },
            (err) => {
                this.state = err.code === err.PERMISSION_DENIED ? 'denied' : 'error';
                this.message = err.code === err.PERMISSION_DENIED
                    ? 'Location permission was denied. Enable it in your browser settings to share location.'
                    : 'Your location could not be determined right now.';
            },
            { enableHighAccuracy: true, maximumAge: 15000, timeout: 30000 },
        );
        timer = setInterval(() => this.send(), cfg.intervalMs);
    },
    stop() {
        if (watchId !== null) navigator.geolocation.clearWatch(watchId);
        clearInterval(timer);
        watchId = null;
    },
    async send() {
        if (!latest || !this.sharing) return;
        const c = latest.coords;
        try {
            await api(cfg.postUrl, {
                method: 'POST',
                body: JSON.stringify({ lat: c.latitude, lng: c.longitude, accuracy: c.accuracy, recorded_at: new Date(latest.timestamp).toISOString() }),
            });
            this.lastSentAt = Date.now();
            this.lastAccuracy = Math.round(c.accuracy);
            this.message = '';
        } catch (e) {
            this.message = e.message;
            if (e.status === 422 && /on duty/i.test(e.message)) {
                this.stop();
                this.state = 'off';
                this.sharing = false;
            }
        }
    },
    sinceText() {
        if (!this.lastSentAt) return 'not yet sent';
        const s = Math.round((Date.now() - this.lastSentAt) / 1000);
        return s < 60 ? `${s}s ago` : `${Math.round(s / 60)} min ago`;
    },
    };
});

/* Signature capture on a canvas (mouse, touch, pen). */
Alpine.data('signaturePad', () => {
    // Native canvas objects must not live on Alpine's reactive proxy ("Illegal invocation").
    let ctx = null;
    let drawing = false;
    return {
        empty: true,
        init() {
            const c = this.$refs.canvas;
            const input = this.$refs.input;
            const ratio = window.devicePixelRatio || 1;
            const rect = c.getBoundingClientRect();
            c.width = rect.width * ratio;
            c.height = rect.height * ratio;
            ctx = c.getContext('2d');
            ctx.scale(ratio, ratio);
            ctx.lineWidth = 2.2;
            ctx.lineCap = 'round';
            ctx.strokeStyle = '#0f172a';
            const pos = (e) => {
                const r = c.getBoundingClientRect();
                return [e.clientX - r.left, e.clientY - r.top];
            };
            c.addEventListener('pointerdown', (e) => {
                drawing = true;
                c.setPointerCapture?.(e.pointerId);
                ctx.beginPath();
                ctx.moveTo(...pos(e));
            });
            c.addEventListener('pointermove', (e) => {
                if (!drawing) return;
                ctx.lineTo(...pos(e));
                ctx.stroke();
                this.empty = false;
            });
            const end = () => {
                if (!drawing) return;
                drawing = false;
                input.value = this.empty ? '' : c.toDataURL('image/png');
            };
            c.addEventListener('pointerup', end);
            c.addEventListener('pointercancel', end);
        },
        clear() {
            const c = this.$refs.canvas;
            ctx.clearRect(0, 0, c.width, c.height);
            this.empty = true;
            this.$refs.input.value = '';
        },
    };
});

/* Repeating parcel rows in the booking form. */
Alpine.data('parcels', (initial) => ({
    rows: initial.length ? initial : [{ weight_kg: '', length_cm: '', width_cm: '', height_cm: '' }],
    add() {
        if (this.rows.length < 20) this.rows.push({ weight_kg: '', length_cm: '', width_cm: '', height_cm: '' });
    },
    remove(i) {
        if (this.rows.length > 1) this.rows.splice(i, 1);
    },
}));

/* Fill booking address fields from a saved address. */
Alpine.data('addressPicker', (addresses, prefix) => ({
    pick(id) {
        const a = addresses.find((x) => String(x.id) === String(id));
        if (!a) return;
        const set = (name, v) => {
            const el = document.querySelector(`[name="${name}"]`);
            if (el) el.value = v ?? '';
        };
        const who = prefix === 'pickup' ? 'sender' : 'recipient';
        set(`${who}_name`, a.contact_name);
        set(`${who}_phone`, a.phone);
        set(`${who}_email`, a.email);
        set(`${prefix}_address`, [a.line1, a.line2].filter(Boolean).join(', '));
        set(`${prefix}_city`, a.city);
        set(`${prefix}_state`, a.state);
        set(prefix === 'pickup' ? 'origin_zone_id' : 'destination_zone_id', a.service_zone_id);
    },
}));


/* ---------------------------------------------------------------
 * Extra motion. Everything below checks prefers-reduced-motion.
 * ------------------------------------------------------------- */
const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/* Branded progress bar while the next page loads. */
(() => {
    const bar = document.getElementById('nav-progress');
    if (!bar) return;
    const start = () => { bar.classList.remove('running'); void bar.offsetWidth; bar.classList.add('running'); };
    document.addEventListener('click', (e) => {
        const a = e.target.closest('a[href]');
        if (!a || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        if (a.target && a.target !== '_self') return;
        if (a.hasAttribute('download') || a.origin !== location.origin) return;
        if (a.pathname === location.pathname && a.hash) return;
        if (/\.(csv|pdf|png|jpe?g|webp)$/i.test(a.pathname) || a.pathname.includes('/export') || a.pathname.startsWith('/files/')) return;
        start();
    });
    document.addEventListener('submit', (e) => { if (!e.defaultPrevented && !e.target.dataset.noLock) start(); });
    window.addEventListener('pageshow', () => bar.classList.remove('running'));
})();

/* Rotating headline words. */
Alpine.data('rotatingWords', (words) => ({
    words,
    i: 0,
    init() {
        if (reduceMotion) return;
        setInterval(() => { this.i = (this.i + 1) % this.words.length; }, 2600);
    },
}));

/* Subtle mouse parallax for layered hero elements (desktop pointers only). */
Alpine.data('parallax', () => ({
    init() {
        if (reduceMotion || !window.matchMedia('(pointer: fine)').matches) return;
        const layers = [...this.$el.querySelectorAll('[data-depth]')];
        let raf = null;
        this.$el.addEventListener('mousemove', (e) => {
            const r = this.$el.getBoundingClientRect();
            const x = (e.clientX - r.left) / r.width - 0.5;
            const y = (e.clientY - r.top) / r.height - 0.5;
            cancelAnimationFrame(raf);
            raf = requestAnimationFrame(() => layers.forEach((l) => {
                const d = parseFloat(l.dataset.depth);
                l.style.translate = `${-x * d}px ${-y * d}px`;
            }));
        });
        this.$el.addEventListener('mouseleave', () => layers.forEach((l) => { l.style.translate = ''; }));
    },
}));

/* Lightweight brand-coloured confetti burst (no library). */
window.confettiBurst = (originEl) => {
    if (reduceMotion) return;
    const canvas = document.createElement('canvas');
    canvas.setAttribute('aria-hidden', 'true');
    Object.assign(canvas.style, { position: 'fixed', inset: '0', width: '100%', height: '100%', pointerEvents: 'none', zIndex: 80 });
    document.body.appendChild(canvas);
    const dpr = window.devicePixelRatio || 1;
    canvas.width = innerWidth * dpr;
    canvas.height = innerHeight * dpr;
    const ctx = canvas.getContext('2d');
    ctx.scale(dpr, dpr);
    const r = originEl?.getBoundingClientRect();
    const ox = r ? r.left + r.width / 2 : innerWidth / 2;
    const oy = r ? r.top + r.height / 2 : innerHeight / 3;
    const colors = ['#1447e6', '#5b81f4', '#f5b41e', '#fbcb5a', '#10b981', '#ffffff'];
    const parts = Array.from({ length: 140 }, () => ({
        x: ox, y: oy, vx: (Math.random() - 0.5) * 14, vy: Math.random() * -13 - 3,
        s: Math.random() * 7 + 4, r: Math.random() * Math.PI, vr: (Math.random() - 0.5) * 0.3, c: colors[(Math.random() * colors.length) | 0],
    }));
    const t0 = performance.now();
    const frame = (t) => {
        ctx.clearRect(0, 0, innerWidth, innerHeight);
        const alive = t - t0 < 2600;
        parts.forEach((p) => {
            p.vy += 0.35; p.vx *= 0.99; p.x += p.vx; p.y += p.vy; p.r += p.vr;
            ctx.save(); ctx.translate(p.x, p.y); ctx.rotate(p.r);
            ctx.globalAlpha = Math.max(0, 1 - (t - t0) / 2600);
            ctx.fillStyle = p.c; ctx.fillRect(-p.s / 2, -p.s / 4, p.s, p.s / 2);
            ctx.restore();
        });
        alive ? requestAnimationFrame(frame) : canvas.remove();
    };
    requestAnimationFrame(frame);
};

/* Back-to-top button visibility. */
Alpine.data('backToTop', () => ({
    show: false,
    init() { window.addEventListener('scroll', () => { this.show = window.scrollY > 900; }, { passive: true }); },
    go() { window.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' }); },
}));

window.Alpine = Alpine;
Alpine.start();
