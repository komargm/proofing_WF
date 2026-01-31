#!/usr/bin/env python3
"""WhisperedFrames Ingest Worker

Czyta manifest JSON wygenerowany przez PHP i tworzy:
 - preview (dłuższy bok max 1600px) + watermark (opcjonalnie)
 - thumb  (dłuższy bok max 420px)

Obsługuje EXIF Orientation (pion/poziom).
"""

import argparse
import json
import os
import sys
from datetime import datetime
from typing import Optional

from PIL import Image, ImageOps


# ======= Twoje ustawienia =======
WIDTH_RATIO = 0.80   # watermark zajmuje 80% szerokości zdjęcia
OPACITY     = 0.7    # siła znaku 0..1
WHITE_CUT   = 245    # próg bieli, gdy watermark ma białe tło
# ================================


def log(msg: str):
    ts = datetime.utcnow().strftime("%Y-%m-%d %H:%M:%S")
    print(f"[{ts}] {msg}", flush=True)


def ensure_dir(p: str):
    os.makedirs(p, exist_ok=True)


def resize_max(img: Image.Image, max_side: int) -> Image.Image:
    w, h = img.size
    if max(w, h) <= max_side:
        return img
    if w >= h:
        new_w = max_side
        new_h = int(h * (max_side / w))
    else:
        new_h = max_side
        new_w = int(w * (max_side / h))
    return img.resize((new_w, new_h), Image.LANCZOS)


def _resolve_watermark_path(explicit_path: Optional[str] = None) -> str:
    """Szuka watermark.png obok skryptu albo w /var/www/html/watermark.png."""
    if explicit_path and os.path.isfile(explicit_path):
        return explicit_path

    here = os.path.dirname(os.path.abspath(__file__))
    p1 = os.path.join(here, "watermark.png")
    if os.path.isfile(p1):
        return p1

    p2 = "/var/www/html/watermark.png"
    if os.path.isfile(p2):
        return p2

    raise FileNotFoundError(f"Nie znaleziono watermark.png (szukałem: {p1} oraz {p2})")


def _alpha_from_luma(wm_rgb: Image.Image, opacity: float, white_cut: int) -> Image.Image:
    """
    Tworzy kanał alfa z jasności:
    - białe/prawie białe (>= white_cut) => alfa 0
    - ciemniejsze => alfa rośnie wraz z "ciemnością"
    """
    opacity = max(0.0, min(1.0, opacity))
    gray = wm_rgb.convert("L")  # 0..255

    def to_alpha(luma: int) -> int:
        if luma >= white_cut:
            return 0
        a = 255 - luma
        return int(max(0, min(255, a * opacity)))

    return gray.point(to_alpha)


def load_watermark_rgba(wm_path: str, opacity: float, white_cut: int) -> Image.Image:
    """
    Ładuje watermark do RGBA.
    - Jeśli PNG ma alfę: używa jej i mnoży przez opacity.
    - Jeśli PNG nie ma alfy / ma białe tło: buduje alfę z luminancji (white_cut) + opacity.
    """
    wm = Image.open(wm_path)

    bands = wm.getbands()
    if "A" in bands:
        wm = wm.convert("RGBA")
        r, g, b, a = wm.split()
        opacity = max(0.0, min(1.0, opacity))
        a = a.point(lambda px: int(px * opacity))
        wm = Image.merge("RGBA", (r, g, b, a))
        return wm

    wm_rgb = wm.convert("RGB")
    alpha = _alpha_from_luma(wm_rgb, opacity=opacity, white_cut=white_cut)
    wm_rgba = wm_rgb.convert("RGBA")
    wm_rgba.putalpha(alpha)
    return wm_rgba


def apply_watermark_center(
    base_rgb: Image.Image,
    wm_rgba: Image.Image,
    width_ratio: float,
) -> Image.Image:
    """
    Najprostsza, stabilna metoda:
    - tworzy pustą warstwę RGBA
    - wkleja watermark
    - alpha_composite
    """
    base = base_rgb.convert("RGBA")
    bw, bh = base.size

    # Skalowanie watermarka do width_ratio szerokości zdjęcia
    ww, wh = wm_rgba.size
    target_w = max(1, int(bw * width_ratio))
    scale = target_w / max(1, ww)
    target_h = max(1, int(wh * scale))
    wm = wm_rgba.resize((target_w, target_h), Image.LANCZOS)

    # Centrowanie
    x = (bw - wm.size[0]) // 2
    y = (bh - wm.size[1]) // 2

    layer = Image.new("RGBA", base.size, (0, 0, 0, 0))
    layer.paste(wm, (x, y), wm)  # maska = alfa watermarka

    out = Image.alpha_composite(base, layer).convert("RGB")
    return out


def process_item(item: dict, watermark: bool):
    photo_id = item.get("photo_id")
    src = item.get("src")
    preview_dest = item.get("preview_dest")
    thumb_dest = item.get("thumb_dest")

    if not (photo_id and src and preview_dest and thumb_dest):
        raise ValueError("Niepoprawny item w manifeście")

    if not os.path.isfile(src):
        raise FileNotFoundError(f"Brak pliku źródłowego: {src}")

    ensure_dir(os.path.dirname(preview_dest))
    ensure_dir(os.path.dirname(thumb_dest))

    with Image.open(src) as im:
        # EXIF orientation fix
        im = ImageOps.exif_transpose(im).convert("RGB")

        # preview
        prev = resize_max(im, 1600)

        if watermark:
            wm_path = _resolve_watermark_path(None)
            wm = load_watermark_rgba(wm_path, opacity=OPACITY, white_cut=WHITE_CUT)
            prev = apply_watermark_center(prev, wm, width_ratio=WIDTH_RATIO)

        prev.save(preview_dest, format="JPEG", quality=86, optimize=True, progressive=True)

        # thumb (bez watermarka)
        th = resize_max(im, 420)
        th.save(thumb_dest, format="JPEG", quality=82, optimize=True, progressive=True)


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--manifest", required=True)
    parser.add_argument("--log", required=False)
    args = parser.parse_args()

    if args.log:
        try:
            with open(args.log, "a", encoding="utf-8") as f:
                f.write("[WF] Python worker started\n")
        except Exception:
            pass

    with open(args.manifest, "r", encoding="utf-8") as f:
        manifest = json.load(f)

    job_id = manifest.get("job_id", "")
    album_id = manifest.get("album_id")
    watermark = bool(manifest.get("watermark", True))
    items = manifest.get("items", [])

    log(f"[WF] job={job_id} album_id={album_id} items={len(items)} watermark={watermark}")

    ok = 0
    for idx, item in enumerate(items, start=1):
        try:
            process_item(item, watermark=watermark)
            ok += 1
            log(f"[WF] {idx}/{len(items)} OK photo_id={item.get('photo_id')}")
        except Exception as e:
            log(f"[WF] ERROR {idx}/{len(items)} photo_id={item.get('photo_id')}: {e}")

    if ok == len(items):
        log("[WF] DONE")
        return 0
    log(f"[WF] DONE_WITH_ERRORS ok={ok} total={len(items)}")
    return 2


if __name__ == "__main__":
    sys.exit(main())
