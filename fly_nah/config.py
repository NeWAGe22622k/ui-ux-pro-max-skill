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
                 beige=(0.55, 0.45, 0.30), grey=(0.25, 0.26, 0.27)),
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
    billboard=[(0.70, 0.10, 0.08), (0.08, 0.40, 0.15), (0.10, 0.25, 0.65)],
    kiosk=[(0.10, 0.35, 0.60), (0.65, 0.12, 0.10), (0.20, 0.50, 0.20)],
    tree_trunk=(0.12, 0.07, 0.04), tree_leaf=(0.06, 0.20, 0.04),
    dust=(0.35, 0.28, 0.20), bag_white=(0.85, 0.85, 0.85), bag_blue=(0.10, 0.25, 0.70),
)

# ---------------------------------------------------------------- road layout
ROAD_X = (-300.0, 300.0)
MEDIAN = dict(y=0.0, width=0.5, height=0.8)
LANE_W = 3.6
LANES = dict(L1=-2.2, L2=-5.8, L3=-9.4, L4=-13.0)
OPP_LANES = [2.4, 6.0, 9.6]
SHOULDER = (-18.0, -14.8)
PAVEMENT_RIGHT = (-21.5, -18.0)
OPP_EDGE = 11.4
OPP_SHOULDER = (11.4, 14.0)
PAVEMENT_LEFT = (14.0, 17.5)
PAVEMENT_H = 0.15
BRT_KERB = dict(y=-4.0, width=0.25, height=0.15)
LIGHT_SPACING = 30.0
LIGHT_HEIGHT = 9.0
LIGHT_ARM = 2.6
BRIDGE_X = 70.0
BRIDGE = dict(deck_z=6.2, width=3.0, y0=-21.0, y1=17.0, truss_h=2.2, stair_len=11.0)
BILLBOARDS = [(40.0, -20.5, 8.0, 4.0, 7.0), (-55.0, 16.5, 8.0, 4.0, 7.0), (135.0, -20.5, 9.0, 4.5, 8.0)]
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

COUPE = dict(L=4.8, W=1.95, H=1.30, clear=0.14, belt=0.80, wheel_r=0.36, wheel_w=0.26, axle_x=1.45, track=0.82,
             A=(0.75, 0.80), B=(-0.15, 1.27), Cr=(-1.15, 1.27), D=(-2.00, 0.86), hw_b=0.92, hw_t=0.72,
             pillars_x=[], roof_t=0.04,
             profile=[(2.20, 0.14), (2.42, 0.28), (2.40, 0.50), (2.25, 0.60), (0.75, 0.80), (-2.00, 0.86),
                      (-2.40, 0.82), (-2.44, 0.45), (-2.30, 0.22), (-2.10, 0.14)],
             seat_hip=(-0.32, 0.40, 0.33), dash_x=(0.30, 0.75), dash_z=0.80,
             well_inner=0.45, well_r=0.44)
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
    hatch=dict(L=4.0, W=1.75, H=1.50, clear=0.16, belt=0.92, wheel_r=0.31, wheel_w=0.20, axle_x=1.25, track=0.74,
               A=(0.85, 0.92), B=(0.05, 1.45), Cr=(-1.55, 1.45), D=(-1.90, 1.00), hw_b=0.84, hw_t=0.72,
               pillars_x=[-0.55], roof_t=0.05,
               profile=[(1.80, 0.16), (2.00, 0.30), (2.02, 0.60), (1.85, 0.80), (0.85, 0.92), (-1.90, 1.00),
                        (-2.00, 0.80), (-2.00, 0.30), (-1.80, 0.16)],
               seat_hip=(-0.20, 0.37, 0.50), dash_x=(0.35, 0.85), dash_z=0.95),
)
# 7 distinct generic designs (body type, colour); traffic re-uses them as linked copies
GENERIC_DESIGNS = [("sedan", "white"), ("sedan", "red"), ("suv", "darkblue"), ("sedan", "beige"),
                   ("suv", "grey"), ("hatch", "white"), ("suv", "beige")]

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
    ("CAR_generic_03", 3, 6.4, 1.4), ("CAR_generic_04", 4, -0.8, 1.4),
    # deliberate gap (camera corridor for S01 / S06), then slower cars
    ("CAR_generic_05", 1, -29.0, 1.0), ("CAR_generic_06", 5, -36.6, 1.0)]
