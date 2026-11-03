"""Fly Nah -- every tweakable number lives here.

Axes: +X forward (direction of travel), +Y left, +Z up. Metres. 24 fps.
All frames are MASTER timeline frames.
"""
import math
import os

ROOT = os.path.dirname(os.path.abspath(__file__))
BLEND_PATH = os.path.join(ROOT, "fly_nah.blend")
AUDIO_DIR = os.path.join(ROOT, "assets", "audio")
CHAR_DIR = os.path.join(ROOT, "assets", "characters")
REVIEW_DIR = os.path.join(ROOT, "review")
RENDER_DIR = os.path.join(ROOT, "renders")
SEED = 1977

# ---------------------------------------------------------------- timeline
FPS = 24
FRAME_START, FRAME_END = 1, 864
STORY_START, STORY_END = 49, 816

# name, story_start, story_end, render_start, render_end, final file stem
SHOTS = [
    ("S01", 49, 336, 37, 348, "S01_drone_oner"),
    ("S02", 337, 384, 313, 408, "S02_pov_creep"),
    ("S03", 385, 420, 355, 450, "S03_mirror_flash"),
    ("S03B", 385, 420, 355, 450, "S03B_rear_window_flash"),
    ("S04", 421, 468, 397, 492, "S04_horner_horning"),
    ("S05", 469, 504, 439, 534, "S05_hero_grip"),
    ("S06", 505, 600, 493, 612, "S06_hero_shout"),
    ("S07", 601, 648, 577, 672, "S07_glasses_button"),
    ("S08", 649, 744, 637, 756, "S08_liftoff"),
    ("S09", 745, 780, 715, 810, "S09_hero_lookup"),
    ("S10", 781, 816, 751, 846, "S10_flyaway"),
]
MIN_RENDER_LEN = 96

