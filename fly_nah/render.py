"""Rendering: review stills, contact sheet, Workbench animatic, EEVEE test frame, final shots.

python3 fly_nah/render.py --mode review        # stills (EEVEE 960x540) + contact sheet + animatic
python3 fly_nah/render.py --mode stills        # just the review stills (+ contact sheet)
python3 fly_nah/render.py --mode animatic      # Workbench animatic only (chunked, resumable)
python3 fly_nah/render.py --mode wbstills      # quick Workbench stills (layout iteration)
python3 fly_nah/render.py --mode testframe [--shot S06] [--engine eevee|cycles]
python3 fly_nah/render.py --mode final [--shot S01,S02] [--engine eevee|workbench]
(blender -b fly_nah/fly_nah.blend -P fly_nah/render.py -- --mode review  also works)
"""
import argparse
import glob
import json
import os
import shutil
import subprocess
import sys
import time

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)
import config as K  # noqa: E402

PY = sys.executable if "blender" not in os.path.basename(sys.executable).lower() else "python3"
CHUNK = 24
QUALITY = "full"


def _args():
    argv = sys.argv[sys.argv.index("--") + 1:] if "--" in sys.argv else sys.argv[1:]
    ap = argparse.ArgumentParser()
    ap.add_argument("--mode", default="review")
    ap.add_argument("--shot", default="")
    ap.add_argument("--engine", default="eevee")
    ap.add_argument("--start", type=int, default=0)
    ap.add_argument("--end", type=int, default=0)
    ap.add_argument("--out", default="")
    ap.add_argument("--res", default="")
    ap.add_argument("--frames", default="")
    ap.add_argument("--quality", default="full")
    return ap.parse_args(argv)


def _open():
    import bpy
    if os.path.abspath(bpy.data.filepath or "") != os.path.abspath(K.BLEND_PATH):
        bpy.ops.wm.open_mainfile(filepath=K.BLEND_PATH)
    return bpy


def _setup(bpy, engine, res):
    sc = bpy.context.scene
    w, h = res
    sc.render.resolution_x, sc.render.resolution_y = w, h
    sc.render.resolution_percentage = 100
    sc.render.image_settings.file_format = "PNG"
    sc.render.image_settings.color_mode = "RGB"
    sc.render.use_motion_blur = False
    sc.render.film_transparent = False
    card = bpy.data.objects.get("FX_shadow_card")
    if engine == "workbench":
        sc.render.engine = "BLENDER_WORKBENCH"
        sh = sc.display.shading
        sh.light = "STUDIO"
        sh.color_type = "MATERIAL"
        sh.show_shadows = True
        sh.shadow_intensity = 0.6
        sh.show_cavity = True
        sh.cavity_type = "WORLD"
        sh.show_specular_highlight = True
        sc.display.render_aa = "8"
        sc.view_settings.view_transform = "Standard"
        sc.view_settings.look = "None"
        for o in bpy.data.objects:
            if o.get("workbench_hide"):
                o.hide_render = True
        sc.render.use_compositing = False
    elif engine == "cycles":
        sc.render.engine = "CYCLES"
        sc.cycles.samples = K.CYCLES_SAMPLES
        sc.cycles.use_denoising = True
        sc.view_settings.view_transform = "AgX"
        sc.view_settings.look = "AgX - Medium High Contrast"
    else:
        sc.render.engine = "BLENDER_EEVEE"
        if w < 1920:
            sc.eevee.taa_render_samples = K.REVIEW_EEVEE_SAMPLES
        if QUALITY == "fast":   # cheaper EEVEE preset for CPU-only machines
            sc.eevee.taa_render_samples = 8
            sc.eevee.shadow_resolution_scale = 0.25
            sc.eevee.use_fast_gi = False
        sc.view_settings.view_transform = "AgX"
        sc.view_settings.look = "AgX - Medium High Contrast"
        sc.render.use_compositing = True
    return sc


