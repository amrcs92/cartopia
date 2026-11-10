import { AbsoluteFill, Audio, interpolate, OffthreadVideo, Sequence, spring, staticFile, useCurrentFrame, useVideoConfig } from 'remotion';
import type { ShotPlan } from '../shots';

export interface Focus {
    /** The scene frame the zoom lands on. */
    frame: number;
    /** The area to zoom into, in the recording's pixels. */
    box: { x: number; y: number; w: number; h: number };
    /** Frames to hold the zoom before easing back out. */
    hold: number;
}

const EASE = 18;
const CROSS = 6;

function ShotVideo({ src, trimBefore, rate, fadeIn }: { src: string; trimBefore: number; rate: number; fadeIn: boolean }) {
    const frame = useCurrentFrame();
    const opacity = fadeIn ? interpolate(frame, [0, CROSS], [0, 1], { extrapolateRight: 'clamp' }) : 1;
    return (
        <AbsoluteFill style={{ opacity }}>
            <OffthreadVideo src={src} trimBefore={trimBefore} playbackRate={rate} muted style={{ width: '100%', height: '100%' }} />
        </AbsoluteFill>
    );
}

/**
 * A recording cut down to its shots, with eased zooms onto the moments that matter, a slow
 * push-in so the frame never sits still, and a soft click wherever the pointer clicked.
 */
export function CameraClip({
    file,
    width: clipWidth,
    plan,
    focus = [],
    clicks = [],
    frames,
}: {
    file: string;
    width: number;
    plan: ShotPlan;
    focus?: Focus[];
    clicks?: number[];
    frames: number;
}) {
    const frame = useCurrentFrame();
    const { fps, width, height } = useVideoConfig();
    const k = width / clipWidth;
    const src = staticFile(`clips/${file}`);

    let zoom = 1;
    let origin = '50% 50%';
    for (const f of focus) {
        const start = f.frame - EASE;
        const into = spring({ frame: frame - start, fps, config: { damping: 200 }, durationInFrames: EASE });
        const out = spring({ frame: frame - (f.frame + f.hold), fps, config: { damping: 200 }, durationInFrames: EASE });
        const amount = into - out;
        if (amount > 0.001) {
            const boxH = f.box.h * k;
            const target = boxH > 320 ? 1.35 : boxH > 120 ? 1.45 : 1.6;
            zoom = 1 + (target - 1) * amount;
            // Zooming about a point inside the frame keeps the frame covered, so keep the point inside.
            const cx = Math.min(width - 1, Math.max(1, (f.box.x + f.box.w / 2) * k));
            const cy = Math.min(height - 1, Math.max(1, (f.box.y + f.box.h / 2) * k));
            origin = `${cx}px ${cy}px`;
        }
    }
    const drift = interpolate(frame, [0, frames], [1, 1.035], { extrapolateRight: 'clamp' });

    return (
        <AbsoluteFill style={{ overflow: 'hidden', backgroundColor: '#edf2e4' }}>
            <AbsoluteFill style={{ transform: `scale(${drift})` }}>
                <AbsoluteFill style={{ transform: `scale(${zoom})`, transformOrigin: origin }}>
                    {plan.shots.map((shot, i) => (
                        <Sequence key={i} from={shot.from} durationInFrames={shot.frames + (i < plan.shots.length - 1 ? CROSS : 30)}>
                            <ShotVideo src={src} trimBefore={shot.trimBefore} rate={plan.rate} fadeIn={i > 0} />
                        </Sequence>
                    ))}
                </AbsoluteFill>
            </AbsoluteFill>
            {clicks.map((at, i) => (
                <Sequence key={`click-${i}`} from={at} durationInFrames={10}>
                    <Audio src={staticFile('music/click.wav')} volume={0.5} />
                </Sequence>
            ))}
        </AbsoluteFill>
    );
}
