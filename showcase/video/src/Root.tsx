import { Composition, staticFile, Still } from 'remotion';
import manifest from '../public/clips/manifest.json';
import timings from '../public/voice/timings.json';
import type { Channel } from './components/ClosingCard';
import { buildEdit, type Manifest } from './edit';
import './theme';
import { Thumbnail } from './Thumbnail';
import { buildTimeline, type Timings } from './timeline';
import { Walkthrough } from './Walkthrough';

// The recordings and narration are generated (npm run clips, npm run voice), so the
// timeline and the edit are worked out from whatever they hold right now.
const timeline = buildTimeline(timings as Timings, 30);
const edit = buildEdit(timeline, manifest as Manifest);

const channel: Channel = {
    name: 'Amr Ashraf',
    handle: '@amrashrafserageldin',
    avatar: staticFile('brand/amr-avatar.png'),
};

const video = { fps: 30, width: 1920, height: 1080, durationInFrames: timeline.totalFrames };

export function Root() {
    return (
        <>
            {/* The YouTube cut ends on the channel: like, comment, subscribe. */}
            <Composition id="Walkthrough" component={Walkthrough} {...video} defaultProps={{ timeline, edit, channel }} />
            {/* The README cut, for hiring managers on GitHub, ends on the contact details alone. */}
            <Composition id="WalkthroughReadme" component={Walkthrough} {...video} defaultProps={{ timeline, edit }} />
            <Still id="Thumbnail" component={Thumbnail} width={1280} height={720} defaultProps={{ edit }} />
        </>
    );
}