# ---------------------------------------------------------------- colours (linear RGB)
C = dict(
    hero_skin=(0.30, 0.18, 0.12), hero_shirt=(0.45, 0.65, 0.90), hero_tie=(0.35, 0.04, 0.05),
    hero_trousers=(0.06, 0.06, 0.07), hero_shoes=(0.05, 0.03, 0.02), hero_hair=(0.015, 0.012, 0.01),
    horner_skin=(0.25, 0.15, 0.10), horner_polo=(0.03, 0.03, 0.035), horner_trousers=(0.16, 0.12, 0.08),
    horner_shoes=(0.12, 0.07, 0.04), watch_gold=(0.8, 0.55, 0.2), glasses=(0.005, 0.005, 0.005),
    hawker_skin=(0.28, 0.16, 0.10), hawker_wrapper=(0.85, 0.30, 0.03), hawker_wrapper2=(0.10, 0.35, 0.60),
    hawker_blouse=(0.75, 0.70, 0.10), hawker_scarf=(0.55, 0.05, 0.25), tray=(0.6, 0.6, 0.62),
    oranges=(0.8, 0.35, 0.02),
    conductor_skin=(0.27, 0.16, 0.11), conductor_shirt=(0.55, 0.08, 0.06), conductor_jeans=(0.06, 0.10, 0.22),
    sneakers=(0.8, 0.8, 0.8),
    envelope=(0.85, 0.85, 0.82),
    sedan_silver=(0.55, 0.56, 0.58), coupe_black=(0.01, 0.01, 0.012),
    minibus_yellow=(0.85, 0.55, 0.02), stripe_black=(0.02, 0.02, 0.02),
    brt_blue=(0.03, 0.15, 0.55), brt_white=(0.80, 0.80, 0.80),
    keke_yellow=(0.80, 0.60, 0.03), keke_green=(0.05, 0.35, 0.08),
    generic=dict(white=(0.80, 0.80, 0.78), red=(0.50, 0.03, 0.03), darkblue=(0.03, 0.06, 0.22),
                 beige=(0.55, 0.45, 0.30), grey=(0.25, 0.26, 0.27), champagne=(0.52, 0.45, 0.33),
                 maroon=(0.22, 0.03, 0.04)),
    glass_tint=(0.75, 0.85, 0.85), rubber=(0.02, 0.02, 0.02), rim=(0.45, 0.45, 0.47),
    trim=(0.04, 0.04, 0.045), interior=(0.07, 0.065, 0.06), interior_light=(0.30, 0.26, 0.20),
    headlight=(1.0, 0.97, 0.9), taillight=(0.8, 0.02, 0.01), lightbar=(1.0, 1.0, 1.0),
    underglow=(0.2, 0.5, 1.0), button=(0.3, 0.7, 1.0), fan_glow=(0.2, 0.5, 1.0),
    asphalt=(0.045, 0.045, 0.048), lane_paint=(0.55, 0.55, 0.52), concrete=(0.35, 0.33, 0.30),
    kerb=(0.45, 0.43, 0.40), pavement=(0.30, 0.27, 0.24), earth=(0.30, 0.20, 0.12),
    steel=(0.30, 0.32, 0.34), pole=(0.25, 0.26, 0.27), lamp=(0.9, 0.9, 0.85),
    building=[(0.55, 0.45, 0.35), (0.50, 0.50, 0.42), (0.45, 0.38, 0.33), (0.60, 0.55, 0.45),
              (0.40, 0.42, 0.40), (0.55, 0.40, 0.30), (0.62, 0.58, 0.50), (0.42, 0.36, 0.28)],
    window=(0.04, 0.05, 0.06), skyline=(0.22, 0.24, 0.28),
    billboard=[(0.70, 0.10, 0.08), (0.08, 0.40, 0.15), (0.10, 0.25, 0.65), (0.85, 0.60, 0.05), (0.55, 0.08, 0.35)],
    kiosk=[(0.10, 0.35, 0.60), (0.65, 0.12, 0.10), (0.20, 0.50, 0.20)],
    tree_trunk=(0.12, 0.07, 0.04), tree_leaf=(0.06, 0.20, 0.04),
    dust=(0.50, 0.42, 0.33), bag_white=(0.85, 0.85, 0.85), bag_blue=(0.10, 0.25, 0.70),
)

# ---------------------------------------------------------------- road layout
ROAD_X = (-300.0, 300.0)
MEDIAN = dict(y=-3.6, width=0.5, height=0.8)
LANE_W = 3.6
# Two lanes each way (no BRT lane). Our side: L2 (inner) and L3 (HERO's lane), hard shoulder outside L3.
LANES = dict(L2=-5.8, L3=-9.4)
OPP_LANES = [-1.4, 2.2]
SHOULDER = (-14.4, -11.2)
PAVEMENT_RIGHT = (-17.9, -14.4)
OPP_EDGE = 4.0
OPP_SHOULDER = (4.0, 6.6)
PAVEMENT_LEFT = (6.6, 10.1)
PAVEMENT_H = 0.15
BRT_KERB = dict(y=-4.0, width=0.25, height=0.15)
LIGHT_SPACING = 30.0
LIGHT_HEIGHT = 9.0
LIGHT_ARM = 2.6
BRIDGE_X = 70.0
BRIDGE = dict(deck_z=6.2, width=3.0, y0=-17.4, y1=9.6, truss_h=2.2, stair_len=11.0)
BILLBOARDS = [(40.0, -16.9, 8.0, 4.0, 7.0), (-55.0, 9.1, 8.0, 4.0, 7.0), (135.0, -16.9, 9.0, 4.5, 8.0),
              (-30.0, 9.4, 10.0, 5.0, 9.0), (95.0, 9.4, 9.0, 4.5, 8.5)]
SKYLINE_X0 = 260.0
SUN = dict(elevation=18.0, azimuth=-8.0, color=(1.0, 0.78, 0.55), strength=4.0, angle=1.5)
HAZE_DENSITY = 0.002

