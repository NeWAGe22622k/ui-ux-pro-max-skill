"""Characters: proxy mannequins (default), FBX import or MPFB2 (if available).

Armature space == character space: +X forward, +Y left, +Z up (origin = hip centre for seated rigs,
ground between the feet for standing rigs). Rest pose for seated characters = hands on the wheel.

Poses are described by a small dict (see Pose) and solved here with simple FK + analytic 2-bone IK,
then written as Euler XYZ keys on the pose bones.
"""
import math
import os
import bpy
from mathutils import Matrix, Vector, Euler, Quaternion

import config as K
from util import MeshBuilder, mat, subsurf, link, set_keys

COL = "CHARS"
BONES = ["root", "pelvis", "spine", "chest", "neck", "head", "jaw",
         "shoulder.L", "upper_arm.L", "forearm.L", "hand.L",
         "shoulder.R", "upper_arm.R", "forearm.R", "hand.R",
         "thigh.L", "shin.L", "thigh.R", "shin.R"]
PARENT = {"root": None, "pelvis": "root", "spine": "pelvis", "chest": "spine", "neck": "chest", "head": "neck",
          "jaw": "head", "shoulder.L": "chest", "upper_arm.L": "shoulder.L", "forearm.L": "upper_arm.L",
          "hand.L": "forearm.L", "shoulder.R": "chest", "upper_arm.R": "shoulder.R", "forearm.R": "upper_arm.R",
          "hand.R": "forearm.R", "thigh.L": "pelvis", "shin.L": "thigh.L", "thigh.R": "pelvis", "shin.R": "thigh.R"}
MIXAMO = {"pelvis": "mixamorig:Hips", "spine": "mixamorig:Spine", "chest": "mixamorig:Spine2",
          "neck": "mixamorig:Neck", "head": "mixamorig:Head", "shoulder.L": "mixamorig:LeftShoulder",
          "upper_arm.L": "mixamorig:LeftArm", "forearm.L": "mixamorig:LeftForeArm", "hand.L": "mixamorig:LeftHand",
          "shoulder.R": "mixamorig:RightShoulder", "upper_arm.R": "mixamorig:RightArm",
          "forearm.R": "mixamorig:RightForeArm", "hand.R": "mixamorig:RightHand",
          "thigh.L": "mixamorig:LeftUpLeg", "shin.L": "mixamorig:LeftLeg", "thigh.R": "mixamorig:RightUpLeg",
          "shin.R": "mixamorig:RightLeg"}

# --------------------------------------------------------------------------- rest skeletons (1.78 m reference)


def _mirror(d):
    out = dict(d)
    for k, (h, t) in d.items():
        if k.endswith(".L"):
            out[k[:-2] + ".R"] = ((h[0], -h[1], h[2]), (t[0], -t[1], t[2]))
    return out


