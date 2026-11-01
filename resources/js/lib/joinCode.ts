/**
 * Reduce whatever a person pasted into a bare join code.
 *
 * Both "Copy code" and "Copy link" get pasted back in, so a full share link is
 * accepted and trimmed down to its last path segment. The server normalises the
 * result again; this only exists so the generated URL stays readable instead of
 * being percent-encoded.
 */
export function codeFromInput(input: string): string {
    const trimmed = input.trim();

    if (!trimmed.includes('/')) {
        return trimmed;
    }

    const segments = trimmed.split(/[?#]/)[0].split('/').filter(Boolean);

    return segments.at(-1) ?? trimmed;
}
