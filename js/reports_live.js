/* Shared Reports page: live analytics, charts and downloads for Staff, District and System admins.
   DATA SOURCE: buildSnapshot() below generates demo data. To use real data, replace it with a fetch()
   to your PHP endpoint that returns the same shape (see the "snapshot" comment). Everything else
   (KPIs, charts, tables, downloads, live ticks) works from that one object. */
(function () {
    "use strict";
    const root = document.getElementById("rlRoot");
    if (!root || typeof Chart === "undefined") return;
    const ROLE = root.dataset.role;

    /* ---------- helpers ---------- */
    const $ = (id) => document.getElementById(id);
    const css = (n) => getComputedStyle(document.documentElement).getPropertyValue(n).trim();
    const fmt = (n) => Math.round(n).toLocaleString("en-IN");
    const sum = (a) => a.reduce((x, y) => x + y, 0);
    const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" }[c]));
    function rng(seed) { let a = 0; for (let i = 0; i < seed.length; i++) a = (a * 31 + seed.charCodeAt(i)) | 0;
        return () => { a |= 0; a = (a + 0x6D2B79F5) | 0; let t = Math.imul(a ^ (a >>> 15), 1 | a); t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t; return ((t ^ (t >>> 14)) >>> 0) / 4294967296; }; }

    /* ---------- demo data ("snapshot") ----------
       snapshot = { labels[], visits[], dispensed[], received[], newShare, units:[{name,visits,dispensed}], unitTitle,
                    meds:[{name,qty,min,issued,received,expDays}], docs:[{name,type,consults,mins}],
                    reqs:[{id,unit,item,qty,status,date}], prevVisits } */
    const MEDS = ["Paracetamol 500mg","Amoxicillin 250mg","ORS Sachet","Metformin 500mg","Amlodipine 5mg","Cetirizine 10mg","Iron-Folic Acid","Azithromycin 500mg","Pantoprazole 40mg","Salbutamol Inhaler"];
    const DOCS = [["Dr. Anjali Sharma","Permanent"],["Dr. R. Mehta","Permanent"],["Dr. S. Joshi","Visiting"],["Dr. K. Patel","Permanent"],["Dr. N. Desai","Visiting"]];
    const PHCS = ["PHC Veraval","PHC Sutrapada","PHC Kodinar","PHC Una"];
    const DISTRICTS = ["Gir Somnath","Junagadh","Amreli","Rajkot","Porbandar"];
    const AGES = ["0-5 yrs","6-17 yrs","18-40 yrs","41-60 yrs","60+ yrs"];
    const MON = ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];

    function timeAxis(period) {
        const now = new Date(), L = [];
        if (period === "today") { const end = Math.min(Math.max(now.getHours(), 11), 18); for (let h = 8; h <= end; h++) L.push(h <= 12 ? h + (h === 12 ? "pm" : "am") : (h - 12) + "pm"); }
        else if (period === "7d" || period === "30d") { const n = period === "7d" ? 7 : 30; for (let i = n - 1; i >= 0; i--) { const d = new Date(now - i * 864e5); L.push(n === 7 ? d.toLocaleDateString("en-GB", { weekday: "short" }) : d.getDate() + " " + MON[d.getMonth()]); } }
        else if (period === "90d") { for (let i = 1; i <= 13; i++) L.push("W" + i); }
        else { for (let i = 11; i >= 0; i--) L.push(MON[(now.getMonth() - i + 12) % 12]); }
        return L;
    }
    function scopeInfo(scope) {
        if (ROLE === "staff") return { k: 1, units: AGES, unitTitle: "age group", unitsAreAges: true, name: "your PHC" };
        if (ROLE === "district") return scope === "all" ? { k: 4.2, units: PHCS, unitTitle: "PHC", name: "all PHCs" } : { k: 1, units: AGES, unitTitle: "age group", unitsAreAges: true, name: scope };
        return scope === "all" ? { k: 34, units: DISTRICTS, unitTitle: "district", name: "all districts" } : { k: 6.5, units: PHCS, unitTitle: "PHC", name: scope };
    }
    function buildSnapshot(scope, period) {
        const r = rng(ROLE + scope + period), si = scopeInfo(scope), labels = timeAxis(period);
        const per = { today: 6, "7d": 48, "30d": 48, "90d": 330, year: 1400 }[period] * si.k;
        const wave = (base, j) => base * (0.72 + r() * 0.56) * (period === "7d" || period === "30d" ? (j % 7 === 6 ? 0.55 : 1) : 1);
        const visits = labels.map((_, j) => Math.round(wave(per, j)));
        const dispensed = visits.map((v) => Math.round(v * (2.2 + r() * 1.2)));
        const received = labels.map(() => (r() < 0.45 ? 0 : Math.round(per * (1 + r() * 3))));
        const scale = si.k * (period === "today" ? 0.15 : period === "7d" ? 1 : period === "30d" ? 4 : period === "90d" ? 12 : 48);
        const meds = MEDS.map((name, i) => { const min = Math.round((120 + r() * 220) * si.k); const mode = r();
            const qty = Math.round(min * (mode < 0.18 ? 0.25 + r() * 0.4 : mode < 0.4 ? 0.9 + r() * 0.2 : 1.3 + r() * 2.2));
            return { name, min, qty, issued: Math.round((60 + r() * 260) * scale * (1 - i * 0.05)), received: Math.round((r() < 0.15 ? 0 : 80 + r() * 300) * scale), expDays: Math.round(-10 + r() * r() * 330) }; });
        const docs = DOCS.map(([name, type]) => ({ name, type, consults: Math.round((type === "Permanent" ? 0.28 : 0.12) * visits.reduce((a, b) => a + b, 0) * (0.7 + r() * 0.5) / (si.k > 5 ? 1 : 1)), mins: Math.round(7 + r() * 8) }));
        const total = sum(visits);
        const w = si.units.map(() => 0.6 + r() * 0.8), ws = sum(w);
        const units = si.units.map((name, i) => ({ name, visits: Math.round(total * w[i] / ws), dispensed: Math.round(sum(dispensed) * w[i] / ws) }));
        const st = ["Pending", "Approved", "Approved", "Rejected", "Pending", "Approved"], rn = Math.max(6, Math.round(8 * Math.sqrt(si.k)));
        const reqs = Array.from({ length: Math.min(rn, 24) }, (_, i) => ({ id: "REQ-" + (1040 + i), unit: si.units[i % si.units.length], item: MEDS[Math.floor(r() * MEDS.length)], qty: Math.round((50 + r() * 450) / 10) * 10, status: st[Math.floor(r() * st.length)], date: new Date(Date.now() - Math.floor(r() * 30) * 864e5).toISOString().slice(0, 10) }));
        return { labels, visits, dispensed, received, newShare: 0.55 + r() * 0.15, units, unitTitle: si.unitTitle, meds, docs, reqs, prevVisits: total * (0.86 + r() * 0.26), scopeName: si.name, k: si.k };
    }
    const status = (m) => (m.qty <= m.min * 0.5 ? "Critical" : m.qty < m.min ? "Low" : "Good");
    const stCls = { Good: "badge-normal", Low: "badge-low", Critical: "badge-critical", Pending: "badge-low", Approved: "badge-normal", Rejected: "badge-critical", Expired: "badge-critical", "Soon": "badge-low", "OK": "badge-normal" };
    const badge = (t) => `<span class="badge-status ${stCls[t] || ""}">${t}</span>`;
    const delta = (a, b) => { const p = (a - b) / b * 100; return (p >= 0 ? "▲ " : "▼ ") + Math.abs(p).toFixed(0) + "% vs previous"; };
    const expLabel = (d) => (d < 0 ? "Expired" : d <= 60 ? "Soon" : "OK");

    /* ---------- report definitions ---------- */
    const REPORTS = [
        { id: "visits", icon: "fa-user-injured", name: "Patient Visits", sub: "Footfall & trends",
          kpis: (d) => { const t = sum(d.visits), pk = d.visits.indexOf(Math.max(...d.visits)); return [
              { l: "Total visits", v: fmt(t), e: delta(t, d.prevVisits) }, { l: "Average per point", v: fmt(t / d.visits.length) },
              { l: "Peak", v: fmt(Math.max(...d.visits)), e: d.labels[pk] }, { l: "New patients", v: Math.round(d.newShare * 100) + "%", e: "rest are follow-ups" }]; },
          charts: (d) => [
              { t: "Visits over time", type: "line", labels: d.labels, ds: [{ n: "Visits", data: d.visits, c: "s1" }] },
              { t: "Visits by " + d.unitTitle, type: "bar", labels: d.units.map((u) => u.name), ds: [{ n: "Visits", data: d.units.map((u) => u.visits), c: "s1" }] }],
          table: (d) => ({ cols: ["Time", "Visits", "New", "Follow-up"], rows: d.labels.map((l, i) => [l, d.visits[i], Math.round(d.visits[i] * d.newShare), d.visits[i] - Math.round(d.visits[i] * d.newShare)]) }) },
        { id: "consumption", icon: "fa-prescription-bottle-alt", name: "Medicine Use", sub: "Dispensed units",
          kpis: (d) => { const t = sum(d.dispensed), top = [...d.meds].sort((a, b) => b.issued - a.issued)[0]; return [
              { l: "Units dispensed", v: fmt(t) }, { l: "Top medicine", v: top.name.split(" ")[0], e: fmt(top.issued) + " units" },
              { l: "Medicines in use", v: d.meds.length }, { l: "Units per visit", v: (t / sum(d.visits)).toFixed(1) }]; },
          charts: (d) => { const m = [...d.meds].sort((a, b) => b.issued - a.issued); return [
              { t: "Units dispensed over time", type: "line", labels: d.labels, ds: [{ n: "Units", data: d.dispensed, c: "s2" }] },
              { t: "Top medicines issued", type: "bar", h: true, labels: m.slice(0, 6).map((x) => x.name), ds: [{ n: "Units", data: m.slice(0, 6).map((x) => x.issued), c: "s2" }] }]; },
          table: (d) => { const t = sum(d.meds.map((m) => m.issued)); return { cols: ["Medicine", "Units issued", "Share"], rows: [...d.meds].sort((a, b) => b.issued - a.issued).map((m) => [m.name, m.issued, (m.issued / t * 100).toFixed(1) + "%"]) }; } },
        { id: "inventory", icon: "fa-warehouse", name: "Inventory Status", sub: "Good / Low / Critical",
          kpis: (d) => { const c = { Good: 0, Low: 0, Critical: 0 }; d.meds.forEach((m) => c[status(m)]++); return [
              { l: "Items tracked", v: d.meds.length }, { l: "Good", v: c.Good, cls: "good" }, { l: "Low", v: c.Low, cls: "low" }, { l: "Critical", v: c.Critical, cls: "crit" }]; },
          charts: (d) => { const c = { Good: 0, Low: 0, Critical: 0 }; d.meds.forEach((m) => c[status(m)]++); return [
              { t: "Stock status", type: "doughnut", labels: ["Good", "Low", "Critical"], ds: [{ n: "Items", data: [c.Good, c.Low, c.Critical], cs: ["c-good", "c-low", "c-crit"] }] },
              { t: "In stock vs minimum level", type: "bar", labels: d.meds.map((m) => m.name.split(" ")[0]), ds: [{ n: "In stock", data: d.meds.map((m) => m.qty), c: "s1" }, { n: "Minimum", data: d.meds.map((m) => m.min), c: "s4" }] }]; },
          table: (d) => ({ cols: ["Medicine", "In stock", "Minimum", "Status"], rows: [...d.meds].sort((a, b) => a.qty / a.min - b.qty / b.min).map((m) => [m.name, m.qty, m.min, { html: badge(status(m)), text: status(m) }]) }) },
        { id: "stockin", icon: "fa-arrow-down", name: "Stock Received", sub: "Incoming batches",
          kpis: (d) => { const b = d.received.filter((x) => x > 0), t = sum(d.received); return [
              { l: "Batches received", v: b.length }, { l: "Units received", v: fmt(t) }, { l: "Average batch", v: b.length ? fmt(t / b.length) : "0" }, { l: "Medicines restocked", v: d.meds.filter((m) => m.received > 0).length }]; },
          charts: (d) => { const m = [...d.meds].sort((a, b) => b.received - a.received); return [
              { t: "Units received over time", type: "bar", labels: d.labels, ds: [{ n: "Units", data: d.received, c: "s3" }] },
              { t: "Received by medicine", type: "bar", h: true, labels: m.slice(0, 6).map((x) => x.name), ds: [{ n: "Units", data: m.slice(0, 6).map((x) => x.received), c: "s3" }] }]; },
          table: (d) => ({ cols: ["Medicine", "Units received", "Batches"], rows: [...d.meds].sort((a, b) => b.received - a.received).map((m) => [m.name, m.received, m.received ? Math.max(1, Math.round(m.received / (150 * Math.max(1, d.k / 4)))) : 0]) }) },
        { id: "doctors", icon: "fa-user-md", name: "Doctor Activity", sub: "Consultations",
          kpis: (d) => { const t = sum(d.docs.map((x) => x.consults)), p = sum(d.docs.filter((x) => x.type === "Permanent").map((x) => x.consults)); return [
              { l: "Doctors active", v: d.docs.length }, { l: "Consultations", v: fmt(t) }, { l: "By permanent staff", v: Math.round(p / t * 100) + "%" }, { l: "Avg consult", v: Math.round(sum(d.docs.map((x) => x.mins)) / d.docs.length) + " min" }]; },
          charts: (d) => [
              { t: "Consultations per doctor", type: "bar", labels: d.docs.map((x) => x.name), ds: [{ n: "Consultations", data: d.docs.map((x) => x.consults), c: "s5" }] },
              { t: "Permanent vs visiting", type: "doughnut", labels: ["Permanent", "Visiting"], ds: [{ n: "Consultations", data: ["Permanent", "Visiting"].map((t) => sum(d.docs.filter((x) => x.type === t).map((x) => x.consults))), cs: ["s1", "s4"] }] }],
          table: (d) => ({ cols: ["Doctor", "Type", "Consultations", "Avg minutes"], rows: [...d.docs].sort((a, b) => b.consults - a.consults).map((x) => [x.name, x.type, x.consults, x.mins]) }) },
        { id: "requests", icon: "fa-file-invoice", name: "Medicine Requests", sub: "Pending & approved",
          kpis: (d) => { const c = (s) => d.reqs.filter((r) => r.status === s).length; return [
              { l: "Total requests", v: d.reqs.length }, { l: "Pending", v: c("Pending"), cls: "low" }, { l: "Approved", v: c("Approved"), cls: "good" }, { l: "Rejected", v: c("Rejected"), cls: "crit" }]; },
          charts: (d) => { const c = (s) => d.reqs.filter((r) => r.status === s).length, by = {}; d.reqs.forEach((r) => (by[r.item] = (by[r.item] || 0) + r.qty)); const k = Object.keys(by).sort((a, b) => by[b] - by[a]).slice(0, 6); return [
              { t: "Request status", type: "doughnut", labels: ["Approved", "Pending", "Rejected"], ds: [{ n: "Requests", data: [c("Approved"), c("Pending"), c("Rejected")], cs: ["c-good", "c-low", "c-crit"] }] },
              { t: "Units requested by medicine", type: "bar", h: true, labels: k, ds: [{ n: "Units", data: k.map((x) => by[x]), c: "s1" }] }]; },
          table: (d) => ({ cols: ["Request", "From", "Medicine", "Qty", "Date", "Status"], rows: d.reqs.map((r) => [r.id, r.unit, r.item, r.qty, r.date, { html: badge(r.status), text: r.status }]) }) },
        { id: "expiry", icon: "fa-hourglass-half", name: "Expiry Watch", sub: "Days to expiry",
          kpis: (d) => [{ l: "Expired", v: d.meds.filter((m) => m.expDays < 0).length, cls: "crit" }, { l: "Within 30 days", v: d.meds.filter((m) => m.expDays >= 0 && m.expDays <= 30).length, cls: "low" }, { l: "Within 90 days", v: d.meds.filter((m) => m.expDays > 30 && m.expDays <= 90).length }, { l: "Safe (> 90 days)", v: d.meds.filter((m) => m.expDays > 90).length, cls: "good" }],
          charts: (d) => { const b = [m => m.expDays < 0, m => m.expDays >= 0 && m.expDays <= 30, m => m.expDays > 30 && m.expDays <= 90, m => m.expDays > 90]; const s = [...d.meds].sort((a, b) => a.expDays - b.expDays).slice(0, 7); return [
              { t: "Medicines by expiry window", type: "bar", labels: ["Expired", "0-30 d", "31-90 d", "> 90 d"], ds: [{ n: "Medicines", data: b.map((f) => d.meds.filter(f).length), cs: ["c-crit", "c-low", "s1", "c-good"] }] },
              { t: "Nearest expiry (days)", type: "bar", h: true, labels: s.map((m) => m.name), ds: [{ n: "Days left", data: s.map((m) => m.expDays), cs: s.map((m) => (m.expDays < 0 ? "c-crit" : m.expDays <= 60 ? "c-low" : "c-good")) }] }]; },
          table: (d) => ({ cols: ["Medicine", "Days to expiry", "State"], rows: [...d.meds].sort((a, b) => a.expDays - b.expDays).map((m) => [m.name, m.expDays, { html: badge(expLabel(m.expDays)), text: expLabel(m.expDays) }]) }) },
    ];

    /* ---------- state ---------- */
    const state = { report: "visits", period: "7d", scope: "", live: true, snap: null };
    const scopeEl = $("rlScope");
    const getScope = () => (scopeEl.tagName === "SELECT" ? scopeEl.value : scopeEl.dataset.value);
    const scopeLabel = () => (scopeEl.tagName === "SELECT" ? scopeEl.options[scopeEl.selectedIndex].text : scopeEl.textContent.trim());
    const periodLabel = () => ({ today: "Today", "7d": "Last 7 days", "30d": "Last 30 days", "90d": "Last 3 months", year: "Last 12 months" }[state.period]);
    let charts = [];

    function load() { state.scope = getScope(); state.snap = buildSnapshot(state.scope, state.period); }

    /* ---------- render ---------- */
    function renderCatalog() {
        $("rlCatalog").innerHTML = REPORTS.map((r) => `<button type="button" role="tab" class="rl-opt" data-r="${r.id}" aria-selected="${r.id === state.report}"><i class="fas ${r.icon}"></i><span><b>${r.name}</b><small>${r.sub}</small></span></button>`).join("");
    }
    function renderKpis(rep) {
        $("rlKpis").innerHTML = rep.kpis(state.snap).map((k) => `<div class="rl-kpi ${k.cls || ""}"><span>${esc(k.l)}</span><b>${esc(k.v)}</b><em>${esc(k.e || "")}&nbsp;</em></div>`).join("");
    }
    function renderTable(rep) {
        const t = rep.table(state.snap);
        $("rlTable").innerHTML = "<thead><tr>" + t.cols.map((c) => `<th>${esc(c)}</th>`).join("") + "</tr></thead><tbody>" +
            t.rows.map((r) => "<tr>" + r.map((c) => `<td>${c && c.html ? c.html : esc(typeof c === "number" ? fmt(c) : c)}</td>`).join("") + "</tr>").join("") + "</tbody>";
    }
    function chartConfig(spec) {
        const text = css("--text-secondary"), grid = css("--border-color"), surf = css("--bg-card"), multi = spec.ds.length > 1 || spec.type === "doughnut";
        const col = (tok) => css("--" + tok);
        const datasets = spec.ds.map((s) => {
            if (spec.type === "doughnut") return { label: s.n, data: s.data, backgroundColor: s.cs.map(col), borderColor: surf, borderWidth: 2 };
            const bg = s.cs ? s.cs.map(col) : col(s.c);
            return spec.type === "line"
                ? { label: s.n, data: s.data, borderColor: col(s.c), backgroundColor: col(s.c) + "22", fill: true, tension: 0.3, borderWidth: 2, pointRadius: s.data.length > 14 ? 0 : 3, pointHoverRadius: 5, pointBackgroundColor: col(s.c), pointBorderColor: surf, pointBorderWidth: 2 }
                : { label: s.n, data: s.data, backgroundColor: bg, borderRadius: 4, borderSkipped: false, maxBarThickness: 28 };
        });
        const opts = { responsive: true, maintainAspectRatio: false, animation: { duration: 350 }, interaction: { mode: spec.type === "line" ? "index" : "nearest", intersect: false },
            plugins: { legend: { display: multi, position: "bottom", labels: { color: text, boxWidth: 10, boxHeight: 10, font: { size: 11 } } }, tooltip: { padding: 8, bodyFont: { size: 12 }, titleFont: { size: 12 } } } };
        if (spec.type !== "doughnut") {
            const num = { ticks: { color: text, font: { size: 11 }, maxTicksLimit: 6 }, grid: { color: grid }, border: { display: false }, beginAtZero: true };
            const cat = { ticks: { color: text, font: { size: 11 }, maxRotation: 0, autoSkip: true }, grid: { display: false }, border: { color: grid } };
            opts.indexAxis = spec.h ? "y" : "x"; opts.scales = spec.h ? { x: num, y: cat } : { x: cat, y: num };
        } else opts.cutout = "62%";
        return { type: spec.type, data: { labels: spec.labels, datasets }, options: opts };
    }
    function renderCharts(rep, rebuild) {
        const specs = rep.charts(state.snap);
        if (rebuild || charts.length !== specs.length) {
            charts.forEach((c) => c.destroy());
            $("rlCharts").innerHTML = specs.map((s, i) => `<div class="rl-chart"><h4>${esc(s.t)}</h4><div class="rl-canvas"><canvas id="rlc${i}" role="img" aria-label="${esc(s.t)}"></canvas></div></div>`).join("");
            charts = specs.map((s, i) => new Chart($("rlc" + i), chartConfig(s)));
        } else {
            charts.forEach((c, i) => { c.data.labels = specs[i].labels; specs[i].ds.forEach((s, j) => (c.data.datasets[j].data = s.data)); c.update("none"); });
        }
    }
    function stamp() { $("rlStamp").textContent = "Updated " + new Date().toLocaleTimeString("en-GB"); }
    function render(rebuild) {
        const rep = REPORTS.find((r) => r.id === state.report);
        $("rlIcon").innerHTML = `<i class="fas ${rep.icon}"></i>`;
        $("rlTitle").textContent = rep.name + " Report";
        $("rlMeta").textContent = scopeLabel() + " · " + periodLabel() + (state.live ? " · live" : " · paused");
        renderKpis(rep); renderCharts(rep, rebuild); renderTable(rep); stamp();
    }

    /* ---------- live feed (demo ticks) ---------- */
    function tick() {
        if (!state.live || document.hidden) return;
        const d = state.snap, r = Math.random, last = d.visits.length - 1;
        const v = Math.floor(r() * 3); d.visits[last] += v; d.dispensed[last] += v ? Math.round(v * (2 + r() * 2)) : Math.floor(r() * 3);
        d.units[Math.floor(r() * d.units.length)].visits += v;
        const m = d.meds[Math.floor(r() * d.meds.length)]; const n = Math.floor(r() * 5); m.qty = Math.max(0, m.qty - n); m.issued += n;
        if (r() < 0.12) { d.received[last] += Math.round(40 + r() * 120); const x = d.meds[Math.floor(r() * d.meds.length)]; x.qty += 60; x.received += 60; }
        render(false);
    }
    setInterval(tick, 4000);

    /* ---------- downloads ---------- */
    function save(name, blob) { const a = document.createElement("a"); a.href = URL.createObjectURL(blob); a.download = name; document.body.appendChild(a); a.click(); a.remove(); setTimeout(() => URL.revokeObjectURL(a.href), 2000); }
    const fileBase = () => { const rep = REPORTS.find((r) => r.id === state.report); return (rep.name + "_" + scopeLabel() + "_" + state.period + "_" + new Date().toISOString().slice(0, 10)).replace(/[^\w-]+/g, "_"); };
    $("dlCsv").addEventListener("click", () => {
        const rep = REPORTS.find((r) => r.id === state.report), t = rep.table(state.snap), q = (c) => '"' + String(c && c.text !== undefined ? c.text : c).replace(/"/g, '""') + '"';
        const lines = [["Report", rep.name], ["Scope", scopeLabel()], ["Period", periodLabel()], ["Generated", new Date().toLocaleString("en-GB")], [], t.cols, ...t.rows].map((r) => r.map(q).join(","));
        save(fileBase() + ".csv", new Blob(["﻿" + lines.join("\r\n")], { type: "text/csv;charset=utf-8" }));
    });
    $("dlPng").addEventListener("click", () => {
        charts.forEach((c, i) => { const w = c.canvas.width, h = c.canvas.height, cv = document.createElement("canvas"); cv.width = w; cv.height = h + 44 * (w / c.canvas.clientWidth);
            const x = cv.getContext("2d"), s = w / c.canvas.clientWidth; x.fillStyle = css("--bg-card"); x.fillRect(0, 0, cv.width, cv.height);
            x.fillStyle = css("--text-primary"); x.font = "600 " + 14 * s + "px sans-serif"; x.fillText(c.canvas.closest(".rl-chart").querySelector("h4").textContent + " - " + scopeLabel() + ", " + periodLabel(), 10 * s, 24 * s);
            x.drawImage(c.canvas, 0, 40 * s); cv.toBlob((b) => setTimeout(() => save(fileBase() + "_chart" + (i + 1) + ".png", b), i * 250)); });
    });
    $("dlPdf").addEventListener("click", () => { const t = document.title; document.title = fileBase(); charts.forEach((c) => c.resize()); setTimeout(() => { window.print(); document.title = t; }, 250); });

    /* ---------- events ---------- */
    $("rlCatalog").addEventListener("click", (e) => { const b = e.target.closest(".rl-opt"); if (!b) return; state.report = b.dataset.r; renderCatalog(); render(true); $("rlDetail").scrollIntoView({ behavior: "smooth", block: "nearest" }); });
    $("rlPeriod").addEventListener("click", (e) => { const b = e.target.closest("button"); if (!b) return; state.period = b.dataset.p; [...$("rlPeriod").children].forEach((x) => x.classList.toggle("on", x === b)); load(); render(true); });
    if (scopeEl.tagName === "SELECT") scopeEl.addEventListener("change", () => { load(); render(true); });
    $("rlLive").addEventListener("click", () => { state.live = !state.live; const b = $("rlLive"); b.classList.toggle("on", state.live); b.setAttribute("aria-pressed", state.live); $("rlLiveText").textContent = state.live ? "Live" : "Paused"; render(false); });
    new MutationObserver(() => render(true)).observe(document.documentElement, { attributes: true, attributeFilter: ["data-theme"] });

    load(); renderCatalog(); render(true);
})();
