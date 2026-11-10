import { interpolate, useCurrentFrame } from 'remotion';
import { colors } from '../theme';

/**
 * The store's stall mark (resources/js/common/brand/AppLogo.vue). With `animate`,
 * the awning's stripes paint in left to right over 18 frames.
 */
export function Logo({ size, animate = false, delay = 0 }: { size: number; animate?: boolean; delay?: number }) {
    const frame = useCurrentFrame();
    const reveal = animate
        ? interpolate(frame - delay, [0, 18], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' })
        : 1;
    const clip = `inset(0 ${(1 - reveal) * 100}% 0 0)`;

    return (
        <svg viewBox="0 0 44 44" width={size} height={size} aria-hidden="true">
            <path d="M4 14h36l-3 4H7z" fill={colors.green} stroke={colors.ink} strokeWidth={2.5} strokeLinejoin="round" />
            <path d="M8 4h28l4 10H4z" fill={colors.stripe} />
            <path d="M15 4l-3 10M22 4v10M29 4l3 10" stroke={colors.green} strokeWidth={5} style={{ clipPath: clip }} />
            <path d="M8 4h28l4 10H4z" fill="none" stroke={colors.ink} strokeWidth={2.5} strokeLinejoin="round" />
            <rect x={9} y={22} width={26} height={14} rx={3} fill={colors.marigold} stroke={colors.ink} strokeWidth={2.5} />
            <circle cx={14} cy={39.5} r={2.5} fill={colors.ink} />
            <circle cx={30} cy={39.5} r={2.5} fill={colors.ink} />
        </svg>
    );
}