SEATED = _mirror({
    "root": ((0, 0, -0.10), (0, 0, 0.0)),
    "pelvis": ((0, 0, 0.0), (-0.02, 0, 0.12)),
    "spine": ((-0.02, 0, 0.12), (-0.05, 0, 0.32)),
    "chest": ((-0.05, 0, 0.32), (-0.08, 0, 0.52)),
    "neck": ((-0.08, 0, 0.52), (-0.065, 0, 0.62)),
    "head": ((-0.065, 0, 0.62), (-0.045, 0, 0.84)),
    "jaw": ((-0.075, 0, 0.695), (0.045, 0, 0.64)),
    "shoulder.L": ((-0.07, 0.03, 0.485), (-0.08, 0.18, 0.475)),
    "upper_arm.L": ((-0.08, 0.19, 0.47), (0.10, 0.25, 0.24)),
    "forearm.L": ((0.10, 0.25, 0.24), (0.36, 0.17, 0.42)),
    "hand.L": ((0.36, 0.17, 0.42), (0.44, 0.15, 0.48)),
    "thigh.L": ((0, 0.10, -0.02), (0.44, 0.12, 0.04)),
    "shin.L": ((0.44, 0.12, 0.04), (0.77, 0.12, -0.26)),
})
SEATED_LOW = dict(SEATED)
SEATED_LOW.update(_mirror({
    "thigh.L": ((0, 0.10, -0.02), (0.45, 0.12, 0.07)),
    "shin.L": ((0.45, 0.12, 0.07), (0.88, 0.12, -0.06)),
}))
STANDING = _mirror({
    "root": ((0, 0, 0.0), (0, 0, 0.10)),
    "pelvis": ((0, 0, 0.94), (0, 0, 1.06)),
    "spine": ((0, 0, 1.06), (0.0, 0, 1.26)),
    "chest": ((0.0, 0, 1.26), (-0.01, 0, 1.46)),
    "neck": ((-0.01, 0, 1.46), (0.0, 0, 1.56)),
    "head": ((0.0, 0, 1.56), (0.02, 0, 1.78)),
    "jaw": ((-0.01, 0, 1.635), (0.11, 0, 1.58)),
    "shoulder.L": ((0.0, 0.03, 1.425), (-0.01, 0.18, 1.415)),
    "upper_arm.L": ((-0.01, 0.19, 1.41), (-0.02, 0.22, 1.11)),
    "forearm.L": ((-0.02, 0.22, 1.11), (0.03, 0.22, 0.84)),
    "hand.L": ((0.03, 0.22, 0.84), (0.04, 0.22, 0.74)),
    "thigh.L": ((0, 0.10, 0.92), (0.01, 0.11, 0.49)),
    "shin.L": ((0.01, 0.11, 0.49), (-0.01, 0.11, 0.07)),
})


def _scaled(table, s):
    return {k: (Vector(h) * s, Vector(t) * s) for k, (h, t) in table.items()}

# --------------------------------------------------------------------------- body building


def _build_armature(name, joints):
    ad = bpy.data.armatures.new(name + "_rig")
    ob = bpy.data.objects.new(name, ad)
    link(ob, COL)
    ad.display_type = "STICK"
    bpy.context.view_layer.objects.active = ob
    bpy.ops.object.mode_set(mode="EDIT")
    for b in BONES:
        eb = ad.edit_bones.new(b)
        h, t = joints[b]
        eb.head, eb.tail = h, t
        eb.roll = 0.0
        eb.use_deform = False
    for b in BONES:
        if PARENT[b]:
            ad.edit_bones[b].parent = ad.edit_bones[PARENT[b]]
            ad.edit_bones[b].use_connect = False
    bpy.ops.object.mode_set(mode="OBJECT")
    for pb in ob.pose.bones:
        pb.rotation_mode = "XYZ"
    ob.hide_render = True
    return ob


def _attach(arm, bone, mb, name, coll=COL, sub=True):
    ob = mb.to_object(name, coll)
    ob.parent = arm
    ob.parent_type = "BONE"
    ob.parent_bone = bone
    b = arm.data.bones[bone]
    tail_m = Matrix.Translation(b.tail_local) @ b.matrix_local.to_3x3().to_4x4()
    ob.matrix_parent_inverse = tail_m.inverted()
    if sub:
        subsurf(ob, 1)
    return ob


def _seg(joints, b):
    h, t = joints[b]
    return Vector(h), Vector(t)


