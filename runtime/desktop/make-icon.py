#!/usr/bin/env python3
"""
生成「拾日」的 App 图标。

一张奶油色的月历（4 列 × 3 行小方块），其中一格被「拾起来」——
放大、倾斜、带投影，填成陶土色。读法：从一整月的格子里拈出今天。

小尺寸（≤64pt）是这套设计的软肋：格子太小会糊成灰带。
所以格子用实心（不是线框）、对比度给足，抬起的那块做到接近两格宽，
倾斜角度也加大——32pt 时剩下的是「一张浅色卡 + 一块歪着的橘块」，
形状仍然成立。

1024 直出会有锯齿，内部按 4 倍画再降采样。

用法：python3 make-icon.py <输出.png> [尺寸]
"""
import sys

from PIL import Image, ImageDraw, ImageFilter

S = int(sys.argv[2]) if len(sys.argv) > 2 else 1024
OUT = sys.argv[1] if len(sys.argv) > 1 else "/tmp/icon.png"
K = 4
W = S * K

INK = (34, 28, 25)
CREAM_TOP = (253, 250, 245)
CREAM_BOTTOM = (240, 232, 220)
CLAY_LIGHT = (210, 103, 62)
CLAY_DARK = (172, 66, 36)

MARGIN = 0.075
RADIUS_RATIO = 0.2237
CELL_ALPHA = 40           # 格子颜色浓度（/255）——0.10 那档在 64pt 会整体糊掉
CELL_GAP_RATIO = 0.18


def lerp(a, b, t):
    return tuple(int(round(a[i] + (b[i] - a[i]) * t)) for i in range(3))


def vertical_gradient(size, top, bottom):
    g = Image.new("RGB", (1, size))
    px = g.load()
    for y in range(size):
        px[0, y] = lerp(top, bottom, y / max(1, size - 1))
    return g.resize((size, size), Image.BILINEAR)


def rounded(size, radius, color):
    layer = Image.new("RGBA", (size, size), (0, 0, 0, 0))
    ImageDraw.Draw(layer).rounded_rectangle(
        [0, 0, size - 1, size - 1], radius=radius, fill=color,
    )
    return layer


def main():
    canvas = Image.new("RGBA", (W, W), (0, 0, 0, 0))

    # ── 底板：奶油渐变圆角方 + 极淡描边 ───────────────────────────────────
    inset = int(W * MARGIN)
    body = W - inset * 2
    radius = int(body * RADIUS_RATIO)

    plate_mask = Image.new("L", (body, body), 0)
    ImageDraw.Draw(plate_mask).rounded_rectangle(
        [0, 0, body - 1, body - 1], radius=radius, fill=255,
    )
    plate = Image.new("RGBA", (body, body), (0, 0, 0, 0))
    plate.paste(vertical_gradient(body, CREAM_TOP, CREAM_BOTTOM).convert("RGBA"), (0, 0), plate_mask)

    edge = Image.new("RGBA", (body, body), (0, 0, 0, 0))
    ImageDraw.Draw(edge).rounded_rectangle(
        [1, 1, body - 2, body - 2], radius=radius,
        outline=INK + (28,), width=max(2, int(body * 0.0035)),
    )
    plate = Image.alpha_composite(plate, edge)
    canvas.alpha_composite(plate, (inset, inset))

    # ── 月历：4 列 × 3 行实心小方块 ────────────────────────────────────────
    cols, rows = 3, 3
    gw = body * 0.66
    cell = gw / (cols + (cols - 1) * CELL_GAP_RATIO)
    gap = cell * CELL_GAP_RATIO
    gh = rows * cell + (rows - 1) * gap
    gx = (W - gw) / 2
    gy = (W - gh) / 2 + body * 0.012
    cell_r = int(cell * 0.26)

    grid = Image.new("RGBA", (W, W), (0, 0, 0, 0))
    gd = ImageDraw.Draw(grid)
    for r in range(rows):
        for c in range(cols):
            x = gx + c * (cell + gap)
            y = gy + r * (cell + gap)
            gd.rounded_rectangle([x, y, x + cell, y + cell], radius=cell_r,
                                 fill=INK + (CELL_ALPHA,))
    canvas.alpha_composite(grid)

    # ── 被拾起的那一格 ────────────────────────────────────────────────────
    focus_col, focus_row = 1, 1
    cx = gx + focus_col * (cell + gap) + cell * 0.5
    cy = gy + focus_row * (cell + gap) + cell * 0.5

    tile = int(cell * 1.75)                # 比一格大 3/4：既看得出「被拈起来」，又不压垮四周
    pad = int(tile * 1.0)
    box = tile + pad * 2
    tile_r = int(tile * 0.22)

    # 投影：先铺一层纯色，再用同形状的蒙版调 alpha，比 PIL 内置 shadow 干净
    sh_mask = Image.new("L", (box, box), 0)
    ImageDraw.Draw(sh_mask).rounded_rectangle(
        [pad, pad, pad + tile, pad + tile], radius=tile_r, fill=255,
    )
    shadow = Image.new("RGBA", (box, box), (96, 44, 22, 255))
    shadow.putalpha(sh_mask.point(lambda v: v * 110 // 255))
    shadow = shadow.filter(ImageFilter.GaussianBlur(int(body * 0.011)))

    chip_mask = Image.new("L", (box, box), 0)
    ImageDraw.Draw(chip_mask).rounded_rectangle(
        [pad, pad, pad + tile, pad + tile], radius=tile_r, fill=255,
    )
    chip = Image.new("RGBA", (box, box), (0, 0, 0, 0))
    chip.paste(
        vertical_gradient(tile, CLAY_LIGHT, CLAY_DARK).convert("RGBA"),
        (pad, pad), chip_mask.crop((pad, pad, pad + tile, pad + tile)),
    )

    stack = Image.alpha_composite(shadow, chip).rotate(
        -12, resample=Image.BICUBIC, center=(box / 2, box / 2),
    )
    canvas.alpha_composite(
        stack,
        (int(cx - box / 2 - body * 0.008), int(cy - box / 2 - body * 0.012)),
    )

    out = canvas.resize((S, S), Image.LANCZOS)
    out.save(OUT)
    print(f"{OUT}  {S}x{S}")


if __name__ == "__main__":
    main()
