"""Traffic, character performances, coupe transformation + flight, FX, lip-sync. Master frames throughout."""
import math
import os
import random
import struct
import subprocess
import wave
import bpy
from mathutils import Vector, Matrix, Euler

import config as K
import characters as CH
from util import (set_keys, key_vec, flatten_keys, smoothstep, fcurve, fcurves, MeshBuilder, mat, link)

F0, F1 = K.FRAME_START, K.FRAME_END
EMIS = 'nodes["Principled BSDF"].inputs[29].default_value'  # Emission Strength (verified in build_scene)
REPORT = {}

# ============================================================================ traffic


def sedan_x(f):
    if f <= K.SEDAN_STOP_F:
        return K.SEDAN_START_X + K.SEDAN_CRAWL * (f - 1) / K.FPS
    a, b, d = K.SEDAN_CREEP
    if f <= a:
        return 0.0
    if f >= b:
        return d
    return d * smoothstep((f - a) / (b - a))


def _key_l3_follower(ob, offset, creep=True):
    """Same motion profile as the sedan (crawl, stop, creep) shifted by offset; wheels roll."""
    a, b, d = K.SEDAN_CREEP
    if creep:
        frames = [1, K.SEDAN_STOP_F, a, b]
        xs = [K.SEDAN_START_X + offset, offset, offset, offset + d]
    else:
        frames = [1, K.SEDAN_STOP_F]
        xs = [K.SEDAN_START_X + offset, offset]
    fc = set_keys(ob, "location", 0, frames, xs)
    _lin_then_ease(fc, len(frames))
    _roll_wheels(ob, frames, xs, len(frames))
    return fc


def _lin_then_ease(fc, n):
    kps = fc.keyframe_points
    kps[0].interpolation = "LINEAR"
    if n > 1:
        kps[1].interpolation = "LINEAR"
    if n > 2:
        flatten_keys(fc, {round(kps[2].co.x), round(kps[3].co.x)})
    fc.update()


def _wheels(root):
    out = []
    for o in root.children_recursive:
        if "_wheel_" in o.name and o.type == "MESH" and not o.name.startswith("FX_"):
            out.append(o)
    return out


def _roll_wheels(root, frames, xs, n_lin_ease, interp=None):
    r = root.get("wheel_r", 0.33)
    for w in _wheels(root):
        vals = [(x - xs[0]) / r for x in xs]
        fc = set_keys(w, "rotation_euler", 1, frames, vals)
        if interp == "LINEAR":
            for kp in fc.keyframe_points:
                kp.interpolation = "LINEAR"
            fc.update()
        else:
            _lin_then_ease(fc, n_lin_ease)


def _const_speed(ob, y, x1, v, direction=1):
    ob.location = (x1, y, 0)
    if direction < 0:
        ob.rotation_euler.z = math.pi
    xe = x1 + direction * v * (F1 - F0) / K.FPS
    fc = set_keys(ob, "location", 0, [F0, F1], [x1, xe], interp="LINEAR")
    _roll_wheels(ob, [F0, F1], [0, v * (F1 - F0) / K.FPS], 0, interp="LINEAR")
    ob["speed"] = v * direction


def traffic(cars):
    L3 = K.LANES["L3"]
    sed = cars["CAR_hero_sedan"]
    sed.location = (K.SEDAN_START_X, L3, 0)
    _key_l3_follower(sed, 0.0)
    cou = cars["CAR_coupe"]
    cou.location = (K.SEDAN_START_X - K.COUPE_GAP, L3, 0)
    mba = cars["CAR_minibus_ahead"]
    mba.location = (K.SEDAN_START_X + K.MINIBUS_AHEAD, L3, 0)
    _key_l3_follower(mba, K.MINIBUS_AHEAD, creep=False)
    front = K.MINIBUS_AHEAD + mba["L"] / 2
    for (n, design, off) in K.TRAFFIC_L3_EXTRA_AHEAD:
        c = cars[n]
        x = front + 1.6 + c["L"] / 2
        c.location = (x + K.SEDAN_START_X, L3, 0)
        _key_l3_follower(c, x, creep=False)
        front = x + c["L"] / 2
    back = -K.COUPE_GAP - cars["CAR_coupe"]["L"] / 2
    for (n, design) in K.TRAFFIC_L3_BEHIND:
        c = cars[n]
        x = back - K.L3_GAP - c["L"] / 2
        c.location = (x + K.SEDAN_START_X, L3, 0)
        _key_l3_follower(c, x)
        back = x - c["L"] / 2
    for (n, design, x, v) in K.TRAFFIC_L2:
        _const_speed(cars[n], K.LANES["L2"], x, v)
    for (n, x, v) in K.KEKES_L2:
        _const_speed(cars[n], K.LANES["L2"], x, v)
    v = K.MINIBUS_COND_SPEED
    _const_speed(cars["CAR_minibus_conductor"], K.MINIBUS_COND_Y,
                 K.MINIBUS_COND_X - v * (K.MINIBUS_COND_F - 1) / K.FPS, v)
    for (n, x, v) in K.BRT_TRAFFIC:
        _const_speed(cars[n], K.LANES["L1"], x, v)
    for (n, x, v) in K.KEKES:
        _const_speed(cars[n], K.KEKE_Y, x, v)
    for i, (lane, x, design) in enumerate(K.OPP_TRAFFIC):
        n = f"CAR_{design}_{i:02d}" if isinstance(design, str) else f"CAR_generic_opp_{i:02d}"
        _const_speed(cars[n], K.OPP_LANES[lane], x, K.OPP_SPEED, direction=-1)

# ============================================================================ coupe


