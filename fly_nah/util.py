"""Shared helpers: collections, materials, bmesh primitives, keyframing (Blender 5 layered actions)."""
import math
import bmesh
import bpy
from bpy_extras import anim_utils
from mathutils import Matrix, Vector, Euler, Quaternion

# ------------------------------------------------------------------ scene / collections

def clean_scene():
    for ob in list(bpy.data.objects):
        bpy.data.objects.remove(ob, do_unlink=True)
    for coll in (bpy.data.meshes, bpy.data.materials, bpy.data.cameras, bpy.data.lights, bpy.data.actions,
                 bpy.data.armatures, bpy.data.lightprobes, bpy.data.worlds, bpy.data.node_groups,
                 bpy.data.curves, bpy.data.images):
        for d in list(coll):
            coll.remove(d)
    for c in list(bpy.data.collections):
        bpy.data.collections.remove(c)


def coll(name):
    c = bpy.data.collections.get(name)
    if c is None:
        c = bpy.data.collections.new(name)
        bpy.context.scene.collection.children.link(c)
    return c


def link(ob, collection):
    for c in ob.users_collection:
        c.objects.unlink(ob)
    coll(collection).objects.link(ob)
    return ob


def empty(name, loc=(0, 0, 0), collection="EMPTIES", size=0.2, parent=None):
    ob = bpy.data.objects.new(name, None)
    ob.empty_display_size = size
    ob.location = loc
    link(ob, collection)
    if parent is not None:
        ob.parent = parent
    return ob

# ------------------------------------------------------------------ materials

_MATS = {}


def mat(name, color, metallic=0.0, rough=0.5, coat=0.0, coat_rough=0.03, emission=None, strength=0.0,
        transmission=0.0, ior=1.45, alpha=1.0, spec=0.5):
    if name in _MATS and _MATS[name].name in bpy.data.materials:
        return _MATS[name]
    m = bpy.data.materials.new(name)
    m.use_nodes = True
    b = m.node_tree.nodes.get("Principled BSDF")
    b.inputs["Base Color"].default_value = (*color, 1.0)
    b.inputs["Metallic"].default_value = metallic
    b.inputs["Roughness"].default_value = rough
    b.inputs["Coat Weight"].default_value = coat
    b.inputs["Coat Roughness"].default_value = coat_rough
    b.inputs["Specular IOR Level"].default_value = spec
    b.inputs["IOR"].default_value = ior
    if emission is not None:
        b.inputs["Emission Color"].default_value = (*emission, 1.0)
        b.inputs["Emission Strength"].default_value = strength
    if transmission > 0:
        b.inputs["Transmission Weight"].default_value = transmission
        b.inputs["Thin Wall"].default_value = True
        m.surface_render_method = "BLENDED"
        m.use_transparency_overlap = True
    if alpha < 1.0:
        b.inputs["Alpha"].default_value = alpha
        m.surface_render_method = "BLENDED"
    # Workbench / viewport colour
    vis = color if emission is None or strength == 0 else emission
    m.diffuse_color = (*vis, alpha if transmission == 0 else 0.12)
    m.metallic = metallic
    m.roughness = rough
    _MATS[name] = m
    return m


def bsdf(m):
    return m.node_tree.nodes.get("Principled BSDF")


def glass_mat(name="glass", tint=(0.75, 0.85, 0.85)):
    # Thin see-through glass: a dark, glossy 18 % layer over a clear 82 %. No refraction -- in EEVEE the
    # refraction samples the bright hazy sky probe and lays a milky veil over everything seen through a window.
    m = mat(name, tuple(c * 0.12 for c in tint), rough=0.03, ior=1.45, alpha=0.18)
    m.diffuse_color = (*tint, 0.12)   # Workbench look unchanged
    return m

# ------------------------------------------------------------------ bmesh primitives (all take a 4x4 matrix)