# ---------------------------------------------------------------- vehicles (car-local, origin = ground centre)
SEDAN = dict(L=4.6, W=1.8, H=1.45, clear=0.16, belt=0.92, wheel_r=0.32, wheel_w=0.20, axle_x=1.35, track=0.76,
             A=(0.95, 0.92), B=(0.10, 1.45), Cr=(-0.95, 1.45), D=(-1.65, 0.98), hw_b=0.86, hw_t=0.70,
             pillars_x=[-0.50], roof_t=0.05,
             profile=[(2.05, 0.16), (2.30, 0.30), (2.32, 0.60), (2.15, 0.80), (0.95, 0.92), (-1.65, 0.98),
                      (-2.20, 0.93), (-2.32, 0.70), (-2.30, 0.30), (-2.05, 0.16)],
             seat_hip=(-0.15, 0.38, 0.50), dash_x=(0.45, 0.95), dash_z=0.95)
HERO_WINDOW = dict(x_rear=-0.46, sill=0.92, top=1.40)  # top = roof-slab underside  # driver's open window (front +Y)
MIRROR = dict(pos=(0.04, 0.0, 1.33), size=(0.26, 0.075), aim_cam=(-0.72, -0.15, 1.22), aim_rear=(-1.30, 0.0, 1.20))

COUPE = dict(L=4.8, W=1.95, H=1.30, clear=0.14, belt=0.80, wheel_r=0.34, wheel_w=0.26, axle_x=1.45, track=0.82,
             A=(0.75, 0.80), B=(-0.15, 1.27), Cr=(-1.15, 1.27), D=(-2.00, 0.86), hw_b=0.92, hw_t=0.72,
             pillars_x=[], roof_t=0.04,
             profile=[(2.20, 0.14), (2.42, 0.28), (2.41, 0.52), (2.25, 0.68), (0.75, 0.80), (-2.00, 0.86),
                      (-2.40, 0.82), (-2.44, 0.45), (-2.30, 0.22), (-2.10, 0.14)],
             seat_hip=(-0.32, 0.40, 0.33), dash_x=(0.30, 0.75), dash_z=0.80,
             well_inner=0.45, well_r=0.38)
COUPE_PAINT = dict(coat=0.0, coat_rough=0.1, rough=0.6, spec=0.05, metallic=0.0)   # matte-satin black: must read black, not mirror the hazy sky (Cycles grazing angle)
COUPE_BUTTON = (0.16, 0.08, 0.80)   # centre-console top, right-hand reach, visible over the dash
WHEEL_FOLD_DEG = 90.0
WHEEL_SLIDE = 0.10

MINIBUS = dict(L=5.2, W=1.9, H=2.2, clear=0.22, belt=1.15, wheel_r=0.36, wheel_w=0.22, axle_x=1.75, track=0.80,
               A=(2.15, 1.15), B=(1.80, 2.13), Cr=(-2.55, 2.13), D=(-2.60, 1.15), hw_b=0.93, hw_t=0.86,
               pillars_x=[1.55, 0.25, -1.15], roof_t=0.07,
               profile=[(2.45, 0.22), (2.60, 0.32), (2.62, 0.85), (2.45, 1.10), (2.15, 1.15), (-2.60, 1.15),
                        (-2.62, 0.35), (-2.45, 0.22)],
               seat_hip=(1.45, 0.42, 0.85), dash_x=(1.80, 2.20), dash_z=1.15,
               door_x=(0.30, 1.50), door_z=(0.30, 2.00))
