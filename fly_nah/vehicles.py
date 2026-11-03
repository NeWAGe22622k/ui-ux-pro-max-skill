"""Vehicles: hero sedan, coupe, minibuses, kekes, generic cars, tanker, lorry.

Every car: origin at ground centre, faces +X, LEFT-HAND DRIVE (steering wheel on +Y side).
Children: _cabin (roof/pillars/lights), _glass, _interior, _steering, _wheel_XX.
"""
import math
import bpy
from mathutils import Matrix, Vector, Euler

import config as K
from util import (MeshBuilder, mat, glass_mat, bevel, apply_modifiers, boolean_diff, cutter_box, cutter_cyl,
                  link, empty)

COL = "CARS"


def _common():
    c = K.C
    return dict(
        glass=glass_mat("CAR_glass", c["glass_tint"]),
        rubber=mat("CAR_rubber", c["rubber"], rough=0.9),
        rim=mat("CAR_rim", c["rim"], metallic=0.9, rough=0.3),
        trim=mat("CAR_trim", c["trim"], rough=0.6),
        interior=mat("CAR_interior", c["interior"], rough=0.8),
        interior_l=mat("CAR_interior_light", c["interior_light"], rough=0.8),
        head=mat("CAR_headlight", c["headlight"], emission=c["headlight"], strength=1.0, rough=0.2),
        tail=mat("CAR_taillight", c["taillight"], emission=c["taillight"], strength=2.0, rough=0.3),
        brake=mat("CAR_brakelight", c["taillight"], emission=c["taillight"], strength=10.0, rough=0.3),
        stripe=mat("TRIM_stripe_black", c["stripe_black"], rough=0.5),
    )


def _belt_z(S, x):
    (ax, az), (dx, dz) = S["A"], S["D"]
    t = (x - ax) / (dx - ax) if dx != ax else 0
    return az + (dz - az) * min(1, max(0, t))


def _y_at(S, z, s):
    belt, top = S["belt"], S["B"][1]
    t = (z - belt) / (top - belt)
    return s * (S["hw_b"] + (S["hw_t"] - S["hw_b"]) * min(1, max(0, t)))


def _lower_body(name, S, paint, kind, door_open=False):
    mb = MeshBuilder()
    W = S["W"]
    mb.prism(paint, S["profile"], -W / 2, W / 2)
    ob = mb.to_object(name, COL)
    bevel(ob, width=0.05 if kind != "brt" else 0.08, segs=3, angle=30)
    apply_modifiers(ob)
    cutters = []
    floor = S["clear"] + 0.12
    xs = [p[0] for p in S["profile"]]
    x_front = S["A"][0] - 0.2 if kind not in ("minibus", "brt") else max(xs) - 0.25
    x_rear = S["D"][0] + 0.2 if kind not in ("minibus", "brt") else min(xs) + 0.12
    if kind in ("sedan", "hatch", "pickup"):
        x_rear = max(x_rear, S["seat_hip"][0] - 1.35)
    if "bed" in S:   # open pickup bed
        b0, b1 = S["bed"]
        cutters.append(cutter_box(((b0 + b1) / 2, 0, S["belt"] + 1.2), (b1 - b0, W - 0.16, 3.0 - 0.0)))
    cutters.append(cutter_box(((x_front + x_rear) / 2, 0, floor + 1.5), (x_front - x_rear, W - 0.14, 3.0)))
    r = S["wheel_r"]
    for ax in (S["axle_x"], -S["axle_x"]):
        for s in (1, -1):
            if kind == "coupe":
                inner = S["well_inner"]
                cutters.append(cutter_cyl((ax, s * (inner + 0.4), r), S["well_r"], 0.8))
            else:
                cutters.append(cutter_cyl((ax, s * (W / 2 - 0.15), r), r + 0.06, 0.42))
    if door_open:
        x0, x1 = S["door_x"]
        z0, z1 = S["door_z"]
        cutters.append(cutter_box(((x0 + x1) / 2, -W / 2, (z0 + z1) / 2 + 0.2), (x1 - x0, 0.4, z1 - z0 + 0.4)))
    boolean_diff(ob, cutters, _common()["interior"])
    for p in ob.data.polygons:
        p.use_smooth = False
    return ob


