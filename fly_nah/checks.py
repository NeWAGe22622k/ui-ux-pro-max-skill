"""Automated verification (Phase 2). python3 fly_nah/checks.py  -> PASS/FAIL per check + review/check_report.txt"""
import math
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import bpy
from mathutils import Vector, Matrix
from mathutils.bvhtree import BVHTree

import config as K

LINES = []


def out(s=""):
    print(s, flush=True)
    LINES.append(s)


def result(n, title, ok, detail=""):
    out(f"[{'PASS' if ok else 'FAIL'}] {n:2d}. {title}" + (f" -- {detail}" if detail else ""))
    return ok


def roots():
    return [o for o in bpy.data.objects if o.name.startswith("CAR_") and o.type == "MESH" and
            (o.parent is None or not o.parent.name.startswith("CAR_")) and "kind" in o]


def car_meshes(root):
    return [root] + [o for o in root.children_recursive if o.type == "MESH" and
                     (o.name.startswith("CAR_") or o.name.startswith("FX_coupe")) and not o.name.startswith("CHR_")]


def world_aabb(obs):
    lo = Vector((1e9, 1e9, 1e9))
    hi = -lo
    for o in obs:
        mw = o.matrix_world
        for c in o.bound_box:
            p = mw @ Vector(c)
            lo = Vector(map(min, lo, p))
            hi = Vector(map(max, hi, p))
    return lo, hi