def coupe_anim(cars):
    cou = cars["CAR_coupe"]
    ck = K.COUPE_KEYS
    a, b, d = K.SEDAN_CREEP
    g = K.COUPE_GAP
    x_park = d - g
    g0, g1, g_ahead, g_z = ck["glide"]
    f0, f1, f_ahead, f_z = ck["fly"]
    fe, fe_x, fe_z = ck["fly_extra"]
    r0, r1, rz = ck["rise"]
    # X
    frames = [1, K.SEDAN_STOP_F, a, b, g0, g1, f1, fe]
    xs = [K.SEDAN_START_X - g, -g, -g, x_park, x_park, d + g_ahead, d + f_ahead, d + fe_x]
    fc = set_keys(cou, "location", 0, frames, xs)
    kp = fc.keyframe_points
    kp[0].interpolation = kp[1].interpolation = "LINEAR"
    flatten_keys(fc, {a, b, g0})
    fc.update()
    # Z
    zf = [1, r0, r1, 740, g0, g1, f1, fe]
    zs = [0, 0, rz, rz + ck["hover_bob"], rz, g_z, f_z, fe_z]
    fcz = set_keys(cou, "location", 2, zf, zs)
    flatten_keys(fcz, {r0, r1, 740, g0})
    fy846, fy864 = ck["fly_y"]
    fcy = set_keys(cou, "location", 1, [1, g1, f1, fe], [K.LANES["L3"], K.LANES["L3"], fy846, fy864])
    flatten_keys(fcy, {1, g1})
    # nose wobble while rising, then banking on the fly-away
    set_keys(cou, "rotation_euler", 1, [r0, 695, 708, 721, r1, 745, 770, 800, 846],
             [0, math.radians(1), math.radians(-1), math.radians(0.8), 0, 0, math.radians(-4), math.radians(-6), math.radians(-3)])
    set_keys(cou, "rotation_euler", 0, [g1, 795, 820, 846], [0, math.radians(9), math.radians(6), math.radians(2)])
    set_keys(cou, "rotation_euler", 2, [g1, 815, 846], [0, math.radians(-12), math.radians(-14)])
    # wheels: roll with the crawl, then stay upright and still (the air jets do the lifting)
    r = cou["wheel_r"]
    for piv in [o for o in cou.children if o.name.startswith("EMP_coupe_wheel")]:
        wh = piv.children[0]
        roll = [(x - xs[0]) / r for x in xs[:4]]
        fw = set_keys(wh, "rotation_euler", 1, [1, K.SEDAN_STOP_F, a, b], roll)
        kpw = fw.keyframe_points
        kpw[0].interpolation = kpw[1].interpolation = "LINEAR"
        flatten_keys(fw, {a, b})
    # emissive materials
    lb = K.COUPE_KEYS["lightbar_base"]
    for mn in ("CAR_coupe_lightbar", "CAR_coupe_tailbar"):
        m = bpy.data.materials[mn]
        fr_l, vs_l = [ck["underglow"][0][0], ck["underglow"][1][0]], [lb, lb * ck["lightbar_boost"]]
        n_flash = 0
        if mn == "CAR_coupe_lightbar":   # the front bar flashes with the headlights (it is what shows in the mirror)
            pre_f, pre_v = [1], [lb]
            for st in K.HEADLIGHT_FLASHES:
                pre_f += [st, st + 4]
                pre_v += [K.LIGHTBAR_FLASH_STRENGTH, lb]
            n_flash = len(pre_f)
            fr_l, vs_l = pre_f + fr_l, pre_v + vs_l
        fcm = set_keys(m.node_tree, EMIS, 0, fr_l, vs_l)
        for kp in list(fcm.keyframe_points)[:n_flash]:
            kp.interpolation = "CONSTANT"
    ug = bpy.data.materials["FX_coupe_underglow"]
    (u0, s0), (u1, s1) = ck["underglow"]
    fcu = set_keys(ug.node_tree, EMIS, 0, [u0, u1], [s0, s1])
    fcu.keyframe_points[0].handle_right_type = "AUTO_CLAMPED"
    # headlight flashes: 0 -> 20 -> 0 twice, 4 frames on / 4 off (crisp, constant interpolation)
    hl = bpy.data.materials["CAR_coupe_headlight"]
    fr, vs = [1], [0.0]
    for st in K.HEADLIGHT_FLASHES:
        fr += [st, st + 4]
        vs += [K.HEADLIGHT_FLASH_STRENGTH, 0.0]
    fch = set_keys(hl.node_tree, EMIS, 0, fr, vs, interp="CONSTANT")
    # headlight beams: spot lights keyed with the same on/off pattern
    hb = K.HEADLIGHT_BEAM
    for s in (1, -1):
        ld = bpy.data.lights.new(f"LGT_coupe_beam_{'L' if s > 0 else 'R'}", "SPOT")
        ld.spot_size, ld.spot_blend = math.radians(hb["size"]), hb["blend"]
        ld.color, ld.shadow_soft_size = hb["colour"], 0.05
        ld.volume_factor = 0.0      # don't light up the FX_haze box (EEVEE washes the whole street out)
        ob = bpy.data.objects.new(ld.name, ld)
        cou.users_collection[0].objects.link(ob)
        ob.parent = cou
        ob.visible_volume_scatter = False   # same for Cycles
        ob.location = (hb["pos"][0], s * hb["pos"][1], hb["pos"][2])
        ob.rotation_euler = (0.0, math.radians(hb["tilt"]), 0.0)
        set_keys(ld, "energy", 0, fr, [v / K.HEADLIGHT_FLASH_STRENGTH * hb["energy"] for v in vs], interp="CONSTANT")
    btn = bpy.data.materials["FX_coupe_button"]
    (b0, v0), (b1, v1) = K.BUTTON_KEYS
    set_keys(btn.node_tree, EMIS, 0, [b0, b1], [v0, v1])
    # Workbench can't show emission animation; keep viewport colour constant.


def coupe_pos(cou, f):
    fx = fcurve(cou, "location", 0).evaluate(f)
    fy = fcurve(cou, "location", 1).evaluate(f)
    fz = fcurve(cou, "location", 2).evaluate(f)
    return Vector((fx, fy, fz))

# ============================================================================ FX