BRT = dict(L=12.0, W=2.55, H=3.1, clear=0.30, belt=1.25, wheel_r=0.50, wheel_w=0.30, axle_x=4.0, track=1.05,
           A=(5.95, 1.25), B=(5.80, 3.02), Cr=(-5.95, 3.02), D=(-5.98, 1.25), hw_b=1.25, hw_t=1.20,
           pillars_x=[4.5, 3.0, 1.5, 0.0, -1.5, -3.0, -4.5], roof_t=0.08,
           profile=[(5.80, 0.30), (6.00, 0.45), (6.00, 1.25), (-6.00, 1.25), (-6.00, 0.40), (-5.85, 0.30)],
           seat_hip=(5.10, 0.70, 1.10), dash_x=(5.40, 5.95), dash_z=1.30)
KEKE = dict(L=2.6, W=1.3, H=1.75)
GENERIC = dict(
    sedan=dict(SEDAN, pillars_x=[-0.50]),
    suv=dict(L=4.7, W=1.9, H=1.75, clear=0.22, belt=1.05, wheel_r=0.38, wheel_w=0.24, axle_x=1.40, track=0.80,
             A=(1.15, 1.05), B=(0.35, 1.70), Cr=(-2.05, 1.70), D=(-2.30, 1.10), hw_b=0.90, hw_t=0.80,
             pillars_x=[-0.35, -1.45], roof_t=0.06,
             profile=[(2.10, 0.22), (2.35, 0.38), (2.37, 0.80), (2.20, 0.98), (1.15, 1.05), (-2.30, 1.10),
                      (-2.36, 0.90), (-2.35, 0.38), (-2.10, 0.22)],
             seat_hip=(0.0, 0.40, 0.62), dash_x=(0.65, 1.15), dash_z=1.08),
    minivan=dict(L=5.05, W=1.95, H=1.75, clear=0.17, belt=0.98, wheel_r=0.34, wheel_w=0.22, axle_x=1.50, track=0.83,
                 A=(1.30, 0.98), B=(0.55, 1.72), Cr=(-2.35, 1.72), D=(-2.45, 1.05), hw_b=0.92, hw_t=0.80,
                 pillars_x=[-0.15, -1.30], roof_t=0.06,
                 profile=[(2.30, 0.17), (2.52, 0.32), (2.53, 0.70), (2.30, 0.92), (1.30, 0.98), (-2.45, 1.05),
                          (-2.52, 0.85), (-2.50, 0.32), (-2.30, 0.17)],
                 seat_hip=(0.25, 0.40, 0.62), dash_x=(0.85, 1.30), dash_z=1.0),
    pickup=dict(L=5.3, W=1.85, H=1.8, clear=0.25, belt=1.08, wheel_r=0.38, wheel_w=0.24, axle_x=1.55, track=0.78,
                A=(1.05, 1.08), B=(0.35, 1.78), Cr=(-1.15, 1.78), D=(-1.25, 1.10), hw_b=0.88, hw_t=0.78,
                pillars_x=[-0.40], roof_t=0.06,
                profile=[(2.30, 0.25), (2.62, 0.45), (2.64, 0.85), (2.45, 1.02), (1.05, 1.08), (-1.25, 1.10),
                         (-2.60, 1.05), (-2.65, 0.45), (-2.40, 0.25)],
                seat_hip=(-0.05, 0.40, 0.70), dash_x=(0.60, 1.05), dash_z=1.10, bed=(-2.55, -1.35)),
    hatch=dict(L=4.0, W=1.75, H=1.50, clear=0.16, belt=0.92, wheel_r=0.31, wheel_w=0.20, axle_x=1.25, track=0.74,
               A=(0.85, 0.92), B=(0.05, 1.45), Cr=(-1.55, 1.45), D=(-1.90, 1.00), hw_b=0.84, hw_t=0.72,
               pillars_x=[-0.55], roof_t=0.05,
               profile=[(1.80, 0.16), (2.00, 0.30), (2.02, 0.60), (1.85, 0.80), (0.85, 0.92), (-1.90, 1.00),
                        (-2.00, 0.80), (-2.00, 0.30), (-1.80, 0.16)],
               seat_hip=(-0.20, 0.37, 0.50), dash_x=(0.35, 0.85), dash_z=0.95),
)
# 10 distinct generic designs (body type, colour); traffic re-uses them as linked copies
GENERIC_DESIGNS = [("sedan", "white"), ("sedan", "red"), ("suv", "darkblue"), ("sedan", "beige"),
                   ("suv", "grey"), ("hatch", "white"), ("suv", "beige"), ("minivan", "champagne"), ("pickup", "white"), ("minivan", "maroon")]