def main():
    if os.path.abspath(bpy.data.filepath or "") != os.path.abspath(K.BLEND_PATH):
        bpy.ops.wm.open_mainfile(filepath=K.BLEND_PATH)
    sc = bpy.context.scene
    sc.timeline_markers.clear()
    allok = True
    out("=== FLY NAH AUTOMATED CHECKS ===")

    # 1 cameras + ranges
    bad = []
    for s in K.SHOTS:
        cam = bpy.data.objects.get(f"CAM_{s[0]}")
        if cam is None:
            bad.append(f"{s[0]} missing")
            continue
        rr = tuple(cam.get("render_range", (0, 0)))
        if rr != (s[3], s[4]) or s[4] - s[3] + 1 < K.MIN_RENDER_LEN:
            bad.append(f"{s[0]} range {rr}")
    allok &= result(1, "cameras S01..S10 + S03B exist, render ranges match, >= 96 frames", not bad, "; ".join(bad))

    # 2 no text
    fonts = [o.name for o in bpy.data.objects if o.type == "FONT"]
    imgs = [i.name for i in bpy.data.images if i.type == "IMAGE" and i.source in ("FILE", "GENERATED")]
    allok &= result(2, "no text objects / no image textures", not fonts and not imgs,
                    f"fonts={fonts} images={imgs}" if (fonts or imgs) else "0 text objects, 0 image textures")

    sc.frame_set(1)
    R = roots()
    # 3 LHD
    bad, exempt = [], []
    for r in R:
        st = bpy.data.objects.get(f"{r.name}_steering")
        if st is None:
            bad.append(f"{r.name}: no steering")
            continue
        loc = r.matrix_world.inverted() @ (st.matrix_world @ (sum((Vector(c) for c in st.bound_box), Vector()) / 8))
        if r["kind"] == "keke":
            exempt.append(r.name)
            continue
        if loc.y <= 0.05:
            bad.append(f"{r.name} y={loc.y:.2f}")
    allok &= result(3, "every car left-hand drive (steering wheel on +Y)", not bad,
                    (f"{len(R) - len(exempt)} cars OK; kekes centre-steered (handlebar), exempt: {len(exempt)}"
                     if not bad else "; ".join(bad)))

    # 4 traffic intersections every 6 frames
    hits = []
    meshes = {r.name: car_meshes(r) for r in R}
    for f in range(K.FRAME_START, K.FRAME_END + 1, 6):
        sc.frame_set(f)
        boxes = {n: world_aabb(m) for n, m in meshes.items()}
        names = list(boxes)
        for i in range(len(names)):
            a = names[i]
            la, ha = boxes[a]
            for b in names[i + 1:]:
                lb, hb = boxes[b]
                pen = min(ha.x - lb.x, hb.x - la.x), min(ha.y - lb.y, hb.y - la.y), min(ha.z - lb.z, hb.z - la.z)
                tol = 0.3 if "minibus_conductor" in (a + b) else 0.0
                if min(pen) > 0.01 + tol:
                    hits.append(f"f{f} {a}x{b} pen={min(pen):.2f}")
    allok &= result(4, "no vehicle bounding boxes overlap (every 6 frames)", not hits,
                    f"{len(hits)} overlaps: " + "; ".join(hits[:6]) if hits else f"{len(R)} vehicles, 144 samples")

    # 5 camera clearance
    dg = bpy.context.evaluated_depsgraph_get()
    obst = []
    for r in R:
        for o in car_meshes(r):
            obst.append((o, r.name))
    for o in bpy.data.objects:
        if o.type == "MESH" and (o.name == "ENV_median" or o.name.startswith("ENV_street_light") or o.name == "ENV_ped_bridge"):
            obst.append((o, o.name))
    bvh = {}
    for o, _ in obst:
        ev = o.evaluated_get(dg)
        me = ev.to_mesh()
        bvh[o.name] = BVHTree.FromPolygons([v.co.copy() for v in me.vertices], [tuple(p.vertices) for p in me.polygons])
        ev.to_mesh_clear()
    worst = {}
    fails = []
    for s in K.SHOTS:
        cam = bpy.data.objects[f"CAM_{s[0]}"]
        exempt = {"CAR_hero_sedan"} if s[0] in ("S02", "S03", "S03B") else set()
        mind = (1e9, "")
        for f in range(s[3], s[4] + 1, 4):
            sc.frame_set(f)
            p = cam.matrix_world.translation
            for o, group in obst:
                if group in exempt:
                    continue
                mw = o.matrix_world
                lo, hi = world_aabb([o])
                d_box = max(lo.x - p.x, p.x - hi.x, lo.y - p.y, p.y - hi.y, lo.z - p.z, p.z - hi.z, 0)
                if d_box > 2.0:
                    continue
                lp = mw.inverted() @ p
                hit = bvh[o.name].find_nearest(lp)
                if hit[0] is None:
                    continue
                d = (mw @ hit[0] - p).length
                if d < mind[0]:
                    mind = (d, f"{o.name}@f{f}")
        worst[s[0]] = mind
        if mind[0] < K.CLEARANCE:
            fails.append(f"{s[0]} {mind[0]:.2f}m {mind[1]}")
    detail = ", ".join(f"{k} {v[0]:.2f}" if v[0] < 1e8 else f"{k} >2" for k, v in worst.items())
    allok &= result(5, "camera clearance >= 0.3 m (every 4 frames; S02/S03/S03B exempt from sedan)", not fails,
                    ("; ".join(fails) + " | " if fails else "") + "min: " + detail)

    # 6 coupe over sedan
    cou, sed = bpy.data.objects["CAR_coupe"], bpy.data.objects["CAR_hero_sedan"]
    minclear = 1e9
    for f in range(745, 781):
        sc.frame_set(f)
        lo_c, _ = world_aabb(car_meshes(cou))
        _, hi_s = world_aabb(car_meshes(sed))
        minclear = min(minclear, lo_c.z - hi_s.z)
    allok &= result(6, "coupe >= 1.2 m above sedan roof during 745-780", minclear >= 1.2, f"min clearance {minclear:.2f} m")

    # 7 wheels vertical at 700
    sc.frame_set(700)
    angs = []
    for piv in [o for o in cou.children if o.name.startswith("EMP_coupe_wheel")]:
        wh = piv.children[0]
        axle = (wh.matrix_world.to_3x3() @ Vector((0, 1, 0))).normalized()
        angs.append(math.degrees(math.acos(min(1, abs(axle.z)))))
    allok &= result(7, "coupe wheel axles within 5 deg of vertical at f700", max(angs) <= 5.0,
                    "angles " + ", ".join(f"{a:.1f}" for a in angs))

    # 8 hero head/arm vs window opening 505-600
    hero = bpy.data.objects["CHR_hero"]
    s_ = hero["height"] / 1.78
    S = K.SEDAN
    Wn = K.HERO_WINDOW
    A, B = S["A"], S["B"]

    def x_a(z):
        t = (z - A[1]) / (B[1] - A[1])
        return A[0] + (B[0] - A[0]) * max(0, min(1, t))

    def y_side(z):
        if z < S["belt"]:
            return S["W"] / 2
        t = (z - S["belt"]) / (B[1] - S["belt"])
        return S["hw_b"] + (S["hw_t"] - S["hw_b"]) * max(0, min(1, t))
    viol = []
    max_out = -1
    for f in range(505, 601):
        sc.frame_set(f)
        inv = sed.matrix_world.inverted()
        pb = hero.pose.bones
        mw = hero.matrix_world
        samples = []
        hh, ht = mw @ pb["head"].head, mw @ pb["head"].tail
        samples.append((inv @ (hh + (ht - hh) * 0.5), 0.115 * s_, "head"))
        for bn, r in (("upper_arm.L", 0.058), ("forearm.L", 0.045), ("hand.L", 0.045)):
            a, b = inv @ (mw @ pb[bn].head), inv @ (mw @ pb[bn].tail)
            for k in range(6):
                samples.append((a + (b - a) * (k / 5), r * s_, bn))
        for p, r, nm in samples:
            ys = y_side(p.z)
            if abs(p.y - ys) < r:
                ok = (p.x - r >= Wn["x_rear"] and p.x + r <= x_a(p.z) and p.z - r >= Wn["sill"] - 0.02 and p.z + r <= Wn["top"])
                if not ok:
                    viol.append(f"f{f} {nm} ({p.x:.2f},{p.y:.2f},{p.z:.2f})")
        max_out = max(max_out, samples[0][0].y - y_side(samples[0][0].z))
    allok &= result(8, "HERO head/arm clear the door frame 505-600", not viol,
                    (f"{len(viol)} contacts: " + "; ".join(viol[:5])) if viol else f"head reaches {max_out:+.2f} m beyond the window line")

    # 9 unique black paint
    blacks = []
    for m in bpy.data.materials:
        if m.name.startswith("PAINT_"):
            c = m.node_tree.nodes["Principled BSDF"].inputs["Base Color"].default_value
            if max(c[0], c[1], c[2]) < 0.05:
                blacks.append(m.name)
    allok &= result(9, "coupe is the only near-black car paint", blacks == ["PAINT_CAR_coupe"], f"{blacks}")

    # 10 lip-sync keys
    keys = []
    ad = hero.animation_data
    from bpy_extras import anim_utils
    cb = anim_utils.action_get_channelbag_for_slot(ad.action, ad.action_slot)
    for fc in cb.fcurves:
        if fc.data_path == 'pose.bones["jaw"].rotation_euler':
            keys += [kp for kp in fc.keyframe_points if K.LINE_START <= kp.co.x <= K.LINE_END and abs(kp.co.y) > 1e-4]
    src = sc.get("lipsync_source", "?")
    allok &= result(10, "jaw keys between 515-565", len(keys) > 0, f"{len(keys)} keys, source: {src} ({sc.get('lipsync_duration', 0):.2f}s)")
    out(f"\nOVERALL: {'ALL PASS' if allok else 'SOME CHECKS FAILED'}")
    os.makedirs(K.REVIEW_DIR, exist_ok=True)
    with open(os.path.join(K.REVIEW_DIR, "check_report.txt"), "w") as fh:
        fh.write("\n".join(LINES) + "\n")
    return allok


if __name__ == "__main__":
    ok = main()
    sys.exit(0 if ok else 1)
