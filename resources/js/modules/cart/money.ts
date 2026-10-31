// Formats cents the way the server's Money::format() does: "$1,234.56".
export function formatCents(cents: number): string {
    const abs = Math.abs(Math.trunc(cents));
    const dollars = Math.floor(abs / 100).toLocaleString('en-US');
    const rest = String(abs % 100).padStart(2, '0');

    return `${cents < 0 ? '-' : ''}$${dollars}.${rest}`;
}
