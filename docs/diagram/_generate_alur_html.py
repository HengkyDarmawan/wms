# -*- coding: utf-8 -*-
"""
Halaman baca alur proses (BPMN 1-10) sebagai diagram alir sederhana.

Sumber data: FLOWS di docs/diagram/_generate.py (tidak diketik ulang).
Keluaran:    docs/00-audit/alur-proses.html  (jangan diedit manual)

Jalankan:  py -3 docs/diagram/_generate_alur_html.py   (dari root proyek)
"""
import os, sys, html

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)
import _generate as g  # noqa: E402

OUT = os.path.join(os.path.dirname(HERE), "00-audit", "alur-proses.html")

# Kelompok pelaku -> (kelas, fill, stroke). Warna isi pastel + teks gelap tetap terbaca di dua tema.
GROUPS = [
    ("sys",   "Sistem",          "#e4e7ec", "#8a94a3"),
    ("kg",    "Kepala Gudang",   "#cdeee6", "#2f8f7a"),
    ("sg",    "Staf Gudang",     "#d6e6fb", "#3b73c4"),
    ("apr",   "Approver",        "#fbe7c2", "#b7801b"),
    ("pem",   "Pemohon",         "#e6dcfb", "#7654c4"),
    ("drv",   "Driver",          "#fcdcc8", "#c4632a"),
    ("pr",    "Penindak PR",     "#dcf1c8", "#5c9a2a"),
    ("adm",   "Admin",           "#f9d3dc", "#b8445f"),
]
GMAP = {k: (fill, stroke) for k, _, fill, stroke in GROUPS}


def group_of(lane):
    l = lane.lower()
    if l == "sistem": return "sys"
    if "kepala gudang" in l and not l.startswith("pengaju"): return "kg"
    if "staf" in l or "penghitung" in l: return "sg"
    if "approver" in l or "delegat" in l: return "apr"
    if "pemohon" in l or "pengaju" in l: return "pem"
    if "driver" in l: return "drv"
    if "penindak" in l: return "pr"
    if "admin" in l: return "adm"
    return "sys"


def q(s):
    return (s.replace("&", "#amp;").replace('"', "#quot;").replace("<", "#lt;").replace(">", "#gt;"))


def mermaid(f):
    lines = ["flowchart TD"]
    for k, _, fill, stroke in GROUPS:
        lines.append(f"  classDef {k} fill:{fill},stroke:{stroke},stroke-width:1.5px,color:#1b2430")
    for nid, lane, kind, label, _ref in f["nodes"]:
        i, t = "n_" + nid, q(label)
        shape = {"start": f'(["{t}"])', "end": f'(["{t}"])', "gw": f'{{"{t}"}}',
                 "timer": f'(("{t}"))'}.get(kind, f'["{t}"]')
        lines.append(f"  {i}{shape}:::{group_of(lane)}")
    for a, b, label, back in f["edges"]:
        arrow = "-.->" if back else "-->"
        lab = f'|"{q(label)}"|' if label else ""
        lines.append(f"  n_{a} {arrow}{lab} n_{b}")
    return "\n".join(lines)


KIND = {"start": "Mulai", "end": "Selesai", "task": "Langkah", "gw": "Keputusan", "timer": "Tunggu"}

sections, toc = [], []
for f in g.FLOWS:
    n = f["n"]
    toc.append(f'<li><a href="#alur-{n}"><span class="num">{n}</span>{html.escape(f["title"])}</a></li>')
    used = []
    for lane in f["lanes"]:
        used.append(f'<li><span class="sw" style="background:{GMAP[group_of(lane)][0]};border-color:{GMAP[group_of(lane)][1]}"></span>{html.escape(lane)}</li>')
    rows = "".join(
        f'<tr><td class="k">{KIND[k]}</td><td>{html.escape(lane)}</td><td>{html.escape(label)}</td><td class="ref">{html.escape(ref)}</td></tr>'
        for _, lane, k, label, ref in f["nodes"])
    note = f'<p class="note"><b>Catatan:</b> {html.escape(f["gateways_note"]).replace("`", "")}</p>' if f.get("gateways_note") else ""
    sections.append(f'''
<section id="alur-{n}" class="flow">
  <header class="fh">
    <p class="eyebrow">Alur {n} · <span class="mono">{" · ".join(f["status"])}</span></p>
    <h2>{html.escape(f["title"])}</h2>
    <p class="intro">{html.escape(f["intro"])}</p>
    <ul class="legend">{"".join(used)}</ul>
  </header>
  <div class="diagram"><pre class="mermaid">
{html.escape(mermaid(f), quote=False)}
</pre></div>
  {note}
  <details><summary>Detail langkah: status &amp; aturan ({len(f["nodes"])} langkah)</summary>
    <div class="tw"><table><thead><tr><th>Jenis</th><th>Pelaku</th><th>Langkah</th><th>Status / aturan</th></tr></thead><tbody>{rows}</tbody></table></div>
  </details>
  <p class="top"><a href="#daftar">↑ Daftar alur</a></p>
</section>''')
    print(f"alur {n}: {len(f['nodes'])} node, {len(f['edges'])} edge")

