// Line icons for the closing card, drawn at 24 x 24 like the store's own icons.
import type { CSSProperties } from 'react';

interface IconProps {
    size: number;
    color: string;
    fill?: string;
    style?: CSSProperties;
}

const line = (color: string) => ({ fill: 'none', stroke: color, strokeWidth: 2, strokeLinecap: 'round' as const, strokeLinejoin: 'round' as const });

export function MailIcon({ size, color, style }: IconProps) {
    return (
        <svg width={size} height={size} viewBox="0 0 24 24" style={style} aria-hidden="true">
            <rect x="2.5" y="4.5" width="19" height="15" rx="2.5" {...line(color)} />
            <path d="m3 6.5 9 6.5 9-6.5" {...line(color)} />
        </svg>
    );
}

/** GitHub's mark, used here as a link label for the profile. */
export function GitHubIcon({ size, color, style }: IconProps) {
    return (
        <svg width={size} height={size} viewBox="0 0 16 16" style={style} aria-hidden="true">
            <path
                fill={color}
                d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0 0 16 8c0-4.42-3.58-8-8-8z"
            />
        </svg>
    );
}

export function ThumbIcon({ size, color, fill = 'none', style }: IconProps) {
    return (
        <svg width={size} height={size} viewBox="0 0 24 24" style={style} aria-hidden="true">
            <path d="M7 10v11" {...line(color)} />
            <path
                d="M15 5.9 14 10h5.8a2 2 0 0 1 1.9 2.6l-2.3 8a2 2 0 0 1-1.9 1.4H4a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2h2.8a2 2 0 0 0 1.8-1.1L12 2a3.1 3.1 0 0 1 3 3.9Z"
                {...line(color)}
                fill={fill}
            />
        </svg>
    );
}

export function CommentIcon({ size, color, style }: IconProps) {
    return (
        <svg width={size} height={size} viewBox="0 0 24 24" style={style} aria-hidden="true">
            <path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z" {...line(color)} />
        </svg>
    );
}

export function BellIcon({ size, color, fill = 'none', style }: IconProps) {
    return (
        <svg width={size} height={size} viewBox="0 0 24 24" style={style} aria-hidden="true">
            <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" {...line(color)} fill={fill} />
            <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" {...line(color)} />
        </svg>
    );
}

/** The same drawn pointer the recordings use: an ink arrow with a cream fill. */
export function PointerIcon({ size, style }: { size: number; style?: CSSProperties }) {
    return (
        <svg width={size} height={size} viewBox="0 0 24 24" style={style} aria-hidden="true">
            <path d="M4 2.5v17.2l4.6-4.3 3 6.6 3.1-1.4-3-6.5h6.3Z" fill="#fff4d6" stroke="#1a2b21" strokeWidth={1.6} strokeLinejoin="round" />
        </svg>
    );
}