def build_proxy(name, kind, height, look, girth=1.0, seated=True, low=False, female=False):
    """look: dict of material keys -> colours. Returns armature object."""
    s = height / 1.78
    table = (SEATED_LOW if low else SEATED) if seated else STANDING
    J = _scaled(table, s)
    arm = _build_armature(name, J)
    M = {k: mat(f"CHR_{name[4:]}_{k}", v, rough=0.55 if k != "skin" else 0.45)
         for k, v in look.items() if not isinstance(v, str)}
    g = girth

    def part(bone, fn, label, sub=True):
        mb = MeshBuilder()
        fn(mb)
        return _attach(arm, bone, mb, f"{name}_{label}", sub=sub)

    sh, st = _seg(J, "spine")
    ch, ct = _seg(J, "chest")
    ph, pt = _seg(J, "pelvis")
    torso_top = look.get("top_key", "top")
    # pelvis / hips
    part("pelvis", lambda mb: mb.capsule(M["bottom"], ph + Vector((0.0, 0, 0.02 * s)), pt, 0.13 * s * g, sx=0.95, sy=1.25), "hips")
    part("spine", lambda mb: mb.capsule(M[torso_top], sh, st, 0.135 * s * g, sx=0.85, sy=1.3), "belly")
    part("chest", lambda mb: mb.capsule(M[torso_top], ch, ct - Vector((0, 0, 0.04 * s)), 0.15 * s * g, sx=0.75, sy=1.35, r2=0.13 * s * g), "chest")
    nh, nt = _seg(J, "neck")
    part("neck", lambda mb: mb.capsule(M["skin"], nh, nt, 0.05 * s), "neck")
    hh, ht = _seg(J, "head")
    hc = hh + (ht - hh) * 0.5

    def head(mb):
        mb.sphere(M["skin"], hc + Vector((0.005, 0, 0.01)) * s, 0.105 * s, scale=(1.05, 0.88, 1.1), segs=20, rings=12)
        mb.sphere(M["skin"], hc + Vector((0.105, 0, -0.005)) * s, 0.022 * s, scale=(1.0, 0.8, 1.3), segs=10, rings=6)
        for side in (1, -1):
            mb.sphere(M["skin"], hc + Vector((-0.01, side * 0.093, 0.0)) * s, 0.025 * s, scale=(0.6, 0.35, 1.0), segs=10, rings=6)
        mb.sphere(M["hair"], hc + Vector((-0.012, 0, 0.035)) * s, 0.104 * s, scale=(1.05, 0.9, 1.0), segs=20, rings=12)
    part("head", head, "head")
    jh, jt = _seg(J, "jaw")

    def jaw(mb):
        mb.sphere(M["skin"], jh + (jt - jh) * 0.42 + Vector((0, 0, 0.004 * s)), 0.062 * s, scale=(0.95, 1.1, 0.5), segs=16, rings=8)
    part("jaw", jaw, "jaw")
    for side in ("L", "R"):
        sgn = 1 if side == "L" else -1
        uh, ut = _seg(J, f"upper_arm.{side}")
        fh, ft = _seg(J, f"forearm.{side}")
        ahh, aht = _seg(J, f"hand.{side}")
        part(f"shoulder.{side}", lambda mb, uh=uh: mb.sphere(M[torso_top], uh + Vector((0, 0, 0.005)), 0.062 * s * g, segs=12, rings=8), f"shoulder_{side}")
        sleeve = look.get("sleeve", "full")

        def upper(mb, uh=uh, ut=ut):
            if sleeve == "short":
                mid = uh + (ut - uh) * 0.45
                mb.capsule(M[torso_top], uh, mid, 0.058 * s * g, r2=0.056 * s * g)
                mb.capsule(M["skin"], mid, ut, 0.047 * s * g, r2=0.043 * s)
            else:
                mb.capsule(M[torso_top], uh, ut, 0.055 * s * g, r2=0.048 * s * g)
                if sleeve == "rolled":
                    mb.capsule(M[torso_top], ut - (ut - uh) * 0.12, ut + (ft - fh).normalized() * 0.02, 0.056 * s * g)
        part(f"upper_arm.{side}", upper, f"upper_arm_{side}")
        farm_mat = M["skin"] if sleeve in ("rolled", "short") else M[torso_top]
        part(f"forearm.{side}", lambda mb, fh=fh, ft=ft, fm=farm_mat: mb.capsule(fm, fh, ft, 0.043 * s * g, r2=0.034 * s), f"forearm_{side}")

        def hand(mb, ahh=ahh, aht=aht):
            d = (aht - ahh)
            rot = d.normalized().to_track_quat("Z", "X").to_matrix()
            mb.box(M["skin"], ahh + d * 0.55, (0.035 * s, 0.085 * s, 0.11 * s), rot=rot)
            mb.capsule(M["skin"], ahh + d * 0.3 + Vector((0, sgn * 0.035, 0)) * s, ahh + d * 0.75 + Vector((0, sgn * 0.05, 0.0)) * s, 0.014 * s)
        part(f"hand.{side}", hand, f"hand_{side}")
        th, tt = _seg(J, f"thigh.{side}")
        shh, sht = _seg(J, f"shin.{side}")
        part(f"thigh.{side}", lambda mb, th=th, tt=tt: mb.capsule(M["bottom"], th, tt, 0.082 * s * g, r2=0.06 * s * g), f"thigh_{side}")

        def shin(mb, shh=shh, sht=sht):
            mb.capsule(M["bottom"], shh, sht, 0.058 * s * g, r2=0.045 * s)
            fwd = Vector((1, 0, 0))
            mb.box(M["shoes"], sht + fwd * 0.07 * s + Vector((0, 0, -0.04 * s)), (0.25 * s, 0.10 * s, 0.08 * s))
        part(f"shin.{side}", shin, f"shin_{side}")
    arm["height"] = height
    arm["kind"] = kind
    return arm