def fx(cars):
    rnd = random.Random(K.SEED + 5)
    cou = cars["CAR_coupe"]
    d0, d1 = K.COUPE_KEYS["dust"]
    base = coupe_pos(cou, d0)
    dust_m = _vapour_material("FX_dust", K.C["dust"], K.THRUST["dust_density"])
    bw = mat("FX_bag_white", K.C["bag_white"], rough=0.6)
    bb = mat("FX_bag_blue", K.C["bag_blue"], rough=0.6)
    mb = MeshBuilder()
    mb.sphere(dust_m, (0, 0, 0), 0.25, segs=12, rings=8)
    dust_mesh = mb.to_object("FX_dust_src", "FX").data
    bpy.data.objects.remove(bpy.data.objects["FX_dust_src"])
    frames = list(range(d0 - 1, d1 + 1, 2))
    for i in range(K.N_DUST):
        ob = bpy.data.objects.new(f"FX_dust_{i:02d}", dust_mesh)
        link(ob, "FX")
        start = d0 + rnd.randint(0, 14)
        p0 = base + Vector((rnd.uniform(-2.2, 2.2), rnd.uniform(-0.9, 0.9), 0.05))
        out = Vector((p0.x - base.x, p0.y - base.y, 0)).normalized() if (p0 - base).length > 0.1 else Vector((1, 0, 0))
        v0 = out * rnd.uniform(2.5, 6.0) + Vector((0, 0, rnd.uniform(0.8, 3.0)))
        k = rnd.uniform(1.2, 2.2)
        sc0 = rnd.uniform(0.3, 0.8)
        locs, scs = [], []
        for f in frames:
            t = max(0.0, (f - start) / K.FPS)
            p = p0 + v0 * ((1 - math.exp(-k * t)) / k)
            locs.append(p)
            grow = 0.0 if f < start else min(1.0, (f - start) / 4) * (1.0 + 0.8 * t)
            fade = 1.0 - smoothstep((f - (d1 - 14)) / 14)
            scs.append(sc0 * grow * fade)
        key_vec(ob, "location", frames, locs)
        for ax in range(3):
            set_keys(ob, "scale", ax, frames, scs)
        set_keys(ob, "color", 3, frames, [0.0 if f < start else min(1.0, (f - start) / 4) * (1.0 - smoothstep((f - (d1 - 20)) / 20))
                                           for f in frames])
    for i in range(K.N_BAGS):
        mb = MeshBuilder()
        mb.box(bw if i % 2 == 0 else bb, (0, 0, 0), (0.28, 0.32, 0.004))
        ob = mb.to_object(f"FX_bag_{i}", "FX")
        start = d0 + 2 + i * 2
        a = 2 * math.pi * i / K.N_BAGS + rnd.uniform(-0.3, 0.3)
        p0 = base + Vector((math.cos(a) * 1.6, math.sin(a) * 0.7, 0.02))
        v0 = Vector((math.cos(a), math.sin(a), 0)) * rnd.uniform(3, 5) + Vector((0, 0, rnd.uniform(3, 5)))
        k = 1.6
        fr = list(range(d0 - 1, F1 + 1, 2))
        locs, rots, scs = [], [], []
        for f in fr:
            t = max(0.0, (f - start) / K.FPS)
            p = p0 + v0 * ((1 - math.exp(-k * t)) / k)
            p.z = max(0.02, p.z - 0.9 * t * t)  # drag + gentle fall back to the road
            locs.append(p)
            rots.append(Vector((t * 4 + i, t * 3, t * 2)))
            scs.append(0.0 if f < start else 1.0)
        key_vec(ob, "location", fr, locs)
        key_vec(ob, "rotation_euler", fr, rots)
        for ax in range(3):
            fcs = set_keys(ob, "scale", ax, fr, scs, interp="CONSTANT")
    # shadow card for S09: invisible to camera, casts shadow, sweeps across HERO's face
    mb = MeshBuilder()
    sm = mat("FX_shadow_card", (0.1, 0.1, 0.1), rough=1.0)
    w, h = K.SHADOW_CARD["size"]
    mb.box(sm, (0, 0, 0), (0.01, h, w))
    card = mb.to_object("FX_shadow_card", "FX")
    card.visible_camera = False
    card.visible_diffuse = False
    card.visible_glossy = False
    card.visible_transmission = False
    card.visible_volume_scatter = False
    card.visible_shadow = True
    card["workbench_hide"] = True
    return card


def coupe_matrix(cou, f):
    loc = Vector([fcurve(cou, "location", i).evaluate(f) for i in range(3)])
    rot = Euler([fcurve(cou, "rotation_euler", i).evaluate(f) for i in range(3)], "XYZ")
    return Matrix.Translation(loc) @ rot.to_matrix().to_4x4()


def _thrust_intensity(f):
    keys = K.THRUST["intensity"]
    if f <= keys[0][0]:
        return 0.0 if f < keys[0][0] else keys[0][1]
    for (fa, va), (fb, vb) in zip(keys, keys[1:]):
        if fa <= f <= fb:
            return va + (vb - va) * (f - fa) / (fb - fa)
    return 0.0


