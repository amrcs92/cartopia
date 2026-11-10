import { OffthreadVideo, Sequence, staticFile } from 'remotion';
import type { ShotPlan } from '../shots';

/** A recording's shots back to back, without zooms: for clips shown inside a frame. */
export function Played({ file, plan, tail = 30 }: { file: string; plan: ShotPlan; tail?: number }) {
    return (
        <>
            {plan.shots.map((shot, i) => (
                <Sequence key={i} from={shot.from} durationInFrames={shot.frames + tail}>
                    <OffthreadVideo
                        src={staticFile(`clips/${file}`)}
                        trimBefore={shot.trimBefore}
                        playbackRate={plan.rate}
                        muted
                        style={{ width: '100%', height: '100%', objectFit: 'cover' }}
                    />
                </Sequence>
            ))}
        </>
    );
}