def add_tie(arm, color):
    J = {b.name: (b.head_local, b.tail_local) for b in arm.data.bones}
    ch, ct = J["chest"]
    m = mat("CHR_hero_tie", color, rough=0.5)
    mb = MeshBuilder()
    s = arm["height"] / 1.78
    top = ct + Vector((0.115 * s, 0.0, -0.05 * s))
    mb.box(m, top, (0.025 * s, 0.05 * s, 0.045 * s))                      # loosened knot, low
    mb.slab_between(m, top + Vector((0, 0, -0.02 * s)), top + Vector((0.035 * s, -0.025 * s, -0.36 * s)), 0.06 * s, 0.012 * s)
    return _attach(arm, "chest", mb, "CHR_hero_tie", sub=False)


def add_watch(arm, color):
    m = mat("CHR_horner_watch", color, metallic=1.0, rough=0.25)
    b = arm.data.bones["forearm.L"]
    h, t = b.head_local, b.tail_local
    p = h + (t - h) * 0.9
    mb = MeshBuilder()
    d = (t - h).normalized()
    rot = d.to_track_quat("Z", "X").to_matrix()
    mb.torus(m, p, 0.036, 0.008, rot=rot, segs=20, rsegs=6)
    mb.cyl(m, p + rot @ Vector((0, 0.037, 0)), 0.016, 0.008, axis="Y", rot=rot, segs=16)
    return _attach(arm, "forearm.L", mb, "CHR_horner_watch", sub=False)


def add_glasses(arm, color):
    m = mat("CHR_horner_glasses", color, metallic=0.3, rough=0.05, coat=1.0)
    b = arm.data.bones["head"]
    h, t = b.head_local, b.tail_local
    s = arm["height"] / 1.78
    hc = h + (t - h) * 0.5
    mb = MeshBuilder()
    for side in (1, -1):
        mb.box(m, hc + Vector((0.103, side * 0.034, 0.028)) * s, (0.012 * s, 0.05 * s, 0.032 * s))
        mb.slab_between(m, hc + Vector((0.10, side * 0.06, 0.03)) * s, hc + Vector((-0.02, side * 0.088, 0.025)) * s, 0.008, 0.006)
    mb.box(m, hc + Vector((0.108, 0, 0.033)) * s, (0.01 * s, 0.02 * s, 0.008 * s))
    ob = mb.to_object("CHR_horner_glasses", COL)
    ob.parent = arm
    ob.parent_type = "BONE"
    ob.parent_bone = "head"
    tail_m = Matrix.Translation(b.tail_local) @ b.matrix_local.to_3x3().to_4x4()
    ob.matrix_parent_inverse = tail_m.inverted()
    return ob