def _cabin(name, S, paint, M, cabin_mat=None, open_front_left=False, door_open=False, kind="sedan"):
    """Roof, pillars, mirrors, lights -> _cabin ; window panels -> _glass."""
    cm = cabin_mat or paint
    mb = MeshBuilder()
    g = MeshBuilder()
    W = S["W"]
    A, B, Cr, D = S["A"], S["B"], S["Cr"], S["D"]
    top = B[1]
    rt = S["roof_t"]
    hw_t, hw_b = S["hw_t"], S["hw_b"]
    # roof slab
    mb.prism(cm, [(B[0] + 0.04, top - rt), (Cr[0] - 0.04, top - rt), (Cr[0] - 0.04, top), (B[0] + 0.04, top)],
             -hw_t - 0.03, hw_t + 0.03)
    for s in (1, -1):
        mb.slab_between(cm, (A[0], s * hw_b, A[1]), (B[0], s * hw_t, top - rt / 2), 0.07, 0.07)
        mb.slab_between(cm, (Cr[0], s * hw_t, top - rt / 2), (D[0], s * hw_b, D[1]), 0.14 if kind != "coupe" else 0.06, 0.07)
        for xb in S["pillars_x"]:
            if door_open and s < 0 and S["door_x"][0] - 0.1 <= xb <= S["door_x"][1] + 0.1:
                continue
            zb = _belt_z(S, xb)
            mb.slab_between(cm, (xb, _y_at(S, zb, s), zb), (xb, s * hw_t, top - rt / 2), 0.08, 0.07)
    # windscreen + rear window
    g.poly(M["glass"], [(A[0], -hw_b, A[1]), (A[0], hw_b, A[1]), (B[0], hw_t, top - 0.02), (B[0], -hw_t, top - 0.02)])
    g.poly(M["glass"], [(D[0], hw_b, D[1]), (D[0], -hw_b, D[1]), (Cr[0], -hw_t, top - 0.02), (Cr[0], hw_t, top - 0.02)])
    # side windows between boundaries
    bounds = ["A"] + list(S["pillars_x"]) + ["D"]
    for s in (1, -1):
        for i in range(len(bounds) - 1):
            if open_front_left and s > 0 and i == 0:
                continue
            fb, rb = bounds[i], bounds[i + 1]
            if door_open and s < 0 and fb != "A" and rb != "D" and fb >= S["door_x"][1] - 0.1 and rb <= S["door_x"][0] + 0.1:
                continue
            def bx(b, low):
                if b == "A":
                    return A[0] if low else B[0]
                if b == "D":
                    return D[0] if low else Cr[0]
                return b
            xf0, xf1 = bx(fb, True), bx(fb, False)
            xr0, xr1 = bx(rb, True), bx(rb, False)
            zf, zr = _belt_z(S, xf0), _belt_z(S, xr0)
            zt = top - 0.03
            pts = [(xf0, _y_at(S, zf, s), zf), (xr0, _y_at(S, zr, s), zr), (xr1, s * hw_t, zt), (xf1, s * hw_t, zt)]
            g.poly(M["glass"], pts)
    # side mirrors
    for s in (1, -1):
        mb.box(paint, (A[0] - 0.12, s * (W / 2 + 0.07), S["belt"] + 0.1), (0.10, 0.16, 0.11))
    # head / tail lights (not for coupe, which has light bars)
    xs = [p[0] for p in S["profile"]]
    if kind != "coupe":
        zh = S["clear"] + (S["belt"] - S["clear"]) * 0.62
        for s in (1, -1):
            mb.box(M["head"], (max(xs) - 0.02, s * (W / 2 - 0.30), zh), (0.08, 0.34, 0.11))
            mb.box(M["brake"] if name == "CAR_minibus_ahead" else M["tail"],
                   (min(xs) + 0.02, s * (W / 2 - 0.22), zh + 0.05), (0.08, 0.26, 0.14))
    cab = mb.to_object(f"{name}_cabin", COL)
    bevel(cab, width=0.015, segs=2, angle=40)
    gl = g.to_object(f"{name}_glass", COL, recalc=False)
    return cab, gl


