import type { ReactNode } from 'react';
import { AbsoluteFill, Audio, Img, interpolate, Sequence, spring, staticFile, useCurrentFrame, useVideoConfig } from 'remotion';
import { colors, sans, serif } from '../theme';
import { BellIcon, CommentIcon, GitHubIcon, MailIcon, PointerIcon, ThumbIcon } from './Icons';
import { Logo } from './Logo';

export interface Channel {
    name: string;
    handle: string;
    avatar: string;
}

const rise = (value: number, by = 26) => ({ opacity: value, transform: `translateY(${(1 - value) * by}px)` });

// The channel row's fixed layout, so the pointer knows where the buttons are.
const ROW_TOP = 700;
const PILL_Y = 716;
const PILL_H = 72;
const LIKE = { x: 872, w: 170 };
const COMMENT = { x: 1058, w: 216 };
const SUBSCRIBE = { x: 1290, w: 250 };
// The pointer waits for the narrator's last line, then likes and subscribes.
const POINTER_IN = 196;
const LIKE_CLICK = 222;
const SUBSCRIBE_CLICK = 258;

function ContactRow({ icon, text, at }: { icon: 'mail' | 'github'; text: string; at: number }) {
    const Icon = icon === 'mail' ? MailIcon : GitHubIcon;
    return (
        <div style={{ display: 'flex', alignItems: 'center', gap: 20, ...rise(at, 16) }}>
            <Icon size={40} color={colors.marigold} />
            <span style={{ fontFamily: sans, fontWeight: 600, fontSize: 42, color: colors.cream, letterSpacing: 0.2 }}>{text}</span>
        </div>
    );
}

function Pill({ x, w, children, active, activeColor }: { x: number; w: number; children: ReactNode; active: boolean; activeColor: string }) {
    return (
        <div
            style={{
                position: 'absolute',
                left: x,
                top: PILL_Y,
                width: w,
                height: PILL_H,
                borderRadius: 999,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                gap: 12,
                fontFamily: sans,
                fontWeight: 600,
                fontSize: 30,
                color: colors.cream,
                background: active ? activeColor : 'rgba(255, 244, 214, 0.1)',
                border: `2px solid ${active ? activeColor : 'rgba(255, 244, 214, 0.35)'}`,
            }}
        >
            {children}
        </div>
    );
}

/** A glide from one point to another over `frames`, eased at both ends. */
function glide(frame: number, start: number, frames: number, from: [number, number], to: [number, number]): [number, number] {
    const t = interpolate(frame, [start, start + frames], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: (x) => x * x * (3 - 2 * x) });
    return [from[0] + (to[0] - from[0]) * t, from[1] + (to[1] - from[1]) * t];
}