def _vapour_material(name="FX_thrust_vapour", colour=None, density=None):
    """Soft smoke-puff surface: opaque-ish core, transparent rim, noise breakup.
    Per-puff fade comes from the object colour alpha (keyed)."""
    T = K.THRUST
    colour = colour or T["colour"]
    opacity = min(1.0, (density or T["density"]) / 30.0)
    m = bpy.data.materials.new(name)
    m.use_nodes = True
    nt = m.node_tree
    b = nt.nodes["Principled BSDF"]
    b.inputs["Base Color"].default_value = (*colour, 1)
    b.inputs["Roughness"].default_value = 1.0
    b.inputs["Specular IOR Level"].default_value = 0.0
    b.inputs["Subsurface Weight"].default_value = 0.0
    info = nt.nodes.new("ShaderNodeObjectInfo")
    lw = nt.nodes.new("ShaderNodeLayerWeight")
    lw.inputs["Blend"].default_value = 0.5
    inv = nt.nodes.new("ShaderNodeMath")
    inv.operation = "SUBTRACT"
    inv.inputs[0].default_value = 1.0
    pw = nt.nodes.new("ShaderNodeMath")
    pw.operation = "POWER"
    pw.inputs[1].default_value = 2.2
    tc = nt.nodes.new("ShaderNodeTexCoord")
    noise = nt.nodes.new("ShaderNodeTexNoise")
    noise.inputs["Scale"].default_value = 1.8
    noise.inputs["Detail"].default_value = 4.0
    ramp = nt.nodes.new("ShaderNodeMapRange")
    ramp.inputs["From Min"].default_value = 0.3
    ramp.inputs["From Max"].default_value = 0.7
    m1 = nt.nodes.new("ShaderNodeMath")
    m1.operation = "MULTIPLY"
    m2 = nt.nodes.new("ShaderNodeMath")
    m2.operation = "MULTIPLY"
    m3 = nt.nodes.new("ShaderNodeMath")
    m3.operation = "MULTIPLY"
    m3.inputs[1].default_value = opacity
    nt.links.new(lw.outputs["Facing"], inv.inputs[1])
    nt.links.new(inv.outputs[0], pw.inputs[0])
    nt.links.new(tc.outputs["Object"], noise.inputs["Vector"])
    nt.links.new(noise.outputs["Fac"], ramp.inputs["Value"])
    nt.links.new(pw.outputs[0], m1.inputs[0])
    nt.links.new(ramp.outputs[0], m1.inputs[1])
    nt.links.new(m1.outputs[0], m2.inputs[0])
    nt.links.new(info.outputs["Alpha"], m2.inputs[1])
    nt.links.new(m2.outputs[0], m3.inputs[0])
    nt.links.new(m3.outputs[0], b.inputs["Alpha"])
    m.surface_render_method = "BLENDED"
    m.use_transparency_overlap = True
    m.use_transparent_shadow = True
    m.diffuse_color = (*colour, 0.35)   # Workbench: translucent puffs
    return m


def thrust_fx(cars):
    """Air jets from the 4 vents: vapour puffs blast down, hit the road and billow outward."""
    T = K.THRUST
    rnd = random.Random(K.SEED + 11)
    cou = cars["CAR_coupe"]
    m = _vapour_material()
    mb = MeshBuilder()
    mb.sphere(m, (0, 0, 0), 1.0, segs=16, rings=10)
    src = mb.to_object("FX_thrust_src", "FX")
    mesh = src.data
    bpy.data.objects.remove(src)
    n = 0
    ground = 0.10
    for fb in range(T["start"], T["end"], T["every"]):
        inten = _thrust_intensity(fb)
        if inten <= 0.02:
            continue
        M = coupe_matrix(cou, fb)
        centre = M.translation.copy()
        down = (M.to_3x3() @ Vector((0, 0, -1))).normalized()
        for (nx, ny) in T["nozzles"]:
            p0 = M @ Vector((nx, ny, T["nozzle_z"] - 0.05))
            jit = Vector((rnd.uniform(-0.15, 0.15), rnd.uniform(-0.15, 0.15), 0))
            d = (down + jit).normalized()
            v = T["jet_speed"] * (0.6 + 0.4 * inten)
            k = T["drag"]
            out_dir = Vector((p0.x - centre.x + rnd.uniform(-0.3, 0.3), p0.y - centre.y + rnd.uniform(-0.3, 0.3), 0))
            out_dir = out_dir.normalized() if out_dir.length > 1e-3 else Vector((1, 0, 0))
            life = int(T["life"] * (0.8 + 0.4 * rnd.random()))
            fr = list(range(fb, fb + life + 1, 2))
            locs, scs, alphas = [], [], []
            t_hit = None
            for f in fr:
                t = (f - fb) / K.FPS
                s = v * (1 - math.exp(-k * t)) / k
                p = p0 + d * s
                if p.z < ground:
                    if t_hit is None:
                        # time when the jet column met the road
                        lo, hi = 0.0, t
                        for _ in range(20):
                            mid = (lo + hi) / 2
                            if (p0 + d * (v * (1 - math.exp(-k * mid)) / k)).z < ground:
                                hi = mid
                            else:
                                lo = mid
                        t_hit = hi
                    th = t_hit
                    ph = p0 + d * (v * (1 - math.exp(-k * th)) / k)
                    vh = v * math.exp(-k * th) * T["spread_keep"] + 1.5
                    tt = t - th
                    sp = vh * (1 - math.exp(-1.4 * tt)) / 1.4
                    p = Vector((ph.x, ph.y, ground)) + out_dir * sp + Vector((0, 0, 0.35 * (1 - math.exp(-1.2 * tt))))
                locs.append(p)
                age = (f - fb) / life
                r = (0.10 + 0.55 * age + (0.35 * age if t_hit is not None else 0.0)) * (0.7 + 0.5 * inten)
                scs.append(r)
                a_in = min(1.0, (f - fb) / 4.0)
                a_out = 1.0 - smoothstep((age - 0.45) / 0.55)
                alphas.append(inten * a_in * a_out)
            ob = bpy.data.objects.new(f"FX_thrust_{n:03d}", mesh)
            link(ob, "FX")
            n += 1
            key_vec(ob, "location", fr, locs)
            frs = [fb - 1] + fr + [fr[-1] + 1]
            scv = [0.0] + scs + [0.0]
            for ax in range(3):
                fc = set_keys(ob, "scale", ax, frs, scv)
                fc.keyframe_points[0].interpolation = "CONSTANT"
                fc.keyframe_points[-2].interpolation = "CONSTANT"
            set_keys(ob, "color", 3, [fb - 1] + fr, [0.0] + alphas)
    REPORT["thrust_puffs"] = n
    return n


def shadow_card_anim(card, hero_head):
    el, az = math.radians(K.SUN["elevation"]), math.radians(K.SUN["azimuth"])
    d = Vector((math.cos(el) * math.cos(az), math.cos(el) * math.sin(az), math.sin(el)))
    side = d.cross(Vector((0, 0, 1))).normalized()
    f0, f1 = K.SHADOW_CARD["frames"]
    sw = K.SHADOW_CARD["sweep"]
    centre = hero_head + d * K.SHADOW_CARD["dist"]
    rot = d.to_track_quat("X", "Z").to_euler()
    card.rotation_euler = rot
    far = centre + Vector((0, 0, 60))
    frames = [f0 - 1, f0, f1, f1 + 1]
    locs = [far, centre - side * sw / 2, centre + side * sw / 2, far]
    fcs = key_vec(card, "location", frames, locs, interp="LINEAR")
    for fc in fcs:
        fc.keyframe_points[0].interpolation = "CONSTANT"
        fc.keyframe_points[2].interpolation = "CONSTANT"

