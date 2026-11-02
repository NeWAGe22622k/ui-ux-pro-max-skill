"""Entry point: builds the whole Fly Nah scene from scratch and saves fly_nah.blend.

Run:  python3 fly_nah/build_scene.py        (bpy module)   or   blender -b -P fly_nah/build_scene.py
"""
import os
import sys
import time
from collections import Counter

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

import bpy
from mathutils import Vector

import config as K
import util
import env
import vehicles
import characters as CH
import animation as AN
import cameras
import lighting


def main():
    t0 = time.time()
    util.clean_scene()
    util._MATS.clear()
    sc = bpy.context.scene
    sc.render.fps = K.FPS
    sc.frame_start, sc.frame_end = K.FRAME_START, K.FRAME_END
    sc.unit_settings.system = "METRIC"
    sc.unit_settings.scale_length = 1.0
    # emission socket index sanity check (used by animation.EMIS)
    m = util.mat("_probe", (1, 1, 1))
    assert util.bsdf(m).inputs[29].name == "Emission Strength"
    bpy.data.materials.remove(m)
    util._MATS.pop("_probe", None)

    env.build()
    cars = vehicles.build_all()
    sed, cou = cars["CAR_hero_sedan"], cars["CAR_coupe"]

    c = K.C
    hero, src_h = CH.make_character(
        "CHR_hero", "hero.fbx", kind="hero", height=K.HERO_HEIGHT,
        look=dict(skin=c["hero_skin"], top=c["hero_shirt"], bottom=c["hero_trousers"], shoes=c["hero_shoes"],
                  hair=c["hero_hair"], sleeve="rolled"))
    if src_h == "proxy":
        CH.add_tie(hero, c["hero_tie"])
        CH.add_envelope(hero)
    hero.parent = sed
    hero.location = K.SEDAN["seat_hip"]
    horner, src_o = CH.make_character(
        "CHR_horner", "horner.fbx", kind="horner", height=K.HORNER_HEIGHT, girth=1.18, low=True,
        look=dict(skin=c["horner_skin"], top=c["horner_polo"], bottom=c["horner_trousers"], shoes=c["horner_shoes"],
                  hair=c["hero_hair"], sleeve="short"))
    if src_o == "proxy":
        CH.add_watch(horner, c["watch_gold"])
        CH.add_glasses(horner, c["glasses"])
    horner.parent = cou
    horner.location = K.COUPE["seat_hip"]
    hawker = CH.build_proxy("CHR_hawker", kind="hawker", height=K.HAWKER_HEIGHT, seated=False, girth=0.95,
                            look=dict(skin=c["hawker_skin"], top=c["hawker_blouse"], bottom=c["hawker_skin"],
                                      shoes=c["hawker_wrapper2"], hair=c["hawker_scarf"], sleeve="short"))
    CH.add_wrapper(hawker, c["hawker_wrapper"], c["hawker_wrapper2"])
    CH.add_headscarf(hawker, c["hawker_scarf"])
    CH.add_tray(hawker)
    conductor = CH.build_proxy("CHR_conductor", kind="conductor", height=K.CONDUCTOR_HEIGHT, seated=False, girth=0.9,
                               look=dict(skin=c["conductor_skin"], top=c["conductor_shirt"], bottom=c["conductor_jeans"],
                                         shoes=c["sneakers"], hair=c["hero_hair"], sleeve="short"))
    peds = []
    for k, (pn, px, py, ph, pv, pf, tray) in enumerate(K.PEDS):
        top, bottom, skin, shoes, hair = K.PED_LOOKS[k % len(K.PED_LOOKS)]
        female = (k % 2 == 1) or pn.startswith("CHR_hawker")
        arm = CH.build_proxy(pn, kind="ped", height=1.62 + 0.04 * (k % 4), seated=False, girth=0.92 + 0.04 * (k % 3),
                             look=dict(skin=skin, top=top, bottom=skin if female else bottom, shoes=shoes, hair=hair,
                                       sleeve="short"))
        if female:
            CH.add_wrapper(arm, bottom, top, name=f"{pn}_wrapper")
        if tray:
            CH.add_tray(arm, name=f"{pn}_tray")
        peds.append((arm, px, py, ph, pv, pf, k))
    mbc = cars["CAR_minibus_conductor"]
    conductor.parent = mbc
    conductor.location = K.CONDUCTOR_LOCAL

    # ---------------- animation
    AN.traffic(cars)
    AN.coupe_anim(cars)
    jaw_fn, jaw_src, jaw_dur, wav = AN.jaw_curve()
    AN.hero_anim(hero, sed, cou, jaw_fn)
    AN.envelope_swap(hero, sed)
    AN.horner_anim(horner, cou)
    AN.hawker_anim(hawker, cou)
    AN.conductor_anim(conductor, cou, mbc)
    for arm, px, py, ph, pv, pf, k in peds:
        AN.ped_anim(arm, px, py, ph, pv, pf, K.SEED + 50 + k)
    card = AN.fx(cars)
    AN.thrust_fx(cars)
    sc.frame_set(762)
    AN.shadow_card_anim(card, CH.head_world(hero, 762))
    if wav:
        AN.add_sound_strip(wav)
    sc["lipsync_source"] = jaw_src
    sc["lipsync_duration"] = jaw_dur
    sc["character_source"] = f"hero={src_h}, horner={src_o}, extras=proxy"

    lighting.build()
    cams = cameras.build(cars, hero, horner)
    sc.camera = cams["S01"]
    sc.frame_set(K.STORY_START)

    # tidy: Workbench viewport colours / metallic so the grey-box animatic reads clearly
    for m in bpy.data.materials:
        m.metallic = min(m.metallic, 0.15)
    bpy.ops.wm.save_as_mainfile(filepath=K.BLEND_PATH, compress=True)

    # ---------------- summary
    cnt = Counter(o.name.split("_")[0] + "_" for o in bpy.data.objects)
    print("\n=== FLY NAH BUILD SUMMARY ===")
    for k in sorted(cnt):
        print(f"  {k:6s} {cnt[k]:4d}")
    print(f"  total objects: {len(bpy.data.objects)}")
    print(f"  frames {K.FRAME_START}-{K.FRAME_END} ({K.FRAME_END - K.FRAME_START + 1} frames @ {K.FPS} fps), "
          f"story {K.STORY_START}-{K.STORY_END}")
    print(f"  characters: {sc['character_source']} | lip-sync: {jaw_src} ({jaw_dur:.2f}s)")
    print("  shot  story      render     len")
    for s in K.SHOTS:
        print(f"  {s[0]:5s} {s[1]:3d}-{s[2]:3d}    {s[3]:3d}-{s[4]:3d}    {s[4] - s[3] + 1}")
    print(f"  saved {K.BLEND_PATH}  ({time.time() - t0:.1f}s)")


if __name__ == "__main__":
    main()
