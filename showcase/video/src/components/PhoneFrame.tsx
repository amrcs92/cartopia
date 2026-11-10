import { AbsoluteFill, spring, useCurrentFrame, useVideoConfig } from 'remotion';
import type { ShotPlan } from '../shots';
import { colors } from '../theme';
import { Played } from './Played';

/** The shop on a desktop at 66% beside the same store on a phone in a rounded device frame. */
export function PhoneFrame({ desktop, phone }: { desktop: ShotPlan; phone: ShotPlan }) {
    const frame = useCurrentFrame();
    const { fps } = useVideoConfig();
    const rise = spring({ frame: frame - 6, fps, config: { damping: 200 } });
    const phoneH = 800;
    const phoneW = Math.round((phoneH * 1170) / 2532);

    return (
        <AbsoluteFill style={{ background: colors.paper }}>
            <div
                style={{
                    position: 'absolute',
                    left: 96,
                    top: 176,
                    width: 1267,
                    height: 713,
                    borderRadius: 18,
                    border: `4px solid ${colors.ink}`,
                    overflow: 'hidden',
                    boxShadow: `8px 8px 0 ${colors.green}`,
                }}
            >
                <Played file="responsive-desktop.mp4" plan={desktop} />
            </div>
            <div
                style={{
                    position: 'absolute',
                    left: 1452,
                    top: 112,
                    width: phoneW,
                    height: phoneH,
                    borderRadius: 44,
                    border: `12px solid ${colors.ink}`,
                    overflow: 'hidden',
                    boxShadow: `8px 8px 0 ${colors.green}`,
                    background: colors.ink,
                    transform: `translateY(${(1 - rise) * 120}px)`,
                    opacity: rise,
                }}
            >
                <Played file="responsive-phone.mp4" plan={phone} />
            </div>
        </AbsoluteFill>
    );
}
