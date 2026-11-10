"""Speaks the walkthrough's narration with Kokoro and notes when each word is said.

    python narrate.py --samples   the first two lines in each candidate voice, to choose from
    python narrate.py --final     the whole script in the voice from config.json, plus timings.json
"""

from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path
from typing import Any, Iterable

HERE = Path(__file__).resolve().parent
SHOWCASE = HERE.parent
SAMPLES_DIR = SHOWCASE / "out" / "voice-samples"
FINAL_DIR = SHOWCASE / "video" / "public" / "voice"

SAMPLE_RATE = 24_000
LEAD_IN_MS = 400
MIN_MS, MAX_MS = 80_000, 88_000
CANDIDATES = ("am_michael", "am_onyx", "am_fenrir", "bm_george")


def words_from_tokens(tokens: Iterable[Any], offset_ms: int) -> list[dict[str, Any]]:
    """Turns Kokoro's tokens into caption words.

    Punctuation tokens join the word before them, so captions read "Cartopia," rather
    than "Cartopia" and ",". A token Kokoro left untimed borrows its neighbours' times.
    """
    words: list[dict[str, Any]] = []
    for token in tokens:
        text = token.text
        if not any(ch.isalnum() for ch in text):
            if words:
                words[-1]["text"] += text
            continue
        start = token.start_ts
        end = token.end_ts
        if start is None:
            start = words[-1]["endMs"] / 1000 - offset_ms / 1000 if words else 0.0
        if end is None:
            end = start
        words.append({
            "text": text,
            "startMs": offset_ms + round(start * 1000),
            "endMs": offset_ms + round(end * 1000),
        })
    return words


def length_problem(duration_ms: int) -> str | None:
    """Explains why the narration can't be used, or returns None when it fits."""
    # Kokoro's speed is a rate: a lower speed reads slower, so it takes longer.
    if duration_ms < MIN_MS:
        return f"The narration runs {duration_ms / 1000:.1f} s, under {MIN_MS / 1000:.0f} s: lower the speed in config.json."
    if duration_ms > MAX_MS:
        return f"The narration runs {duration_ms / 1000:.1f} s, over {MAX_MS / 1000:.0f} s: raise the speed in config.json."
    return None


def silence(ms: int):
    import numpy as np

    return np.zeros(round(SAMPLE_RATE * ms / 1000), dtype=np.float32)


def speak(pipeline: Any, text: str, voice: str, speed: float, offset_ms: int):
    """Returns the audio for one line and its words, timed from offset_ms."""
    import numpy as np

    chunks = []
    words: list[dict[str, Any]] = []
    elapsed_ms = offset_ms
    for result in pipeline(text, voice=voice, speed=speed):
        audio = result.audio.numpy().astype(np.float32)
        words.extend(words_from_tokens(result.tokens or [], elapsed_ms))
        chunks.append(audio)
        elapsed_ms += round(len(audio) * 1000 / SAMPLE_RATE)
    return np.concatenate(chunks), words


def pipeline_for(voice: str):
    from kokoro import KPipeline

    # The voice's first letter is its accent: a for American English, b for British.
    return KPipeline(lang_code=voice[0], repo_id="hexgrad/Kokoro-82M")


def load_script() -> list[dict[str, Any]]:
    return json.loads((HERE / "script.json").read_text(encoding="utf-8"))["segments"]


def render(segments: list[dict[str, Any]], voice: str, speed: float):
    import numpy as np

    pipeline = pipeline_for(voice)
    parts = [silence(LEAD_IN_MS)]
    cursor_ms = LEAD_IN_MS
    timed = []
    for segment in segments:
        audio, words = speak(pipeline, segment["text"], voice, speed, cursor_ms)
        end_ms = cursor_ms + round(len(audio) * 1000 / SAMPLE_RATE)
        timed.append({
            "id": segment["id"],
            "text": segment["text"],
            "startMs": cursor_ms,
            "endMs": end_ms,
            "words": words,
        })
        parts.extend([audio, silence(segment["pauseAfterMs"])])
        cursor_ms = end_ms + segment["pauseAfterMs"]
    return np.concatenate(parts), timed, cursor_ms


def samples() -> None:
    import soundfile as sf

    SAMPLES_DIR.mkdir(parents=True, exist_ok=True)
    lines = [s for s in load_script() if s["id"] in ("intro", "browse")]
    for voice in CANDIDATES:
        audio, _, duration_ms = render(lines, voice, 1.0)
        path = SAMPLES_DIR / f"{voice}.wav"
        sf.write(path, audio, SAMPLE_RATE)
        print(f"{path}  {duration_ms / 1000:.1f} s")


def final() -> int:
    import soundfile as sf

    config = json.loads((HERE / "config.json").read_text(encoding="utf-8"))
    voice, speed = config["voice"], float(config["speed"])
    audio, timed, duration_ms = render(load_script(), voice, speed)

    FINAL_DIR.mkdir(parents=True, exist_ok=True)
    sf.write(FINAL_DIR / "narration.wav", audio, SAMPLE_RATE)
    timings = {"durationMs": duration_ms, "voice": voice, "segments": timed}
    (FINAL_DIR / "timings.json").write_text(json.dumps(timings, indent=2), encoding="utf-8")
    print(f"{FINAL_DIR / 'narration.wav'}  {duration_ms / 1000:.1f} s  {voice} at {speed}")

    problem = length_problem(duration_ms)
    if problem:
        print(problem, file=sys.stderr)
        return 1
    return 0


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    mode = parser.add_mutually_exclusive_group(required=True)
    mode.add_argument("--samples", action="store_true")
    mode.add_argument("--final", action="store_true")
    args = parser.parse_args()
    if args.samples:
        samples()
        return 0
    return final()


if __name__ == "__main__":
    sys.exit(main())
