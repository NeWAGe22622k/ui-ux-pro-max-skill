"""Lagos set dressing: procedural weathered materials, Lagos shop-houses / zinc roofs / unfinished frames /
container shops, painted kerbs, drainage gutters, umbrellas + trader tables, electricity poles with sagging
wires, palms, bus shelter, litter, sand drifts, potholes, wheel-track wear.  No text, logos or plates anywhere.
"""
import math
import random
import bmesh
import bpy
from mathutils import Matrix, Vector

import config as K
from util import MeshBuilder, mat, link, bevel

COL = "ENV"
L = K.LAGOS
_PM = {}

# ============================================================================ procedural materials


def _nodes(m):
    nt = m.node_tree
    return nt, nt.nodes["Principled BSDF"]


def pmat(name, color, kind="wall", rough=0.85, metallic=0.0, dark=0.55, scale=1.2, alt=None):
    """Weathered procedural material. kind: wall | asphalt | concrete | zinc | shutter | sand | water."""
    if name in _PM and _PM[name].name in bpy.data.materials:
        return _PM[name]
    m = mat(name, color, rough=rough, metallic=metallic)
    nt, b = _nodes(m)
    tc = nt.nodes.new("ShaderNodeTexCoord")
    noise = nt.nodes.new("ShaderNodeTexNoise")
    noise.inputs["Scale"].default_value = scale
    noise.inputs["Detail"].default_value = 8.0
    noise.inputs["Roughness"].default_value = 0.6
    nt.links.new(tc.outputs["Object"], noise.inputs["Vector"])
    ramp = nt.nodes.new("ShaderNodeValToRGB")
    ramp.color_ramp.elements[0].position = 0.35
    ramp.color_ramp.elements[1].position = 0.75
    nt.links.new(noise.outputs["Fac"], ramp.inputs["Fac"])
    c2 = alt if alt is not None else tuple(v * dark for v in color)
    ramp.color_ramp.elements[0].color = (*c2, 1)
    ramp.color_ramp.elements[1].color = (*color, 1)
    col_out = ramp.outputs["Color"]
    if kind in ("wall", "concrete"):
        # dirt splash / rising damp near the ground + vertical rain streaks
        sep = nt.nodes.new("ShaderNodeSeparateXYZ")
        nt.links.new(tc.outputs["Object"], sep.inputs["Vector"])
        mr = nt.nodes.new("ShaderNodeMapRange")
        mr.inputs["From Min"].default_value = 0.0
        mr.inputs["From Max"].default_value = 1.4
        mr.inputs["To Min"].default_value = 0.55
        mr.inputs["To Max"].default_value = 0.0
        nt.links.new(sep.outputs["Z"], mr.inputs["Value"])
        wave = nt.nodes.new("ShaderNodeTexNoise")
        wave.inputs["Scale"].default_value = 3.0
        mapping = nt.nodes.new("ShaderNodeMapping")
        mapping.inputs["Scale"].default_value = (6.0, 6.0, 0.15)
        nt.links.new(tc.outputs["Object"], mapping.inputs["Vector"])
        nt.links.new(mapping.outputs["Vector"], wave.inputs["Vector"])
        streak = nt.nodes.new("ShaderNodeMapRange")
        streak.inputs["From Min"].default_value = 0.55
        streak.inputs["From Max"].default_value = 0.8
        streak.inputs["To Min"].default_value = 0.0
        streak.inputs["To Max"].default_value = 0.3
        nt.links.new(wave.outputs["Fac"], streak.inputs["Value"])
        add = nt.nodes.new("ShaderNodeMath")
        add.operation = "ADD"
        add.use_clamp = True
        nt.links.new(mr.outputs[0], add.inputs[0])
        nt.links.new(streak.outputs[0], add.inputs[1])
        mix = nt.nodes.new("ShaderNodeMix")
        mix.data_type = "RGBA"
        mix.blend_type = "MULTIPLY"
        mix.inputs["B"].default_value = (0.35, 0.28, 0.2, 1)
        nt.links.new(add.outputs[0], mix.inputs["Factor"])
        nt.links.new(col_out, mix.inputs["A"])
        col_out = mix.outputs["Result"]
    if kind in ("zinc", "shutter"):
        wave = nt.nodes.new("ShaderNodeTexWave")
        wave.wave_type = "BANDS"
        wave.bands_direction = "X" if kind == "zinc" else "Z"
        wave.inputs["Scale"].default_value = 9.0 if kind == "zinc" else 14.0
        nt.links.new(tc.outputs["Object"], wave.inputs["Vector"])
        bump = nt.nodes.new("ShaderNodeBump")
        bump.inputs["Strength"].default_value = 0.6
        nt.links.new(wave.outputs["Fac"], bump.inputs["Height"])
        nt.links.new(bump.outputs["Normal"], b.inputs["Normal"])
    if kind == "asphalt":
        big = nt.nodes.new("ShaderNodeTexNoise")
        big.inputs["Scale"].default_value = 0.06
        nt.links.new(tc.outputs["Object"], big.inputs["Vector"])
        mix = nt.nodes.new("ShaderNodeMix")
        mix.data_type = "RGBA"
        mix.blend_type = "MULTIPLY"
        mix.inputs["B"].default_value = (0.75, 0.75, 0.75, 1)
        nt.links.new(big.outputs["Fac"], mix.inputs["Factor"])
        nt.links.new(col_out, mix.inputs["A"])
        col_out = mix.outputs["Result"]
        rr = nt.nodes.new("ShaderNodeMapRange")
        rr.inputs["To Min"].default_value = 0.65
        rr.inputs["To Max"].default_value = 0.95
        nt.links.new(noise.outputs["Fac"], rr.inputs["Value"])
        nt.links.new(rr.outputs[0], b.inputs["Roughness"])
    nt.links.new(col_out, b.inputs["Base Color"])
    _PM[name] = m
    return m