# ============================================================================ lip-sync


def _to_wav(src):
    dst = os.path.join(K.AUDIO_DIR, "hero_line.wav")
    subprocess.run(["ffmpeg", "-y", "-loglevel", "error", "-i", src, "-ac", "1", "-ar", "48000", dst], check=True)
    return dst


def jaw_curve():
    """Returns (function frame->deg, source string, duration seconds)."""
    wav = os.path.join(K.AUDIO_DIR, "hero_line.wav")
    if not os.path.exists(wav):
        for ext in ("mp3", "m4a", "aac", "ogg", "flac"):
            src = os.path.join(K.AUDIO_DIR, f"hero_line.{ext}")
            if os.path.exists(src):
                wav = _to_wav(src)
                break
    if os.path.exists(wav):
        with wave.open(wav, "rb") as w:
            n, sr, ch, sw = w.getnframes(), w.getframerate(), w.getnchannels(), w.getsampwidth()
            raw = w.readframes(n)
        if sw != 2 or ch != 1 or sr != 48000:
            wav = _to_wav(wav)
            with wave.open(wav, "rb") as w:
                n, sr = w.getnframes(), w.getframerate()
                raw = w.readframes(n)
        samples = struct.unpack(f"<{len(raw) // 2}h", raw)
        win = sr // K.FPS
        rms = []
        for i in range(0, len(samples), win):
            chunk = samples[i:i + win]
            rms.append(math.sqrt(sum(s * s for s in chunk) / max(1, len(chunk))))
        mx = max(rms) or 1.0
        amp = [r / mx for r in rms]
        sm = [sum(amp[max(0, i - 1):i + 2]) / len(amp[max(0, i - 1):i + 2]) for i in range(len(amp))]
        dur = len(samples) / sr
        if dur > 2.5:
            print(f"WARNING: hero_line.wav is {dur:.2f}s (> 2.5 s); the shout will run past the planned beat")
        print(f"[lipsync] hero_line.wav duration {dur:.2f}s")

        def fn(f):
            i = f - K.LINE_START
            return sm[i] * K.JAW_MAX_DEG if 0 <= i < len(sm) else 0.0
        return fn, "audio", dur, wav
    # fallback: "Fly nah, where you want make I go?!"  (syllable onsets in frames from LINE_START)
    syl = [(1, 9, 1.0), (10, 6, 0.65), (19, 5, 0.6), (24, 4, 0.5), (28, 5, 0.6), (33, 5, 0.55), (38, 4, 0.5),
           (42, 9, 0.85)]

    def fn(f):
        i = f - K.LINE_START
        v = 0.0
        for st, ln, a in syl:
            if st <= i <= st + ln:
                x = (i - st) / ln
                v = max(v, a * math.sin(math.pi * x) ** 0.7)
        return v * K.JAW_MAX_DEG
    return fn, "fallback", K.FALLBACK_LINE_SEC, None


def add_sound_strip(wav):
    sc = bpy.context.scene
    if sc.sequence_editor is None:
        sc.sequence_editor_create()
    se = sc.sequence_editor
    strips = getattr(se, "strips", None) or se.sequences
    strips.new_sound("hero_line", wav, channel=1, frame_start=K.LINE_START)

# ============================================================================ characters


def _sm(f, a, b):
    return smoothstep((f - a) / (b - a)) if b > a else (1.0 if f >= b else 0.0)


def _timeline(track, f):
    """track: list of (frame, pose). Smoothstep blend between neighbours, hold outside."""
    if f <= track[0][0]:
        return track[0][1]
    for (fa, pa), (fb, pb) in zip(track, track[1:]):
        if fa <= f <= fb:
            return CH.blend(pa, pb, _sm(f, fa, fb))
    return track[-1][1]


def _look_angles(src, dst, forward_yaw=0.0):
    d = dst - src
    yaw = math.degrees(math.atan2(d.y, d.x)) - forward_yaw
    while yaw > 180:
        yaw -= 360
    while yaw < -180:
        yaw += 360
    pitch = -math.degrees(math.atan2(d.z, math.hypot(d.x, d.y)))
    return yaw, pitch


