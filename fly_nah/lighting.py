"""Sun, Nishita-type sky, haze, EEVEE settings, compositor glare, colour management."""
import math
import bpy
from mathutils import Vector

import config as K
from util import link


def sun_dir():
    el, az = math.radians(K.SUN["elevation"]), math.radians(K.SUN["azimuth"])
    return Vector((math.cos(el) * math.cos(az), math.cos(el) * math.sin(az), math.sin(el)))


def build():
    sc = bpy.context.scene
    d = sun_dir()
    ld = bpy.data.lights.new("LGT_sun", "SUN")
    ld.color = K.SUN["color"]
    ld.energy = K.SUN["strength"]
    ld.angle = math.radians(K.SUN["angle"])
    ob = bpy.data.objects.new("LGT_sun", ld)
    link(ob, "LIGHTS")
    ob.location = d * 50
    ob.rotation_euler = d.to_track_quat("Z", "Y").to_euler()

    w = bpy.data.worlds.new("WORLD_lagos")
    sc.world = w
    w.use_nodes = True
    nt = w.node_tree
    nt.nodes.clear()
    out = nt.nodes.new("ShaderNodeOutputWorld")
    bg = nt.nodes.new("ShaderNodeBackground")
    sky = nt.nodes.new("ShaderNodeTexSky")
    # "Nishita" is called SINGLE_SCATTERING since Blender 5.0
    sky.sky_type = "SINGLE_SCATTERING"
    sky.sun_elevation = math.radians(K.SUN["elevation"])
    sky.sun_rotation = math.radians(K.SUN["azimuth"]) - math.pi / 2
    sky.sun_disc = True
    sky.sun_size = math.radians(1.2)
    sky.sun_intensity = 0.35
    sky.altitude = 0.0
    sky.air_density = 1.0
    sky.aerosol_density = 3.0
    sky.ozone_density = 1.0
    bg.inputs["Strength"].default_value = 0.10
    nt.links.new(sky.outputs[0], bg.inputs["Color"])
    nt.links.new(bg.outputs[0], out.inputs["Surface"])
    # haze: a large volume box (a WORLD volume in EEVEE Next absorbs the sun completely -> black frames)
    hm = bpy.data.materials.new("FX_haze")
    hm.use_nodes = True
    hn = hm.node_tree
    hn.nodes.remove(hn.nodes["Principled BSDF"])
    vol = hn.nodes.new("ShaderNodeVolumePrincipled")
    vol.inputs["Density"].default_value = K.HAZE_DENSITY
    vol.inputs["Color"].default_value = (0.95, 0.85, 0.72, 1)
    hn.links.new(vol.outputs[0], hn.nodes["Material Output"].inputs["Volume"])
    from util import MeshBuilder
    mb = MeshBuilder()
    mb.box(hm, (110, 0, 60), (900, 500, 120))
    hz = mb.to_object("FX_haze", "FX")
    hz["workbench_hide"] = True
    w.color = (0.55, 0.62, 0.72)  # Workbench background

    # EEVEE
    sc.render.engine = "BLENDER_EEVEE"
    ee = sc.eevee
    ee.use_raytracing = True
    ee.use_shadows = True
    ee.shadow_ray_count = 2
    ee.shadow_step_count = 6
    ee.use_fast_gi = True
    ee.taa_render_samples = 16
    ee.shadow_resolution_scale = 0.5
    ee.volumetric_end = 250.0
    ee.volumetric_tile_size = "8"
    ee.use_volumetric_shadows = False
    if hasattr(ee, "use_gtao"):
        ee.use_gtao = True
    sc.render.use_motion_blur = False
    sc.render.film_transparent = False
    sc.view_settings.view_transform = "AgX"
    sc.view_settings.look = "AgX - Medium High Contrast"

    # compositor: fog-glow glare for headlights / underglow
    ng = bpy.data.node_groups.new("COMP_flynah", "CompositorNodeTree")
    ng.interface.new_socket("Image", in_out="OUTPUT", socket_type="NodeSocketColor")
    rl = ng.nodes.new("CompositorNodeRLayers")
    gl = ng.nodes.new("CompositorNodeGlare")
    gl.inputs["Type"].default_value = "Fog Glow"
    gl.inputs["Threshold"].default_value = 1.5
    gl.inputs["Strength"].default_value = 0.35
    go = ng.nodes.new("NodeGroupOutput")
    ng.links.new(rl.outputs["Image"], gl.inputs["Image"])
    ng.links.new(gl.outputs[0], go.inputs[0])
    sc.compositing_node_group = ng
    return ob
