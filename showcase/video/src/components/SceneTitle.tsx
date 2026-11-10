import { interpolate, useCurrentFrame } from 'remotion';
import { colors, serif } from '../theme';

/** The scene's name, top left on a green pill with a marigold underline. It slides in over 12 frames. */
export function SceneTitle({ text }: { text: string }) {
    const frame = useCurrentFrame();
    const enter = interpolate(frame, [4, 16], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
    const underline = interpolate(frame, [12, 26], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });

    return (
        <div
            style={{
                position: 'absolute',
                left: 56,
                top: 48,
                padding: '14px 32px 20px',
                borderRadius: 999,
                background: colors.green,
                color: colors.cream,
                fontFamily: serif,
                fontSize: 52,
                lineHeight: 1.1,
                boxShadow: `5px 5px 0 ${colors.ink}`,
                opacity: enter,
                transform: `translateX(${(1 - enter) * -40}px)`,
            }}
        >
            {text}
            <div
                style={{
                    height: 6,
                    marginTop: 6,
                    borderRadius: 3,
                    background: colors.marigold,
                    width: `${underline * 100}%`,
                }}
            />
        </div>
    );
}
