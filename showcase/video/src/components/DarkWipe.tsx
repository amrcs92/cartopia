import { AbsoluteFill, Freeze, interpolate, OffthreadVideo, staticFile, useCurrentFrame } from 'remotion';
import type { ShotPlan } from '../shots';
import { Played } from './Played';

/**
 * The shop switches to dark mode. As the toggle is clicked, a frozen frame of the light
 * page is wiped away on a diagonal, uncovering the dark page underneath.
 */
export function DarkWipe({ plan, toggleFrame, lightSourceFrame }: { plan: ShotPlan; toggleFrame: number; lightSourceFrame: number }) {
    const frame = useCurrentFrame();
    const wipe = interpolate(frame, [toggleFrame + 3, toggleFrame + 27], [0, 1], {
        extrapolateLeft: 'clamp',
        extrapolateRight: 'clamp',
        easing: (t) => t * t * (3 - 2 * t),
    });
    // The light layer's left edge leans like a slash and sweeps from left to right.
    const edge = wipe * 140 - 20;
    const mask = `polygon(${edge + 20}% 0, 100% 0, 100% 100%, ${edge - 20}% 100%)`;

    return (
        <AbsoluteFill>
            <Played file="dark.mp4" plan={plan} />
            {frame >= toggleFrame && wipe < 1 ? (
                <AbsoluteFill style={{ clipPath: mask }}>
                    <Freeze frame={lightSourceFrame}>
                        <OffthreadVideo src={staticFile('clips/dark.mp4')} muted />
                    </Freeze>
                </AbsoluteFill>
            ) : null}
        </AbsoluteFill>
    );
}
