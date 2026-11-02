# Fly Nah: changes from the brief (and why)

## Revision 2 (your review notes)
- **The wheels no longer fold.** They stay upright and still for the whole lift-off and flight. No hover-fan glow.
- **Air thrust lifts the coupe.** Four vents under the body glow soft blue and blast vapour down from
  frame 655. The vapour hits the road and billows outward and upward as a ground-hugging cloud (about 300
  puffs; soft-edged, noise broken, each one fading in and out). The jets peak at lift-off (685), keep
  running through the hover and glide, then trail off during the fly-away (to frame 812). Dust is now soft
  dust clouds, and the plastic bags still blow out. The underglow now sits only in the vents, strength 6
  instead of 15, so the underside doesn't read as a white panel. Check 7 now verifies the wheels stay
  upright and that the jets are active under the car at frame 700.
- **Lagos road setting** (all text-free and logo-free):
  - Lagos-style buildings: painted 2-4 storey shop-houses with burglar-proof window bars, balconies, AC units,
    roll-up shop shutters and rusty zinc awnings. Houses with hipped corrugated-zinc roofs behind compound walls
    and gates. Unfinished bare-concrete frames with rebar sticking up. Flat roofs with black water tanks and
    satellite dishes.
  - Shipping-container shops with open doors and goods. Colourful segmented umbrellas over trader tables with
    produce. Generators, plain colour signboards, kiosks.
  - Black-and-yellow painted kerbs on the pavements, the BRT lane and the median base. Open drainage gutters
    with cover slabs.
  - Concrete electricity poles every 38 m with sagging cables and pole-mounted transformers.
  - Worn, patched asphalt with darker wheel tracks, sand drifts along the shoulder and median, muddy
    potholes, and litter (sachet-water bags, bottles).
  - Palm trees and dusty shrubs. A bus shelter with people waiting, pedestrians walking both pavements,
    and two more hawkers with trays working the jam.
  - Traffic: a petrol tanker and a covered lorry in the oncoming lanes, Sienna-style minivans and a
    Hilux-style pickup in the mix.
  - Weathered procedural materials: grime and rising damp on walls, rust on zinc, corrugation, worn concrete.


## Environment / engine
1. **Blender 5.2.3 LTS as the `bpy` Python module** (cloud box, no GPU, no blender.org access). Scripts run with
   `python3 <script>.py`. Engine IDs here are `BLENDER_EEVEE` (EEVEE Next), `BLENDER_WORKBENCH`, `CYCLES`.
2. **Nishita sky** is called `SINGLE_SCATTERING` since Blender 5.0, and that is what's used.
3. **Haze**: an EEVEE Next *world* volume absorbs the sun completely, so every frame rendered black. The
   0.002 haze is now a large volume box, `FX_haze` (900 x 500 x 120 m, hidden in Workbench).
4. **Planar light probe**: built and aligned to the mirror (`CAR_hero_sedan_mirror_probe`), but under the CPU
   software OpenGL here it renders noise. **S03 is therefore rendered with Cycles on this machine**
   (`config.ENGINE_OVERRIDE`). On a GPU machine you can delete that override and EEVEE will use the probe.
   S03B (the fallback) is rendered as well.
5. "Ambient occlusion ON": EEVEE Next has no AO toggle any more. AO comes from horizon scan / fast GI, which is on.
6. Review stills use 8 EEVEE samples and shadow resolution 0.5 so the pack renders in about an hour on CPU.

## Set / vehicles
7. **Sedan roof at 1.45 m** (the brief's height). The open driver's window is 0.92 to 1.40 m (0.48 m tall),
   1.41 m wide at the sill and 0.60 m at the top.
8. **Rear-view mirror** is angled to reflect the rear window into CAM_S03. That puts the camera on the
   passenger side of centre (0.77 m away at 85 mm, about 70% fill). Aimed at HERO's eyes, his own head
   would block the camera.
9. **Coupe light bar and headlights** sit on the nose's top edge (z about 0.57 to 0.61). At a 2 m gap the sedan's
   trunk hides anything lower from the mirror, so lower lights wouldn't show the flashes.
10. **Coupe button** moved to the centre-console top (0.16, 0.08, 0.80) so it shows over the dash in S07. It is
    still within HORNER's right-hand reach.
11a. **Coupe body**: wheel radius 0.34 m (not set in the brief) and a slightly taller nose (0.68 m), so the
    wheel wells don't cut through the front fenders. Paint stays glossy black, coat 1.0, roughness 0.15. Specular
    level is 0.2 and coat IOR 1.3, so the hood doesn't mirror the bright hazy sky as white (it must read black).
12. **Generic cars**: 7 distinct designs (sedan/SUV/hatch in white, red, dark blue, beige, grey), reused as
    linked copies (27 generic cars) so every lane has traffic. More minibuses in L2/L4, and 2 stopped cars ahead
    of the minibus in L3 (the "jammed traffic ahead" HERO points at). None are black.
13. **Traffic gaps**: L2 has a deliberate gap that moves past the sedan, so the S01 drone path and the S06 camera
    never hit a car. L4 has a long gap behind the conductor's minibus, so nothing blocks S08 or S10.
14. **Kekes** are centre-steered (handlebar), so the left-hand-drive check exempts them.

## Animation
15. **HERO lean-out** (505-525): to keep his head and arm clear of the door frame (check 8), he rises about 0.3 m
    off the seat, shifts toward the door, shrugs the window-side shoulder and leans out about 50 degrees.
    He looks back at the coupe with neck and head turn, not torso twist. A mid-transition pose at 509 ducks his
    head under the roof edge and lifts his arm over the sill.
16. **Envelope drop** (452): the envelope in his hand is hidden, and a second one (`CHR_hero_envelope_seat`)
    appears on the passenger seat.
17. **Dust and plastic bags** are keyframed objects, not a particle system. That makes them deterministic and
    safe for chunked, out-of-order headless rendering, which a particle cache isn't.
18. **Fly-away (781-846)**: x/z are as specified. The coupe also drifts toward the sun's azimuth
    (y -9.4 to -19.5) with a banking turn, so in S10 it really flies *into* the sun.
19. **Sun azimuth -8 degrees** (light from +X, slightly -Y). At -15 degrees the roadside rooftops hid the low sun in S10.
20. **Conductor** leans 12 degrees out of the doorway, holding the rail. Otherwise the minibus body hides him from
    the S10 camera behind it.
21. **Lip-sync**: no `assets/audio/hero_line.wav`, so the fallback jaw pattern (2.1 s, biggest open on "Fly") is
    used. Drop a wav/mp3/m4a in and rebuild; it is converted to 48 kHz mono and drives the jaw automatically.
22. **Characters**: no FBX in `assets/characters` and no MPFB2 add-on, so proxy mannequins are used (with a
    short-hair cap so heads read).

## Cameras
23. **S03B** is inside the sedan, so like S02/S03 it is exempt from sedan clearance.
24. **S08** at (-9.5, -16.5, 0.6), about 11 m from the cars, so the coupe, sedan and hawker are big enough.
25. **S09**: with HERO back in his seat, the sedan roof is directly behind his head from a door-height
    camera. The shot frames his upturned face through the open window, with sky and the passing coupe above
    the roof edge.
26. **S10** camera at y = -13.8 (right part of L4) and pitched up about 12 degrees, so the conductor reads past the
    minibus and the sun and coupe are in frame.

## Render outputs
27. Animatic uses "material colour" Workbench shading (per-part colours of the cars and characters, the
    equivalent of object colours). S03 is in the cut; S03B is not.
28. `FX_shadow_card` is hidden in Workbench renders (Workbench ignores ray visibility).
