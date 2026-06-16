#!/usr/bin/env python3
"""
MUS SOU MANO — CS2 scoreboard banner generator.
Produces sv_server_graphic1 (360x60) and sv_server_graphic2 (220x45) PNGs (<16kb).
Brand: amber/orange on dark, MSM monogram — matches stats.damineweb.work + favicon.
"""
import math
from PIL import Image, ImageDraw, ImageFont, ImageFilter

# ---- palette (matches the website) ----
BG_DARK   = (13, 17, 23)      # #0d1117
BG_PANEL  = (22, 27, 34)      # #161b22
AMBER     = (240, 165, 0)     # #f0a500
ORANGE    = (255, 107, 53)    # #ff6b35
TEXT      = (230, 237, 243)   # #e6edf3
MUTED     = (139, 148, 158)   # #8b949e
INK       = (6, 16, 31)       # logo text on amber

FONT_BLACK = "/System/Library/Fonts/Supplemental/Arial Black.ttf"
FONT_BOLD  = "/System/Library/Fonts/Supplemental/Arial Bold.ttf"
FONT_REG   = "/System/Library/Fonts/Supplemental/Arial.ttf"


def font(path, size):
    return ImageFont.truetype(path, size)


def lerp(a, b, t):
    return tuple(round(a[i] + (b[i] - a[i]) * t) for i in range(3))


def rounded_mask(size, radius):
    m = Image.new("L", size, 0)
    d = ImageDraw.Draw(m)
    d.rounded_rectangle([0, 0, size[0] - 1, size[1] - 1], radius=radius, fill=255)
    return m


def horizontal_gradient(size, c1, c2):
    w, h = size
    base = Image.new("RGB", size, c1)
    top = Image.new("RGB", size, c2)
    mask = Image.new("L", size)
    md = mask.load()
    for x in range(w):
        v = int(255 * (x / max(1, w - 1)))
        for y in range(h):
            md[x, y] = v
    return Image.composite(top, base, mask)


def diagonal_gradient(size, c1, c2):
    w, h = size
    base = Image.new("RGB", size, c1)
    top = Image.new("RGB", size, c2)
    mask = Image.new("L", size)
    md = mask.load()
    for x in range(w):
        for y in range(h):
            t = (x / max(1, w - 1)) * 0.7 + (y / max(1, h - 1)) * 0.3
            md[x, y] = int(255 * t)
    return Image.composite(top, base, mask)


def gradient_text(draw_size, text, fnt, c1, c2, anchor_xy, anchor="lm"):
    """Render text filled with a horizontal gradient. Returns an RGBA layer."""
    grad = horizontal_gradient(draw_size, c1, c2).convert("RGBA")
    txtmask = Image.new("L", draw_size, 0)
    d = ImageDraw.Draw(txtmask)
    d.text(anchor_xy, text, font=fnt, fill=255, anchor=anchor)
    out = Image.new("RGBA", draw_size, (0, 0, 0, 0))
    out.paste(grad, (0, 0), txtmask)
    return out