page = f'''<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Alur Proses WMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap">
<style>
:root {{
  --bg:#f4f6f5; --surface:#ffffff; --ink:#1b2430; --muted:#5b6673; --line:#d9dfdc; --accent:#1f6f66;
  --sans:"IBM Plex Sans", system-ui, -apple-system, "Segoe UI", sans-serif;
  --mono:"IBM Plex Mono", ui-monospace, Consolas, monospace;
}}
@media (prefers-color-scheme: dark) {{
  :root:not([data-theme="light"]) {{ --bg:#141a1d; --surface:#1c2428; --ink:#e6ecea; --muted:#9aa7a4; --line:#2e3a3e; --accent:#6cc5b8; color-scheme:dark; }}
}}
:root[data-theme="dark"] {{ --bg:#141a1d; --surface:#1c2428; --ink:#e6ecea; --muted:#9aa7a4; --line:#2e3a3e; --accent:#6cc5b8; color-scheme:dark; }}
body {{ background:var(--bg); color:var(--ink); font:15px/1.6 var(--sans); padding-inline:16px; padding-block:32px 64px; }}
.wrap {{ max-width:1100px; margin:0 auto; display:grid; gap:40px; }}
h1 {{ font-size:1.9rem; line-height:1.2; margin:0 0 8px; text-wrap:balance; }}
h2 {{ font-size:1.3rem; line-height:1.3; margin:0; text-wrap:balance; }}
.lede {{ color:var(--muted); max-width:65ch; margin:0; }}
.mono {{ font-family:var(--mono); }}
a {{ color:var(--accent); }}
a:focus-visible, summary:focus-visible {{ outline:2px solid var(--accent); outline-offset:2px; }}
.toc {{ list-style:none; padding:0; margin:20px 0 0; display:grid; grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); gap:6px 20px; }}
.toc a {{ display:flex; gap:10px; align-items:baseline; text-decoration:none; color:var(--ink); padding:6px 0; border-bottom:1px solid var(--line); }}
.toc a:hover {{ color:var(--accent); }}
.num {{ font-family:var(--mono); font-weight:500; color:var(--accent); min-width:1.6em; font-variant-numeric:tabular-nums; }}
.keybox {{ display:flex; flex-wrap:wrap; gap:8px 20px; margin-top:16px; color:var(--muted); font-size:.85rem; }}
.flow {{ background:var(--surface); border:1px solid var(--line); border-radius:10px; padding:24px 20px; display:grid; gap:16px; scroll-margin-top:16px; }}
.fh {{ display:grid; gap:8px; }}
.eyebrow {{ margin:0; font-size:.75rem; letter-spacing:.06em; text-transform:uppercase; color:var(--accent); font-weight:600; }}
.eyebrow .mono {{ text-transform:none; letter-spacing:0; }}
.intro {{ margin:0; color:var(--muted); max-width:75ch; }}
.legend {{ list-style:none; padding:0; margin:4px 0 0; display:flex; flex-wrap:wrap; gap:6px 16px; font-size:.85rem; }}
.legend li {{ display:flex; align-items:center; gap:6px; }}
.sw {{ width:14px; height:14px; border-radius:3px; border:1.5px solid; display:inline-block; }}
.diagram {{ overflow-x:auto; background:var(--bg); border:1px solid var(--line); border-radius:8px; padding:12px; }}
.diagram pre {{ margin:0; display:flex; justify-content:center; }}
.note {{ margin:0; font-size:.9rem; color:var(--muted); max-width:85ch; }}
.note b {{ color:var(--ink); }}
details summary {{ cursor:pointer; color:var(--accent); font-weight:500; }}
.tw {{ overflow-x:auto; margin-top:10px; }}
table {{ border-collapse:collapse; width:100%; font-size:.85rem; }}
th, td {{ text-align:left; vertical-align:top; padding:6px 8px; border-bottom:1px solid var(--line); }}
th {{ color:var(--muted); font-weight:600; }}
td.k {{ white-space:nowrap; color:var(--muted); }}
td.ref {{ font-family:var(--mono); font-size:.78rem; color:var(--muted); }}
.top {{ margin:0; font-size:.85rem; }}
</style>
</head>
<body>
<div class="wrap">
  <header id="daftar">
    <h1>Alur Proses WMS</h1>
    <p class="lede">Sepuluh alur proses bisnis to-be (Part 2, BPMN v0.4) dalam bentuk diagram alir sederhana. Warna kotak menandai siapa yang mengerjakan langkahnya. Data diambil langsung dari <span class="mono">docs/diagram/_generate.py</span>, sumber yang sama dengan berkas <span class="mono">bpmn-*.drawio</span>.</p>
    <div class="keybox"><span>▭ langkah</span><span>◇ keputusan</span><span>⬭ mulai / selesai</span><span>◯ tunggu waktu</span><span>- - → kembali ke langkah sebelumnya</span></div>
    <ol class="toc">{"".join(toc)}</ol>
  </header>
  {"".join(sections)}
  <p class="lede">Berkas ini dibuat otomatis — jangan diedit manual. Buat ulang dengan <span class="mono">py -3 docs/diagram/_generate_alur_html.py</span>. Diagram dirender Mermaid dari CDN, jadi perlu koneksi internet.</p>
</div>
<script src="https://cdn.jsdelivr.net/npm/mermaid@11.17.2/dist/mermaid.min.js"></script>
<script>
  var dark = document.documentElement.getAttribute("data-theme") === "dark" ||
    (document.documentElement.getAttribute("data-theme") !== "light" &&
     window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches);
  mermaid.initialize({{ startOnLoad: true, theme: dark ? "dark" : "default", flowchart: {{ useMaxWidth: false }} }});
</script>
</body>
</html>
'''
with open(OUT, "w", encoding="utf-8") as fh:
    fh.write(page)
print("OK", OUT, len(g.FLOWS), "alur")