def _interior(name, S, M, kind="sedan"):
    mb = MeshBuilder()
    hx, hy, hz = S["seat_hip"]
    W = S["W"]
    seats = [(hx, hy), (hx, -hy)]
    for (x, y) in seats:
        mb.box(M["interior"], (x + 0.05, y, hz - 0.09), (0.50, 0.48, 0.12))
        rot = Matrix.Rotation(math.radians(-14), 3, "Y")
        mb.box(M["interior"], (x - 0.22, y, hz + 0.27), (0.12, 0.48, 0.66), rot=rot)
        mb.box(M["interior"], (x - 0.30, y, hz + 0.70), (0.10, 0.26, 0.18), rot=rot)
    if kind in ("sedan", "suv", "hatch", "minivan", "pickup"):
        rx = hx - 0.95
        mb.box(M["interior"], (rx + 0.05, 0, hz - 0.11), (0.48, W - 0.25, 0.12))
        mb.box(M["interior"], (rx - 0.22, 0, hz + 0.22), (0.12, W - 0.25, 0.56),
               rot=Matrix.Rotation(math.radians(-14), 3, "Y"))
    elif kind == "minibus":
        for i in range(3):
            rx = hx - 1.15 - i * 0.95
            mb.box(M["interior"], (rx, 0.1, hz - 0.12), (0.45, W - 0.6, 0.12))
            mb.box(M["interior"], (rx - 0.22, 0.1, hz + 0.2), (0.08, W - 0.6, 0.55))
    elif kind == "brt":
        for i in range(8):
            rx = hx - 1.6 - i * 1.15
            for y in (0.75, -0.75):
                mb.box(M["interior"], (rx, y, hz - 0.15), (0.45, 0.9, 0.12))
                mb.box(M["interior"], (rx - 0.22, y, hz + 0.2), (0.08, 0.9, 0.6))
    # dashboard
    x0, x1 = S["dash_x"]
    mb.box(M["interior"], ((x0 + x1) / 2, 0, S["dash_z"] - 0.13), (x1 - x0, W - 0.16, 0.26))
    ob = mb.to_object(f"{name}_interior", COL)
    # steering wheel (separate object for the LHD check)
    sw = MeshBuilder()
    wc = Vector((hx + 0.43, hy, hz + 0.45))
    tilt = Matrix.Rotation(math.radians(-65), 3, "Y")
    sw.torus(M["trim"], wc, 0.19, 0.018, rot=tilt)
    sw.box(M["trim"], wc, (0.03, 0.30, 0.04), rot=tilt)
    sw.cyl(M["trim"], wc + Vector((0.12, 0, -0.06)), 0.03, 0.26, axis="X", rot=Matrix.Rotation(math.radians(25), 3, "Y"))
    sob = sw.to_object(f"{name}_steering", COL)
    return ob, sob, wc


def _wheel(name, S, M, parent, loc, side):
    mb = MeshBuilder()
    r, w = S["wheel_r"], S["wheel_w"]
    mb.cyl(M["rubber"], (0, 0, 0), r, w, axis="Y", segs=28)
    mb.cyl(M["rim"], (0, side * 0.012, 0), r * 0.62, w + 0.01, axis="Y", segs=20)
    for i in range(5):
        a = 2 * math.pi * i / 5
        mb.box(M["trim"], (math.cos(a) * r * 0.33, side * (w / 2 + 0.012), math.sin(a) * r * 0.33),
               (r * 0.42, 0.012, 0.04), rot=Matrix.Rotation(-a, 3, "Y"))
    ob = mb.to_object(name, COL, parent=parent)
    ob.location = loc
    bevel(ob, width=0.02, segs=2, angle=40)
    return ob


