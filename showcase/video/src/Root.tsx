import { Composition, Still } from 'remotion';
import manifest from '../public/clips/manifest.json';
import timings from '../public/voice/timings.json';
import { buildEdit, type Manifest } from './edit';
import './theme';
import { Thumbnail } from './Thumbnail';
import { buildTimeline, type Timings } from './timeline';
import { Walkthrough } from './Walkthrough';

// The recordings and narration are generated (npm run clips, npm run voice), so the
// timeline and the edit are worked out from whatever they hold right now.
const timeline = buildTimeline(timings as Timings, 30);
const edit = buildEdit(timeline, manifest as Manifest);

export function Root() {
    return (
        <>
            <Composition
                id="Walkthrough"
                component={Walkthrough}
                durationInFrames={timeline.totalFrames}
                fps={30}
                width={1920}
                height={1080}
                defaultProps={{ timeline, edit }}
            />
            <Still id="Thumbnail" component={Thumbnail} width={1280} height={720} defaultProps={{ edit }} />
        </>
    );
}
