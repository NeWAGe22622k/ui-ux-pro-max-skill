"""Road, lanes, median, shoulder, buildings, street lights, pedestrian bridge, billboards, skyline."""
import math
import random
import bmesh
import bpy
from mathutils import Matrix, Vector

import config as K
from util import MeshBuilder, mat, bevel, link

COL = "ENV"


def _m():
    c = K.C
    return dict(
        asphalt=mat("ENV_asphalt", c["asphalt"], rough=0.85),
        paint=mat("ENV_lane_paint", c["lane_paint"], rough=0.7),
        concrete=mat("ENV_concrete", c["concrete"], rough=0.8),
        kerb=mat("ENV_kerb", c["kerb"], rough=0.8),
        pave=mat("ENV_pavement", c["pavement"], rough=0.9),
        earth=mat("ENV_earth", c["earth"], rough=1.0),
        steel=mat("ENV_steel", c["steel"], metallic=0.7, rough=0.45),
        pole=mat("ENV_pole", c["pole"], metallic=0.5, rough=0.5),
        lamp=mat("ENV_lamp", c["lamp"], emission=c["lamp"], strength=0.0, rough=0.3),
        window=mat("ENV_window", c["window"], metallic=0.2, rough=0.15),
        skyline=mat("ENV_skyline", c["skyline"], rough=0.8),
        trunk=mat("ENV_tree_trunk", c["tree_trunk"], rough=0.9),
        leaf=mat("ENV_tree_leaf", c["tree_leaf"], rough=0.8),
    )


def build():
    rnd = random.Random(K.SEED)
    M = _m()
    x0, x1 = K.ROAD_X
    L = x1 - x0
    xc = (x0 + x1) / 2

    # ground
    mb = MeshBuilder()
    mb.box(M["earth"], (0, 0, -0.06), (2400, 2400, 0.1))
    mb.to_object("ENV_ground", COL)

    # road surface (both carriageways + shoulders)
    mb = MeshBuilder()
    mb.box(M["asphalt"], (xc, (K.SHOULDER[0] + K.OPP_SHOULDER[1]) / 2, -0.01),
           (L, K.OPP_SHOULDER[1] - K.SHOULDER[0], 0.02))
    mb.to_object("ENV_road", COL)

    # pavements (raised)
    mb = MeshBuilder()
    gut = K.LAGOS["gutter_w"] + 0.27   # kerb + open gutter + gutter wall between road and pavement
    for (a, b) in ((K.PAVEMENT_RIGHT[0], K.PAVEMENT_RIGHT[1] - gut), (K.PAVEMENT_LEFT[0] + gut, K.PAVEMENT_LEFT[1])):
        mb.box(M["pave"], (xc, (a + b) / 2, K.PAVEMENT_H / 2), (L, b - a, K.PAVEMENT_H))
    mb.to_object("ENV_pavements", COL)

    # median barrier (Jersey-ish profile)
    mb = MeshBuilder()
    w, h = K.MEDIAN["width"], K.MEDIAN["height"]
    prof = [(-w / 2, 0), (w / 2, 0), (w / 2 - 0.05, 0.25), (0.09, h), (-0.09, h), (-w / 2 + 0.05, 0.25)]
    # profile is in YZ -> build as prism along X by swapping axes
    my = K.MEDIAN["y"]
    a = [mb.bm.verts.new((x0, y + my, z)) for y, z in prof]
    b = [mb.bm.verts.new((x1, y + my, z)) for y, z in prof]
    mb.bm.faces.new(a)
    mb.bm.faces.new(list(reversed(b)))
    for i in range(len(prof)):
        j = (i + 1) % len(prof)
        mb.bm.faces.new((a[i], b[i], b[j], a[j]))
    for f in mb.bm.faces:
        f.material_index = mb._mi(M["concrete"])
    mb.to_object("ENV_median", COL)

    # lane markings: plain paint only
    mb = MeshBuilder()
    # one dashed divider per carriageway (2 lanes each way)
    dashed = [K.LANES["L3"] + K.LANE_W / 2, K.OPP_LANES[0] + K.LANE_W / 2]
    for y in dashed:
        x = x0 + 1
        while x < x1 - 3:
            mb.box(M["paint"], (x + 1.5, y, 0.003), (3.0, 0.12, 0.006))
            x += 9.0
    for y in (K.LANES["L3"] - K.LANE_W / 2 + 0.15, K.OPP_EDGE - 0.15, K.MEDIAN["y"] - 0.4, K.MEDIAN["y"] + 0.4):
        mb.box(M["paint"], (xc, y, 0.003), (L, 0.12, 0.006))
    mb.to_object("ENV_lane_markings", COL)

    _street_lights(M)
    import lagos
    lagos.build()
    _kiosks(M)
    # weathered procedural surfaces for the big pieces
    LM = lagos.materials()
    bpy.data.objects["ENV_road"].data.materials[0] = LM["asphalt"]
    bpy.data.objects["ENV_median"].data.materials[0] = LM["concrete"]
    bpy.data.objects["ENV_pavements"].data.materials[0] = lagos.pmat("ENV_pavement_worn", K.C["pavement"], "concrete", scale=2.0)

    _billboards(M)
    _bridge(M)
    _skyline(M, rnd)


