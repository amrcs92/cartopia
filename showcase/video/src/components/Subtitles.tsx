import { interpolate, useCurrentFrame } from 'remotion';
import type { Timeline } from '../timeline';
import { colors, sans } from '../theme';

const FADE = 6;

/** One subtitle at a time, bottom centre; the word being spoken turns marigold. */
export function Subtitles({ captions }: { captions: Timeline['captions'] }) {
    const frame = useCurrentFrame();
    const index = captions.findIndex((c) => frame >= c.from && frame < c.from + c.frames);
    if (index === -1) {
        return null;
    }
    const caption = captions[index];
    const end = caption.from + caption.frames;
    // Fade only across a pause: back-to-back captions swap without flashing the box.
    const before = captions[index - 1];
    const after = captions[index + 1];
    const fadeIn = !before || caption.from - (before.from + before.frames) > 2;
    const fadeOut = !after || after.from - end > 2;
    const opacity = Math.min(
        fadeIn ? interpolate(frame, [caption.from, caption.from + FADE], [0, 1], { extrapolateRight: 'clamp' }) : 1,
        fadeOut ? interpolate(frame, [end - FADE, end], [1, 0], { extrapolateLeft: 'clamp' }) : 1,
    );

    return (
        <div style={{ position: 'absolute', left: 0, right: 0, bottom: 56, display: 'flex', justifyContent: 'center', opacity }}>
            <div
                style={{
                    padding: '12px 30px 14px',
                    borderRadius: 14,
                    background: 'rgba(26, 43, 33, 0.82)',
                    color: colors.cream,
                    fontFamily: sans,
                    fontWeight: 600,
                    fontSize: 44,
                    lineHeight: 1.25,
                    whiteSpace: 'nowrap',
                }}
            >
                {caption.words.map((word, i) => {
                    const speaking = frame >= word.from && frame < Math.max(word.to, word.from + 4);
                    return (
                        <span key={i} style={{ color: speaking ? colors.marigold : colors.cream }}>
                            {i > 0 ? ' ' : ''}
                            {word.text}
                        </span>
                    );
                })}
            </div>
        </div>
    );
}