def hero_anim(arm, sedan, cou, jaw_fn):
    sv = CH.Solver(arm)
    s = arm["height"] / 1.78
    RL, RR = sv.rest_wrist("L"), sv.rest_wrist("R")
    P = CH.blank_pose
    wheel = CH.copy_pose(P(), ikL=RL, ikR=RR, poleL=(0.3, 0.6, -1), poleR=(0.3, -0.6, -1))
    sill_wrist = Vector((0.38, 0.47, 0.47)) * s
    idle = CH.copy_pose(wheel, root=(-0.02, 0.03, -0.03),
                        rot={"spine": (-4, -6, 0), "chest": (0, 10, 4), "neck": (0, 4, 0), "head": (3, 2, -6)},
                        ikL=sill_wrist, poleL=(0, 1, 0.1), ikR=Vector((0.17, -0.17, 0.58)) * s, poleR=(0.2, -1, -0.8))
    lean_fwd = CH.copy_pose(wheel, root=(0.03, 0, 0), rot={"spine": (0, 5, 0), "chest": (0, 4, 0), "head": (0, -7, 0)})
    drop = CH.copy_pose(wheel, rot={"spine": (4, 4, -6), "chest": (6, 6, -10), "head": (0, 10, -15)},
                        ikR=Vector((0.30, -0.42, 0.10)) * s, poleR=(0, -1, 0))
    grip = CH.copy_pose(wheel, root=(0.02, 0, 0.0),
                        rot={"spine": (0, 3, 0), "chest": (0, 4, 0), "shoulder.L": (11, 0, 0),
                             "shoulder.R": (-11, 0, 0), "neck": (0, 4, 0), "head": (0, 10, 0)},
                        ikL=RL + Vector((-0.02, 0, 0.01)), ikR=RR + Vector((-0.02, 0, 0.01)))
    exhale = CH.copy_pose(grip, root=(0.02, 0, -0.012), rot={"chest": (0, 1, 0), "shoulder.L": (5, 0, 0), "shoulder.R": (-5, 0, 0)})
    lean = CH.copy_pose(wheel, root=Vector((0.10, 0.20, 0.33)) * s,
                        rot={"pelvis": (-10, 0, 4), "spine": (-18, 4, 6), "chest": (-24, 0, 12),
                             "neck": (16, 0, 45), "head": (8, -6, 60), "shoulder.L": (25, 0, 0)},
                        poleR=(0, -1, -0.5))
    # left-arm targets relative to the leaning shoulder
    _, A, Pj = sv.solve(lean)
    shL = Pj["upper_arm.L"]
    lean["ikL"] = shL + Vector((0.25, 0.30, 0.02)) * s
    lean["poleL"] = Vector((0.5, 0.6, -0.2))
    lean["ikR"] = RR + Vector((0, 0.1, 0.0))
    lean_mid = CH.blend(grip, lean, 0.5)
    lean_mid["root"] = Vector((0.07, 0.16, 0.17)) * s
    lean_mid["rot"]["chest"] = (-34, 2, 8)
    lean_mid["rot"]["shoulder.L"] = (25, 0, 0)
    lean_mid["ikL"] = Vector((0.15, 0.55, 0.62)) * s
    lean_mid["poleL"] = Vector((1.0, -0.3, 0.2))
    fling = CH.copy_pose(lean, ikL=shL + Vector((0.50, 0.25, 0.12)) * s, poleL=(0, 0, -1), handL=(0, 0, -15),
                         rot={"head": (8, -10, 25), "neck": (16, 0, 20)})
    sweep = CH.copy_pose(lean, ikL=shL + Vector((-0.42, 0.33, 0.08)) * s, poleL=(0.3, 0.2, -1), handL=(0, 0, 20),
                         rot={"head": (8, -4, 70), "neck": (16, 0, 50)})
    glare = CH.copy_pose(lean, ikL=shL + Vector((0.22, 0.32, 0.0)) * s)
    shock = CH.copy_pose(wheel, rot={"spine": (0, -4, 0), "chest": (0, -3, 0), "neck": (0, -12, 0), "head": (0, -23, 0)},
                         ikL=sill_wrist, poleL=(0, 1, 0.1), ikR=RR + Vector((-0.08, 0.02, -0.08)))
    shock_low = CH.copy_pose(shock, ikL=Vector((0.33, 0.15, 0.12)) * s, poleL=(0.3, 1, -0.5))
    track = [(1, idle), (330, idle), (345, wheel), (350, wheel), (375, lean_fwd), (380, lean_fwd), (395, idle),
             (440, idle), (452, drop), (469, wheel), (480, grip), (504, exhale), (509, lean_mid), (516, lean), (517, fling),
             (527, fling), (540, sweep), (565, sweep), (600, glare), (700, glare), (745, shock), (780, shock_low)]
    ENV_SEAT_F = K.ENVELOPE_DROP_F

    def fan_w(f):
        if f < 330:
            return 1.0
        if f < 345:
            return 1 - _sm(f, 330, 342)
        if f < 380:
            return 0.0
        if f < 440:
            return _sm(f, 382, 395)
        return 1 - _sm(f, 440, 446)

    hero_head_rest = arm.matrix_world.copy()

    def pose(f):
        p = CH.copy_pose(_timeline(track, f))
        w = fan_w(f)
        if w > 0:
            ph = math.sin(2 * math.pi * f / 18.0)
            p["handR"] = p["handR"] + Vector((0, 0, 35 * ph * w))
            if p["ikR"] is not None:
                p["ikR"] = p["ikR"] + Vector((0, 0.035 * ph * w, 0.01 * ph * w))
        breath = math.sin(2 * math.pi * f / 80.0)
        if 565 <= f <= 610:
            breath = 3.0 * math.sin(2 * math.pi * f / 20.0) * (1 - _sm(f, 590, 610))
        r = dict(p["rot"])
        cx, cy, cz = r.get("chest", (0, 0, 0))
        r["chest"] = (cx, cy + 1.2 * breath, cz)
        # look up / follow the coupe 700..864
        if f >= 700:
            src = sedan.matrix_world @ (Vector(K.SEDAN["seat_hip"]) + Vector((-0.05, 0.0, 0.73)) * s)
            yaw, pitch = _look_angles(src, coupe_pos(cou, f))
            w2 = _sm(f, 700, 745)
            if f < 745:
                hx, hy, hz = r.get("head", (0, 0, 0))
                r["head"] = (hx, hy + (max(-40, pitch) * 0.5 - hy) * w2 * 0.6, hz)
            if f >= 780:
                hx, hy, hz = r.get("head", (0, 0, 0))
                turn = max(-6, min(6, (yaw if abs(yaw) < 90 else 0) * 0.1)) * _sm(f, 780, 840)
                r["head"] = (hx, hy + 5 * _sm(f, 780, 846), hz + turn)
        p["rot"] = r
        return p

    def jaw(f):
        if K.LINE_START <= f <= K.LINE_END:
            return jaw_fn(f)
        if f >= 745:
            return 15.0 * _sm(f, 745, 752)
        if 700 <= f < 745:
            return 6.0 * _sm(f, 715, 745)
        return 0.0

    frames = list(range(F0, F1 + 1, 2))
    CH.key_timeline(arm, pose, frames, jaw_fn=jaw, jaw_frames=list(range(F0, F1 + 1)))
    REPORT["hero_track"] = track
    return pose


