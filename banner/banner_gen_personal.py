#!/usr/bin/env python3
"""
MUS SOU MANO — personalized CS2 scoreboard banner.
Built around the owner's real Steam profile:
  persona "Mus Sou Mano" (Mohamed, Porto Alegre - Brazil), avatar steam_avatar.jpg.
Produces sv_server_graphic1 (360x60) and sv_server_graphic2 (220x45) PNGs (<16kb).
"""
import os
from PIL import Image, ImageDraw, ImageFont, ImageFilter

# ---- palette (matches the website) ----
BG_DARK   = (13, 17, 23)
BG_PANEL  = (22, 27, 34)
AMBER     = (240, 165, 0)
ORANGE    = (255, 107, 53)
TEXT      = (230, 237, 243)
MUTED     = (139, 148, 158)
INK       = (6, 16, 31)
# Brazil accents (subtle nod to the profile location)
BR_GREEN  = (0, 151, 57)
BR_YELLOW = (255, 223, 0)

FONT_BLACK = "/System/Library/Fonts/Supplemental/Arial Black.ttf"
FONT_BOLD  = "/System/Library/Fonts/Supplemental/Arial Bold.ttf"
FONT_REG   = "/System/Library/Fonts/Supplemental/Arial.ttf"

AVATAR = os.path.join(os.path.dirname(__file__), "steam_avatar.jpg")


def font(path, size):
    return ImageFont.truetype(path, size)


def rounded_mask(size, radius):
    m = Image.new("L", size, 0)
    ImageDraw.Draw(m).rounded_rectangle([0, 0, size[0] - 1, size[1] - 1], radius=radius, fill=255)
    return m


def circle_mask(size, ss=4):
    """Anti-aliased circular mask."""
    big = Image.new("L", (size * ss, size * ss), 0)
    ImageDraw.Draw(big).ellipse([0, 0, size * ss - 1, size * ss - 1], fill=255)
    return big.resize((size, size), Image.LANCZOS)


def horizontal_gradient(size, c1, c2):
    w, h = size
    base, top = Image.new("RGB", size, c1), Image.new("RGB", size, c2)
    mask = Image.new("L", size)
    md = mask.load()
    for x in range(w):
        v = int(255 * (x / max(1, w - 1)))
        for y in range(h):
            md[x, y] = v
    return Image.composite(top, base, mask)


def diagonal_gradient(size, c1, c2):
    w, h = size
    base, top = Image.new("RGB", size, c1), Image.new("RGB", size, c2)
    mask = Image.new("L", size)
    md = mask.load()
    for x in range(w):
        for y in range(h):
            t = (x / max(1, w - 1)) * 0.7 + (y / max(1, h - 1)) * 0.3
            md[x, y] = int(255 * t)
    return Image.composite(top, base, mask)


def gradient_text(draw_size, text, fnt, c1, c2, anchor_xy, anchor="lm"):
    grad = horizontal_gradient(draw_size, c1, c2).convert("RGBA")
    txtmask = Image.new("L", draw_size, 0)
    ImageDraw.Draw(txtmask).text(anchor_xy, text, font=fnt, fill=255, anchor=anchor)
    out = Image.new("RGBA", draw_size, (0, 0, 0, 0))
    out.paste(grad, (0, 0), txtmask)
    return out


