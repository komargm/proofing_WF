#!/usr/bin/env python3
"""WhisperedFrames Rescan Worker

Uruchamiany z poziomu panelu admina dla konkretnego albumu.

Wejście: manifest JSON wygenerowany przez PHP, zawierający tylko te zdjęcia,
których plik oryginalny (mtime/rozmiar) różni się od zapisanych metadanych w DB.

Dla każdego item:
 - nadpisuje preview_800 oraz thumb
 - watermark zostaje (jeżeli watermark=True w manifeście)

Uwaga: skrypt NIE usuwa niczego i NIE dotyka oryginałów.
"""

import argparse
import json
import os
import sys
from datetime import datetime

from PIL import Image, ImageDraw, ImageFont


def log(msg: str):
    ts = datetime.utcnow().strftime("%Y-%m-%d %H:%M:%S")
    print(f"[{ts}] {msg}", flush=True)


def ensure_dir(p: str):
    os.makedirs(p, exist_ok=True)


def load_font(size: int):
    for path in [
        "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf",
        "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf",
    ]:
        if os.path.isfile(path):
            try:
                return ImageFont.truetype(path, size=size)
            except Exception:
                pass
    return ImageFont.load_default()


def apply_watermark(img: Image.Image, text: str = "WhisperedFrames") -> Image.Image:
    base = img.convert("RGBA")
    w, h = base.size
    overlay = Image.new("RGBA", base.size, (0, 0, 0, 0))
    draw = ImageDraw.Draw(overlay)

    font_size = max(14, int(min(w, h) * 0.035))
    font = load_font(font_size)

    pad = int(font_size * 0.6)
    text_w, text_h = draw.textbbox((0, 0), text, font=font)[2:4]
    x = w - text_w - pad
    y = h - text_h - pad

    draw.text((x + 2, y + 2), text, font=font, fill=(0, 0, 0, 110))
    draw.text((x, y), text, font=font, fill=(255, 255, 255, 140))

    out = Image.alpha_composite(base, overlay).convert("RGB")
    return out


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
        im = im.convert("RGB")

        prev = resize_max(im, 1600)
        if watermark:
            prev = apply_watermark(prev)
        prev.save(preview_dest, format="JPEG", quality=86, optimize=True, progressive=True)

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
                f.write("[WF] Python rescan worker started\n")
        except Exception:
            pass

    with open(args.manifest, "r", encoding="utf-8") as f:
        manifest = json.load(f)

    job_id = manifest.get("job_id", "")
    album_id = manifest.get("album_id")
    watermark = bool(manifest.get("watermark", True))
    items = manifest.get("items", [])

    log(f"[WF] RESCAN job={job_id} album_id={album_id} items={len(items)} watermark={watermark}")

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