def _street_lights(M):
    x0, x1 = K.ROAD_X
    n = int((x1 - x0) / K.LIGHT_SPACING)
    for i in range(n):
        x = x0 + K.LIGHT_SPACING / 2 + i * K.LIGHT_SPACING
        mb = MeshBuilder()
        H = K.LIGHT_HEIGHT
        mb.cyl(M["pole"], (0, 0, H / 2 + K.MEDIAN["height"]), 0.09, H, segs=12, r2=0.06)
        top = H + K.MEDIAN["height"]
        for s in (1, -1):
            mb.slab_between(M["pole"], (0, 0, top - 0.3), (0, s * K.LIGHT_ARM, top), 0.08, 0.08)
            mb.box(M["lamp"], (0, s * (K.LIGHT_ARM + 0.2), top - 0.05), (0.35, 0.6, 0.12))
        ob = mb.to_object(f"ENV_street_light_{i:02d}", COL)
        ob.location = (x, K.MEDIAN["y"], 0)


def _facade_building(name, M, rnd, x, y_front, facing, width, depth, floors, color):
    """Low building with window insets on the road-facing facade."""
    h = floors * 3.2
    body = mat(f"ENV_bldg_{color}", color, rough=0.85)
    bm = bmesh.new()
    sgn = 1 if facing > 0 else -1  # facade normal direction in Y
    yb = y_front - sgn * depth      # back
    yc = (y_front + yb) / 2
    bmesh.ops.create_cube(bm, size=1.0, matrix=Matrix.Translation((x, yc, h / 2)) @ Matrix.Diagonal((width, depth, h, 1)))
    # find facade face and replace it with a window grid
    bm.faces.ensure_lookup_table()
    front = [f for f in bm.faces if f.normal.y * sgn > 0.9][0]
    bm.faces.remove(front)
    nx = max(2, int(width / 3.0))
    gx, gz = width / nx, 3.2
    grid = []
    for iz in range(floors):
        for ix in range(nx):
            xa = x - width / 2 + ix * gx
            za = iz * gz
            vs = [bm.verts.new((xa, y_front, za)), bm.verts.new((xa + gx, y_front, za)),
                  bm.verts.new((xa + gx, y_front, za + gz)), bm.verts.new((xa, y_front, za + gz))]
            if sgn < 0:
                vs = list(reversed(vs))
            f = bm.faces.new(vs if sgn < 0 else list(reversed(vs)))
            grid.append(f)
    bmesh.ops.remove_doubles(bm, verts=bm.verts, dist=1e-4)
    bm.faces.ensure_lookup_table()
    grid = [f for f in bm.faces if abs(f.calc_center_median().y - y_front) < 1e-3 and f.is_valid]
    # skip ground-floor door bays occasionally; inset others as windows
    wins = [f for f in grid if f.calc_center_median().z > 1.0 or rnd.random() < 0.5]
    res = bmesh.ops.inset_individual(bm, faces=wins, thickness=0.45, depth=-0.18)
    for f in bm.faces:
        f.material_index = 0
    for f in wins:
        if f.is_valid:
            f.material_index = 1
    bmesh.ops.recalc_face_normals(bm, faces=bm.faces)
    me = bpy.data.meshes.new(name)
    bm.to_mesh(me)
    bm.free()
    me.materials.append(body)
    me.materials.append(M["window"])
    ob = bpy.data.objects.new(name, me)
    link(ob, COL)
    # parapet
    return ob


def _buildings(M, rnd):
    i = 0
    for side, y_front, facing in ((-1, K.PAVEMENT_RIGHT[0] - 0.5, 1), (1, K.PAVEMENT_LEFT[1] + 0.5, -1)):
        x = K.ROAD_X[0]
        while x < K.SKYLINE_X0 - 10:
            w = rnd.uniform(12, 26)
            d = rnd.uniform(10, 15)
            floors = rnd.choice([2, 2, 3, 3, 4])
            col = rnd.choice(K.C["building"])
            if not (K.BRIDGE_X - 8 < x + w / 2 < K.BRIDGE_X + 22 and side < 0) and \
               not (K.BRIDGE_X - 8 < x + w / 2 < K.BRIDGE_X + 22 and side > 0):
                _facade_building(f"ENV_building_{i:03d}", M, rnd, x + w / 2, y_front, facing, w, d, floors, col)
                i += 1
            x += w + rnd.uniform(1.5, 6)


