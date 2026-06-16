#!/usr/bin/env python3
"""
MUS SOU MANO — 1200x630 social share image (og:image / twitter:image).
Reuses the helpers + palette from banner_gen_personal.py so it matches the
in-game scoreboard banner and the website branding.

Run:  ./venv/bin/python3 social_gen.py <outdir>   (default: parent web root)
"""
import os
import sys
from PIL import Image, ImageDraw, ImageFilter

from banner_gen_personal import (
    font, diagonal_gradient, horizontal_gradient, gradient_text, avatar_badge,
    FONT_BLACK, FONT_BOLD, FONT_REG,
    AMBER, ORANGE, BG_DARK, BG_PANEL, TEXT, MUTED, BR_GREEN, BR_YELLOW,
)

W, H = 1200, 630
CHIP_BG = (28, 34, 42)


def chip(draw, x, y, label, fnt, pad=18, h=46):
    w = draw.textlength(label, font=fnt)
    draw.rounded_rectangle([x, y, x + w + pad * 2, y + h], radius=h // 2,
                           fill=CHIP_BG, outline=AMBER, width=2)
    draw.text((x + pad, y + h / 2), label, font=fnt, fill=TEXT, anchor="lm")
    return x + w + pad * 2 + 16


def brazil_chip(draw, x, y, w=44, hgt=30):
    draw.rounded_rectangle([x, y, x + w, y + hgt], radius=4, fill=BR_GREEN)
    cx, cy = x + w / 2, y + hgt / 2
    draw.polygon([(cx, y + 4), (x + w - 6, cy), (cx, y + hgt - 4), (x + 6, cy)], fill=BR_YELLOW)
    draw.ellipse([cx - 6, cy - 6, cx + 6, cy + 6], fill=(0, 39, 118))


def build():
    img = diagonal_gradient((W, H), BG_PANEL, BG_DARK).convert("RGBA")

    # amber glow behind the avatar
    glow = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    ImageDraw.Draw(glow).ellipse([-220, 40, 540, 700],
                                 fill=(AMBER[0], AMBER[1], AMBER[2], 60))
    glow = glow.filter(ImageFilter.GaussianBlur(170))
    img = Image.alpha_composite(img, glow)

    # avatar (left)
    side = 320
    badge = avatar_badge(side)
    ay = (H - side) // 2 - 24
    img.paste(badge, (96, ay), badge)

    draw = ImageDraw.Draw(img)
    tx = 96 + side + 72  # text column start

    # wordmark — auto-fit to the available width so it never clips
    avail = W - tx - 56
    wm_size = 96
    while wm_size > 40 and draw.textlength("MUS SOU MANO", font=font(FONT_BLACK, wm_size)) > avail:
        wm_size -= 2
    wm = gradient_text((W, H), "MUS SOU MANO", font(FONT_BLACK, wm_size),
                       AMBER, ORANGE, (tx, 196), anchor="lm")
    img = Image.alpha_composite(img, wm)
    draw = ImageDraw.Draw(img)

    # subtitle
    draw.text((tx + 3, 272), "CS2 COMMUNITY FLEET", font=font(FONT_BOLD, 38),
              fill=TEXT, anchor="lm")

    # amber divider
    draw.rectangle([tx + 3, 312, tx + 392, 316], fill=AMBER)

    # gamemode chips
    cx = tx + 3
    cf = font(FONT_BOLD, 24)
    for lbl in ["ZOMBIE", "1v1 ARENA", "HS DM", "KZ", "AWP"]:
        cx = chip(draw, cx, 344, lbl, cf)

    # prominent URL
    draw.text((tx + 3, 452), "stats.damineweb.work", font=font(FONT_BLACK, 50),
              fill=AMBER, anchor="lm")

    # feature line + Brazil flag
    draw.text((tx + 5, 512), "Global Ranks  \u00b7  Live Servers  \u00b7  Store  \u00b7  Multi-1v1 Arena",
              font=font(FONT_REG, 24), fill=MUTED, anchor="lm")
    brazil_chip(draw, W - 96, 60)
    draw.text((W - 74, 100), "BRAZIL", font=font(FONT_BOLD, 16), fill=MUTED, anchor="mm")

    # bottom accent bar
    bar = horizontal_gradient((W, 8), AMBER, ORANGE).convert("RGBA")
    img.paste(bar, (0, H - 8))

    flat = Image.new("RGB", (W, H), BG_DARK)
    flat.paste(img, (0, 0), img)
    return flat


if __name__ == "__main__":
    outdir = sys.argv[1] if len(sys.argv) > 1 else os.path.join(os.path.dirname(__file__), "..")
    out = os.path.join(outdir, "social.png")
    build().save(out, "PNG", optimize=True)
    print(f"social.png -> {os.path.getsize(out)} bytes  ({out})")
