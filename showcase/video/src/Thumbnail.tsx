import { AbsoluteFill, OffthreadVideo, staticFile } from 'remotion';
import { Logo } from './components/Logo';
import type { Edit } from './edit';
import { colors, sans, serif } from './theme';

/** The YouTube thumbnail: the paid order behind the logo, the name and the pitch. */
export function Thumbnail({ edit }: { edit: Edit }) {
    return (
        <AbsoluteFill style={{ background: colors.ink }}>
            <AbsoluteFill style={{ filter: 'blur(7px) brightness(0.6)', transform: 'scale(1.06)' }}>
                <OffthreadVideo
                    src={staticFile(`clips/${edit.thumbnail.file}`)}
                    trimBefore={edit.thumbnail.sourceFrame}
                    muted
                    style={{ width: '100%', height: '100%', objectFit: 'cover' }}
                />
            </AbsoluteFill>
            <AbsoluteFill style={{ alignItems: 'center', justifyContent: 'center', gap: 22 }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 30 }}>
                    <div style={{ background: colors.cream, borderRadius: 28, padding: 16, border: `4px solid ${colors.ink}`, boxShadow: `6px 6px 0 ${colors.ink}` }}>
                        <Logo size={120} />
                    </div>
                    <div style={{ fontFamily: serif, fontSize: 132, color: colors.cream, textShadow: `5px 5px 0 ${colors.ink}` }}>Cartopia</div>
                </div>
                <div
                    style={{
                        fontFamily: sans,
                        fontWeight: 800,
                        fontSize: 48,
                        color: colors.ink,
                        background: colors.marigold,
                        padding: '14px 36px 16px',
                        borderRadius: 14,
                        border: `4px solid ${colors.ink}`,
                        boxShadow: `6px 6px 0 ${colors.ink}`,
                        transform: 'rotate(-1.5deg)',
                    }}
                >
                    A full online store, built in Laravel &amp; Vue
                </div>
            </AbsoluteFill>
        </AbsoluteFill>
    );
}