function ChannelRow({ channel }: { channel: Channel }) {
    const frame = useCurrentFrame();
    const { fps } = useVideoConfig();
    const show = spring({ frame: frame - 56, fps, config: { damping: 200 } });
    const liked = frame >= LIKE_CLICK;
    const subscribed = frame >= SUBSCRIBE_CLICK;
    const likePop = liked ? spring({ frame: frame - LIKE_CLICK, fps, config: { damping: 9, stiffness: 220 } }) : 0;

    const likeAt: [number, number] = [LIKE.x + LIKE.w / 2 - 10, PILL_Y + PILL_H / 2 - 4];
    const subscribeAt: [number, number] = [SUBSCRIBE.x + SUBSCRIBE.w / 2 - 10, PILL_Y + PILL_H / 2 - 4];
    const toLike = glide(frame, POINTER_IN, LIKE_CLICK - POINTER_IN - 6, [1760, 1010], likeAt);
    const restAt: [number, number] = [subscribeAt[0] + 170, subscribeAt[1] + 70];
    const pointer =
        frame < LIKE_CLICK + 6
            ? toLike
            : frame < SUBSCRIBE_CLICK + 10
              ? glide(frame, LIKE_CLICK + 6, SUBSCRIBE_CLICK - LIKE_CLICK - 12, likeAt, subscribeAt)
              : glide(frame, SUBSCRIBE_CLICK + 10, 16, subscribeAt, restAt);
    const pointerShown = interpolate(frame, [POINTER_IN, POINTER_IN + 8], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });

    const ripple = (at: number, [x, y]: [number, number]) => {
        const t = frame - at;
        if (t < 0 || t > 16) {
            return null;
        }
        const size = 20 + t * 5;
        return (
            <div
                style={{
                    position: 'absolute',
                    left: x + 6 - size / 2,
                    top: y + 6 - size / 2,
                    width: size,
                    height: size,
                    borderRadius: '50%',
                    border: `4px solid ${colors.marigold}`,
                    opacity: 1 - t / 16,
                }}
            />
        );
    };

    return (
        <AbsoluteFill style={{ opacity: show }}>
            <div style={{ position: 'absolute', left: 380, right: 380, top: ROW_TOP - 30, height: 2, background: 'rgba(255, 244, 214, 0.22)' }} />
            <div style={{ position: 'absolute', left: 380, top: ROW_TOP, display: 'flex', alignItems: 'center', gap: 22, ...rise(show, 20) }}>
                <Img src={channel.avatar} style={{ width: 104, height: 104, borderRadius: '50%', border: `3px solid ${colors.marigold}` }} />
                <div>
                    <div style={{ fontFamily: serif, fontSize: 44, color: colors.cream, lineHeight: 1.1 }}>{channel.name}</div>
                    <div style={{ fontFamily: sans, fontWeight: 500, fontSize: 28, color: 'rgba(255, 244, 214, 0.75)', marginTop: 4 }}>{channel.handle}</div>
                </div>
            </div>
            <Pill x={LIKE.x} w={LIKE.w} active={liked} activeColor={colors.marigold}>
                <ThumbIcon size={32} color={liked ? colors.ink : colors.cream} fill={liked ? colors.ink : 'none'} style={{ transform: `scale(${1 + likePop * 0.15 - (liked ? 0.15 : 0)})` }} />
                <span style={{ color: liked ? colors.ink : colors.cream }}>Like</span>
            </Pill>
            <Pill x={COMMENT.x} w={COMMENT.w} active={false} activeColor={colors.marigold}>
                <CommentIcon size={30} color={colors.cream} />
                Comment
            </Pill>
            <Pill x={SUBSCRIBE.x} w={SUBSCRIBE.w} active={!subscribed} activeColor="#cc0000">
                <BellIcon size={30} color={colors.cream} fill={subscribed ? colors.cream : 'none'} />
                {subscribed ? 'Subscribed' : 'Subscribe'}
            </Pill>
            {ripple(LIKE_CLICK, likeAt)}
            {ripple(SUBSCRIBE_CLICK, subscribeAt)}
            <PointerIcon size={54} style={{ position: 'absolute', left: pointer[0], top: pointer[1], opacity: pointerShown }} />
            {[LIKE_CLICK, SUBSCRIBE_CLICK].map((at) => (
                <Sequence key={at} from={at} durationInFrames={10}>
                    <Audio src={staticFile('music/click.wav')} volume={0.5} />
                </Sequence>
            ))}
        </AbsoluteFill>
    );
}

/**
 * The call to action: who to contact for a store like this one. On the YouTube cut it
 * also shows the channel, and the pointer likes and subscribes once the narrator is done.
 */
export function ClosingCard({ channel }: { channel?: Channel }) {
    const frame = useCurrentFrame();
    const { fps } = useVideoConfig();
    const at = (delay: number) => spring({ frame: frame - delay, fps, config: { damping: 200 } });
    const rule = interpolate(frame, [30, 48], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
    const compact = channel !== undefined;

    return (
        <AbsoluteFill style={{ background: colors.green }}>
            <div
                style={{
                    position: 'absolute',
                    left: 0,
                    right: 0,
                    top: compact ? 70 : 150,
                    display: 'flex',
                    flexDirection: 'column',
                    alignItems: 'center',
                    gap: compact ? 22 : 28,
                }}
            >
                <div
                    style={{
                        background: colors.cream,
                        borderRadius: 28,
                        padding: compact ? 16 : 20,
                        border: `4px solid ${colors.ink}`,
                        boxShadow: `6px 6px 0 ${colors.ink}`,
                        transform: `scale(${spring({ frame: frame - 2, fps, config: { damping: 12, stiffness: 120 } })})`,
                    }}
                >
                    <Logo size={compact ? 92 : 116} animate delay={10} />
                </div>
                <div style={{ fontFamily: serif, fontSize: compact ? 92 : 104, color: colors.cream, lineHeight: 1.05, ...rise(at(12)) }}>
                    Need a store like this?
                </div>
                <div style={{ fontFamily: sans, fontWeight: 500, fontSize: compact ? 38 : 42, color: colors.cream, ...rise(at(22)) }}>
                    I design and build custom web apps with Laravel and Vue.
                </div>
                <div style={{ width: 120 * rule, height: 4, borderRadius: 2, background: colors.marigold, marginTop: compact ? 6 : 14 }} />
                <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'flex-start', gap: 14 }}>
                    <ContactRow icon="mail" text="amrcs1992@gmail.com" at={at(36)} />
                    <ContactRow icon="github" text="github.com/amrcs92" at={at(44)} />
                </div>
            </div>
            {channel ? <ChannelRow channel={channel} /> : null}
        </AbsoluteFill>
    );
}