def _kiosks(M):
    mb = MeshBuilder()
    for j, x in enumerate([-120, -18, 22, 95, 160, 210]):
        col = mat(f"ENV_kiosk_{j % 3}", K.C["kiosk"][j % 3], rough=0.6)
        y = (K.PAVEMENT_RIGHT[0] + K.PAVEMENT_RIGHT[1]) / 2 - 0.3
        mb.box(col, (x, y, 1.15 + K.PAVEMENT_H), (2.2, 1.6, 2.3))
        mb.box(col, (x, y + 1.0, 2.35 + K.PAVEMENT_H), (2.6, 0.9, 0.06),
               rot=Matrix.Rotation(math.radians(-12), 3, "X"))
    mb.to_object("ENV_kiosks", COL)


def _billboards(M):
    for j, (x, y, w, h, pole_h) in enumerate(K.BILLBOARDS):
        mb = MeshBuilder()
        panel = mat(f"ENV_billboard_{j}", K.C["billboard"][j], rough=0.5)
        mb.cyl(M["pole"], (0, 0, pole_h / 2), 0.25, pole_h, segs=12)
        mb.box(M["steel"], (0, 0, pole_h + h / 2), (0.3, w + 0.2, h + 0.2))
        mb.box(panel, (-0.17, 0, pole_h + h / 2), (0.04, w, h))
        ob = mb.to_object(f"ENV_billboard_{j}", COL)
        ob.location = (x, y, 0)
        ob.rotation_euler.z = math.radians(-15 if y < 0 else 15)


def _bridge(M):
    B = K.BRIDGE
    x = K.BRIDGE_X
    z = B["deck_z"]
    w = B["width"]
    mb = MeshBuilder()
    y0, y1 = B["y0"], B["y1"]
    span = y1 - y0
    yc = (y0 + y1) / 2
    mb.box(M["concrete"], (x, yc, z), (w, span, 0.35))
    th = B["truss_h"]
    for s in (1, -1):
        xs = x + s * (w / 2)
        mb.box(M["steel"], (xs, yc, z + 0.2), (0.15, span, 0.2))
        mb.box(M["steel"], (xs, yc, z + th), (0.15, span, 0.2))
        n = 16
        for i in range(n + 1):
            yy = y0 + i * span / n
            mb.box(M["steel"], (xs, yy, z + th / 2 + 0.1), (0.12, 0.12, th))
            if i < n:
                mb.slab_between(M["steel"], (xs, yy, z + 0.25), (xs, yy + span / n, z + th - 0.05), 0.1, 0.1)
    # roof members
    for i in range(9):
        yy = y0 + i * span / 8
        mb.box(M["steel"], (x, yy, z + th), (w, 0.12, 0.12))
    # columns: both pavements and the median
    for yy in (y0 + 1.0, y1 - 1.0, 0.0):
        mb.box(M["concrete"], (x, yy, z / 2), (0.6, 0.6, z))
    # stairs at both ends, running along +X on the pavements
    for ys in (y0 + 1.2, y1 - 1.2):
        steps = 30
        for i in range(steps):
            zz = z - (i + 1) * z / steps
            mb.box(M["concrete"], (x + w / 2 + (i + 0.5) * B["stair_len"] / steps, ys, zz + z / steps / 2),
                   (B["stair_len"] / steps, 1.8, z / steps))
        mb.slab_between(M["steel"], (x + w / 2, ys + 0.95, z + 1.0),
                        (x + w / 2 + B["stair_len"], ys + 0.95, 1.0), 0.06, 0.06)
    mb.to_object("ENV_ped_bridge", COL)


def _skyline(M, rnd):
    mb = MeshBuilder()
    x = K.SKYLINE_X0
    while x < 520:
        for y in (rnd.uniform(-160, -60), rnd.uniform(-60, 60), rnd.uniform(60, 160)):
            w = rnd.uniform(14, 30)
            h = rnd.uniform(28, 75)
            mb.box(M["skyline"], (x, y, h / 2), (w, rnd.uniform(14, 30), h))
        x += rnd.uniform(18, 35)
    mb.to_object("ENV_skyline", COL)