def add_envelope(arm):
    m = mat("CHR_hero_envelope", K.C["envelope"], rough=0.6)
    b = arm.data.bones["hand.R"]
    h, t = b.head_local, b.tail_local
    d = (t - h).normalized()
    rot = d.to_track_quat("Z", "X").to_matrix()
    mb = MeshBuilder()
    mb.box(m, h + d * 0.12, (0.004, 0.16, 0.24), rot=rot)
    return _attach(arm, "hand.R", mb, "CHR_hero_envelope", sub=False)


def add_tray(arm):
    m = mat("CHR_hawker_tray", K.C["tray"], metallic=0.8, rough=0.35)
    o = mat("CHR_hawker_oranges", K.C["oranges"], rough=0.5)
    b = arm.data.bones["head"]
    top = b.tail_local + Vector((0, 0, 0.02))
    mb = MeshBuilder()
    mb.cyl(m, top + Vector((0, 0, 0.03)), 0.33, 0.06, segs=28, r2=0.36)
    for i, (x, y) in enumerate([(0, 0), (0.12, 0.05), (-0.1, 0.1), (0.05, -0.13), (-0.12, -0.08), (0.18, -0.1), (-0.02, 0.2)]):
        mb.sphere(o, top + Vector((x, y, 0.1 + (0.05 if i == 0 else 0))), 0.045, segs=10, rings=6)
    return _attach(arm, "head", mb, "CHR_hawker_tray", sub=False)


def add_wrapper(arm, c1, c2):
    m1 = mat("CHR_hawker_wrapper", c1, rough=0.7)
    m2 = mat("CHR_hawker_wrapper_band", c2, rough=0.7)
    b = arm.data.bones["pelvis"]
    p = b.head_local
    mb = MeshBuilder()
    mb.cyl(m1, p + Vector((0, 0, -0.30)), 0.17, 0.72, segs=20, r2=0.15)
    mb.cyl(m2, p + Vector((0, 0, -0.40)), 0.215, 0.1, segs=20, r2=0.205)
    mb.cyl(m2, p + Vector((0, 0, -0.12)), 0.19, 0.08, segs=20)
    return _attach(arm, "pelvis", mb, "CHR_hawker_wrapper", sub=False)


def add_headscarf(arm, c):
    m = mat("CHR_hawker_scarf", c, rough=0.7)
    b = arm.data.bones["head"]
    h, t = b.head_local, b.tail_local
    hc = h + (t - h) * 0.5
    mb = MeshBuilder()
    mb.sphere(m, hc + Vector((-0.01, 0, 0.045)), 0.112, scale=(1.08, 0.95, 0.9), segs=18, rings=10)
    return _attach(arm, "head", mb, "CHR_hawker_scarf", sub=False)

# --------------------------------------------------------------------------- import paths


def _try_fbx(name, path):
    if not os.path.exists(path):
        return None
    before = set(bpy.data.objects)
    bpy.ops.import_scene.fbx(filepath=path)
    new = [o for o in bpy.data.objects if o not in before]
    arm = next((o for o in new if o.type == "ARMATURE"), None)
    if arm is None:
        return None
    arm.name = name
    for o in new:
        link(o, COL)
        if o is not arm and o.type == "MESH":
            o.name = f"{name}_body"
    for pb in arm.pose.bones:
        pb.rotation_mode = "XYZ"
    arm["source"] = "fbx"
    return arm


def _mpfb_available():
    try:
        import addon_utils
        return any(m.__name__.startswith("mpfb") and addon_utils.check(m.__name__)[1] for m in addon_utils.modules())
    except Exception:
        return False


