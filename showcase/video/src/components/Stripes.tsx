import { colors } from '../theme';

/** The market stall's awning: a band of green and white stripes with a scalloped edge. */
export function Stripes({ height = 64 }: { height?: number }) {
    const stripe = 48;
    return (
        <div style={{ position: 'absolute', left: 0, right: 0, top: 0, height: height + 18 }}>
            <div
                style={{
                    height,
                    background: `repeating-linear-gradient(90deg, ${colors.green} 0 ${stripe}px, ${colors.stripe} ${stripe}px ${stripe * 2}px)`,
                    borderBottom: `4px solid ${colors.ink}`,
                }}
            />
        </div>
    );
}
