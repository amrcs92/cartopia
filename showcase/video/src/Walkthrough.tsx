import type { ReactNode } from 'react';
import { AbsoluteFill, Audio, interpolate, Sequence, staticFile, useCurrentFrame, useVideoConfig } from 'remotion';
import { CameraClip } from './components/CameraClip';
import { ClosingCard, type Channel } from './components/ClosingCard';
import { DarkWipe } from './components/DarkWipe';
import { PhoneFrame } from './components/PhoneFrame';
import { SceneTitle } from './components/SceneTitle';
import { StackCards } from './components/StackCards';
import { Subtitles } from './components/Subtitles';
import { TitleCard } from './components/TitleCard';
import type { Edit } from './edit';
import type { SegmentId, Timeline } from './timeline';

const CROSSFADE = 8;
const SLIDE = 12;
const MUSIC_UP = 0.55;
const MUSIC_DOWN = 0.16;

const TITLES: Partial<Record<SegmentId, string>> = {
    browse: 'Browse and search',
    pay: 'Checkout',
    ledger: 'Wallet and reviews',
    admin: 'Back office',
    screens: 'Any screen, light or dark',
};

/** Fades its children in over `frames`, or slides them in from the right. */
function Enter({ frames, slide = false, children }: { frames: number; slide?: boolean; children: ReactNode }) {
    const frame = useCurrentFrame();
    const t = frames <= 0 ? 1 : interpolate(frame, [0, frames], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: (x) => 1 - (1 - x) ** 3 });
    const style = slide ? { transform: `translateX(${(1 - t) * 100}%)` } : { opacity: t };
    return <AbsoluteFill style={style}>{children}</AbsoluteFill>;
}

function musicVolume(frame: number, timeline: Timeline): number {
    const duck = Math.max(
        0,
        ...timeline.musicDuck.map((d) =>
            interpolate(frame, [d.from, d.from + 6, d.to - 6, d.to], [0, 1, 1, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' }),
        ),
    );
    const level = MUSIC_UP - (MUSIC_UP - MUSIC_DOWN) * duck;
    const fade = interpolate(frame, [0, 20, timeline.totalFrames - 75, timeline.totalFrames], [0, 1, 1, 0], {
        extrapolateLeft: 'clamp',
        extrapolateRight: 'clamp',
    });
    return level * fade;
}

export function Walkthrough({ timeline, edit, channel }: { timeline: Timeline; edit: Edit; channel?: Channel }) {
    const { fps } = useVideoConfig();

    const content = (id: SegmentId, frames: number): ReactNode => {
        switch (id) {
            case 'intro':
                return (
                    <>
                        <TitleCard />
                        <Sequence from={edit.intro.titleFrames}>
                            <Enter frames={12}>
                                <CameraClip {...edit.intro.home} frames={frames - edit.intro.titleFrames} />
                            </Enter>
                        </Sequence>
                    </>
                );
            case 'screens':
                return (
                    <>
                        <PhoneFrame desktop={edit.screens.desktop} phone={edit.screens.phone} />
                        <Sequence from={edit.screens.phoneFrames}>
                            <Enter frames={CROSSFADE}>
                                <DarkWipe plan={edit.screens.dark} toggleFrame={edit.screens.toggleFrame} lightSourceFrame={edit.screens.lightSourceFrame} />
                            </Enter>
                        </Sequence>
                    </>
                );
            case 'close':
                return (
                    <>
                        <StackCards />
                        <Sequence from={edit.close.stackFrames}>
                            <Enter frames={12}>
                                <ClosingCard channel={channel} />
                            </Enter>
                        </Sequence>
                    </>
                );
            default:
                return <CameraClip {...edit[id]} frames={frames} />;
        }
    };

    return (
        <AbsoluteFill style={{ backgroundColor: '#edf2e4' }}>
            {timeline.scenes.map((scene, i) => {
                const last = i === timeline.scenes.length - 1;
                const title = TITLES[scene.id];
                return (
                    <Sequence key={scene.id} from={scene.from} durationInFrames={scene.frames + (last ? 0 : CROSSFADE)}>
                        <Enter frames={i === 0 ? 0 : i === 1 ? SLIDE : CROSSFADE} slide={i === 1}>
                            {content(scene.id, scene.frames)}
                            {title ? <SceneTitle text={title} /> : null}
                        </Enter>
                    </Sequence>
                );
            })}

            <Subtitles captions={timeline.captions} />

            {timeline.voice.map((line) => (
                <Sequence key={line.id} from={line.from} durationInFrames={line.frames + 2}>
                    <Audio src={staticFile('voice/narration.wav')} trimBefore={Math.round((line.startMs * fps) / 1000)} />
                </Sequence>
            ))}
            <Audio src={staticFile('music/music.wav')} volume={(f) => musicVolume(f, timeline)} />
        </AbsoluteFill>
    );
}