def envelope_swap(arm, sedan):
    env = bpy.data.objects["CHR_hero_envelope"]
    f = K.ENVELOPE_DROP_F
    for ax in range(3):
        set_keys(env, "scale", ax, [1, f], [1.0, 0.0], interp="CONSTANT")
    mb = MeshBuilder()
    mb.box(bpy.data.materials["CHR_hero_envelope"], (0, 0, 0), (0.24, 0.16, 0.004))
    ob = mb.to_object("CHR_hero_envelope_seat", "CHARS", parent=sedan)
    ob.location = K.ENVELOPE_SEAT
    ob.rotation_euler.z = math.radians(25)
    for ax in range(3):
        set_keys(ob, "scale", ax, [1, f], [0.0, 1.0], interp="CONSTANT")


def horner_anim(arm, cou):
    sv = CH.Solver(arm)
    s = arm["height"] / 1.78
    RL, RR = sv.rest_wrist("L"), sv.rest_wrist("R")
    hip = Vector(K.COUPE["seat_hip"])
    wc = Vector((0.43, 0, 0.45))
    btn = Vector(K.COUPE_BUTTON) - hip
    wheel = CH.copy_pose(CH.blank_pose(), ikL=RL, ikR=RR, poleL=(0.3, 0.6, -1), poleR=(0.3, -0.6, -1))
    idle = CH.copy_pose(wheel, rot={"spine": (0, -3, 0), "head": (0, 2, 4)})
    lean_in = CH.copy_pose(wheel, root=(0.04, 0, 0), rot={"spine": (0, 5, 0), "neck": (0, 12, 0), "head": (0, -8, 0)},
                           ikR=wc + Vector((-0.08, 0, 0.07)), poleR=(0, -1, -0.5))
    press = CH.copy_pose(lean_in, ikR=wc + Vector((-0.025, 0, 0.015)))
    wave_up = CH.copy_pose(lean_in, ikL=Vector((0.38, 0.22, 0.66)) * s, poleL=(0, 1, -0.6), handL=(0, -45, 0))
    wave_dn = CH.copy_pose(wave_up, handL=(0, 35, 0), ikL=Vector((0.43, 0.22, 0.62)) * s)
    longhorn = CH.copy_pose(press, rot={"spine": (0, 6, 0), "neck": (0, 14, 0), "head": (0, -10, 0)})
    fold = CH.copy_pose(wheel, root=(-0.03, 0, -0.01), rot={"spine": (0, -7, 0), "chest": (0, -3, 0), "head": (0, 3, 0)},
                        ikL=Vector((0.20, -0.13, 0.30)) * s, ikR=Vector((0.18, 0.13, 0.34)) * s,
                        poleL=(0, 1, -0.6), poleR=(0, -1, -0.6))
    glasses = CH.copy_pose(fold, rot={"head": (0, 8, 0), "neck": (0, 2, 0)},
                           ikL=Vector((0.05, 0.07, 0.62)) * s, poleL=(0, 1, -0.6), handL=(0, -70, 0))
    smirk = CH.copy_pose(fold, rot={"head": (5, 8, 0), "shoulder.L": (17, 0, 0), "shoulder.R": (-17, 0, 0)})
    smirk_end = CH.copy_pose(fold, rot={"head": (5, 6, 0)})
    reach = CH.copy_pose(smirk_end, ikR=btn + Vector((-0.06, 0, 0.05)), poleR=(0, -1, -0.5), handR=(0, 20, 0))
    push = CH.copy_pose(reach, ikR=btn + Vector((-0.035, 0, 0.02)))
    relaxed = CH.copy_pose(wheel, rot={"spine": (0, -5, 0), "head": (0, 0, 0)},
                           ikL=Vector((0.32, 0.13, 0.10)) * s, ikR=Vector((0.32, -0.13, 0.10)) * s,
                           poleL=(0.2, 1, -0.3), poleR=(0.2, -1, -0.3))
    track = [(1, idle), (408, idle), (419, lean_in)]
    presses = [(423, 6), (437, 6), (452, 10)]
    for st, ln in presses:
        track += [(st - 2, lean_in), (st, press), (st + ln, press), (st + ln + 2, lean_in)]
    track += [(467, lean_in), (470, longhorn), (505, longhorn), (518, fold), (601, fold), (612, glasses), (620, glasses),
              (626, smirk), (632, smirk_end), (635, reach), (636, push), (640, reach), (652, relaxed)]
    track.sort(key=lambda t: t[0])
    waves = [(425, 437), (445, 457)]

    def pose(f):
        p = CH.copy_pose(_timeline(track, f))
        for a, b in waves:
            if a - 3 <= f <= b + 3:
                w = _sm(f, a - 3, a) * (1 - _sm(f, b, b + 3))
                ph = 0.5 + 0.5 * math.sin(2 * math.pi * (f - a) / 6.0)
                wv = CH.blend(wave_up, wave_dn, ph)
                p["ikL"] = p["ikL"].lerp(wv["ikL"], w)
                p["poleL"] = p["poleL"].lerp(wv["poleL"], w)
                p["handL"] = p["handL"].lerp(wv["handL"], w)
        if f < 408:  # impatient finger tapping
            p["handL"] = p["handL"] + Vector((0, 8 * max(0, math.sin(2 * math.pi * f / 6.0)), 0))
        r = dict(p["rot"])
        cx, cy, cz = r.get("chest", (0, 0, 0))
        r["chest"] = (cx, cy + 0.8 * math.sin(2 * math.pi * f / 90.0), cz)
        p["rot"] = r
        return p

    frames = list(range(F0, F1 + 1, 2))
    CH.key_timeline(arm, pose, frames)
    # sunglasses slide 2.5 cm down the nose (local position of CHR_horner_glasses)
    g = bpy.data.objects["CHR_horner_glasses"]
    fcs = key_vec(g, "location", [604, 618], [Vector((0, 0, 0)), Vector((0.006, 0, -0.025))])
    return pose