def _engine_for(sc, shot, engine):
    """Per-shot engine override (config.ENGINE_OVERRIDE) when the requested engine is EEVEE."""
    if engine == "eevee" and shot in K.ENGINE_OVERRIDE:
        sc.render.engine = "CYCLES"
        sc.cycles.device = "CPU"
        sc.cycles.samples = K.CYCLES_SAMPLES
        sc.cycles.use_denoising = True
    elif engine == "eevee":
        sc.render.engine = "BLENDER_EEVEE"


def _cam(bpy, shot):
    return bpy.data.objects[f"CAM_{shot}"]


def _shot(name):
    return next(s for s in K.SHOTS if s[0] == name)


def _clear_markers(sc):
    sc.timeline_markers.clear()


def _bind_markers(bpy, sc):
    sc.timeline_markers.clear()
    for s in K.SHOTS:
        if s[0] == "S03B":
            continue
        m = sc.timeline_markers.new(s[0], frame=s[1])
        m.camera = _cam(bpy, s[0])

# ------------------------------------------------------------------ workers (run inside bpy)


def worker_frames(a):
    bpy = _open()
    res = tuple(int(x) for x in a.res.split("x"))
    sc = _setup(bpy, a.engine, res)
    os.makedirs(a.out, exist_ok=True)
    if a.mode == "w_seq":     # sequence rendered with markers (animatic) or single camera (final)
        if a.shot:
            _clear_markers(sc)
            sc.camera = _cam(bpy, a.shot)
            _engine_for(sc, a.shot, a.engine)
        else:
            _bind_markers(bpy, sc)
        sc.render.use_overwrite = False
        sc.render.use_placeholder = True
        sc.render.filepath = os.path.join(a.out, "####")
        sc.frame_start, sc.frame_end = a.start, a.end
        bpy.ops.render.render(animation=True)
    elif a.mode == "w_stills":
        _clear_markers(sc)
        for item in a.frames.split(","):
            shot, f = item.split(":")
            path = os.path.join(a.out, f"{shot}_f{int(f):03d}.png")
            if os.path.exists(path) and os.path.getsize(path) > 0:
                continue
            sc.camera = _cam(bpy, shot)
            _engine_for(sc, shot, a.engine)
            sc.frame_set(int(f))
            sc.render.filepath = path
            t = time.time()
            bpy.ops.render.render(write_still=True)
            print(f"[still] {shot} f{f} {time.time() - t:.1f}s", flush=True)


def _spawn(args):
    cmd = [PY, os.path.abspath(__file__)] + args
    r = subprocess.run(cmd, capture_output=True, text=True)
    if r.returncode != 0:
        print(r.stdout[-2000:], r.stderr[-4000:])
        raise SystemExit(f"worker failed: {' '.join(args)}")
    for line in r.stdout.splitlines():
        if line.startswith("[still]"):
            print(line, flush=True)

# ------------------------------------------------------------------ drivers