def materials():
    c = K.C
    return dict(
        asphalt=pmat("ENV_asphalt_worn", c["asphalt"], "asphalt", scale=3.0, dark=0.7),
        patch=pmat("ENV_asphalt_patch", (0.028, 0.028, 0.03), "asphalt", scale=4.0, dark=0.8),
        track=mat("ENV_wheel_track", (0.032, 0.032, 0.034), rough=0.7),
        concrete=pmat("ENV_concrete_weathered", c["concrete"], "concrete", scale=1.5, dark=0.6),
        sand=pmat("ENV_sand", (0.42, 0.33, 0.22), "sand", rough=1.0, scale=2.0, dark=0.75),
        mud=mat("ENV_pothole_water", (0.06, 0.05, 0.035), rough=0.08),
        gutter=mat("ENV_gutter_sludge", (0.05, 0.06, 0.04), rough=0.4),
        kerb_light=pmat("ENV_kerb_white", L["kerb_colours"][0], "concrete", rough=0.7, scale=3.0, dark=0.75),
        kerb_dark=mat("ENV_kerb_black", L["kerb_colours"][1], rough=0.7),
        zinc=pmat("ENV_zinc_rusty", (0.33, 0.32, 0.31), "zinc", rough=0.55, metallic=0.4, scale=1.5,
                  alt=(0.30, 0.12, 0.04)),
        zinc_new=pmat("ENV_zinc_grey", (0.42, 0.43, 0.44), "zinc", rough=0.4, metallic=0.6, scale=1.0, dark=0.8),
        bars=mat("ENV_burglar_bars", (0.05, 0.05, 0.05), metallic=0.6, rough=0.5),
        window=mat("ENV_window_dark", (0.03, 0.035, 0.04), metallic=0.1, rough=0.12),
        interior=mat("ENV_shop_interior", (0.025, 0.022, 0.02), rough=0.9),
        tank=mat("ENV_water_tank", (0.02, 0.02, 0.022), rough=0.45),
        pole=pmat("ENV_pole_concrete", (0.45, 0.44, 0.42), "concrete", scale=2.0),
        wire=mat("ENV_wire", (0.01, 0.01, 0.01), rough=0.5),
        insulator=mat("ENV_insulator", (0.6, 0.6, 0.58), rough=0.3),
        trunk=pmat("ENV_palm_trunk", (0.22, 0.18, 0.14), "concrete", scale=6.0, dark=0.6),
        frond=mat("ENV_palm_frond", (0.07, 0.18, 0.04), rough=0.7),
        shrub=pmat("ENV_shrub", (0.10, 0.20, 0.05), "wall", scale=4.0, dark=0.6),
        cloth=[mat(f"ENV_cloth_{i}", col, rough=0.8) for i, col in enumerate(L["cloth"])],
        goods=[mat(f"ENV_goods_{i}", col, rough=0.6) for i, col in enumerate(L["goods"])],
        litter_w=mat("ENV_litter_white", (0.8, 0.8, 0.78), rough=0.4),
        litter_b=mat("ENV_litter_blue", (0.15, 0.35, 0.7), rough=0.2),
        gen=mat("ENV_generator", (0.75, 0.55, 0.05), rough=0.5),
        steel=mat("ENV_steel", K.C["steel"], metallic=0.7, rough=0.45),
    )