class MeshBuilder:
    """Accumulates geometry with material indices into one bmesh."""

    def __init__(self):
        self.bm = bmesh.new()
        self.mats = []

    def _mi(self, m):
        if m not in self.mats:
            self.mats.append(m)
        return self.mats.index(m)

    def _tag(self, faces, m):
        idx = self._mi(m)
        for f in faces:
            f.material_index = idx
            f.smooth = False
        return faces

    def _new_faces(self, before):
        return [f for f in self.bm.faces if f.index == -1 or f not in before]

    def _wrap(self, fn, m, smooth=False):
        before = set(self.bm.faces)
        fn()
        new = [f for f in self.bm.faces if f not in before]
        self._tag(new, m)
        if smooth:
            for f in new:
                f.smooth = True
        return new

    def box(self, m, center, size, rot=None, smooth=False):
        def fn():
            mat4 = Matrix.Translation(center)
            if rot is not None:
                mat4 = mat4 @ rot.to_4x4()
            mat4 = mat4 @ Matrix.Diagonal((*size, 1.0))
            bmesh.ops.create_cube(self.bm, size=1.0, matrix=mat4)
        return self._wrap(fn, m, smooth)

    def cyl(self, m, center, radius, depth, axis="Z", segs=24, rot=None, r2=None, smooth=True, cap=True):
        def fn():
            mat4 = Matrix.Translation(center)
            if rot is not None:
                mat4 = mat4 @ rot.to_4x4()
            if axis == "Y":
                mat4 = mat4 @ Matrix.Rotation(math.radians(90), 4, "X")
            elif axis == "X":
                mat4 = mat4 @ Matrix.Rotation(math.radians(90), 4, "Y")
            bmesh.ops.create_cone(self.bm, cap_ends=cap, segments=segs, radius1=radius,
                                  radius2=radius if r2 is None else r2, depth=depth, matrix=mat4)
        return self._wrap(fn, m, smooth)

    def sphere(self, m, center, radius, scale=(1, 1, 1), segs=16, rings=10, rot=None):
        def fn():
            mat4 = Matrix.Translation(center)
            if rot is not None:
                mat4 = mat4 @ rot.to_4x4()
            mat4 = mat4 @ Matrix.Diagonal((*scale, 1.0))
            bmesh.ops.create_uvsphere(self.bm, u_segments=segs, v_segments=rings, radius=radius, matrix=mat4)
        return self._wrap(fn, m, True)

    def torus(self, m, center, R, r, rot=None, segs=28, rsegs=8):
        def fn():
            mat4 = Matrix.Translation(center)
            if rot is not None:
                mat4 = mat4 @ rot.to_4x4()
            verts = []
            for i in range(segs):
                a = 2 * math.pi * i / segs
                ring = []
                for j in range(rsegs):
                    b = 2 * math.pi * j / rsegs
                    p = Vector(((R + r * math.cos(b)) * math.cos(a), (R + r * math.cos(b)) * math.sin(a),
                                r * math.sin(b)))
                    ring.append(self.bm.verts.new(mat4 @ p))
                verts.append(ring)
            for i in range(segs):
                for j in range(rsegs):
                    a, b = verts[i][j], verts[(i + 1) % segs][j]
                    c, d = verts[(i + 1) % segs][(j + 1) % rsegs], verts[i][(j + 1) % rsegs]
                    self.bm.faces.new((a, b, c, d))
        return self._wrap(fn, m, True)

    def capsule(self, m, p, q, r, sx=1.0, sy=1.0, segs=14, rings=5, r2=None):
        """Capsule from p to q (radius r at p, r2 at q), cross-section scaled (sx, sy)."""
        p, q = Vector(p), Vector(q)
        r2 = r if r2 is None else r2

        def fn():
            d = q - p
            L = d.length
            rot = d.normalized().to_track_quat("Z", "X").to_matrix().to_4x4() if L > 1e-6 else Matrix()
            mat4 = Matrix.Translation(p) @ rot
            rows = []
            # bottom hemisphere (radius r) then top hemisphere (radius r2) offset by L
            for k in range(rings, -1, -1):
                t = (math.pi / 2) * k / rings
                rows.append((-r * math.sin(t), r * math.cos(t)))
            for k in range(0, rings + 1):
                t = (math.pi / 2) * k / rings
                rows.append((L + r2 * math.sin(t), r2 * math.cos(t)))
            vrows = []
            for z, rad in rows:
                ring = []
                for i in range(segs):
                    a = 2 * math.pi * i / segs
                    ring.append(self.bm.verts.new(mat4 @ Vector((rad * sx * math.cos(a), rad * sy * math.sin(a), z))))
                vrows.append(ring)
            for k in range(len(vrows) - 1):
                for i in range(segs):
                    a, b = vrows[k][i], vrows[k][(i + 1) % segs]
                    c, dd = vrows[k + 1][(i + 1) % segs], vrows[k + 1][i]
                    if (a.co - dd.co).length < 1e-7 and (b.co - c.co).length < 1e-7:
                        continue
                    try:
                        self.bm.faces.new((a, b, c, dd))
                    except ValueError:
                        pass
            bmesh.ops.remove_doubles(self.bm, verts=[v for r_ in vrows for v in r_], dist=1e-6)
        return self._wrap(fn, m, True)

    def prism(self, m, profile_xz, y0, y1, smooth=False):
        """Extrude a closed XZ polygon (CCW seen from -Y) between y0 and y1."""
        def fn():
            a = [self.bm.verts.new((x, y0, z)) for x, z in profile_xz]
            b = [self.bm.verts.new((x, y1, z)) for x, z in profile_xz]
            n = len(profile_xz)
            self.bm.faces.new(list(reversed(a)))
            self.bm.faces.new(b)
            for i in range(n):
                j = (i + 1) % n
                self.bm.faces.new((a[i], a[j], b[j], b[i]))
        return self._wrap(fn, m, smooth)

    def poly(self, m, pts):
        def fn():
            vs = [self.bm.verts.new(p) for p in pts]
            self.bm.faces.new(vs)
        return self._wrap(fn, m)

    def slab_between(self, m, p, q, w, t):
        """Box beam from p to q, width w (perp, horizontal-ish), thickness t."""
        p, q = Vector(p), Vector(q)
        d = q - p
        rot = d.normalized().to_track_quat("Z", "X").to_matrix()
        return self.box(m, (p + q) / 2, (t, w, d.length), rot=rot)

    def to_object(self, name, collection, parent=None, recalc=True):
        if recalc:
            bmesh.ops.recalc_face_normals(self.bm, faces=self.bm.faces)
        me = bpy.data.meshes.new(name)
        self.bm.to_mesh(me)
        self.bm.free()
        for m in self.mats:
            me.materials.append(m)
        ob = bpy.data.objects.new(name, me)
        link(ob, collection)
        if parent is not None:
            ob.parent = parent
        return ob