# ---------------------------------------------------------------- traffic
SEDAN_CRAWL = 0.5            # m/s, frames 1..300
SEDAN_STOP_F = 300
SEDAN_CREEP = (350, 375, 1.0)
SEDAN_START_X = -0.5 * 299 / 24
COUPE_GAP = 6.7
MINIBUS_AHEAD = 7.1
# (name, design, lane, x at frame 1, speed m/s). Speeds never increase front-to-back -> gaps never close.
TRAFFIC_L2 = [  # front to back
    ("CAR_generic_01", 0, 29.0, 1.5), ("CAR_generic_02", 2, 21.4, 1.5), ("CAR_minibus_02", "minibus", 13.8, 1.45),
    ("CAR_generic_03", 3, 6.4, 1.4), ("CAR_generic_04", 7, -0.8, 1.4),
    # deliberate gap (camera corridor for S01 / S06), then slower cars
    ("CAR_generic_05", 1, -29.0, 1.0), ("CAR_generic_06", 5, -36.6, 1.0)]
TRAFFIC_L4 = []   # outer lane removed; the conductor's danfo now uses the hard shoulder
MINIBUS_COND_X, MINIBUS_COND_F = 14.0, 798
MINIBUS_COND_Y = -12.8      # conductor's danfo crawls along the hard shoulder (Lagos style)
MINIBUS_COND_SPEED = 1.0
TRAFFIC_L3_EXTRA_AHEAD = [("CAR_generic_13", 2, 6.9), ("CAR_generic_14", 0, 14.0)]  # offsets ahead of minibus_ahead
TRAFFIC_L3_BEHIND = [("CAR_generic_15", 4), ("CAR_generic_16", 3)]
L3_GAP = 2.5
BRT_TRAFFIC = []   # BRT lane removed
KEKES = [("CAR_keke_01", -10.0, 2.0)]   # on the shoulder, ahead of the conductor's danfo (faster -> never meets it)
KEKES_L2 = [("CAR_keke_02", -42.2, 1.0)]  # one keke filtering at the back of L2
KEKE_Y = -12.8
OPP_SPEED = 5.0
OPP_TRAFFIC = [  # lane index, x at frame 1, design
    (0, 290, 0), (0, 250, 3), (0, 210, 2), (0, 160, "tanker"), (0, 140, 9), (0, 60, "lorry"),
    (1, 270, 4), (1, 185, 7), (1, 100, 5), (1, 75, 8), (1, 20, 0)]
TRUCK = dict(L=11.5, W=2.5, cab=(3.55, 5.75), cab_h=3.0, wheel_r=0.52, wheel_w=0.30, axles=[4.6, -3.4, -4.7],
             track=0.95, tank_r=1.1, cab_colours=dict(tanker=(0.75, 0.75, 0.73), lorry=(0.55, 0.06, 0.05)),
             tank=(0.72, 0.72, 0.70), band=(0.75, 0.40, 0.03), tarp=(0.06, 0.20, 0.55), wood=(0.35, 0.22, 0.12))

# ---------------------------------------------------------------- characters
HERO_HEIGHT, HORNER_HEIGHT = 1.78, 1.75
HAWKER_HEIGHT, CONDUCTOR_HEIGHT = 1.66, 1.72
HAWKER_Y = -11.0
HAWKER_SPEED = 1.0
HAWKER_FREEZE_F = 680
CONDUCTOR_LOCAL = (0.90, -1.10, 0.30)
CONDUCTOR_LEAN = 16.0                    # deg, hangs out of the doorway   # on the door step, minibus local
ENVELOPE_SEAT = (0.10, -0.22, 0.49)       # sedan local (passenger seat edge)
ENVELOPE_DROP_F = 452