def make_character(name, fbx, **proxy_kw):
    """Source priority: FBX (Mixamo naming) > MPFB2 > proxy mannequin.

    FBX/MPFB rigs are accepted only if they carry the Mixamo bone names used by the pose mapper; the
    proxy path is always the fallback so the scene always builds.
    """
    arm = _try_fbx(name, os.path.join(K.CHAR_DIR, fbx)) if fbx else None
    if arm is not None and all(v in arm.pose.bones for v in MIXAMO.values()):
        return arm, "fbx"
    if arm is not None:
        print(f"[characters] {fbx} lacks Mixamo bone names -> using proxy")
        for o in [arm] + list(arm.children_recursive):
            bpy.data.objects.remove(o, do_unlink=True)
    if _mpfb_available():
        print("[characters] MPFB2 is installed, but its rig differs from the pose mapper; using proxy for determinism")
    arm = build_proxy(name, **proxy_kw)
    arm["source"] = "proxy"
    return arm, "proxy"

# --------------------------------------------------------------------------- pose solving


class Pose(dict):
    """keys: root (vec), rot: {bone: (rx, ry, rz) deg in carried character axes},
    ikL / ikR: wrist target (vec) or None, poleL / poleR (vec), handL / handR (deg)."""


def blank_pose():
    return Pose(root=Vector((0, 0, 0)), rot={}, ikL=None, ikR=None, poleL=Vector((0, 1, -1)),
                poleR=Vector((0, -1, -1)), handL=Vector((0, 0, 0)), handR=Vector((0, 0, 0)))


def blend(a, b, t):
    out = blank_pose()
    out["root"] = a["root"].lerp(b["root"], t)
    keys = set(a["rot"]) | set(b["rot"])
    for k in keys:
        va = Vector(a["rot"].get(k, (0, 0, 0)))
        vb = Vector(b["rot"].get(k, (0, 0, 0)))
        out["rot"][k] = tuple(va.lerp(vb, t))
    for side in ("L", "R"):
        ta, tb = a[f"ik{side}"], b[f"ik{side}"]
        if ta is None and tb is None:
            out[f"ik{side}"] = None
        else:
            out[f"ik{side}"] = (ta if ta is not None else tb).lerp(tb if tb is not None else ta, t)
        out[f"pole{side}"] = a[f"pole{side}"].lerp(b[f"pole{side}"], t)
        out[f"hand{side}"] = a[f"hand{side}"].lerp(b[f"hand{side}"], t)
    return out


def copy_pose(p, **kw):
    q = blank_pose()
    q["root"] = Vector(p["root"])
    q["rot"] = dict(p["rot"])
    for k in ("ikL", "ikR"):
        q[k] = None if p[k] is None else Vector(p[k])
    for k in ("poleL", "poleR", "handL", "handR"):
        q[k] = Vector(p[k])
    for k, v in kw.items():
        if k == "rot":
            q["rot"].update(v)
        else:
            q[k] = Vector(v) if (v is not None and not isinstance(v, dict)) else v
    return q


def _eul(v):
    return Euler([math.radians(a) for a in v], "XYZ").to_matrix()


