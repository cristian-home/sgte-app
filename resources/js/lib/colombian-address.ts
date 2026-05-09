import type { AddressAutofillSuggestion } from '@mapbox/search-js-core';

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

/**
 * Take a Mapbox AddressAutofill suggestion and rebuild it as a Colombian
 * address: "Calle X #Y-Z, Ciudad". Falls back to the suggestion's
 * `feature_name` (with city appended) when the pattern doesn't match —
 * for non-numeric features like intersections or named places, or for
 * street-level picks that only carry one number.
 *
 * Mapbox returns autofill addresses in anglo order — the **leading** two
 * numbers are the primary + secondary house numbers, followed by the
 * street name. We swap them into the Colombian "<street> #<primary>-<secondary>"
 * form. The leading two-number pair is the only thing the regex touches;
 * the trailing street name (including qualifiers like "Sur" / "Norte")
 * comes out verbatim.
 *
 * | feature_name                | address_level2 | Output                              |
 * | --------------------------- | -------------- | ----------------------------------- |
 * | `83 17 Calle 41A Sur`       | `Bogotá`       | `Calle 41A Sur #83-17, Bogotá`      |
 * | `45 30 Carrera 15`          | `Bogotá`       | `Carrera 15 #45-30, Bogotá`         |
 * | `5 30 Avenida Calle 80`     | `Bogotá`       | `Avenida Calle 80 #5-30, Bogotá`    |
 * | `80 Calle 66 Sur`           | `Bogotá`       | `80 Calle 66 Sur, Bogotá`           |
 * | `Plaza de Bolívar`          | `Bogotá`       | `Plaza de Bolívar, Bogotá`          |
 * | `83 17 Calle 41A Sur`       | (none)         | `Calle 41A Sur #83-17`              |
 */
export function formatColombianAddress(
    suggestion: AddressAutofillSuggestion,
): string {
    const featureName = (suggestion.feature_name ?? '').trim();

    const colombianized = featureName.replace(
        /^(\d+[A-Za-z]*)\s+(\d+[A-Za-z]*)\s+(.+)$/,
        '$3 #$1-$2',
    );

    const city =
        suggestion.address_level2?.trim() ||
        suggestion.context
            ?.find((c) => c.id?.startsWith('place.'))
            ?.text?.trim() ||
        '';

    if (!city) {
        return colombianized;
    }

    if (
        new RegExp(`,\\s*${escapeForRegex(city)}$`, 'i').test(colombianized) ||
        colombianized.toLowerCase().endsWith(city.toLowerCase())
    ) {
        return colombianized;
    }

    return `${colombianized}, ${city}`;
}

function escapeForRegex(value: string): string {
    return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}
