import { AbsoluteFill, Audio, Sequence, spring, staticFile, useCurrentFrame, useVideoConfig } from 'remotion';
import { colors, hardShadow, sans, serif } from '../theme';

const CARDS = [
    { name: 'Laravel 13', note: 'Back end' },
    { name: 'Vue 3', note: 'Store front' },
    { name: 'Filament', note: 'Back office' },
    { name: 'Stripe', note: 'Cards' },
    { name: 'PayPal', note: 'Wallets' },
    { name: '900+ tests', note: 'Pest and Playwright' },
];
const STAGGER = 4;
const START = 10;

/** What it's built with: six cards that pop in one after another, each with a soft click. */
export function StackCards() {
    const frame = useCurrentFrame();
    const { fps } = useVideoConfig();
    const heading = spring({ frame, fps, config: { damping: 200 } });

    return (
        <AbsoluteFill style={{ background: colors.paper, alignItems: 'center', justifyContent: 'center', gap: 56 }}>
            <div style={{ fontFamily: serif, fontSize: 72, color: colors.ink, opacity: heading, transform: `translateY(${(1 - heading) * 24}px)` }}>
                Built with
            </div>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 420px)', gap: 36, marginBottom: 90 }}>
                {CARDS.map((card, i) => {
                    const pop = spring({ frame: frame - START - i * STAGGER, fps, config: { damping: 11, stiffness: 160 } });
                    return (
                        <div
                            key={card.name}
                            style={{
                                background: '#ffffff',
                                border: `3px solid ${colors.ink}`,
                                borderRadius: 16,
                                boxShadow: hardShadow,
                                padding: '26px 32px',
                                transform: `scale(${pop}) rotate(${(1 - pop) * (i % 2 ? 4 : -4)}deg)`,
                                opacity: Math.min(1, pop * 1.5),
                            }}
                        >
                            <div style={{ fontFamily: sans, fontWeight: 800, fontSize: 44, color: colors.ink }}>{card.name}</div>
                            <div style={{ fontFamily: sans, fontWeight: 500, fontSize: 28, color: colors.green, marginTop: 4 }}>{card.note}</div>
                        </div>
                    );
                })}
            </div>
            {CARDS.map((card, i) => (
                <Sequence key={card.name} from={START + i * STAGGER} durationInFrames={10}>
                    <Audio src={staticFile('music/click.wav')} volume={0.35} playbackRate={1.3} />
                </Sequence>
            ))}
        </AbsoluteFill>
    );
}