# ---------------------------------------------------------------- coupe flight
COUPE_KEYS = dict(
    underglow=((649, 0.0), (670, 6.0)), lightbar_base=6.0, lightbar_boost=1.5,
    dust=(660, 720), rise=(685, 735, 3.5), hover_bob=0.03,
    glide=(745, 780, 8.0, 5.0), fly=(781, 846, 80.0, 18.0), fly_extra=(864, 115.0, 23.0),
    fly_y=(-19.5, -23.5))  # y at 846 / 864: drifts toward the sun azimuth
HEADLIGHT_FLASHES = [390, 398]    # each: 4 frames on, 4 off
HEADLIGHT_FLASH_STRENGTH = 20.0
LIGHTBAR_FLASH_STRENGTH = 40.0    # the nose light bar flashes too (base 6)
# real headlight beams (spot lights) that fire with the flashes, so the flash lights the sedan and reads in S03/S03B
HEADLIGHT_BEAM = dict(energy=2000.0, size=70.0, blend=0.6, pos=(2.42, 0.62, 0.55), tilt=-84.0, colour=(1.0, 0.97, 0.9))
BUTTON_KEYS = ((632, 2.0), (636, 25.0))
SHADOW_CARD = dict(size=(3.0, 1.5), frames=(752, 770), dist=3.0, sweep=4.0)
N_DUST, N_BAGS = 70, 7
# Air-thrust lift: 4 vents under the coupe blow vapour down; it hits the road and billows outward.
# intensity: (frame, 0..1) keys, linear between them.  Wheels stay upright the whole time.
THRUST = dict(nozzles=[(1.0, 0.42), (1.0, -0.42), (-1.05, 0.42), (-1.05, -0.42)], nozzle_z=0.12, nozzle_r=0.15,
              start=655, end=812, every=2, life=36, jet_speed=8.0, drag=1.7, spread_keep=0.8,
              intensity=[(655, 0.25), (672, 0.7), (685, 1.0), (735, 0.85), (745, 0.75), (780, 0.6), (812, 0.0)],
              density=27.0, colour=(0.9, 0.9, 0.92), dust_density=11.0)

# ---------------------------------------------------------------- lip sync
LINE_START, LINE_END = 515, 565
JAW_MAX_DEG = 18.0
FALLBACK_LINE_SEC = 2.1