def review_frames():
    out = []
    for s in K.SHOTS:
        a, b = s[1], s[2]
        for f in (a, (a + b) // 2, b):
            out.append((s[0], f))
    return out


def stills(engine="eevee", res=(960, 540), out_dir=None):
    out_dir = out_dir or K.REVIEW_DIR
    fr = review_frames()
    for i in range(0, len(fr), 6):
        part = ",".join(f"{s}:{f}" for s, f in fr[i:i + 6])
        _spawn(["--mode", "w_stills", "--engine", engine, "--res", f"{res[0]}x{res[1]}", "--out", out_dir,
                "--frames", part])
    contact_sheet(out_dir)


def contact_sheet(out_dir):
    from PIL import Image, ImageDraw, ImageFont
    fr = review_frames()
    tw, th = 480, 270
    pad, lab = 8, 26
    cols = 6
    rows = (len(fr) + cols - 1) // cols
    sheet = Image.new("RGB", (cols * (tw + pad) + pad, rows * (th + lab + pad) + pad), (24, 24, 26))
    d = ImageDraw.Draw(sheet)
    try:
        font = ImageFont.truetype("/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf", 18)
    except Exception:
        font = ImageFont.load_default()
    for i, (s, f) in enumerate(fr):
        p = os.path.join(out_dir, f"{s}_f{f:03d}.png")
        x = pad + (i % cols) * (tw + pad)
        y = pad + (i // cols) * (th + lab + pad)
        if os.path.exists(p):
            sheet.paste(Image.open(p).convert("RGB").resize((tw, th)), (x, y + lab))
        d.text((x + 2, y + 3), f"{s}  f{f}", fill=(235, 235, 235), font=font)
    path = os.path.join(out_dir, "contact_sheet.png")
    sheet.save(path)
    print(f"contact sheet -> {path}")
    return path


def sequence(out_dir, start, end, engine, res, shot=""):
    os.makedirs(out_dir, exist_ok=True)
    for a in range(start, end + 1, CHUNK):
        b = min(end, a + CHUNK - 1)
        done = all(os.path.exists(os.path.join(out_dir, f"{f:04d}.png")) and
                   os.path.getsize(os.path.join(out_dir, f"{f:04d}.png")) > 0 for f in range(a, b + 1))
        if done:
            continue
        # remove zero-byte placeholders left by an interrupted run so they get re-rendered
        for f in range(a, b + 1):
            p = os.path.join(out_dir, f"{f:04d}.png")
            if os.path.exists(p) and os.path.getsize(p) == 0:
                os.remove(p)
        t = time.time()
        args = ["--mode", "w_seq", "--engine", engine, "--res", f"{res[0]}x{res[1]}", "--out", out_dir,
                "--start", str(a), "--end", str(b), "--quality", QUALITY]
        if shot:
            args += ["--shot", shot]
        _spawn(args)
        print(f"[seq] {shot or 'animatic'} {a}-{b} done ({(time.time() - t) / (b - a + 1):.1f}s/frame)", flush=True)


def encode(frames_dir, start, end, mp4, crf=16, audio=None, audio_offset=0.0):
    cmd = ["ffmpeg", "-y", "-loglevel", "error", "-framerate", str(K.FPS), "-start_number", str(start),
           "-i", os.path.join(frames_dir, "%04d.png")]
    if audio:
        cmd += ["-i", audio, "-filter_complex", f"[1:a]adelay={int(audio_offset * 1000)}:all=1[a]", "-map", "0:v",
                "-map", "[a]", "-c:a", "aac", "-b:a", "160k"]
    else:
        cmd += ["-an"]
    cmd += ["-frames:v", str(end - start + 1), "-c:v", "libx264", "-preset", "slow", "-crf", str(crf),
            "-pix_fmt", "yuv420p", "-r", str(K.FPS), "-movflags", "+faststart", mp4]
    subprocess.run(cmd, check=True)
    return mp4


def animatic():
    fdir = os.path.join(K.REVIEW_DIR, "animatic_frames")
    sequence(fdir, K.STORY_START, K.STORY_END, "workbench", (960, 540))
    wav = os.path.join(K.AUDIO_DIR, "hero_line.wav")
    mp4 = os.path.join(K.REVIEW_DIR, "animatic.mp4")
    encode(fdir, K.STORY_START, K.STORY_END, mp4, crf=23, audio=wav if os.path.exists(wav) else None,
           audio_offset=(K.LINE_START - K.STORY_START) / K.FPS)
    print(f"animatic -> {mp4}")


def ffprobe(mp4):
    r = subprocess.run(["ffprobe", "-v", "error", "-select_streams", "v:0", "-count_frames", "-show_entries",
                        "stream=width,height,r_frame_rate,nb_read_frames", "-of", "json", mp4],
                       capture_output=True, text=True)
    s = json.loads(r.stdout)["streams"][0]
    num, den = s["r_frame_rate"].split("/")
    return int(s["width"]), int(s["height"]), float(num) / float(den), int(s["nb_read_frames"])


def final(shots, engine):
    """Per-shot 1920x1080 render (frames -> H.264 MP4, no audio) + mid-shot PNG still + ffprobe table."""
    rows = []
    sdir = os.path.join(K.RENDER_DIR, "stills")
    os.makedirs(sdir, exist_ok=True)
    tag = "" if engine == "eevee" else f"_{engine}"
    crf = 14 if engine != "workbench" else 17
    for s in K.SHOTS:
        if shots and s[0] not in shots:
            continue
        name, _, _, a, b, stem = s
        fdir = os.path.join(K.RENDER_DIR, "frames", f"{stem}{tag}")
        t = time.time()
        sequence(fdir, a, b, engine, (1920, 1080), shot=name)
        mp4 = os.path.join(K.RENDER_DIR, f"{stem}{tag}.mp4")
        encode(fdir, a, b, mp4, crf=crf)
        if os.path.getsize(mp4) > K.MAX_MP4_MB * 1e6:   # keep every file under the size limit
            encode(fdir, a, b, mp4, crf=crf + 4)
        mid = (a + b) // 2
        shutil.copy(os.path.join(fdir, f"{mid:04d}.png"), os.path.join(sdir, f"{stem}{tag}_f{mid:03d}.png"))
        w, h, fps, n = ffprobe(mp4)
        ok = (w, h) == (1920, 1080) and abs(fps - K.FPS) < 1e-3 and n == b - a + 1
        rows.append((os.path.basename(mp4), w, h, fps, n, b - a + 1, os.path.getsize(mp4) / 1e6, ok))
        print(f"[final] {name} done in {(time.time() - t) / 60:.1f} min -> {rows[-1]}", flush=True)
    lines = ["file                                   res        fps  frames/expected   MB    check"]
    for r in rows:
        lines.append(f"{r[0]:38s} {r[1]}x{r[2]}  {r[3]:.0f}   {r[4]}/{r[5]}        {r[6]:5.1f}  {'PASS' if r[7] else 'FAIL'}")
    print("\n".join(lines))
    with open(os.path.join(K.RENDER_DIR, f"ffprobe_report{tag}.txt"), "w") as fh:
        fh.write("\n".join(lines) + "\n")


def testframe(shot, engine):
    s = _shot(shot)
    mid = (s[1] + s[2]) // 2
    out = os.path.join(K.RENDER_DIR, "test")
    os.makedirs(out, exist_ok=True)
    t = time.time()
    _spawn(["--mode", "w_stills", "--engine", engine, "--res", "1920x1080", "--out", out, "--frames", f"{shot}:{mid}",
            "--quality", QUALITY])
    dt = time.time() - t
    total = sum(x[4] - x[3] + 1 for x in K.SHOTS)
    print(f"test frame {shot} f{mid} ({engine}, 1920x1080): {dt:.1f}s incl. file load")
    print(f"projected: {total} frames x {dt:.0f}s = {total * dt / 3600:.1f} h")


def main():
    global QUALITY
    a = _args()
    QUALITY = a.quality
    if a.mode.startswith("w_"):
        worker_frames(a)
        return
    shots = [x for x in a.shot.split(",") if x]
    if a.mode in ("review", "stills"):
        stills("eevee")
        if a.mode == "review":
            animatic()
    elif a.mode == "contact":
        contact_sheet(K.REVIEW_DIR)
    elif a.mode == "wbstills":
        stills("workbench", (960, 540), os.path.join(K.ROOT, "review_wb"))
    elif a.mode == "animatic":
        animatic()
    elif a.mode == "testframe":
        testframe(shots[0] if shots else "S06", a.engine)
    elif a.mode == "final":
        final(shots, a.engine)


if __name__ == "__main__":
    main()
