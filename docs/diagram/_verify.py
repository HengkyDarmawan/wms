# -*- coding: utf-8 -*-
"""
Verifikasi ringan dokumentasi WMS (dibuat 23 Sep 2026, Tahap 3 validasi).

Memeriksa semua berkas Markdown di docs/ dan CLAUDE.md:
  1. Link relatif: berkas tujuan ada; anchor (#…) ada di berkas tujuan
     (anchor <a id="…"> atau slug heading gaya GitHub).
  2. Semua ID yang dirujuk terdefinisi: D-xx, A-xx, O-xx (04), P-xx, NFR-xx (01),
     BR-<AREA>-nn (05), AD-xx (08). Rentang seperti `BR-STK-01–07` / `A-29–A-49` diekspansi.
  3. Tidak ada ID yang didefinisikan dua kali.
  4. Tidak ada berkas Markdown > 450 baris.

Jalankan:  py -3 docs/diagram/_verify.py   (dari root proyek). Exit code 1 bila ada temuan.
Tautan di dalam blok kode (```) diabaikan.
"""
import os, re, sys, io, unicodedata
from collections import Counter, defaultdict

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
DOCS = os.path.join(ROOT, "docs")
MAX_LINES = 450

def read(p):
    with io.open(p, encoding="utf-8") as f:
        return f.read()

def md_files():
    out = [os.path.join(ROOT, "CLAUDE.md")]
    for d, _, fs in os.walk(DOCS):
        for f in fs:
            if f.lower().endswith(".md"):
                out.append(os.path.join(d, f))
    return sorted(p for p in out if os.path.exists(p))

def strip_code_blocks(text):
    """Ganti isi blok ``` dengan baris kosong (jumlah baris tetap)."""
    out, in_code = [], False
    for ln in text.split("\n"):
        if ln.strip().startswith("```"):
            in_code = not in_code
            out.append("")
            continue
        out.append("" if in_code else ln)
    return "\n".join(out)

def slug(heading):
    """Slug heading gaya GitHub: huruf kecil, buang tanda baca kecuali - dan _, spasi → -."""
    h = re.sub(r"<[^>]+>", "", heading)          # tag html
    h = re.sub(r"`", "", h)
    h = re.sub(r"[*_]{2}", "", h)                  # bold/italic penanda ganda
    h = h.strip().lower()
    h = re.sub(r"[^\w\s-]", "", h, flags=re.UNICODE)  # \w menyertakan _ dan huruf unicode
    h = re.sub(r"\s", "-", h)
    return h

def anchors_of(text):
    """Kumpulan anchor yang sah di satu berkas."""
    anchors = set(re.findall(r'<a id="([^"]+)"', text))
    counts = Counter()
    for m in re.finditer(r"^#{1,6}\s+(.+?)\s*#*\s*$", strip_code_blocks(text), flags=re.M):
        s = slug(m.group(1))
        n = counts[s]; counts[s] += 1
        anchors.add(s if n == 0 else f"{s}-{n}")
    return anchors

LINK_RE = re.compile(r"\[[^\]]*\]\(([^)\s]+)(?:\s+\"[^\"]*\")?\)")

def check_links(files, texts):
    anchors = {p: anchors_of(texts[p]) for p in files}
    broken, total = [], 0
    for p in files:
        body = strip_code_blocks(texts[p])
        for ln_no, ln in enumerate(body.split("\n"), 1):
            for m in LINK_RE.finditer(ln):
                target = m.group(1)
                if re.match(r"^(https?:|mailto:|tel:)", target):
                    continue
                total += 1
                path, _, anchor = target.partition("#")
                path = path.replace("%20", " ")
                tp = p if path == "" else os.path.normpath(os.path.join(os.path.dirname(p), path))
                rel = os.path.relpath(p, ROOT)
                if not os.path.exists(tp):
                    broken.append(f"{rel}:{ln_no}  berkas tidak ada: {target}")
                    continue
                if anchor:
                    if tp not in anchors:
                        if tp.lower().endswith(".md") and os.path.isfile(tp):
                            anchors[tp] = anchors_of(read(tp))
                        else:
                            continue  # anchor ke berkas non-markdown tidak diperiksa
                    if anchor not in anchors[tp]:
                        broken.append(f"{rel}:{ln_no}  anchor tidak ada: {target}")
    return total, broken

