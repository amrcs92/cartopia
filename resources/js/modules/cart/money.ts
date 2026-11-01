// Formats cents the way the server's Money::format() does: "$1,234.56".
export function formatCents(cents: number): string {
    const abs = Math.abs(Math.trunc(cents));
    const dollars = Math.floor(abs / 100).toLocaleString('en-US');
    const rest = String(abs % 100).padStart(2, '0');

    return `${cents < 0 ? '-' : ''}$${dollars}.${rest}`;
}

// Mirrors App\Payments\SplitPlan::for: how a total divides between the wallet
// and the hosted method. The hosted share is never under 50 cents, so the
// wallet gives up the difference. With no split the wallet pays nothing.
export function splitPlan(
    totalCents: number,
    balanceCents: number,
    useWallet: boolean,
): { walletCents: number; hostedCents: number } {
    const none = { walletCents: 0, hostedCents: totalCents };

    if (!useWallet || balanceCents <= 0 || balanceCents >= totalCents) {
        return none;
    }

    const hostedCents = Math.max(totalCents - balanceCents, 50);
    const walletCents = totalCents - hostedCents;

    return walletCents <= 0 ? none : { walletCents, hostedCents };
}