def car(name, S, color, kind="sedan", metallic=0.4, rough=0.35, coat=0.6, open_front_left=False,
        door_open=False, cabin_color=None):
    M = _common()
    paint = mat(f"PAINT_{name}", color, metallic=metallic, rough=rough, coat=coat)
    cabin_mat = mat(f"PAINT_{name}_upper", cabin_color, metallic=0.2, rough=0.4, coat=0.4) if cabin_color else None
    root = _lower_body(name, S, paint, kind, door_open)
    cab, gl, = _cabin(name, S, paint, M, cabin_mat, open_front_left, door_open, kind)
    inte, steer, wc = _interior(name, S, M, kind)
    for ob in (cab, gl, inte, steer):
        ob.parent = root
    wheels = []
    for tag, ax in (("F", S["axle_x"]), ("R", -S["axle_x"])):
        for side_tag, s in (("L", 1), ("R", -1)):
            wn = f"{name}_wheel_{tag}{side_tag}"
            if kind == "coupe":
                piv = empty(f"EMP_coupe_wheel_{tag}{side_tag}", (ax, s * S["track"], S["wheel_r"]), parent=root)
                wheels.append(_wheel(wn, S, M, piv, (0, 0, 0), s))
            else:
                wheels.append(_wheel(wn, S, M, root, (ax, s * S["track"], S["wheel_r"]), s))
    root["kind"] = kind
    root["wheel_r"] = S["wheel_r"]
    root["L"] = S["L"]
    root["W"] = S["W"]
    return root


# ---------------------------------------------------------------------------- specific vehicles

def hero_sedan():
    S = K.SEDAN
    root = car("CAR_hero_sedan", S, K.C["sedan_silver"], metallic=0.9, rough=0.3, coat=0.3, open_front_left=True)
    M = _common()
    # interior rear-view mirror at windscreen top centre
    mp = Vector(K.MIRROR["pos"])
    u_cam = (Vector(K.MIRROR["aim_cam"]) - mp).normalized()
    u_rear = (Vector(K.MIRROR["aim_rear"]) - mp).normalized()
    n = (u_cam + u_rear).normalized()
    rot = n.to_track_quat("Z", "Y").to_matrix()  # local Z = mirror normal
    mirror_m = mat("CAR_mirror_surface", (0.9, 0.9, 0.9), metallic=1.0, rough=0.0)
    w, h = K.MIRROR["size"]
    mb = MeshBuilder()
    mb.box(M["trim"], mp - n * 0.015, (w + 0.02, h + 0.02, 0.025), rot=rot)
    corners = [rot @ Vector((sx * w / 2, sy * h / 2, 0)) + mp + n * 0.0005 for sx, sy in ((-1, -1), (1, -1), (1, 1), (-1, 1))]
    mb.poly(mirror_m, corners)
    mb.slab_between(M["trim"], mp - n * 0.02, (mp.x + 0.08, 0, K.SEDAN["B"][1] - 0.02), 0.02, 0.02)
    mob = mb.to_object("CAR_hero_sedan_rearview", COL, parent=root, recalc=False)
    # planar light probe aligned with the mirror (EEVEE planar reflection)
    pd = bpy.data.lightprobes.new("CAR_hero_sedan_mirror_probe", "PLANE")
    pob = bpy.data.objects.new("CAR_hero_sedan_mirror_probe", pd)
    link(pob, COL)
    pob.parent = root
    pob.matrix_world = Matrix.Translation(mp + n * 0.001) @ rot.to_4x4() @ Matrix.Diagonal((w / 2 + 0.01, h / 2 + 0.01, 1, 1))
    pd.influence_distance = 0.05
    # dust: slightly rougher paint is set in car(); the hero car's mirror normal is stored for cameras
    root["mirror_normal"] = list(n)
    return root