# ============================================================================ helpers


def _bars_and_frames(mb, M, xa, xb, z0, z1, y, sgn, nbars=5):
    for i in range(nbars + 1):
        x = xa + (xb - xa) * i / nbars
        mb.box(M["bars"], (x, y + sgn * 0.04, (z0 + z1) / 2), (0.02, 0.02, z1 - z0))
    mb.box(M["bars"], ((xa + xb) / 2, y + sgn * 0.04, (z0 + z1) / 2), (xb - xa, 0.02, 0.02))


def _water_tank(mb, M, x, y, z):
    mb.cyl(M["tank"], (x, y, z + 0.75), 0.6, 1.3, segs=20)
    mb.cyl(M["tank"], (x, y, z + 1.45), 0.25, 0.12, segs=16)
    for dx, dy in ((0.45, 0.45), (-0.45, 0.45), (0.45, -0.45), (-0.45, -0.45)):
        mb.box(M["steel"], (x + dx, y + dy, z + 0.05), (0.06, 0.06, 0.1))


def building(i, rnd, M, x, y_front, sgn, w, d, floors, style):
    """sgn = +1 if the facade faces +Y (right side of the road), -1 if it faces -Y."""
    name = f"ENV_building_{i:03d}"
    fh = 3.2
    h = floors * fh
    col = rnd.choice(L["walls"]) if style != "unfinished" else (0.42, 0.41, 0.39)
    wall = pmat(f"ENV_wall_{L['walls'].index(col) if col in L['walls'] else 'raw'}", col, "wall",
                scale=1.0, dark=0.62)
    mb = MeshBuilder()
    yb = y_front - sgn * d
    yc = (y_front + yb) / 2
    if style == "unfinished":
        # bare concrete frame: columns, slabs, a few block-wall infills, rebar on top
        nc = max(2, int(w / 4))
        for k in range(nc + 1):
            xx = x - w / 2 + k * w / nc
            for yy in (y_front - sgn * 0.2, yb + sgn * 0.2):
                mb.box(wall, (xx, yy, h / 2), (0.3, 0.3, h))
                for r in range(4):
                    mb.box(M["bars"], (xx + (r % 2 - 0.5) * 0.15, yy + (r // 2 - 0.5) * 0.15, h + 0.5), (0.016, 0.016, 1.0))
        for fl in range(1, floors + 1):
            mb.box(wall, (x, yc, fl * fh - 0.1), (w + 0.2, d, 0.22))
        mb.box(wall, (x - w / 4, yb + sgn * 0.2, fh / 2), (w / 2, 0.22, fh))
        ob = mb.to_object(name, COL)
        return ob
    # main volume with the road facade replaced by a window / shop grid (insets)
    bm = mb.bm
    mi_wall = mb._mi(wall)
    mi_win = mb._mi(M["window"])
    mi_shop = mb._mi(M["interior"])
    mi_shut = mb._mi(rnd.choice(M["shutters"]))
    bmesh.ops.create_cube(bm, size=1.0, matrix=Matrix.Translation((x, yc, h / 2)) @ Matrix.Diagonal((w, d, h, 1)))
    bm.faces.ensure_lookup_table()
    front = [f for f in bm.faces if f.normal.y * sgn > 0.9][0]
    bm.faces.remove(front)
    nx = max(2, int(w / 3.2))
    gx = w / nx
    grid = []
    for iz in range(floors):
        for ix in range(nx):
            xa = x - w / 2 + ix * gx
            za = iz * fh
            vs = [bm.verts.new((xa, y_front, za)), bm.verts.new((xa + gx, y_front, za)),
                  bm.verts.new((xa + gx, y_front, za + fh)), bm.verts.new((xa, y_front, za + fh))]
            f = bm.faces.new(vs if sgn > 0 else list(reversed(vs)))
            grid.append((f, iz, ix))
    bmesh.ops.remove_doubles(bm, verts=bm.verts, dist=1e-4)
    for f in bm.faces:
        f.material_index = mi_wall
    bm.faces.ensure_lookup_table()
    cells = [f for f in bm.faces if abs(f.calc_center_median().y - y_front) < 1e-3]
    ground = [f for f in cells if f.calc_center_median().z < fh]
    upper = [f for f in cells if f.calc_center_median().z >= fh]
    shop = style == "shop"
    if shop:
        r = bmesh.ops.inset_individual(bm, faces=ground, thickness=0.25, depth=-0.6)
        for f in ground:
            f.material_index = mi_shut if rnd.random() < 0.45 else mi_shop
    else:
        g2 = [f for f in ground if rnd.random() < 0.7]
        bmesh.ops.inset_individual(bm, faces=g2, thickness=0.7, depth=-0.15)
        for f in g2:
            f.material_index = mi_win
    bmesh.ops.inset_individual(bm, faces=upper, thickness=0.75, depth=-0.18)
    for f in upper:
        f.material_index = mi_win
    # extras on the same mesh
    win_boxes = []
    for f in upper:
        c = f.calc_center_median()
        win_boxes.append(c)
    for c in win_boxes:
        _bars_and_frames(mb, M, c.x - gx / 2 + 0.75, c.x + gx / 2 - 0.75, c.z - fh / 2 + 0.75, c.z + fh / 2 - 0.75,
                         y_front, sgn)
        if rnd.random() < 0.12:   # split AC unit
            mb.box(M["insulator"], (c.x + 0.6, y_front + sgn * 0.2, c.z - 0.9), (0.8, 0.3, 0.5))
    if shop:
        zinc = M["zinc"] if rnd.random() < 0.7 else M["zinc_new"]
        mb.box(zinc, (x, y_front + sgn * 0.85, fh - 0.15), (w, 1.8, 0.05),
               rot=Matrix.Rotation(math.radians(-10 * sgn), 3, "X"))
        for k in range(nx + 1):
            mb.box(M["steel"], (x - w / 2 + k * gx, y_front + sgn * 1.65, (fh - 0.4) / 2), (0.06, 0.06, fh - 0.4))
        if rnd.random() < 0.6:   # plain signboard panel (no text)
            mb.box(rnd.choice(M["cloth"]), (x, y_front + sgn * 0.08, fh + 0.5), (w * 0.7, 0.06, 0.8))
    if floors >= 2 and rnd.random() < 0.55:   # balcony with railing on floor 2
        zb = fh
        mb.box(wall, (x, y_front + sgn * 0.55, zb), (w * 0.8, 1.1, 0.18))
        mb.box(M["bars"], (x, y_front + sgn * 1.08, zb + 0.95), (w * 0.8, 0.04, 0.05))
        for k in range(int(w * 0.8 / 0.15)):
            xx = x - w * 0.4 + k * 0.15
            mb.box(M["bars"], (xx, y_front + sgn * 1.08, zb + 0.5), (0.02, 0.02, 0.9))
    roof = style == "house" or (style == "shop" and rnd.random() < 0.35)
    if roof:
        # hipped corrugated-zinc roof
        zr = h
        rh = min(w, d) * 0.28
        mb.cyl(M["zinc"] if rnd.random() < 0.75 else M["zinc_new"], (x, yc, zr + rh / 2), 1.0, rh, segs=4, r2=0.02,
               rot=Matrix.Rotation(math.radians(45), 3, "Z") @ Matrix.Diagonal((w * 0.78, d * 0.78, 1.0)), smooth=False)
    else:
        for ys in (y_front - sgn * 0.1, yb + sgn * 0.1):
            mb.box(wall, (x, ys, h + 0.45), (w, 0.2, 0.9))
        for xs in (x - w / 2 + 0.1, x + w / 2 - 0.1):
            mb.box(wall, (xs, yc, h + 0.45), (0.2, d, 0.9))
        for k in range(rnd.randint(1, 3)):
            _water_tank(mb, M, x - w / 3 + k * 1.6, yc + sgn * rnd.uniform(-1, 1), h)
        if rnd.random() < 0.5:
            mb.box(wall, (x + w / 4, yb + sgn * 1.8, h + 1.3), (3.0, 3.0, 2.6))
        if rnd.random() < 0.4:   # satellite dish
            mb.sphere(M["insulator"], (x - w / 4, y_front - sgn * 1.0, h + 1.2), 0.45, scale=(1, 0.35, 1), segs=12, rings=6)
    ob = mb.to_object(name, COL)
    return ob


def container_shop(i, rnd, M, x, y_front, sgn):
    col = rnd.choice(L["containers"])
    cm = pmat(f"ENV_container_{L['containers'].index(col)}", col, "shutter", rough=0.5, metallic=0.4, scale=1.0, dark=0.7)
    mb = MeshBuilder()
    w, d, h = 6.06, 2.44, 2.59
    yc = y_front - sgn * d / 2
    mb.box(cm, (x, yc, h / 2 + 0.15), (w, d, h))
    mb.box(M["interior"], (x, y_front + sgn * 0.01, h / 2 + 0.15), (w - 0.6, 0.02, h - 0.4))
    for s in (-1, 1):   # open doors folded back
        mb.box(cm, (x + s * (w / 2 + 0.6), y_front + sgn * 0.6, h / 2 + 0.15), (1.2, 0.05, h - 0.1),
               rot=Matrix.Rotation(math.radians(s * sgn * 60), 3, "Z"))
    for k in range(6):
        mb.box(rnd.choice(M["goods"]), (x - 2.2 + k * 0.85, y_front - sgn * 0.5, 0.15 + 0.35 + 0.3 * (k % 2)),
               (0.6, 0.5, 0.6 + 0.6 * (k % 2)))
    mb.box(M["zinc"], (x, y_front + sgn * 0.9, h + 0.2), (w + 0.4, 1.9, 0.04),
           rot=Matrix.Rotation(math.radians(-8 * sgn), 3, "X"))
    return mb.to_object(f"ENV_container_shop_{i:03d}", COL)


def umbrella_stall(mb, M, rnd, x, y):
    r = rnd.uniform(1.0, 1.35)
    hgt = 2.2
    c1, c2 = rnd.sample(M["cloth"], 2)
    mb.cyl(M["steel"], (x, y, hgt / 2), 0.025, hgt, segs=8)
    segs = 8
    for k in range(segs):
        a0, a1 = 2 * math.pi * k / segs, 2 * math.pi * (k + 1) / segs
        mb.poly(c1 if k % 2 == 0 else c2, [(x, y, hgt + 0.35), (x + r * math.cos(a0), y + r * math.sin(a0), hgt - 0.05),
                                           (x + r * math.cos(a1), y + r * math.sin(a1), hgt - 0.05)])
    tc = rnd.choice(M["cloth"])
    mb.box(tc, (x + 0.3, y, 0.45 + K.PAVEMENT_H), (1.2, 0.75, 0.06))
    for lx in (-0.25, 0.85):
        for ly in (-0.3, 0.3):
            mb.box(M["steel"], (x + lx, y + ly, 0.22 + K.PAVEMENT_H), (0.04, 0.04, 0.44))
    for k in range(rnd.randint(3, 7)):
        gm = rnd.choice(M["goods"])
        px, py = x + 0.3 + rnd.uniform(-0.5, 0.5), y + rnd.uniform(-0.3, 0.3)
        if rnd.random() < 0.5:
            mb.sphere(gm, (px, py, 0.55 + K.PAVEMENT_H), 0.07, segs=8, rings=5)
        else:
            mb.box(gm, (px, py, 0.55 + K.PAVEMENT_H), (0.18, 0.14, 0.14))
    if rnd.random() < 0.4:   # stool
        mb.cyl(rnd.choice(M["goods"]), (x - 0.4, y - 0.5, 0.25 + K.PAVEMENT_H), 0.18, 0.04, segs=12)
        mb.cyl(M["steel"], (x - 0.4, y - 0.5, 0.12 + K.PAVEMENT_H), 0.02, 0.24, segs=6)


def palm(mb, M, rnd, x, y):
    h = rnd.uniform(6.5, 10.0)
    lean = Vector((rnd.uniform(-0.6, 0.6), rnd.uniform(-0.6, 0.6), 0))
    pts = [Vector((x, y, 0)) + lean * (t * t) + Vector((0, 0, h * t)) for t in (0, 0.25, 0.5, 0.75, 1.0)]
    for a, b in zip(pts, pts[1:]):
        mb.capsule(M["trunk"], a, b, 0.2 - 0.03 * (a.z / h), segs=10, rings=2)
    top = pts[-1]
    n = 14
    for k in range(n):
        ang = 2 * math.pi * k / n + rnd.uniform(-0.15, 0.15)
        ln = rnd.uniform(3.0, 4.2)
        droop = rnd.uniform(0.3, 1.0)
        d = Vector((math.cos(ang), math.sin(ang), 0))
        side = Vector((-d.y, d.x, 0))
        segs = 6
        prev = None
        for s in range(segs + 1):
            t = s / segs
            p = top + d * ln * t + Vector((0, 0, 0.9 * t - droop * 2.2 * t * t))
            wdt = 0.55 * math.sin(math.pi * min(1, t * 1.1 + 0.05))
            cur = (p - side * wdt, p + side * wdt)
            if prev is not None:
                mb.poly(M["frond"], [prev[0], prev[1], cur[1], cur[0]])
            prev = cur


def shrub(mb, M, rnd, x, y):
    for k in range(rnd.randint(2, 4)):
        mb.sphere(M["shrub"], (x + rnd.uniform(-0.6, 0.6), y + rnd.uniform(-0.4, 0.4), 0.45 + K.PAVEMENT_H),
                  rnd.uniform(0.45, 0.8), scale=(1, 1, 0.8), segs=10, rings=7)

# ============================================================================ main


def build():
    rnd = random.Random(K.SEED + 101)
    M = materials()
    M["shutters"] = [pmat(f"ENV_shutter_{i}", col, "shutter", rough=0.45, metallic=0.5, scale=1.0, dark=0.75)
                     for i, col in enumerate(L["shutters"])]
    _road_wear(M, rnd)
    _kerbs_and_gutters(M)
    _frontage(M, rnd)
    _street_furniture(M, rnd)
    _power_lines(M)
    _bus_shelter(M)


def _road_wear(M, rnd):
    x0, x1 = K.ROAD_X
    mb = MeshBuilder()
    lanes = list(K.LANES.values()) + list(K.OPP_LANES)
    for y in lanes:
        for s in (-0.8, 0.8):
            mb.box(M["track"], ((x0 + x1) / 2, y + s, 0.0015), (x1 - x0, 0.5, 0.003))
    mb.to_object("ENV_wheel_tracks", COL)
    mb = MeshBuilder()
    for (x, y, w, d) in L["patches"]:
        mb.box(M["patch"], (x, y, 0.004), (w, d, 0.006))
    mb.to_object("ENV_road_patches", COL)
    mb = MeshBuilder()
    for (x, y, r) in L["potholes"]:
        mb.cyl(M["mud"], (x, y, 0.005), r, 0.008, segs=18)
        mb.cyl(M["sand"], (x + 0.1, y, 0.004), r * 1.35, 0.006, segs=18)
    # sand drifts along the shoulder edge and the median base
    my = K.MEDIAN["y"]
    for y, w in ((K.SHOULDER[0] + 0.45, 0.9), (my - 0.55, 0.45), (my + 0.55, 0.45), (K.OPP_SHOULDER[1] - 0.4, 0.8)):
        x = x0
        while x < x1:
            ln = rnd.uniform(6, 22)
            if rnd.random() < 0.75:
                mb.box(M["sand"], (x + ln / 2, y + rnd.uniform(-0.1, 0.1), 0.006), (ln, w * rnd.uniform(0.6, 1.1), 0.01))
            x += ln + rnd.uniform(1, 8)
    mb.to_object("ENV_sand_potholes", COL)


def _kerbs_and_gutters(M):
    x0, x1 = K.ROAD_X
    mb = MeshBuilder()
    blk = L["kerb_block"]
    n = int((x1 - x0) / blk)
    # painted kerbs (white / black): road-side pavement kerbs and the median base band
    for i in range(n):
        x = x0 + (i + 0.5) * blk
        m = M["kerb_light"] if i % 2 == 0 else M["kerb_dark"]
        mb.box(m, (x, K.PAVEMENT_RIGHT[1], 0.11), (blk, 0.22, 0.22))
        mb.box(m, (x, K.PAVEMENT_LEFT[0], 0.11), (blk, 0.22, 0.22))
        for s in (1, -1):
            mb.box(m, (x, K.MEDIAN["y"] + s * (K.MEDIAN["width"] / 2 + 0.003), 0.13), (blk, 0.006, 0.26))
    mb.to_object("ENV_painted_kerbs", COL)
    # open drainage gutters between kerb and pavement (with the odd cover slab)
    mb = MeshBuilder()
    for y0, sgn in ((K.PAVEMENT_RIGHT[1], -1), (K.PAVEMENT_LEFT[0], 1)):
        yg = y0 + sgn * (0.11 + L["gutter_w"] / 2)
        mb.box(M["gutter"], ((x0 + x1) / 2, yg, 0.02), (x1 - x0, L["gutter_w"], 0.02))
        mb.box(M["concrete"], ((x0 + x1) / 2, yg + sgn * (L["gutter_w"] / 2 + 0.08), 0.13), (x1 - x0, 0.16, 0.26))
        x = x0 + 3
        k = 0
        while x < x1:
            if k % 5 == 0:
                mb.box(M["concrete"], (x, yg, 0.2), (1.2, L["gutter_w"] + 0.1, 0.1))
            x += 4.0
            k += 1
    mb.to_object("ENV_gutters", COL)


def _frontage(M, rnd):
    i = 0
    for side, y_front, sgn in ((-1, K.PAVEMENT_RIGHT[0] - 0.5, 1), (1, K.PAVEMENT_LEFT[1] + 0.5, -1)):
        x = K.ROAD_X[0]
        while x < K.SKYLINE_X0 - 10:
            near_bridge = K.BRIDGE_X - 8 < x < K.BRIDGE_X + 22
            r = rnd.random()
            if near_bridge:
                x += 30
                continue
            if r < 0.14:
                n_c = rnd.choice([1, 2])
                for k in range(n_c):
                    container_shop(i, rnd, M, x + 3.4 + k * 6.4, y_front, sgn)
                    i += 1
                x += n_c * 6.4 + rnd.uniform(1.5, 4)
                continue
            w = rnd.uniform(9, 22)
            d = rnd.uniform(9, 14)
            if r < 0.24:
                style, floors = "unfinished", rnd.choice([2, 3])
            elif r < 0.45:
                style, floors = "house", rnd.choice([1, 2, 2])
            else:
                style, floors = "shop", rnd.choice([2, 2, 3, 3, 4])
            setback = 0.0 if style == "shop" else rnd.uniform(0.0, 2.5)
            building(i, rnd, M, x + w / 2, y_front - sgn * setback, sgn, w, d, floors, style)
            i += 1
            if style == "house" and rnd.random() < 0.7:   # compound wall with a gate
                wall = pmat("ENV_fence_wall", rnd.choice(L["walls"]), "wall", scale=1.0)
                mb = MeshBuilder()
                yw = y_front + sgn * 0.0
                mb.box(wall, (x + w / 4, yw, 1.1), (w / 2 - 1.8, 0.22, 2.2))
                mb.box(wall, (x + 3 * w / 4 + 0.9, yw, 1.1), (w / 2 - 1.8, 0.22, 2.2))
                mb.box(rnd.choice(M["shutters"]), (x + w / 2, yw, 1.1), (3.4, 0.08, 2.1))
                mb.to_object(f"ENV_compound_wall_{i:03d}", COL)
            x += w + rnd.uniform(0.6, 3.5)


def _street_furniture(M, rnd):
    x0, x1 = K.ROAD_X
    mb = MeshBuilder()
    # umbrella stalls on both pavements (busier near the action, x -120..140)
    for side_y in ((K.PAVEMENT_RIGHT[0] + K.PAVEMENT_RIGHT[1]) / 2 - 0.3, (K.PAVEMENT_LEFT[0] + K.PAVEMENT_LEFT[1]) / 2 + 0.3):
        x = -160.0
        while x < 200:
            clear_cam = side_y < 0 and L["stall_free_x"][0] < x < L["stall_free_x"][1]   # S08 camera spot
            if abs(x - K.BRIDGE_X) > 10 and abs(x - L["bus_shelter_x"]) > 6 and not clear_cam:
                umbrella_stall(mb, M, rnd, x, side_y + rnd.uniform(-0.3, 0.3))
            x += rnd.uniform(5, 16) if -60 < x < 120 else rnd.uniform(14, 30)
    mb.to_object("ENV_umbrella_stalls", COL)
    mb = MeshBuilder()
    for k in range(8):   # small generators by shops
        x = rnd.uniform(-120, 160)
        y = K.PAVEMENT_RIGHT[0] + 0.4 if k % 2 == 0 else K.PAVEMENT_LEFT[1] - 0.4
        mb.box(M["gen"], (x, y, 0.35 + K.PAVEMENT_H), (0.75, 0.55, 0.55))
        mb.box(M["steel"], (x, y, 0.66 + K.PAVEMENT_H), (0.8, 0.6, 0.04))
    # litter: sachet bags, bottles, cans along kerbs and gutters
    for k in range(160):
        x = rnd.uniform(-150, 200)
        y = rnd.choice([K.PAVEMENT_RIGHT[1] + rnd.uniform(0.05, 0.6), K.SHOULDER[0] + rnd.uniform(0.0, 1.2),
                        K.PAVEMENT_LEFT[0] - rnd.uniform(0.05, 0.6), K.MEDIAN["y"] - 0.4 - rnd.random() * 0.3,
                        K.MEDIAN["y"] + 0.4 + rnd.random() * 0.3])
        if rnd.random() < 0.6:
            mb.box(M["litter_w"], (x, y, 0.01), (0.12, 0.18, 0.012), rot=Matrix.Rotation(rnd.uniform(0, 3), 3, "Z"))
        else:
            mb.cyl(M["litter_b"], (x, y, 0.035), 0.033, 0.2, axis="X", segs=8,
                   rot=Matrix.Rotation(rnd.uniform(0, 3), 3, "Z"))
    mb.to_object("ENV_litter_generators", COL)
    mb = MeshBuilder()
    for (x, y) in L["palms"]:
        palm(mb, M, rnd, x, y)
    mb.to_object("ENV_palms", COL)
    mb = MeshBuilder()
    for k in range(40):
        x = rnd.uniform(-200, 220)
        y = rnd.choice([K.PAVEMENT_RIGHT[0] + 0.3, K.PAVEMENT_LEFT[1] - 0.3])
        shrub(mb, M, rnd, x, y)
    mb.to_object("ENV_shrubs", COL)


def _power_lines(M):
    x0, x1 = K.ROAD_X
    sp = L["pole_spacing"]
    for side, y in (("R", L["pole_y_right"]), ("L", L["pole_y_left"])):
        mb = MeshBuilder()
        xs = []
        x = x0 + 8
        while x < x1 - 5:
            if abs(x - K.BRIDGE_X) < 6:
                x += 8
            xs.append(x)
            hgt = L["pole_h"]
            mb.cyl(M["pole"], (x, y, hgt / 2), 0.16, hgt, segs=10, r2=0.1)
            mb.box(M["steel"], (x, y, hgt - 0.4), (0.12, 1.8, 0.12))
            for oy in (-0.75, 0.0, 0.75):
                mb.cyl(M["insulator"], (x, y + oy, hgt - 0.25), 0.05, 0.2, segs=8)
            if len(xs) % 4 == 2:   # pole-mounted transformer
                mb.box(M["steel"], (x - 0.45, y, hgt - 2.4), (0.6, 0.8, 1.0))
                mb.cyl(M["steel"], (x - 0.45, y, hgt - 1.75), 0.08, 0.3, segs=8)
            x += sp
        ob = mb.to_object(f"ENV_power_poles_{side}", COL)
        # sagging wires (curve object)
        cu = bpy.data.curves.new(f"ENV_power_wires_{side}", "CURVE")
        cu.dimensions = "3D"
        cu.bevel_depth = 0.012
        cu.bevel_resolution = 1
        for oy in (-0.75, 0.0, 0.75):
            for a, b in zip(xs, xs[1:]):
                spl = cu.splines.new("POLY")
                n = 10
                spl.points.add(n)
                for k in range(n + 1):
                    t = k / n
                    z = L["pole_h"] - 0.15 - L["sag"] * 4 * t * (1 - t)
                    spl.points[k].co = (a + (b - a) * t, y + oy, z, 1)
        wob = bpy.data.objects.new(f"ENV_power_wires_{side}", cu)
        cu.materials.append(M["wire"])
        link(wob, COL)


def _bus_shelter(M):
    x = L["bus_shelter_x"]
    y = (K.PAVEMENT_RIGHT[0] + K.PAVEMENT_RIGHT[1]) / 2
    mb = MeshBuilder()
    for dx in (-2.4, 2.4):
        for dy in (-0.9, 0.9):
            mb.box(M["steel"], (x + dx, y + dy, 1.4 + K.PAVEMENT_H), (0.08, 0.08, 2.8))
    mb.box(M["zinc_new"], (x, y, 2.85 + K.PAVEMENT_H), (5.4, 2.4, 0.08))
    mb.box(M["cloth"][2], (x, y - 0.95, 1.6 + K.PAVEMENT_H), (4.8, 0.05, 1.6))
    mb.box(M["steel"], (x, y - 0.6, 0.45 + K.PAVEMENT_H), (4.0, 0.45, 0.06))
    mb.to_object("ENV_bus_shelter", COL)
