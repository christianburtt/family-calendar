/**
 * Marker colors for the family whiteboard. Each one is dark enough to carry
 * white text and still reads well as a tint in light and dark mode.
 */
export const familyColors = [
    { name: 'Red', value: '#dc2626' },
    { name: 'Orange', value: '#ea580c' },
    { name: 'Amber', value: '#d97706' },
    { name: 'Green', value: '#16a34a' },
    { name: 'Teal', value: '#0d9488' },
    { name: 'Sky', value: '#0284c7' },
    { name: 'Blue', value: '#2563eb' },
    { name: 'Violet', value: '#7c3aed' },
    { name: 'Fuchsia', value: '#c026d3' },
    { name: 'Pink', value: '#db2777' },
] as const;

export function firstAvailableColor(takenColors: string[]): string {
    const taken = new Set(takenColors.map((color) => color.toLowerCase()));

    return (
        familyColors.find((swatch) => !taken.has(swatch.value))?.value ??
        familyColors[0].value
    );
}

/**
 * Inline styles for an event drawn in a family member's color.
 */
export function eventColorStyle(color: string): Record<string, string> {
    return {
        backgroundColor: `color-mix(in srgb, ${color} 16%, var(--background))`,
        borderColor: color,
    };
}