def coupe():
    S = K.COUPE
    root = car("CAR_coupe", S, K.C["coupe_black"], kind="coupe", metallic=0.3, rough=0.15, coat=1.0)
    pb = bpy.data.materials["PAINT_CAR_coupe"].node_tree.nodes["Principled BSDF"]
    pb.inputs["Specular IOR Level"].default_value = K.COUPE_PAINT["spec"]   # hood must not mirror the hazy sky white
    pb.inputs["Coat IOR"].default_value = 1.3
    pb.inputs["Coat Weight"].default_value = K.COUPE_PAINT["coat"]
    pb.inputs["Coat Roughness"].default_value = K.COUPE_PAINT["coat_rough"]
    pb.inputs["Roughness"].default_value = K.COUPE_PAINT["rough"]
    pb.inputs["Metallic"].default_value = K.COUPE_PAINT["metallic"]
    c = K.C
    bar = mat("CAR_coupe_lightbar", c["lightbar"], emission=c["lightbar"], strength=K.COUPE_KEYS["lightbar_base"])
    head = mat("CAR_coupe_headlight", c["headlight"], emission=c["headlight"], strength=0.6)
    tail = mat("CAR_coupe_tailbar", c["lightbar"], emission=c["lightbar"], strength=K.COUPE_KEYS["lightbar_base"])
    mb = MeshBuilder()
    W = S["W"]
    mb.box(bar, (2.335, 0, 0.605), (0.05, W * 0.88, 0.03))     # high on the nose (reads in mirrors)
    mb.box(tail, (-2.45, 0, 0.74), (0.04, W * 0.92, 0.035))
    for s in (1, -1):
        mb.box(head, (2.38, s * (W / 2 - 0.26), 0.55), (0.06, 0.32, 0.045))
    lights = mb.to_object("CAR_coupe_lights", COL, parent=root)
    # thrust vents under the body (the air jets that lift the car) + their glow
    ug = mat("FX_coupe_underglow", c["underglow"], emission=c["underglow"], strength=0.0)
    M = _common()
    mb = MeshBuilder()
    vz = K.THRUST["nozzle_z"]
    for nx, ny in K.THRUST["nozzles"]:
        mb.cyl(M["trim"], (nx, ny, vz + 0.02), K.THRUST["nozzle_r"] + 0.03, 0.05, segs=24)
        mb.cyl(ug, (nx, ny, vz - 0.008), K.THRUST["nozzle_r"], 0.01, segs=24)
    ob = mb.to_object("FX_coupe_underglow", "FX", parent=root)
    # dashboard button at HORNER's right-hand reach
    btn = mat("FX_coupe_button", c["button"], emission=c["button"], strength=2.0)
    mb = MeshBuilder()
    mb.cyl(btn, (0, 0, 0), 0.025, 0.015, axis="Z", segs=16)
    b = mb.to_object("FX_coupe_button", "FX", parent=root)
    b.location = K.COUPE_BUTTON
    b.rotation_euler = (0, math.radians(-50), 0)
    return root


def minibus(name, door_open=False):
    S = K.MINIBUS
    root = car(name, S, K.C["minibus_yellow"], kind="minibus", metallic=0.1, rough=0.45, coat=0.2,
               door_open=door_open)
    M = _common()
    mb = MeshBuilder()
    L, W = S["L"], S["W"]
    for z in (0.62, 0.82):
        for s in (1, -1):
            if door_open and s < 0:
                x0, x1 = S["door_x"]
                mb.box(M["stripe"], ((x1 + L / 2 - 0.1) / 2 + 0.0, s * (W / 2 + 0.006), z), (L / 2 - 0.1 - x1, 0.012, 0.07))
                mb.box(M["stripe"], ((x0 - L / 2 + 0.1) / 2, s * (W / 2 + 0.006), z), (x0 + L / 2 - 0.1, 0.012, 0.07))
            else:
                mb.box(M["stripe"], (0, s * (W / 2 + 0.006), z), (L - 0.2, 0.012, 0.07))
    # roof rail on the door side
    mb.cyl(M["trim"], (-0.3, -(S["hw_t"] - 0.02), S["B"][1] + 0.05), 0.018, L - 1.2, axis="X", segs=10)
    for x in (-2.2, -0.3, 1.5):
        mb.box(M["trim"], (x, -(S["hw_t"] - 0.02), S["B"][1] + 0.02), (0.04, 0.04, 0.06))
    if door_open:
        x0, x1 = S["door_x"]
        z0, z1 = S["door_z"]
        dl = x1 - x0
        paint = bpy.data.materials[f"PAINT_{name}"]
        # slid-back door panel outside the body
        mb.box(paint, (x0 - dl / 2 + 0.05, -(W / 2 + 0.05), (z0 + 1.15) / 2), (dl, 0.05, 1.15 - z0))
        mb.box(M["trim"], (x0 - dl / 2 + 0.05, -(W / 2 + 0.05), (1.15 + z1) / 2), (dl, 0.05, z1 - 1.15))
        # step
        mb.box(M["trim"], ((x0 + x1) / 2, -(W / 2 - 0.05), 0.30), (dl - 0.1, 0.30, 0.05))
    mb.to_object(f"{name}_trim", COL, parent=root)
    return root