def bevel(ob, width=0.03, segs=2, angle=40):
    md = ob.modifiers.new("Bevel", "BEVEL")
    md.width = width
    md.segments = segs
    md.limit_method = "ANGLE"
    md.angle_limit = math.radians(angle)
    md.harden_normals = False
    return md


def subsurf(ob, level=1):
    md = ob.modifiers.new("Subsurf", "SUBSURF")
    md.levels = level
    md.render_levels = level
    return md


def apply_modifiers(ob):
    dg = bpy.context.evaluated_depsgraph_get()
    ev = ob.evaluated_get(dg)
    me = bpy.data.meshes.new_from_object(ev)
    old = ob.data
    ob.modifiers.clear()
    ob.data = me
    if old.users == 0:
        bpy.data.meshes.remove(old)


def boolean_diff(ob, cutters, fill_mat=None):
    """Cut, then give the new (cutter) faces fill_mat -- empty slots would render default white."""
    for c in cutters:
        md = ob.modifiers.new("Bool", "BOOLEAN")
        md.operation = "DIFFERENCE"
        md.solver = "EXACT"
        md.object = c
    apply_modifiers(ob)
    for c in cutters:
        bpy.data.objects.remove(c, do_unlink=True)
    me = ob.data
    if fill_mat is not None:
        if fill_mat.name not in [m.name for m in me.materials if m]:
            me.materials.append(fill_mat)
        fi = [m.name if m else None for m in me.materials].index(fill_mat.name)
        for i, m in enumerate(me.materials):
            if m is None:
                for p in me.polygons:
                    if p.material_index == i:
                        p.material_index = fi
        while None in list(me.materials):
            me.materials.pop(index=list(me.materials).index(None))


def cutter_box(center, size):
    mb = MeshBuilder()
    mb.box(None, center, size)
    ob = mb.to_object("_cutter", "_TMP")
    ob.hide_render = True
    return ob


def cutter_cyl(center, radius, depth, axis="Y"):
    mb = MeshBuilder()
    mb.cyl(None, center, radius, depth, axis=axis, segs=32)
    ob = mb.to_object("_cutter", "_TMP")
    ob.hide_render = True
    return ob

# ------------------------------------------------------------------ animation


def fcurves(idb):
    ad = idb.animation_data
    if ad is None or ad.action is None:
        return []
    cb = anim_utils.action_get_channelbag_for_slot(ad.action, ad.action_slot)
    return list(cb.fcurves) if cb else []


def fcurve(idb, path, index=0):
    if idb.animation_data is None:
        idb.animation_data_create()
    ad = idb.animation_data
    if ad.action is None:
        ad.action = bpy.data.actions.new(f"{idb.name}_act")
    if ad.action_slot is None:
        slot = ad.action.slots.new(idb.id_type, idb.name)
        ad.action_slot = slot
    return ad.action.fcurve_ensure_for_datablock(idb, path, index=index)


def set_keys(idb, path, index, frames, values, interp="BEZIER", handle="AUTO_CLAMPED"):
    """Write many keys at once (fast). Replaces existing keys on that channel."""
    fc = fcurve(idb, path, index)
    fc.keyframe_points.clear()
    fc.keyframe_points.add(len(frames))
    co = []
    for f, v in zip(frames, values):
        co += [float(f), float(v)]
    fc.keyframe_points.foreach_set("co", co)
    for kp in fc.keyframe_points:
        kp.interpolation = interp
        kp.handle_left_type = handle
        kp.handle_right_type = handle
    fc.update()
    return fc


def key_vec(idb, path, frames, vecs, interp="BEZIER", handle="AUTO_CLAMPED", n=3):
    fcs = []
    for i in range(n):
        fcs.append(set_keys(idb, path, i, frames, [v[i] for v in vecs], interp, handle))
    return fcs


def flatten_keys(fc, frames=None):
    """Make the given keys' handles horizontal (ease in/out at that key)."""
    for kp in fc.keyframe_points:
        if frames is None or round(kp.co.x) in frames:
            kp.handle_left_type = "FREE"
            kp.handle_right_type = "FREE"
            kp.handle_left.y = kp.co.y
            kp.handle_right.y = kp.co.y
    fc.update()


def smoothstep(t):
    t = max(0.0, min(1.0, t))
    return t * t * (3 - 2 * t)


def look_rot(cam_loc, target, up=Vector((0, 0, 1))):
    d = (Vector(target) - Vector(cam_loc)).normalized()
    return d.to_track_quat("-Z", "Y")


def lerp(a, b, t):
    return a + (b - a) * t