TRAFFIC_L4 = [
    ("CAR_generic_07", 6, 26.0, 1.5), ("CAR_generic_08", 0, 18.6, 1.4), ("CAR_minibus_03", "minibus", 10.8, 1.3),
    ("CAR_generic_09", 3, 3.2, 1.2), ("CAR_generic_10", 1, -4.4, 1.1),
    ("CAR_minibus_conductor", "minibus", None, 1.0),  # x solved so it sits at MINIBUS_COND_X at MINIBUS_COND_F
    ("CAR_generic_11", 4, -50.0, 1.0), ("CAR_generic_12", 2, -57.4, 1.0)]
MINIBUS_COND_X, MINIBUS_COND_F = 14.0, 798
TRAFFIC_L3_EXTRA_AHEAD = [("CAR_generic_13", 2, 6.9), ("CAR_generic_14", 0, 14.0)]  # offsets ahead of minibus_ahead
TRAFFIC_L3_BEHIND = [("CAR_generic_15", 4), ("CAR_generic_16", 3)]
L3_GAP = 2.5
BRT_TRAFFIC = [("CAR_brt_01", -90.0, 3.0)]
KEKES = [("CAR_keke_01", -30.0, 2.0), ("CAR_keke_02", -100.0, 2.0)]
KEKE_Y = -16.4
OPP_SPEED = 5.0
OPP_TRAFFIC = [  # lane index, x at frame 1, design
    (0, 290, 0), (0, 210, 2), (0, 140, 6), (0, 60, 3),
    (1, 270, 4), (1, 185, 1), (1, 100, 5), (1, 20, 0),
    (2, 250, 3), (2, 160, 6), (2, 75, 2)]

# ---------------------------------------------------------------- characters
HERO_HEIGHT, HORNER_HEIGHT = 1.78, 1.75
HAWKER_HEIGHT, CONDUCTOR_HEIGHT = 1.66, 1.72
HAWKER_Y = -11.2
HAWKER_SPEED = 1.0
HAWKER_FREEZE_F = 680
CONDUCTOR_LOCAL = (0.90, -1.10, 0.30)
CONDUCTOR_LEAN = 12.0                    # deg, hangs out of the doorway   # on the door step, minibus local
ENVELOPE_SEAT = (0.10, -0.22, 0.49)       # sedan local (passenger seat edge)
ENVELOPE_DROP_F = 452

# ---------------------------------------------------------------- coupe flight
COUPE_KEYS = dict(
    underglow=((649, 0.0), (670, 15.0)), lightbar_base=6.0, lightbar_boost=1.5,
    fold=(655, 691), dust=(660, 720), rise=(685, 735, 3.5), hover_bob=0.03,
    glide=(745, 780, 8.0, 5.0), fly=(781, 846, 80.0, 18.0), fly_extra=(864, 115.0, 23.0),
    fly_y=(-19.5, -23.5))  # y at 846 / 864: drifts toward the sun azimuth
HEADLIGHT_FLASHES = [390, 398]    # each: 4 frames on, 4 off
HEADLIGHT_FLASH_STRENGTH = 20.0
BUTTON_KEYS = ((632, 2.0), (636, 25.0))
SHADOW_CARD = dict(size=(3.0, 1.5), frames=(752, 770), dist=3.0, sweep=4.0)
N_DUST, N_BAGS = 70, 7

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
    S10=dict(lens=35.0, fstop=8.0, pos=(5.0, -13.8, 0.4), look=(60.0, -19.5, 12.0), creep=1.5),
)
CLEARANCE = 0.3
# shots rendered with Cycles instead of EEVEE on this machine (EEVEE planar probe is noise under CPU/llvmpipe)
ENGINE_OVERRIDE = {"S03": "cycles"}
CYCLES_SAMPLES = 48
REVIEW_EEVEE_SAMPLES = 8   # review stills only; finals use the scene setting