def brt(name):
    S = K.BRT
    return car(name, S, K.C["brt_blue"], kind="brt", metallic=0.2, rough=0.4, coat=0.3,
               cabin_color=K.C["brt_white"])


def keke(name):
    M = _common()
    c = K.C
    yel = mat("PAINT_keke_yellow", c["keke_yellow"], metallic=0.1, rough=0.4, coat=0.3)
    grn = mat("PAINT_keke_green", c["keke_green"], metallic=0.1, rough=0.5)
    mb = MeshBuilder()
    prof = [(1.25, 0.25), (1.30, 0.75), (0.85, 0.95), (-1.05, 0.95), (-1.20, 0.80), (-1.20, 0.30), (-0.9, 0.22)]
    mb.prism(yel, prof, -0.62, 0.62)
    root = mb.to_object(name, COL)
    bevel(root, 0.05, 3, 30)
    apply_modifiers(root)
    boolean_diff(root, [cutter_box((-0.1, 0, 1.6), (1.6, 1.1, 1.5))], M["interior"])
    mb = MeshBuilder()
    mb.prism(grn, [(1.0, 1.70), (-1.15, 1.70), (-1.15, 1.78), (1.05, 1.78)], -0.66, 0.66)
    for x in (0.95, -1.1):
        for s in (1, -1):
            mb.box(M["trim"], (x, s * 0.6, 1.33), (0.04, 0.04, 0.75))
    mb.box(grn, (-1.17, 0, 1.25), (0.04, 1.25, 0.9))
    mb.box(M["interior"], (-0.55, 0, 0.92), (0.55, 1.1, 0.12))
    mb.box(M["interior"], (-0.85, 0, 1.25), (0.1, 1.1, 0.6))
    mb.box(M["interior"], (0.35, 0, 0.98), (0.4, 0.4, 0.1))
    mb.box(M["head"], (1.31, 0, 0.62), (0.05, 0.2, 0.12))
    cab = mb.to_object(f"{name}_cabin", COL, parent=root)
    g = MeshBuilder()
    g.poly(M["glass"], [(0.95, -0.6, 0.95), (0.95, 0.6, 0.95), (0.98, 0.6, 1.65), (0.98, -0.6, 1.65)])
    g.to_object(f"{name}_glass", COL, parent=root, recalc=False)
    sw = MeshBuilder()
    sw.cyl(M["trim"], (0.75, 0, 1.15), 0.015, 0.6, axis="Y")
    sw.cyl(M["trim"], (0.82, 0, 1.0), 0.02, 0.35, axis="Z", rot=Matrix.Rotation(math.radians(-20), 3, "Y"))
    sw.to_object(f"{name}_steering", COL, parent=root)
    S = dict(wheel_r=0.24, wheel_w=0.14)
    _wheel(f"{name}_wheel_F", S, M, root, (1.0, 0, 0.24), 1)
    _wheel(f"{name}_wheel_RL", S, M, root, (-0.75, 0.55, 0.24), 1)
    _wheel(f"{name}_wheel_RR", S, M, root, (-0.75, -0.55, 0.24), -1)
    root["kind"] = "keke"
    root["wheel_r"] = 0.24
    root["L"] = K.KEKE["L"]
    root["W"] = K.KEKE["W"]
    return root