# ---------------------------------------------------------------- cameras
SENSOR = 36.0
S01_KEYS = [  # frame, camera, target   ("H" = hero head, computed)
    (37, (-20, -9.4, 110), (-19, -9.4, 0)),
    (49, (-20, -9.4, 100), (-19, -9.4, 0)),
    (150, (-30, -5.0, 45), (-5, -9.4, 0)),
    (220, (-22, -5.8, 6), (0, -9.4, 1.0)),
    (270, (-8, -5.8, 2.2), "H"),
    (305, (-1.0, -6.4, 1.4), "H"),
    (336, ("H", (0.1, 1.25, 0.1)), "H"),
    (348, ("H", (0.1, 1.25, 0.1)), "H"),
]
S01_LENS = ((270, 28.0), (336, 45.0))
S01_FOCUS = ((290, "ahead"), (312, "H"))
CAM = dict(
    S01=dict(fstop=2.8),
    S02=dict(lens=28.0, fstop=4.0, noise_deg=0.2, offset=(-0.17, -0.25, 0.07)),
    S03=dict(lens=85.0, fstop=5.6),
    S03B=dict(lens=35.0, fstop=4.0, pos=(0.08, 0.20, 1.22)),
    S04=dict(lens=40.0, fstop=4.0, front=0.8, height=1.3, push=0.3),
    S05=dict(lens=85.0, fstop=4.0, side=1.4),
    S06=dict(lens=50.0, fstop=2.8, ahead=3.0, left=2.2, z=1.4, drift=0.2),
    S07=dict(lens=65.0, fstop=4.0, front=1.0, height=1.25),
    S08=dict(lens=24.0, fstop=8.0, pos=(-9.5, -16.5, 0.6), look=(-1.8, -9.4, 2.1)),
    S09=dict(lens=35.0, fstop=4.0, side=1.0, ahead=0.9, z=0.9, aim_up=0.08),
    S10=dict(lens=35.0, fstop=8.0, pos=(5.0, -13.55, 0.4), look=(60.0, -19.5, 12.0), creep=1.5),
)
CLEARANCE = 0.3
# near clip per camera (m). Too small a near clip at long range = depth flicker (z-fighting) on the road layers.
CLIP_START = dict(S01=None, S02=0.08, S03=0.3, S03B=0.08, S04=0.1, S05=0.2, S06=0.2, S07=0.1, S08=0.2, S09=0.1, S10=0.15)
S01_CLIP_KEYS = [(37, 2.0), (150, 2.0), (220, 0.5), (270, 0.2), (300, 0.08)]
CLIP_END = 1500.0
MAX_MP4_MB = 49
# shots rendered with Cycles instead of EEVEE on this machine (EEVEE planar probe is noise under CPU/llvmpipe)
ENGINE_OVERRIDE = {"S03": "cycles"}
CYCLES_SAMPLES = 48
CYCLES_SAMPLES_FAST = 24       # S03 in the "fast" final preset
REVIEW_EEVEE_SAMPLES = 8   # review stills only; finals use the scene setting

# ---------------------------------------------------------------- Lagos set dressing
LAGOS = dict(
    walls=[(0.72, 0.64, 0.48), (0.72, 0.48, 0.34), (0.45, 0.55, 0.60), (0.48, 0.56, 0.40), (0.68, 0.67, 0.62),
           (0.66, 0.50, 0.22), (0.68, 0.44, 0.44), (0.55, 0.52, 0.47), (0.62, 0.60, 0.35), (0.40, 0.47, 0.55)],
    cloth=[(0.75, 0.08, 0.06), (0.85, 0.65, 0.03), (0.06, 0.25, 0.65), (0.08, 0.45, 0.15), (0.80, 0.80, 0.78),
           (0.55, 0.10, 0.40), (0.90, 0.40, 0.05)],
    goods=[(0.80, 0.35, 0.02), (0.75, 0.70, 0.10), (0.85, 0.85, 0.85), (0.20, 0.45, 0.10), (0.60, 0.10, 0.08),
           (0.15, 0.30, 0.65), (0.45, 0.30, 0.15)],
    containers=[(0.08, 0.22, 0.45), (0.50, 0.12, 0.08), (0.10, 0.35, 0.18), (0.55, 0.45, 0.30)],
    shutters=[(0.35, 0.36, 0.38), (0.10, 0.25, 0.45), (0.15, 0.35, 0.20), (0.45, 0.15, 0.10)],
    kerb_block=1.0, gutter_w=0.7,
    kerb_colours=((0.78, 0.78, 0.75), (0.02, 0.02, 0.02)),   # painted kerbs: white / black
    pole_spacing=38.0, pole_y_right=-17.6, pole_y_left=9.8, pole_h=9.0, sag=0.6,
    bus_shelter_x=-62.0,
    stall_free_x=(-16.0, -3.0),   # keep the pavement clear around the S08 camera
    patches=[(18, -9.6, 3.5, 2.0), (32, -9.0, 5.0, 1.6), (-6, -5.6, 4.0, 2.2), (6, -9.2, 2.5, 1.8), (-25, -9.6, 6.0, 2.5), (45, -9.4, 3.0, 2.0), (-14, -12.6, 4.0, 1.5), (60, -5.8, 7.0, 3.0), (-48, -9.4, 5.0, 2.6), (90, -9.3, 4.0, 2.0), (-2, 2.4, 6.0, 2.5), (25, 2.4, 4.0, 2.0)],
    potholes=[(-3, -13.2, 0.45), (14, -13.6, 0.6), (27, -10.9, 0.35), (-20, -13.4, 0.5), (40, -12.9, 0.4), (8, -10.7, 0.3)],
    palms=[(-140, -17.7), (-95, -17.8), (-38, -17.7), (12, -17.8), (48, -17.7), (105, -17.8), (150, -17.7), (190, -17.8), (-120, 9.9), (-60, 10.0), (-10, 9.9), (30, 10.0), (90, 9.9), (130, 10.0)],
)

