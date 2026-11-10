"""Composes the walkthrough's background music: a calm, light loop under the narration.

90 BPM in D major, I-vi-IV-V with each chord held for two bars. A soft pad, a plucked
arpeggio on beats 1 and 3, and a light kick from bar 3 that drops out for the last four
bars. The same seed always gives the same track.

    python compose.py   writes ../public/music/music.wav (48 kHz stereo, exactly 92 s)
                        and click.wav, the pointer's click sound
"""

from __future__ import annotations

from pathlib import Path

import numpy as np
import soundfile as sf

OUT = Path(__file__).resolve().parent.parent / "public" / "music" / "music.wav"
CLICK = OUT.parent / "click.wav"

SR = 48_000
LENGTH_S = 92.0
BPM = 90
BEAT = 60 / BPM
BAR = 4 * BEAT
PAD_CUTOFF = 1_200.0
PEAK_DBFS = -3.0
FADE_OUT_S = 3.0
SEED = 2026

# D, Bm, G, A as MIDI notes: a close-voiced triad for the pad, and a root for the bass of the pad.
CHORDS = [
    (50, [62, 66, 69]),  # D:  D4 F#4 A4
    (47, [62, 66, 71]),  # Bm: D4 F#4 B4
    (43, [62, 67, 71]),  # G:  D4 G4 B4
    (45, [61, 64, 69]),  # A:  C#4 E4 A4
]


def hz(midi: float) -> float:
    return 440.0 * 2 ** ((midi - 69) / 12)


def total_samples() -> int:
    return round(LENGTH_S * SR)