def truck(name, variant):
    """Petrol tanker or covered lorry: cab + chassis + tank/cargo, 3 axles. Origin = ground centre."""
    T = K.TRUCK
    M = _common()
    c = K.C
    cab_m = mat(f"PAINT_{name}", T["cab_colours"][variant], metallic=0.3, rough=0.4, coat=0.3)
    mb = MeshBuilder()
    Lh, W = T["L"] / 2, T["W"]
    x0, x1 = T["cab"]
    h = T["cab_h"]
    mb.box(M["trim"], (0, 0.75, 0.95), (T["L"] - 0.3, 0.15, 0.25))
    mb.box(M["trim"], (0, -0.75, 0.95), (T["L"] - 0.3, 0.15, 0.25))
    mb.box(cab_m, ((x0 + x1) / 2, 0, 0.75 + (1.55 - 0.75) / 2), (x1 - x0, W, 0.8))     # lower cab
    mb.box(cab_m, ((x0 + x1) / 2 - 0.1, 0, 1.55 + (h - 1.55) / 2), (x1 - x0 - 0.2, W - 0.06, h - 1.55))
    mb.box(M["trim"], (x1 + 0.02, 0, 1.0), (0.06, W - 0.3, 0.45))                      # grille
    for s_ in (1, -1):
        mb.box(M["head"], (x1 + 0.03, s_ * (W / 2 - 0.3), 0.85), (0.05, 0.3, 0.15))
        mb.box(M["tail"], (-Lh + 0.05, s_ * (W / 2 - 0.25), 1.0), (0.05, 0.25, 0.15))
        mb.box(cab_m, (x1 - 0.3, s_ * (W / 2 + 0.12), 2.0), (0.08, 0.06, 0.45))          # mirror arms
    if variant == "tanker":
        tm = mat(f"PAINT_{name}_tank", T["tank"], metallic=0.8, rough=0.3)
        bm_ = mat(f"PAINT_{name}_band", T["band"], metallic=0.3, rough=0.4)
        tl = x0 - 0.3 - (-Lh + 0.2)
        cx = (x0 - 0.3 + (-Lh + 0.2)) / 2
        r = T["tank_r"]
        mb.cyl(tm, (cx, 0, 1.1 + r), r, tl - 2 * 0.25, axis="X", segs=28)
        for e in (1, -1):
            mb.sphere(tm, (cx + e * (tl / 2 - 0.25), 0, 1.1 + r), r, scale=(0.25 / r, 1, 1), segs=24, rings=12)
        mb.cyl(bm_, (cx, 0, 1.1 + r), r + 0.01, 0.5, axis="X", segs=28)
        mb.box(M["trim"], (cx, 0, 1.1 + 2 * r + 0.08), (tl - 1.0, 0.5, 0.12))
    else:
        tarp = mat(f"PAINT_{name}_tarp", T["tarp"], rough=0.8)
        wood = mat(f"PAINT_{name}_wood", T["wood"], rough=0.9)
        cl = x0 - 0.25 - (-Lh + 0.1)
        cx = (x0 - 0.25 + (-Lh + 0.1)) / 2
        mb.box(wood, (cx, 0, 1.4), (cl, W, 0.9))
        mb.box(tarp, (cx, 0, 1.85 + 0.85), (cl - 0.1, W - 0.1, 1.7))
    root = mb.to_object(name, COL)
    bevel(root, width=0.04, segs=2, angle=40)
    g = MeshBuilder()
    g.poly(M["glass"], [(x1 + 0.005, -W / 2 + 0.15, 1.6), (x1 + 0.005, W / 2 - 0.15, 1.6),
                        (x1 - 0.18, W / 2 - 0.15, h - 0.15), (x1 - 0.18, -W / 2 + 0.15, h - 0.15)])
    g.to_object(f"{name}_glass", COL, parent=root, recalc=False)
    sw = MeshBuilder()
    wc = Vector((x1 - 0.55, 0.55, 1.75))
    sw.torus(M["trim"], wc, 0.22, 0.02, rot=Matrix.Rotation(math.radians(-55), 3, "Y"))
    sw.to_object(f"{name}_steering", COL, parent=root)
    S = dict(wheel_r=T["wheel_r"], wheel_w=T["wheel_w"])
    for i, ax in enumerate(T["axles"]):
        for side, s_ in (("L", 1), ("R", -1)):
            _wheel(f"{name}_wheel_{i}{side}", S, M, root, (ax, s_ * T["track"], T["wheel_r"]), s_)
    root["kind"] = "truck"
    root["wheel_r"] = T["wheel_r"]
    root["L"] = T["L"]
    root["W"] = W
    return root


