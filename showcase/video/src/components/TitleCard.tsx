import { AbsoluteFill, interpolate, spring, useCurrentFrame, useVideoConfig } from 'remotion';
import { colors, sans, serif } from '../theme';
import { Logo } from './Logo';
import { Stripes } from './Stripes';

/** The opening: the stall mark pops in and paints its stripes, then the name, then the pitch. */
export function TitleCard() {
    const frame = useCurrentFrame();
    const { fps } = useVideoConfig();
    const logo = spring({ frame: frame - 4, fps, config: { damping: 12, stiffness: 120 } });
    const name = spring({ frame: frame - 22, fps, config: { damping: 200 } });
    const pitch = spring({ frame: frame - 40, fps, config: { damping: 200 } });
    const band = interpolate(frame, [0, 14], [-90, 0], { extrapolateRight: 'clamp' });

    return (
        <AbsoluteFill style={{ background: colors.paper, alignItems: 'center', justifyContent: 'center' }}>
            <div style={{ position: 'absolute', inset: 0, transform: `translateY(${band}px)` }}>
                <Stripes height={72} />
            </div>
            <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 28, marginTop: 40 }}>
                <div style={{ transform: `scale(${logo})` }}>
                    <Logo size={210} animate delay={14} />
                </div>
                <div
                    style={{
                        fontFamily: serif,
                        fontSize: 156,
                        lineHeight: 1,
                        color: colors.ink,
                        opacity: name,
                        transform: `translateY(${(1 - name) * 30}px)`,
                    }}
                >
                    Cartopia
                </div>
                <div
                    style={{
                        fontFamily: sans,
                        fontWeight: 500,
                        fontSize: 42,
                        color: colors.green,
                        opacity: pitch,
                        transform: `translateY(${(1 - pitch) * 20}px)`,
                    }}
                >
                    An online grocery store, rebuilt from the ground up
                </div>
            </div>
        </AbsoluteFill>
    );
}