# ---------------------------------------------------------------- ID
ID_PATTERNS = {
    "D": r"D-\d{2}", "A": r"A-\d{2}", "O": r"O-\d{2}", "P": r"P-\d{2}",
    "NFR": r"NFR-\d{2}", "AD": r"AD-\d{2}", "BR": r"BR-[A-Z]{2,4}-\d{2}",
}

def definitions(files, texts):
    defs = defaultdict(list)  # id -> [lokasi]
    for p in files:
        rel = os.path.relpath(p, ROOT).replace("\\", "/")
        t = texts[p]
        base = os.path.basename(p)
        if base == "04-keputusan-dan-asumsi.md":
            for m in re.finditer(r'<a id="([dao])-(\d{2})"></a>', t):
                defs[f"{m.group(1).upper()}-{m.group(2)}"].append(rel)
        if base == "01-blueprint.md":
            for m in re.finditer(r"^\| ((?:P|NFR)-\d{2}) \|", t, flags=re.M):
                defs[m.group(1)].append(rel)
        if base == "05-aturan-bisnis.md":
            for m in re.finditer(r"^\| (BR-[A-Z]{2,4}-\d{2}) \|", t, flags=re.M):
                defs[m.group(1)].append(rel)
        if base == "08-arsitektur.md":
            for m in re.finditer(r"^\| (AD-\d{2}) \|", t, flags=re.M):
                defs[m.group(1)].append(rel)
    return defs

RANGE_RE = re.compile(r"\b((?:BR-[A-Z]{2,4}|D|A|O|P|NFR|AD)-)(\d{2})[–-](?:(?:BR-[A-Z]{2,4}|D|A|O|P|NFR|AD)-)?(\d{2})\b")
SINGLE_RE = re.compile(r"\b(BR-[A-Z]{2,4}-\d{2}|(?:D|A|O|P|NFR|AD)-\d{2})\b(?!\d)")

def references(files, texts):
    refs = defaultdict(set)  # id -> {lokasi}
    for p in files:
        rel = os.path.relpath(p, ROOT).replace("\\", "/")
        for ln_no, ln in enumerate(texts[p].split("\n"), 1):
            for m in RANGE_RE.finditer(ln):
                a, b = int(m.group(2)), int(m.group(3))
                if a < b:
                    for n in range(a, b + 1):
                        refs[f"{m.group(1)}{n:02d}"].add(f"{rel}:{ln_no}")
            for m in SINGLE_RE.finditer(ln):
                refs[m.group(1)].add(f"{rel}:{ln_no}")
    return refs

def main():
    files = md_files()
    texts = {p: read(p) for p in files}
    problems = 0

    total_links, broken = check_links(files, texts)
    defs = definitions(files, texts)
    refs = references(files, texts)
    undefined = {i: sorted(l) for i, l in refs.items() if i not in defs}
    dupes = {i: l for i, l in defs.items() if len(l) > 1}
    big = [(os.path.relpath(p, ROOT), texts[p].count("\n") + 1) for p in files if texts[p].count("\n") + 1 > MAX_LINES]

    print(f"Berkas Markdown diperiksa : {len(files)}")
    print(f"Link relatif diperiksa    : {total_links}")
    print(f"Link rusak                : {len(broken)}")
    for b in broken: print("   -", b)
    print(f"ID terdefinisi            : {len(defs)}  (D {sum(k.startswith('D-') for k in defs)}, A {sum(k.startswith('A-') for k in defs)}, "
          f"O {sum(k.startswith('O-') for k in defs)}, P {sum(k.startswith('P-') for k in defs)}, NFR {sum(k.startswith('NFR-') for k in defs)}, "
          f"BR {sum(k.startswith('BR-') for k in defs)}, AD {sum(k.startswith('AD-') for k in defs)})")
    print(f"ID dirujuk (unik)         : {len(refs)}")
    print(f"ID dirujuk tak terdefinisi: {len(undefined)}")
    for i, locs in sorted(undefined.items()): print("   -", i, "←", ", ".join(locs[:4]) + (" …" if len(locs) > 4 else ""))
    print(f"ID ganda                  : {len(dupes)}")
    for i, locs in sorted(dupes.items()): print("   -", i, locs)
    print(f"Berkas > {MAX_LINES} baris        : {len(big)}")
    for f, n in big: print(f"   - {f} ({n})")
    problems = len(broken) + len(undefined) + len(dupes) + len(big)
    print("HASIL:", "OK" if problems == 0 else f"{problems} temuan")
    return 1 if problems else 0

if __name__ == "__main__":
    sys.exit(main())
