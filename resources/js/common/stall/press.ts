// Shared classes for the "sticker" controls: a 2.5px outline, a hard shadow and
// the press motion (the control slides into its shadow over 120ms; hover is a
// colour change over 200ms).
export const pressTransition =
    'transition-[translate,box-shadow,background-color,color,border-color] duration-200 ease-stall active:duration-[120ms]';

export const sticker = {
    md: 'border-[2.5px] border-outline shadow-press',
    lg: 'border-[2.5px] border-outline shadow-press-lg',
} as const;

export const press = {
    md: 'active:translate-x-[3px] active:translate-y-[3px] active:shadow-none',
    lg: 'active:translate-x-1 active:translate-y-1 active:shadow-none',
} as const;

export const stickerFill = {
    marigold: 'bg-marigold text-on-marigold hover:bg-marigold/90',
    green: 'bg-green text-primary-foreground hover:bg-green/90',
} as const;