class Solver:
    def __init__(self, arm):
        self.arm = arm
        self.rest = {}
        for b in arm.data.bones:
            self.rest[b.name] = (b.head_local.copy(), b.tail_local.copy(), b.matrix_local.to_3x3())
        self.prev = {}

    def rest_wrist(self, side):
        return self.rest[f"forearm.{side}"][1].copy()

    def solve(self, pose):
        """Return {bone: local 3x3 rotation}, plus posed joint positions."""
        A = {}
        P = {}
        R = {}
        root_off = pose["root"]
        for b in BONES:
            if b not in self.rest:
                continue
            h, t, Mb = self.rest[b]
            par = PARENT[b]
            Ap = A[par] if par else Matrix.Identity(3)
            if par:
                ph = self.rest[par][0]
                P[b] = P[par] + Ap @ (h - ph)
            else:
                P[b] = h + root_off
            rb = _eul(pose["rot"].get(b, (0, 0, 0)))
            side = b[-1] if b[-2:] in (".L", ".R") else None
            if side and b.startswith("upper_arm") and pose[f"ik{side}"] is not None:
                rb = self._ik_upper(b, side, pose, Ap, P[b])
            elif side and b.startswith("forearm") and pose[f"ik{side}"] is not None:
                rb = self._ik_fore(b, side, pose, Ap, P[b])
            elif side and b.startswith("hand"):
                rb = _eul(pose[f"hand{side}"])
            A[b] = Ap @ rb
            R[b] = rb
        self._last_A, self._last_P = A, P
        return R, A, P

    def _ik_upper(self, b, side, pose, Ap, S):
        h, t, _ = self.rest[b]
        fh, ft, _ = self.rest[f"forearm.{side}"]
        l1, l2 = (t - h).length, (ft - fh).length
        T = pose[f"ik{side}"]
        d = T - S
        dist = min(max(d.length, abs(l1 - l2) + 1e-3), l1 + l2 - 1e-3)
        u = d.normalized()
        pole = pose[f"pole{side}"]
        v = (pole - u * pole.dot(u))
        v = v.normalized() if v.length > 1e-6 else Vector((0, 0, -1))
        a = math.acos(max(-1, min(1, (l1 * l1 + dist * dist - l2 * l2) / (2 * l1 * dist))))
        E = S + l1 * (math.cos(a) * u + math.sin(a) * v)
        self._elbow = (side, E, S + u * dist)
        want = (E - S).normalized()
        restdir = (t - h).normalized()
        local_want = Ap.inverted() @ want
        return restdir.rotation_difference(local_want).to_matrix()

    def _ik_fore(self, b, side, pose, Ap, E):
        h, t, _ = self.rest[b]
        _, E2, W = self._elbow
        want = (W - E2).normalized()
        restdir = (t - h).normalized()
        local_want = Ap.inverted() @ want
        return restdir.rotation_difference(local_want).to_matrix()

    def local_eulers(self, pose):
        R, A, P = self.solve(pose)
        out = {}
        for b, rb in R.items():
            Mb = self.rest[b][2]
            L = Mb.inverted() @ rb @ Mb
            prev = self.prev.get(b)
            e = L.to_euler("XYZ", prev) if prev is not None else L.to_euler("XYZ")
            self.prev[b] = e
            out[b] = e
        return out, A, P


def key_timeline(arm, pose_fn, frames, jaw_fn=None, jaw_frames=None):
    """Sample pose_fn(frame) at frames and write Euler keys on every bone (fast path)."""
    solver = Solver(arm)
    data = {b: ([], [], []) for b in BONES}
    roots = ([], [], [])
    for f in frames:
        pose = pose_fn(f)
        eul, A, P = solver.local_eulers(pose)
        for b in BONES:
            e = eul.get(b, Euler((0, 0, 0)))
            for i in range(3):
                data[b][i].append(e[i])
        rl = arm.data.bones["root"].matrix_local.to_3x3().inverted() @ pose["root"]
        for i in range(3):
            roots[i].append(rl[i])
    for b in BONES:
        if b == "jaw" and jaw_fn is not None:
            continue
        for i in range(3):
            set_keys(arm, f'pose.bones["{b}"].rotation_euler', i, frames, data[b][i])
    for i in range(3):
        set_keys(arm, 'pose.bones["root"].location', i, frames, roots[i])
    if jaw_fn is not None:
        Mb = arm.data.bones["jaw"].matrix_local.to_3x3()
        vals = ([], [], [])
        for f in jaw_frames:
            L = Mb.inverted() @ _eul((0, jaw_fn(f), 0)) @ Mb
            e = L.to_euler("XYZ")
            for i in range(3):
                vals[i].append(e[i])
        for i in range(3):
            set_keys(arm, 'pose.bones["jaw"].rotation_euler', i, jaw_frames, vals[i])
    return solver


def head_world(arm, frame=None):
    if frame is not None:
        bpy.context.scene.frame_set(frame)
    pb = arm.pose.bones["head"]
    h = arm.matrix_world @ pb.head
    t = arm.matrix_world @ pb.tail
    return h + (t - h) * 0.5
