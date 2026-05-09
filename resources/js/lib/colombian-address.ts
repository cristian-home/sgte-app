/**
 * Strip Colombian address punctuation (`#` and `-`) and collapse whitespace
 * before sending the query to Mapbox's `/autofill/v1/suggest`. Mapbox's
 * parser doesn't understand the Colombian "Calle X #Y-Z" form and returns
 * weak / single-result lists when those characters are present, but it
 * matches the same address fine when written "Calle X Y Z".
 *
 * The user-visible input is never rewritten — this only normalizes the
 * outgoing query string.
 *
 * | Input                          | Output                       |
 * | ------------------------------ | ---------------------------- |
 * | `Calle 41A Sur #83-17`         | `Calle 41A Sur 83 17`        |
 * | `calle 41A Sur #83-17 bogota`  | `calle 41A Sur 83 17 bogota` |
 * | `Carrera   15  #45-30`         | `Carrera 15 45 30`           |
 * | `Calle 80`                     | `Calle 80`                   |
 */
export function normalizeForMapbox(input: string): string {
    return input.replace(/[#-]/g, ' ').replace(/\s+/g, ' ').trim();
}

const COMBINING_MARKS = /[̀-ͯ]/g;
const COLOMBIA_CAPITAL_SUFFIX = /,?\s*d\.?\s*c\.?\s*$/i;

/**
 * Normalize a Colombian municipality name for case- and accent-insensitive
 * comparison against Mapbox's `address_level2`. Strips diacritics, the
 * `, D.C.` / `D.C.` suffix that DANE uses for Bogotá, lowercases, and
 * trims.
 *
 * | Input             | Output     |
 * | ----------------- | ---------- |
 * | `BOGOTÁ, D.C.`    | `bogota`   |
 * | `Medellín`        | `medellin` |
 * | `CALI`            | `cali`     |
 * | `Bogotá`          | `bogota`   |
 */
export function normalizeCityName(name: string | null | undefined): string {
    if (!name) return '';
    return name
        .normalize('NFD')
        .replace(COMBINING_MARKS, '')
        .toLowerCase()
        .replace(COLOMBIA_CAPITAL_SUFFIX, '')
        .trim();
}