def instance(src_root, new_name):
    """Linked-duplicate a whole car hierarchy (shares mesh data + materials)."""
    old = src_root.name
    mapping = {}
    for ob in [src_root] + list(src_root.children_recursive):
        n = ob.copy()
        n.animation_data_clear()
        n.name = ob.name.replace(old, new_name, 1)
        link(n, COL)
        mapping[ob] = n
    for ob, n in mapping.items():
        if ob.parent in mapping:
            n.parent = mapping[ob.parent]
            n.matrix_parent_inverse = ob.matrix_parent_inverse.copy()
    return mapping[src_root]


_DESIGN_CACHE = {}


def generic(name, design_idx):
    body, colname = K.GENERIC_DESIGNS[design_idx]
    if design_idx in _DESIGN_CACHE:
        return instance(_DESIGN_CACHE[design_idx], name)
    S = K.GENERIC[body]
    root = car(name, S, K.C["generic"][colname], kind=body, metallic=0.35 if colname != "white" else 0.05,
               rough=0.35, coat=0.6)
    _DESIGN_CACHE[design_idx] = root
    return root


_MINIBUS_SRC = {}


def minibus_any(name, door_open=False):
    if door_open or name == "CAR_minibus_ahead":
        return minibus(name, door_open)
    if "src" in _MINIBUS_SRC:
        return instance(_MINIBUS_SRC["src"], name)
    r = minibus(name)
    _MINIBUS_SRC["src"] = r
    return r


def build_all():
    _DESIGN_CACHE.clear()
    _MINIBUS_SRC.clear()
    cars = {}
    cars["CAR_hero_sedan"] = hero_sedan()
    cars["CAR_coupe"] = coupe()
    cars["CAR_minibus_ahead"] = minibus_any("CAR_minibus_ahead")
    cars["CAR_minibus_conductor"] = minibus_any("CAR_minibus_conductor", door_open=True)
    for lane in (K.TRAFFIC_L2,):
        for (n, design, x, v) in lane:
            if n in cars:
                continue
            cars[n] = minibus_any(n) if design == "minibus" else generic(n, design)
    for (n, design, off) in K.TRAFFIC_L3_EXTRA_AHEAD:
        cars[n] = generic(n, design)
    for (n, design) in K.TRAFFIC_L3_BEHIND:
        cars[n] = generic(n, design)
    for (n, x, v) in K.BRT_TRAFFIC:
        cars[n] = brt(n)
    for (n, x, v) in list(K.KEKES) + list(K.KEKES_L2):
        cars[n] = keke(n) if not any(k.startswith("CAR_keke") for k in cars) else instance(cars["CAR_keke_01"], n)
    for i, (lane, x, design) in enumerate(K.OPP_TRAFFIC):
        if isinstance(design, str):
            n = f"CAR_{design}_{i:02d}"
            cars[n] = truck(n, design)
        else:
            n = f"CAR_generic_opp_{i:02d}"
            cars[n] = generic(n, design)
    # remove temp collection used by cutters
    t = bpy.data.collections.get("_TMP")
    if t:
        bpy.data.collections.remove(t)
    return cars
