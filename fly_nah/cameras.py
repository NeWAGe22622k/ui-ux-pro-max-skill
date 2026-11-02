"""10 shot cameras + S03B fallback. H = HERO head centre computed from the rig."""
import math
import bpy
from mathutils import Vector, Matrix

import config as K
import characters as CH
from util import link, empty, key_vec, set_keys, look_rot, fcurve


def _cam(name, lens, fstop):
    cd = bpy.data.cameras.new(name)
    cd.lens = lens
    cd.sensor_width = K.SENSOR
    cd.sensor_fit = "HORIZONTAL"
    shot = name[4:]
    cd.clip_start = K.CLIP_START.get(shot) or 0.1
    cd.clip_end = K.CLIP_END
    cd.dof.use_dof = True
    cd.dof.aperture_fstop = fstop
    ob = bpy.data.objects.new(name, cd)
    link(ob, "CAMERAS")
    return ob


def _track(cam, target):
    c = cam.constraints.new("TRACK_TO")
    c.target = target
    c.track_axis = "TRACK_NEGATIVE_Z"
    c.up_axis = "UP_Y"
    return c


def _ranges(cam, shot):
    for s in K.SHOTS:
        if s[0] == shot:
            cam["story_range"] = (s[1], s[2])
            cam["render_range"] = (s[3], s[4])
            cam["file_stem"] = s[5]


def H(hero, f):
    return CH.head_world(hero, f).copy()