def avatar_badge(side):
    """Circular Steam avatar with an amber gradient ring."""
    ring = max(2, side // 16)
    av = Image.open(AVATAR).convert("RGB").resize((side - ring * 2, side - ring * 2), Image.LANCZOS)
    av = av.convert("RGBA")
    av.putalpha(circle_mask(side - ring * 2))

    badge = Image.new("RGBA", (side, side), (0, 0, 0, 0))
    # ring = amber->orange gradient disc
    ringdisc = diagonal_gradient((side, side), AMBER, ORANGE).convert("RGBA")
    ringdisc.putalpha(circle_mask(side))
    badge = Image.alpha_composite(badge, ringdisc)
    # paste avatar centered
    badge.paste(av, (ring, ring), av)
    return badge


def build(width, height, big=True):
    img = diagonal_gradient((width, height), BG_PANEL, BG_DARK).convert("RGBA")

    # amber glow behind the avatar
    glow = Image.new("RGBA", (width, height), (0, 0, 0, 0))
    gx = int(height * 0.55)
    ImageDraw.Draw(glow).ellipse([gx - height, -height, gx + height, height * 2],
                                 fill=(AMBER[0], AMBER[1], AMBER[2], 70))
    glow = glow.filter(ImageFilter.GaussianBlur(height * 0.45))
    img = Image.alpha_composite(img, glow)

    draw = ImageDraw.Draw(img)
    pad = max(5, height // 10)

    # avatar badge (left)
    side = height - pad * 2
    badge = avatar_badge(side)
    img.paste(badge, (pad, pad), badge)

    tx = pad + side + max(9, width // 36)

    if big:
        wm = gradient_text((width, height), "MUS SOU MANO", font(FONT_BLACK, 21),
                           AMBER, ORANGE, (tx, height * 0.40), anchor="lm")
        img = Image.alpha_composite(img, wm)
        draw = ImageDraw.Draw(img)
        # subtitle line: handle + URL
        draw.text((tx + 1, height * 0.72), "MOHAMED ", font=font(FONT_BOLD, 10), fill=TEXT, anchor="lm")
        w1 = draw.textlength("MOHAMED ", font=font(FONT_BOLD, 10))
        draw.text((tx + 1 + w1, height * 0.72), "\u2022 stats.damineweb.work",
                  font=font(FONT_BOLD, 10), fill=AMBER, anchor="lm")
        # right side: Brazil flag chip + crosshair
        chip_w, chip_h = 16, 11
        rx = width - pad - chip_w - 2
        ry = int(height * 0.26)
        # green field
        draw.rounded_rectangle([rx, ry, rx + chip_w, ry + chip_h], radius=2, fill=BR_GREEN)
        # yellow diamond
        cxx, cyy = rx + chip_w / 2, ry + chip_h / 2
        draw.polygon([(cxx, ry + 1.5), (rx + chip_w - 2, cyy), (cxx, ry + chip_h - 1.5), (rx + 2, cyy)], fill=BR_YELLOW)
        draw.ellipse([cxx - 2.2, cyy - 2.2, cxx + 2.2, cyy + 2.2], fill=(0, 39, 118))
        draw.text((rx + chip_w / 2, ry + chip_h + 9), "BRAZIL", font=font(FONT_BOLD, 7),
                  fill=(120, 130, 142), anchor="mm")
    else:
        wm = gradient_text((width, height), "MUS SOU MANO", font(FONT_BLACK, 16),
                           AMBER, ORANGE, (tx, height * 0.40), anchor="lm")
        img = Image.alpha_composite(img, wm)
        draw = ImageDraw.Draw(img)
        draw.text((tx + 1, height * 0.73), "CS2 \u2022 stats.damineweb.work",
                  font=font(FONT_BOLD, 8), fill=AMBER, anchor="lm")

    # amber bottom accent
    bar = Image.new("RGBA", (width, height), (0, 0, 0, 0))
    bar.paste(horizontal_gradient((width, 3), AMBER, ORANGE).convert("RGBA"), (0, height - 3))
    img = Image.alpha_composite(img, bar)

    img.putalpha(rounded_mask((width, height), radius=max(6, height // 7)))
    flat = Image.new("RGB", (width, height), BG_DARK)
    flat.paste(img, (0, 0), img)
    return flat


def save_under_16kb(img, path):
    img.save(path, "PNG", optimize=True)
    if os.path.getsize(path) >= 16000:
        img.convert("P", palette=Image.ADAPTIVE, colors=256).save(path, "PNG", optimize=True)
    if os.path.getsize(path) >= 16000:
        img.convert("P", palette=Image.ADAPTIVE, colors=128).save(path, "PNG", optimize=True)
    return os.path.getsize(path)


if __name__ == "__main__":
    import sys
    outdir = sys.argv[1] if len(sys.argv) > 1 else os.path.dirname(__file__)
    s1 = save_under_16kb(build(360, 60, big=True), f"{outdir}/msm_banner_360x60.png")
    s2 = save_under_16kb(build(220, 45, big=False), f"{outdir}/msm_banner_220x45.png")
    print(f"360x60 -> {s1} bytes")
    print(f"220x45 -> {s2} bytes")
