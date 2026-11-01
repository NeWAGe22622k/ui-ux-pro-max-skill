# Fly Nah — Blender (bpy) build

32-second, 10-shot animation built entirely from Python. Everything tweakable lives in `fly_nah/config.py`.

```
pip install bpy            # Blender 5.2 as a Python module (Python 3.13)
python3 fly_nah/build_scene.py              # builds + saves fly_nah/fly_nah.blend (~4 s)
python3 fly_nah/checks.py                   # Phase 2 automated checks -> review/check_report.txt
python3 fly_nah/render.py --mode review     # Phase 3: EEVEE stills, contact sheet, Workbench animatic
python3 fly_nah/render.py --mode testframe --shot S06 --engine eevee
python3 fly_nah/render.py --mode final [--shot S01,S02] [--engine eevee|workbench|cycles]
```
With a Blender app instead: `blender -b -P fly_nah/build_scene.py` and
`blender -b fly_nah/fly_nah.blend -P fly_nah/render.py -- --mode review`.

Renders are chunked (24 frames per worker process, overwrite off, placeholders on), so re-running a
command resumes where a timed-out session stopped.