# ---------------------------------------------------------------- background people (proxy, low detail)
# name, x at frame 1, y, heading (+1 walks +X, -1 walks -X, 0 stands), speed m/s, facing deg (standers), tray
PED_WALK_Y_RIGHT, PED_WALK_Y_LEFT = -15.7, 7.9
PEDS = [
    ("CHR_ped_01", -70.0, PED_WALK_Y_RIGHT, 1, 1.2, 0, False),
    ("CHR_ped_02", 30.0, PED_WALK_Y_RIGHT - 0.4, -1, 1.1, 0, False),
    ("CHR_ped_03", 62.0, PED_WALK_Y_RIGHT, -1, 1.3, 0, False),
    ("CHR_ped_04", -12.0, PED_WALK_Y_RIGHT - 0.3, 1, 1.0, 0, True),
    ("CHR_ped_05", -30.0, PED_WALK_Y_LEFT, 1, 1.2, 0, False),
    ("CHR_ped_06", 40.0, PED_WALK_Y_LEFT + 0.3, -1, 1.1, 0, False),
    ("CHR_ped_07", -63.5, -17.1, 0, 0.0, 90, False),     # bus shelter
    ("CHR_ped_08", -61.8, -17.0, 0, 0.0, 70, False),
    ("CHR_ped_09", -60.2, -17.2, 0, 0.0, 110, False),
    ("CHR_ped_10", -5.0, -17.4, 0, 0.0, 95, False),      # by the shops
    ("CHR_ped_11", 31.0, -17.45, 0, 0.0, 80, True),
    ("CHR_hawker_02", 40.0, -7.6, -1, 0.5, 0, True),     # more hawkers working the jam
    ("CHR_hawker_03", 72.0, -11.0, -1, 0.45, 0, True),
]
PED_LOOKS = [  # top, bottom, skin, shoes, hair
    ((0.70, 0.70, 0.68), (0.10, 0.12, 0.25), (0.28, 0.17, 0.11), (0.05, 0.04, 0.03), (0.015, 0.012, 0.01)),
    ((0.65, 0.35, 0.05), (0.65, 0.35, 0.05), (0.25, 0.15, 0.10), (0.08, 0.05, 0.03), (0.30, 0.08, 0.30)),
    ((0.08, 0.30, 0.55), (0.06, 0.06, 0.07), (0.30, 0.18, 0.12), (0.75, 0.75, 0.75), (0.015, 0.012, 0.01)),
    ((0.55, 0.08, 0.10), (0.35, 0.28, 0.18), (0.22, 0.13, 0.09), (0.05, 0.04, 0.03), (0.015, 0.012, 0.01)),
    ((0.12, 0.40, 0.18), (0.12, 0.40, 0.18), (0.27, 0.16, 0.11), (0.08, 0.05, 0.03), (0.60, 0.45, 0.05)),
    ((0.85, 0.85, 0.82), (0.25, 0.25, 0.27), (0.24, 0.14, 0.10), (0.05, 0.04, 0.03), (0.015, 0.012, 0.01)),
]
