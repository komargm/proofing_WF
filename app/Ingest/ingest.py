#!/usr/bin/env python3
"""WhisperedFrames Ingest Worker

Czyta manifest JSON wygenerowany przez PHP i tworzy:
 - preview_800 (w praktyce: dłuższy bok max 1600px)
 - thumb (dłuższy bok max 420px)
Opcjonalnie wtopiony watermark w preview.

Loguje postęp w trybie line-buffered.
"""

import argparse
import json
import os
import sys
from datetime import datetime
from typing import Optional

from PIL import Image, ImageOps


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
    
    """
    Priorytet:
    1) explicit_path (jeśli podasz)
    2) watermark.png w katalogu skryptu
    3) /var/www/html/watermark.png
    """
    if explicit_path and os.path.isfile(explicit_path):
        return explicit_path

    here = os.path.dirname(os.path.abspath(__file__))
    p1 = os.path.join(here, "watermark.png")
    if os.path.isfile(p1):
        return p1

    p2 = "/var/www/html/watermark.png"
    if os.path.isfile(p2):
        return p2

    raise FileNotFoundError(
        "Nie znaleziono pliku watermark.png (szukałem: katalog skryptu oraz /var/www/html/watermark.png)"
    )


def apply_watermark_image(
    img: Image.Image,
    watermark_path: Optional[str] = None,
    width_ratio: float = 0.75,
    opacity: float = 0.20,
) -> Image.Image:

    """
    Nakłada PNG jako watermark na środek zdjęcia.
    - width_ratio: jaką część szerokości zdjęcia ma zajmować watermark (np. 0.75)
    - opacity: globalna przezroczystość watermarka (0..1)

    Watermark.png powinien mieć kanał alfa (RGBA). Jeśli nie ma, i tak zadziała (dostanie alfę).
    """
    wm_path = _resolve_watermark_path(watermark_path)

    base = img.convert("RGBA")
    bw, bh = base.size

    with Image.open(wm_path) as wm:
        wm = wm.convert("RGBA")
        ww, wh = wm.size

        # Skalowanie watermarka do width_ratio szerokości zdjęcia
        target_w = max(1, int(bw * width_ratio))
        scale = target_w / max(1, ww)
        target_h = max(1, int(wh * scale))
        wm = wm.resize((target_w, target_h), Image.LANCZOS)

        # Globalna kontrola opacity (mnożenie kanału alfa)
        if opacity < 1.0:
            r, g, b, a = wm.split()
            a = a.point(lambda px: int(px * max(0.0, min(1.0, opacity))))
            wm = Image.merge("RGBA", (r, g, b, a))

        # Centrowanie
        x = (bw - wm.size[0]) // 2
        y = (bh - wm.size[1]) // 2

        overlay = Image.new("RGBA", base.size, (0, 0, 0, 0))
        overlay.paste(wm, (x, y), wm)

        out = Image.alpha_composite(base, overlay).convert("RGB")
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
        # 1) popraw orientację wg EXIF
        im = ImageOps.exif_transpose(im)
        im = im.convert("RGB")

        # preview
        prev = resize_max(im, 1600)
        if watermark:
            prev = apply_watermark_image(
                prev,
                watermark_path=None,
                width_ratio=0.75,   # 3/4 szerokości
                opacity=0.20,       # delikatnie - podkręcisz wg uznania
            )
        prev.save(preview_dest, format="JPEG", quality=86, optimize=True, progressive=True)

        # thumb (bez watermarka - jak było)
        th = resize_max(im, 420)
        th.save(thumb_dest, format="JPEG", quality=82, optimize=True, progressive=True)


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--manifest", required=True)
    parser.add_argument("--log", required=False)
    args = parser.parse_args()

    # (Opcjonalnie) dopisz start do wskazanego loga
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
