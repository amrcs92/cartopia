import { AbsoluteFill, spring, useCurrentFrame, useVideoConfig } from 'remotion';
import { colors, sans, serif } from '../theme';
import { Logo } from './Logo';

const rise = (value: number) => ({ opacity: value, transform: `translateY(${(1 - value) * 26}px)` });

/** The call to action: who to contact for a store like this one. It holds to the end. */
export function ClosingCard() {
    const frame = useCurrentFrame();
    const { fps } = useVideoConfig();
    const at = (delay: number) => spring({ frame: frame - delay, fps, config: { damping: 200 } });

    return (
        <AbsoluteFill style={{ background: colors.green, alignItems: 'center', justifyContent: 'center' }}>
            <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 30, marginBottom: 110 }}>
                <div
                    style={{
                        background: colors.cream,
                        borderRadius: 32,
                        padding: 22,
                        border: `4px solid ${colors.ink}`,
                        boxShadow: `6px 6px 0 ${colors.ink}`,
                        transform: `scale(${spring({ frame: frame - 2, fps, config: { damping: 12, stiffness: 120 } })})`,
                    }}
                >
                    <Logo size={120} animate delay={10} />
                </div>
                <div style={{ fontFamily: serif, fontSize: 104, color: colors.cream, lineHeight: 1.05, ...rise(at(12)) }}>
                    Need a store like this?
                </div>
                <div style={{ fontFamily: sans, fontWeight: 500, fontSize: 42, color: colors.cream, ...rise(at(24)) }}>
                    I design and build custom web apps with Laravel and Vue.
                </div>
                <div
                    style={{
                        marginTop: 18,
                        fontFamily: sans,
                        fontWeight: 600,
                        fontSize: 46,
                        color: colors.ink,
                        background: colors.marigold,
                        borderRadius: 999,
                        padding: '14px 40px 16px',
                        border: `4px solid ${colors.ink}`,
                        boxShadow: `5px 5px 0 ${colors.ink}`,
                        ...rise(at(38)),
                    }}
                >
                    amrcs1992@gmail.com · github.com/amrcs92
                </div>
            </div>
        </AbsoluteFill>
    );
}
