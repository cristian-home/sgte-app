const COMBINING_MARKS = /[̀-ͯ]/g;
const COLOMBIA_CAPITAL_SUFFIX = /,?\s*d\.?\s*c\.?\s*$/i;

/**
 * Normalize a Colombian municipality name for case- and accent-insensitive
 * comparison against Mapbox's place context. Strips diacritics, the
 * `, D.C.` / `D.C.` suffix that DANE uses for Bogotá, and trims/lowercases.
 */
export function normalizeCity(name: string | null | undefined): string {
    if (!name) return '';
    return name
        .normalize('NFD')
        .replace(COMBINING_MARKS, '')
        .toLowerCase()
        .replace(COLOMBIA_CAPITAL_SUFFIX, '')
        .trim();
}