def chord_at(bar: int) -> tuple[int, list[int]]:
    return CHORDS[(bar // 2) % len(CHORDS)]


def soft_saw(freq: float, t: np.ndarray) -> np.ndarray:
    """A saw wave built from its harmonics, already low-passed around PAD_CUTOFF."""
    wave = np.zeros_like(t)
    k = 1
    while freq * k < 6_000:
        gain = (1 / k) / np.sqrt(1 + (freq * k / PAD_CUTOFF) ** 4)
        wave += gain * np.sin(2 * np.pi * freq * k * t)
        k += 1
    return wave


def pad(n: int) -> np.ndarray:
    out = np.zeros((n, 2))
    span = 2 * BAR
    attack, release = 0.9, 1.2
    start = 0.0
    bar = 0
    while start < LENGTH_S:
        root, triad = chord_at(bar)
        length = span + release
        i0 = round(start * SR)
        i1 = min(n, i0 + round(length * SR))
        t = np.arange(i1 - i0) / SR
        env = np.minimum(1, t / attack) * np.clip((length - t) / release, 0, 1)
        for index, note in enumerate([root + 12, *triad]):
            for cents, side in ((-7, 0), (7, 1)):
                voice = soft_saw(hz(note + cents / 100), t) * env
                gain = 0.11 if index == 0 else 0.07
                out[i0:i1, side] += voice * gain
                out[i0:i1, 1 - side] += voice * gain * 0.55
        start += span
        bar += 2
    return out


def karplus_strong(freq: float, seconds: float, rng: np.random.Generator) -> np.ndarray:
    """A soft plucked string: filtered noise in a delay line that loses brightness as it rings."""
    period = max(2, round(SR / freq))
    n = round(seconds * SR)
    out = np.zeros(n + period)
    burst = rng.uniform(-1, 1, period)
    out[:period] = np.convolve(burst, np.ones(4) / 4, mode="same")  # a soft pick
    decay = 0.996
    for start in range(period, n + period, period):
        stop = min(start + period, n + period)
        prev = out[start - period:stop - period]
        shifted = out[start - period - 1:stop - period - 1] if start - period - 1 >= 0 else np.concatenate([[0], prev[:-1]])
        out[start:stop] = decay * 0.5 * (prev + shifted)
    note = out[period:]
    return note * np.exp(-np.arange(n) / SR / 0.9)


def plucks(n: int, rng: np.random.Generator) -> np.ndarray:
    out = np.zeros((n, 2))
    bars = int(LENGTH_S / BAR) + 1
    for bar in range(bars):
        _, triad = chord_at(bar)
        for beat in (0, 2):
            hit = bar * BAR + beat * BEAT
            # Up on beat 1, down on beat 3, each note an eighth of a beat after the last.
            notes = [m + 12 for m in triad]
            if beat == 2:
                notes = list(reversed(notes))
            for step, midi in enumerate(notes):
                at = hit + step * BEAT / 4
                i0 = round(at * SR)
                if i0 >= n:
                    continue
                tone = karplus_strong(hz(midi), 1.6, rng) * rng.uniform(0.75, 1.0) * 0.16
                i1 = min(n, i0 + len(tone))
                pan = 0.5 + 0.35 * np.sin(step * 2.1 + bar)
                out[i0:i1, 0] += tone[: i1 - i0] * (1 - pan)
                out[i0:i1, 1] += tone[: i1 - i0] * pan
    return out


def kicks(n: int) -> np.ndarray:
    out = np.zeros((n, 2))
    t = np.arange(round(0.35 * SR)) / SR
    pitch = 45 + 70 * np.exp(-t / 0.04)
    kick = np.sin(2 * np.pi * np.cumsum(pitch) / SR) * np.exp(-t / 0.11) * 0.32
    last_bar = int(LENGTH_S / BAR)
    for bar in range(2, last_bar - 4):
        for beat in range(4):
            i0 = round((bar * BAR + beat * BEAT) * SR)
            i1 = min(n, i0 + len(kick))
            out[i0:i1] += kick[: i1 - i0, None] * (1.0 if beat in (0, 2) else 0.7)
    return out


def reverb(dry: np.ndarray, rng: np.random.Generator) -> np.ndarray:
    """A small room: each channel convolved with its own decaying noise tail."""
    seconds = 2.2
    t = np.arange(round(seconds * SR)) / SR
    wet = np.zeros_like(dry)
    size = 1 << int(np.ceil(np.log2(len(dry) + len(t))))
    for ch in range(2):
        ir = rng.normal(0, 1, len(t)) * np.exp(-t / 0.55)
        ir[: round(0.02 * SR)] = 0
        ir /= np.sqrt(np.sum(ir ** 2))
        full = np.fft.irfft(np.fft.rfft(dry[:, ch], size) * np.fft.rfft(ir, size), size)
        wet[:, ch] = full[: len(dry)]
    return dry * 0.8 + wet * 0.35


def compose() -> np.ndarray:
    rng = np.random.default_rng(SEED)
    n = total_samples()
    mix = pad(n) + plucks(n, rng)
    mix = reverb(mix, rng) + kicks(n)

    t = np.arange(n) / SR
    fade_in = np.minimum(1, t / 1.5)
    fade_out = np.clip((LENGTH_S - t) / FADE_OUT_S, 0, 1)
    mix *= (fade_in * fade_out)[:, None]

    mix -= mix.mean(axis=0)
    peak = np.max(np.abs(mix))
    return (mix / peak * 10 ** (PEAK_DBFS / 20)).astype(np.float32)


def click() -> np.ndarray:
    """A soft mouse click for the pointer: a short, muted tick with a little body under it."""
    rng = np.random.default_rng(SEED + 1)
    t = np.arange(round(0.06 * SR)) / SR
    tick = rng.uniform(-1, 1, len(t)) * np.exp(-t / 0.004)
    tick = np.convolve(tick, np.ones(6) / 6, mode="same")
    body = np.sin(2 * np.pi * 1_400 * t) * np.exp(-t / 0.012) * 0.6
    sound = tick + body
    sound /= np.max(np.abs(sound))
    return np.repeat((sound * 10 ** (-6 / 20))[:, None], 2, axis=1).astype(np.float32)


def main() -> None:
    OUT.parent.mkdir(parents=True, exist_ok=True)
    sf.write(OUT, compose(), SR, subtype="PCM_16")
    sf.write(CLICK, click(), SR, subtype="PCM_16")
    info = sf.info(OUT)
    print(f"{OUT}  {info.duration:.2f} s  {info.samplerate} Hz  {info.channels} ch")


if __name__ == "__main__":
    main()