def hawker_anim(arm, cou):
    s = arm["height"] / 1.78
    fz = K.HAWKER_FREEZE_F
    front_wheel_x = coupe_pos(cou, fz).x + K.COUPE["axle_x"]
    x_at = lambda f: front_wheel_x + K.HAWKER_SPEED * (fz - min(f, fz)) / K.FPS
    arm.rotation_euler.z = math.pi
    frames = list(range(F0, F1 + 1, 2))
    set_keys(arm, "location", 0, frames, [x_at(f) for f in frames], interp="LINEAR")
    set_keys(arm, "location", 1, [1], [K.HAWKER_Y])
    head_h = 1.67 * s

    def pose(f):
        p = CH.blank_pose()
        ff = min(f, fz)
        ph = 2 * math.pi * ff / 24.0
        sn = math.sin(ph)
        r = {"thigh.L": (0, -22 * sn, 0), "thigh.R": (0, 22 * sn, 0),
             "shin.L": (0, 6 + 22 * max(0, math.sin(ph + 1.2)), 0), "shin.R": (0, 6 + 22 * max(0, math.sin(ph + 1.2 + math.pi)), 0),
             "upper_arm.L": (8, 14 * sn, 0), "upper_arm.R": (-8, -14 * sn, 0),
             "forearm.L": (0, -18, 0), "forearm.R": (0, -18, 0), "pelvis": (0, 0, 4 * sn), "chest": (0, 0, -3 * sn)}
        p["root"] = Vector((0, 0, 0.018 * abs(math.cos(ph)) - 0.01))
        if f >= fz:
            hw = arm.matrix_world.translation.copy()
            src = Vector((x_at(f), K.HAWKER_Y, head_h))
            yaw, pitch = _look_angles(src, coupe_pos(cou, f) + Vector((0, 0, 0.6)), forward_yaw=180)
            w = _sm(f, fz, fz + 5)
            pitch = max(-50, min(10, pitch)) - 12
            yaw = max(-70, min(70, yaw))
            r["neck"] = (0, 0.4 * pitch * w, 0.4 * yaw * w)
            r["head"] = (0, 0.6 * pitch * w, 0.6 * yaw * w)
            r["upper_arm.R"] = (-8 - 25 * w, -14 * math.sin(2 * math.pi * fz / 24.0), 0)
        p["rot"] = r
        return p
    CH.key_timeline(arm, pose, frames)


def conductor_anim(arm, cou, minibus):
    s = arm["height"] / 1.78
    rail = Vector((0.05, 0.26, 1.80))
    base = CH.copy_pose(CH.blank_pose(), ikL=rail, poleL=(0, 1, -0.3), handL=(0, -30, 0),
                        rot={"root": (K.CONDUCTOR_LEAN, 0, 0), "spine": (4, 0, 0), "head": (0, 0, -20), "upper_arm.R": (-12, 5, 0), "forearm.R": (0, -20, 0)})

    def pose(f):
        p = CH.copy_pose(base)
        p["root"] = Vector((0, 0, 0.015 * math.sin(2 * math.pi * f / 13.0)))
        r = dict(p["rot"])
        if f >= 760:
            head = minibus.matrix_world.copy()
            mx = fcurve(minibus, "location", 0).evaluate(f)
            src = Vector((mx + K.CONDUCTOR_LOCAL[0], K.MINIBUS_COND_Y + K.CONDUCTOR_LOCAL[1], 1.6 * s + 0.3))
            yaw, pitch = _look_angles(src, coupe_pos(cou, f))
            w = _sm(f, 760, 772)
            pitch = max(-60, min(10, pitch))
            yaw = max(-80, min(80, yaw))
            r["neck"] = (0, 0.4 * pitch * w, 0.4 * yaw * w)
            r["head"] = (0, 0.6 * pitch * w, -20 * (1 - w) + 0.6 * yaw * w)
        p["rot"] = r
        return p
    CH.key_timeline(arm, pose, list(range(F0, F1 + 1, 2)))


# ============================================================================ background people


def _walk_rot(ph, sn, amp=1.0):
    return {"thigh.L": (0, -22 * sn * amp, 0), "thigh.R": (0, 22 * sn * amp, 0),
            "shin.L": (0, 6 + 22 * amp * max(0, math.sin(ph + 1.2)), 0),
            "shin.R": (0, 6 + 22 * amp * max(0, math.sin(ph + 1.2 + math.pi)), 0),
            "upper_arm.L": (8, 14 * sn * amp, 0), "upper_arm.R": (-8, -14 * sn * amp, 0),
            "forearm.L": (0, -18, 0), "forearm.R": (0, -18, 0), "pelvis": (0, 0, 4 * sn), "chest": (0, 0, -3 * sn)}


def ped_anim(arm, x0, y, heading, speed, facing_deg, seed):
    """Walkers: constant-speed walk cycle along X. Standers: weight shift + small head turns."""
    rnd = random.Random(seed)
    frames = list(range(F0, F1 + 1, 2))
    phase0 = rnd.uniform(0, 2 * math.pi)
    if heading != 0:
        arm.rotation_euler.z = 0.0 if heading > 0 else math.pi
        xs = [x0 + heading * speed * (f - 1) / K.FPS for f in frames]
        set_keys(arm, "location", 0, frames, xs, interp="LINEAR")
        cadence = 24.0 * 1.0 / max(0.5, speed)   # frames per stride cycle

        def pose(f):
            p = CH.blank_pose()
            ph = phase0 + 2 * math.pi * f / cadence
            sn = math.sin(ph)
            p["rot"] = _walk_rot(ph, sn, min(1.0, speed / 1.1))
            p["root"] = Vector((0, 0, 0.018 * abs(math.cos(ph)) - 0.01))
            return p
    else:
        arm.rotation_euler.z = math.radians(facing_deg)
        arm.location.x = x0

        def pose(f):
            p = CH.blank_pose()
            w = math.sin(phase0 + 2 * math.pi * f / 110.0)
            look = 25 * math.sin(phase0 * 2 + 2 * math.pi * f / 260.0)
            p["rot"] = {"pelvis": (3 * w, 0, 0), "spine": (-2 * w, 0, 0), "head": (0, 3, look),
                        "upper_arm.L": (6, 4, 0), "upper_arm.R": (-6, 4, 0), "forearm.L": (0, -12, 0),
                        "forearm.R": (0, -12, 0), "thigh.L": (0, 0, 0), "shin.L": (0, 3 * max(0, w), 0)}
            p["root"] = Vector((0, 0.02 * w, 0))
            return p
    arm.location.y = y
    CH.key_timeline(arm, pose, frames)