def msm_logo(side):
    """Rounded amber->orange gradient square with 'MSM'."""
    logo = diagonal_gradient((side, side), AMBER, ORANGE).convert("RGBA")
    logo.putalpha(rounded_mask((side, side), radius=max(6, side // 5)))
    d = ImageDraw.Draw(logo)
    fs = int(side * 0.36)
    f = font(FONT_BLACK, fs)
    d.text((side / 2, side / 2 + 1), "MSM", font=f, fill=INK, anchor="mm")
    return logo


def build(width, height, big=True):
    # background: dark diagonal gradient + amber glow behind logo
    img = diagonal_gradient((width, height), BG_PANEL, BG_DARK).convert("RGBA")

    glow = Image.new("RGBA", (width, height), (0, 0, 0, 0))
    gd = ImageDraw.Draw(glow)
    gx = int(height * 0.55)
    gd.ellipse([gx - height, -height, gx + height, height * 2],
               fill=(AMBER[0], AMBER[1], AMBER[2], 60))
    glow = glow.filter(ImageFilter.GaussianBlur(height * 0.45))
    img = Image.alpha_composite(img, glow)

    draw = ImageDraw.Draw(img)
    pad = max(5, height // 10)

    # logo
    side = height - pad * 2
    logo = msm_logo(side)
    img.paste(logo, (pad, pad), logo)

    tx = pad + side + max(8, width // 40)

    if big:
        # wordmark
        wm = gradient_text((width, height), "MUS SOU MANO", font(FONT_BLACK, 22),
                           AMBER, ORANGE, (tx, height * 0.42), anchor="lm")
        img = Image.alpha_composite(img, wm)
        draw = ImageDraw.Draw(img)
        # subtitle
        draw.text((tx + 1, height * 0.74), "MULTI-GAMEMODE  ", font=font(FONT_BOLD, 10),
                  fill=MUTED, anchor="lm")
        sub_w = draw.textlength("MULTI-GAMEMODE  ", font=font(FONT_BOLD, 10))
        draw.text((tx + 1 + sub_w, height * 0.74), "stats.damineweb.work",
                  font=font(FONT_BOLD, 10), fill=AMBER, anchor="lm")
        # right-side crosshair motif (amber) — clean shooter accent
        cx, cy, r, gap = width - pad - 11, height * 0.42, 10, 3
        for (x0, y0, x1, y1) in [
            (cx - r, cy, cx - gap, cy), (cx + gap, cy, cx + r, cy),
            (cx, cy - r, cx, cy - gap), (cx, cy + gap, cx, cy + r),
        ]:
            draw.line([x0, y0, x1, y1], fill=AMBER, width=2)
        draw.ellipse([cx - 1.5, cy - 1.5, cx + 1.5, cy + 1.5], fill=ORANGE)
        draw.text((cx, height * 0.80), "5 SERVERS", font=font(FONT_BOLD, 7),
                  fill=(120, 130, 142), anchor="mm")
    else:
        wm = gradient_text((width, height), "MUS SOU MANO", font(FONT_BLACK, 17),
                           AMBER, ORANGE, (tx, height * 0.42), anchor="lm")
        img = Image.alpha_composite(img, wm)
        draw = ImageDraw.Draw(img)
        draw.text((tx + 1, height * 0.74), "CS2 COMMUNITY", font=font(FONT_BOLD, 9),
                  fill=MUTED, anchor="lm")

    # amber bottom accent bar
    bar = Image.new("RGBA", (width, height), (0, 0, 0, 0))
    bd = ImageDraw.Draw(bar)
    bargrad = horizontal_gradient((width, 3), AMBER, ORANGE).convert("RGBA")
    bar.paste(bargrad, (0, height - 3))
    img = Image.alpha_composite(img, bar)

    # round the whole banner + flatten on dark
    img.putalpha(rounded_mask((width, height), radius=max(6, height // 7)))
    flat = Image.new("RGB", (width, height), BG_DARK)
    flat.paste(img, (0, 0), img)
    return flat


def save_under_16kb(img, path):
    # PNG, palette-optimize if needed to stay <16kb
    img.save(path, "PNG", optimize=True)
    import os
    if os.path.getsize(path) >= 16000:
        img.convert("P", palette=Image.ADAPTIVE, colors=256).save(path, "PNG", optimize=True)
    return os.path.getsize(path)


if __name__ == "__main__":
    import sys
    outdir = sys.argv[1] if len(sys.argv) > 1 else "."
    b1 = build(360, 60, big=True)
    s1 = save_under_16kb(b1, f"{outdir}/msm_banner_360x60.png")
    b2 = build(220, 45, big=False)
    s2 = save_under_16kb(b2, f"{outdir}/msm_banner_220x45.png")
    print(f"360x60 -> {s1} bytes")
    print(f"220x45 -> {s2} bytes")