def build(cars, hero, horner):
    sc = bpy.context.scene
    sed, cou = cars["CAR_hero_sedan"], cars["CAR_coupe"]
    mba = cars["CAR_minibus_ahead"]
    out = {}

    def world_of(ob, local, f):
        sc.frame_set(f)
        return ob.matrix_world @ Vector(local)

    # ---------------------------------------------------------------- S01 drone oner
    cam = _cam("CAM_S01", 28.0, K.CAM["S01"]["fstop"])
    tgt = empty("EMP_S01_target", collection="CAMERAS")
    foc = empty("EMP_S01_focus", collection="CAMERAS")
    frames, locs, tlocs = [], [], []
    for f, c, t in K.S01_KEYS:
        h = H(hero, f)
        if isinstance(c, tuple) and c and c[0] == "H":
            c = h + Vector(c[1])
        tl = h if t == "H" else Vector(t)
        frames.append(f)
        locs.append(Vector(c))
        tlocs.append(tl)
    key_vec(cam, "location", frames, locs)
    key_vec(tgt, "location", frames, tlocs)
    _track(cam, tgt)
    (fa, la), (fb, lb) = K.S01_LENS
    set_keys(cam.data, "lens", 0, [37, fa, fb], [la, la, lb])
    set_keys(cam.data, "clip_start", 0, [f for f, _ in K.S01_CLIP_KEYS], [v for _, v in K.S01_CLIP_KEYS])
    ff, fl = [], []
    (f_ahead, _), (f_h, _) = K.S01_FOCUS
    for f in (37, 100, 150, 220, 260, f_ahead):
        ff.append(f)
        fl.append(world_of(mba, (-2.6, 0, 1.0), f))
    for f in (f_h, 336, 348):
        ff.append(f)
        fl.append(H(hero, f))
    key_vec(foc, "location", ff, fl)
    cam.data.dof.focus_object = foc
    out["S01"] = cam

    # ---------------------------------------------------------------- S02 POV (parented to the sedan)
    cfg = K.CAM["S02"]
    cam = _cam("CAM_S02", cfg["lens"], cfg["fstop"])
    cam.parent = sed
    sc.frame_set(360)
    inv = sed.matrix_world.inverted()
    h_local = inv @ H(hero, 360)
    p = h_local + Vector(cfg["offset"])
    brake = inv @ world_of(mba, (-2.62, 0, 0.85), 360)
    cam.location = p
    e = look_rot(p, brake).to_euler()
    for i in range(3):
        fc = set_keys(cam, "rotation_euler", i, [313, 408], [e[i], e[i]])
        m = fc.modifiers.new("NOISE")
        m.strength = math.radians(2 * cfg["noise_deg"])
        m.scale = 14.0
        m.phase = 3.0 + 7.0 * i
    foc = empty("EMP_S02_focus", collection="CAMERAS", parent=sed)
    foc.location = brake
    cam.data.dof.focus_object = foc
    out["S02"] = cam

    # ---------------------------------------------------------------- S03 mirror close-up + S03B
    cfg = K.CAM["S03"]
    cam = _cam("CAM_S03", cfg["lens"], cfg["fstop"])
    cam.parent = sed
    mp = Vector(K.MIRROR["pos"])
    cp = Vector(K.MIRROR["aim_cam"])
    cam.location = cp
    cam.rotation_euler = look_rot(cp, mp).to_euler()
    foc = empty("EMP_S03_focus", collection="CAMERAS", parent=sed)
    foc.location = cp + (mp - cp).normalized() * ((mp - cp).length + 6.6)
    cam.data.dof.focus_object = foc
    out["S03"] = cam

    cfg = K.CAM["S03B"]
    cam = _cam("CAM_S03B", cfg["lens"], cfg["fstop"])
    cam.parent = sed
    cp = Vector(cfg["pos"])
    sc.frame_set(400)
    tgt_w = cou.matrix_world @ Vector((2.40, 0, 0.55))
    t_local = sed.matrix_world.inverted() @ tgt_w
    cam.location = cp
    cam.rotation_euler = look_rot(cp, t_local).to_euler()
    foc = empty("EMP_S03B_focus", collection="CAMERAS", parent=sed)
    foc.location = t_local
    cam.data.dof.focus_object = foc
    out["S03B"] = cam

    # ---------------------------------------------------------------- S04 / S07 into the coupe
    sc.frame_set(430)
    A, B = Vector(K.COUPE["A"]), Vector(K.COUPE["B"])
    ws_x = (A.x + B.x) / 2
    hy = K.COUPE["seat_hip"][1]
    for shot in ("S04", "S07"):
        cfg = K.CAM[shot]
        cam = _cam(f"CAM_{shot}", cfg["lens"], cfg["fstop"])
        tgt = empty(f"EMP_{shot}_target", collection="CAMERAS")
        foc = empty(f"EMP_{shot}_focus", collection="CAMERAS")
        a, b = (397, 492) if shot == "S04" else (577, 672)
        hh = H(horner, (a + b) // 2)
        if shot == "S04":
            p0 = cou.matrix_world @ Vector((ws_x + cfg["front"] + cfg["push"] / 2, hy * 0.6, cfg["height"]))
            p1 = p0 - Vector((cfg["push"], 0, 0))
            key_vec(cam, "location", [a, b], [p0, p1])
            tgt.location = hh + Vector((0, 0, -0.08))
        else:
            p0 = cou.matrix_world @ Vector((ws_x + cfg["front"], hy * 0.55, cfg["height"]))
            key_vec(cam, "location", [a, b], [p0, p0 - Vector((0.05, 0, 0))])
            btn = cou.matrix_world @ Vector(K.COUPE_BUTTON)
            tgt.location = hh.lerp(btn, 0.5)
        foc.location = hh
        _track(cam, tgt)
        cam.data.dof.focus_object = foc
        out[shot] = cam

    # ---------------------------------------------------------------- S05 side profile
    cfg = K.CAM["S05"]
    cam = _cam("CAM_S05", cfg["lens"], cfg["fstop"])
    h = H(hero, 486)
    cam.location = h + Vector((0, cfg["side"], 0))
    cam.rotation_euler = look_rot(cam.location, h).to_euler()
    foc = empty("EMP_S05_focus", h, collection="CAMERAS")
    cam.data.dof.focus_object = foc
    out["S05"] = cam

    # ---------------------------------------------------------------- S06 front-left three-quarter
    cfg = K.CAM["S06"]
    cam = _cam("CAM_S06", cfg["lens"], cfg["fstop"])
    tgt = empty("EMP_S06_target", collection="CAMERAS")
    foc = empty("EMP_S06_focus", collection="CAMERAS")
    h = H(hero, 540)
    p0 = Vector((h.x + cfg["ahead"], h.y + cfg["left"], cfg["z"]))
    drift = Vector((-0.12, -0.16, 0.0)).normalized() * cfg["drift"]
    key_vec(cam, "location", [493, 612], [p0, p0 + drift])
    key_vec(tgt, "location", [493, 520, 612], [H(hero, 500) * 0.5 + h * 0.5, h, h])
    fr = list(range(493, 613, 6))
    key_vec(foc, "location", fr, [H(hero, f) for f in fr])
    _track(cam, tgt)
    cam.data.dof.focus_object = foc
    out["S06"] = cam

    # ---------------------------------------------------------------- S08 wide lift-off
    cfg = K.CAM["S08"]
    cam = _cam("CAM_S08", cfg["lens"], cfg["fstop"])
    cam.location = cfg["pos"]
    cam.rotation_euler = look_rot(Vector(cfg["pos"]), Vector(cfg["look"])).to_euler()
    foc = empty("EMP_S08_focus", (-2.5, -9.4, 1.0), collection="CAMERAS")
    cam.data.dof.focus_object = foc
    out["S08"] = cam

    # ---------------------------------------------------------------- S09 low, looking up at HERO
    cfg = K.CAM["S09"]
    cam = _cam("CAM_S09", cfg["lens"], cfg["fstop"])
    h = H(hero, 762)
    p = Vector((h.x + cfg["ahead"], h.y + cfg["side"], cfg["z"]))
    cam.location = p
    cam.rotation_euler = look_rot(p, h + Vector((0, 0, cfg["aim_up"]))).to_euler()
    foc = empty("EMP_S09_focus", h, collection="CAMERAS")
    cam.data.dof.focus_object = foc
    out["S09"] = cam

    # ---------------------------------------------------------------- S10 road level toward the sun
    cfg = K.CAM["S10"]
    cam = _cam("CAM_S10", cfg["lens"], cfg["fstop"])
    p0 = Vector(cfg["pos"])
    key_vec(cam, "location", [751, 846], [p0, p0 + Vector((cfg["creep"], 0, 0))])
    cam.rotation_euler = look_rot(p0, Vector(cfg["look"])).to_euler()
    foc = empty("EMP_S10_focus", (26.0, -10.5, 8.0), collection="CAMERAS")
    cam.data.dof.focus_object = foc
    out["S10"] = cam

    for shot, cam in out.items():
        _ranges(cam, shot)
    return out
