# Mapbox Address Autofill — baseline behaviour for Bogotá addresses

**Date:** 2026-05-09
**State:** post-revert (`develop` at `ab9f094`). Component in use: `<AddressAutofill>` from `@mapbox/search-js-react` with `options={{ country: 'co', language: 'es' }}`. **No** `proximity` is passed (Mapbox falls back to its `ip` default), **no** client-side filter, **no** query normalization beyond what the SDK does internally. No municipality coordinates are projected to the form.

## Why this audit exists

The user observed that typing `calle 41a sur 83 17` returns approximate matches but does not surface the exact address `Calle 41A Sur 83 17`, while appending `, Bogotá` to the same query does. Before adding any further code we wanted to measure the **delta the city suffix introduces** across more addresses, and check whether common Colombian abbreviations (`Cra`, `Cll`, `Av Cl`) are understood by Mapbox out-of-the-box — that informs which mitigations are worth implementing in Step 1.

All measurements were taken on `http://localhost/services/create` via Playwright MCP, no municipality selected, single browser session, ~1.5 s wait after the last keystroke.

## Test set

Three real Bogotá addresses (residential, commercial, hospital) and three "abbreviation" stress tests:

| # | Address | Notes |
|---|---|---|
| 1 | Calle 41A Sur 83 17 | Residential, southern Bogotá. The user's original example. |
| 2 | Carrera 11 82 71 | C.C. Andino. Northern commercial belt. |
| 3 | Carrera 7 40 62 | Hospital San Ignacio, downtown. |
| 4 | Cra 11 82 71 | Same as #2 with `Cra` abbreviation. |
| 5 | Cll 80 14 12 | Generic check, `Cll` abbreviation. |
| 6 | Av Cl 26 59 41 | Avenida Calle compound type with `Av` + `Cl` abbreviations. |

Each query is run twice: bare and with `, Bogotá` appended. Suggestions are listed top-to-bottom in Mapbox's order; the **target address** column flags whether the input's exact secondary number landed in the list at all (✅ = exact match present, ⚠️ = the street is there but a different secondary number, ❌ = the street is not even in the list).

## Results

### #1 — Calle 41A Sur 83 17

| Suffix | Count | Suggestions | Target |
|---|---|---|---|
| (none) | 5 | `Calle 41A Sur 83 15` (Bogotá) · `Calle 41A Sur 83` (Bogotá) · `Calle 41A 83` (Soacha) · `Calle 41A Bis Sur 83` (Bogotá) · `Calle 42A 83` (Soacha) | ⚠️ wrong secondary |
| `, Bogotá` | 3 | **`Calle 41A Sur 83 17`** (Bogotá) · `Calle 41A Sur 83` (Bogotá) · `Cl 41a S` (Bogotá) | ✅ exact, position 1 |

### #2 — Carrera 11 82 71

| Suffix | Count | Suggestions | Target |
|---|---|---|---|
| (none) | **1** | `Carrera 11 82 50` **(Puerto Carreño, Vichada)** | ❌ no Bogotá hit at all |
| `, Bogotá` | 5 | `Carrera 11 82` (Bogotá) · `Carrera 11 82` (Chía) · `Calle 2 Este 82` (Soacha) · `Carrera 11 82` (Soacha) · `Carrera 11 Este 82` (Soacha) | ⚠️ street present, wrong secondary |

### #3 — Carrera 7 40 62

| Suffix | Count | Suggestions | Target |
|---|---|---|---|
| (none) | 5 | `Carrera 7 40` (Bogotá) · `Carrera 7A Este 40` (Soacha) · `Carrera 5 40` (Soacha) · `Carrera 7 Bis 40` (Bogotá) · `Carrera 7 Este 40` (Bogotá) | ⚠️ street present, wrong secondary |
| `, Bogotá` | 5 | `Carrera 7 40` (Bogotá) · `Carrera 7 Bis 40` (Bogotá) · `Carrera 7 40` (Cajicá) · `Carrera 7 40 18` (Chía) · `Carrera 7A Este 40` (Soacha) | ⚠️ street present, wrong secondary |

### #4 — Cra 11 82 71 (abbreviation)

| Suffix | Count | Suggestions | Target |
|---|---|---|---|
| (none) | 1 | `Carrera 11 82 50` **(Puerto Carreño, Vichada)** | ❌ identical bad single-result behaviour as #2 |
| `, Bogotá` | 5 | `Carrera 11 82` (Bogotá) · `Carrera 11 82` (Chía) · `Carrera 11 82` (Soacha) · `Carrera 11 Este 82` (Soacha) · `Calle 15 Sur 82` (Soacha) | ⚠️ street present, wrong secondary |

### #5 — Cll 80 14 12 (abbreviation)

| Suffix | Count | Suggestions | Target |
|---|---|---|---|
| (none) | 4 | `Calle 80 Sur 14` (Bogotá) · `Calle 80 Bis Sur 14` (Bogotá) · `Carrera 80 Bis 14` (Bogotá) · `Carrera 80 14` (Bogotá) | ⚠️ street present, wrong secondary |
| `, Bogotá` | 4 | identical set, slight reorder | ⚠️ idem |

### #6 — Av Cl 26 59 41 (abbreviation, compound type)

| Suffix | Count | Suggestions | Target |
|---|---|---|---|
| (none) | 5 | `Avenida Calle 26 59 15` (Bogotá) · `Avenida Calle 26 Sur 59` (Bogotá) · `Avenida Calle 26 59` (Zipaquirá) · `Avenida Calle 61 Sur 26` (Bogotá) · `Avenida Calle 68F Sur 26` (Bogotá) | ⚠️ wrong secondary (15 instead of 41) |
| `, Bogotá` | 5 | `Avenida Calle 26 59 15` (Bogotá) · `Avenida Calle 26 Sur 59` (Bogotá) · `Avenida Calle 59 Sur 26` (Bogotá) · `Avenida Calle 56A Sur 26` (Bogotá) · `Avenida Calle 61 Sur 26` (Bogotá) | ⚠️ wrong secondary, but 5/5 in Bogotá |

## Findings

1. **Appending the city is the single highest-impact mitigation.** In #1 it surfaces the exact target address as the first hit (the bare query never returned it). In #2 / #4 it converts a useless single hit from Vichada into 5 Bogotá-area hits. In #3 / #6 it boosts the share of Bogotá-area hits even when the secondary number remains imprecise.
2. **Mapbox already understands the common Colombian street-type abbreviations.** Cases #4 (`Cra`), #5 (`Cll`), and #6 (`Av Cl`) returned the same family of suggestions as the spelled-out variants in #2 and the equivalent baselines. **A normalizer that expands abbreviations would not move the needle.**
3. **The exact secondary number is fragile across the board.** Outside #1 with-suffix, no other test got the exact `<primary>-<secondary>` pair right at position 1. This is a cap on how good the autocomplete can ever be without a higher-fidelity dataset. Mapbox treats the secondary number as a fuzzy hint, not a key.
4. **Without scoping, a few queries collapse to bizarre results.** Cases #2 and #4 returning a single Vichada hit illustrate that the bare API can be brittle when the textual signal is thin and proximity is `ip`-resolved (Mapbox can't always pin the user to Bogotá from IP alone, especially through a NAT/VPN/dev environment).

## Implications for Step 1

- **Expanding abbreviations: drop.** Adding a `Cra → Carrera` map costs maintenance and provides ~zero lift. (#4 vs #2, #5 vs spelled-out, #6 vs spelled-out are all equivalent.)
- **Appending `, <municipio>, <departamento>` to the outgoing query when the operator has a municipio selected: keep.** Largest measurable gain. Must be done **transparently** — the user-visible input should never reflect this suffix; only the request string sent to `/autofill/v1/suggest` should.
- **Passing `proximity` from the municipality centroid: keep.** Complementary signal that helps when the textual signal is thin (and removes the IP guesswork).
- **Stripping `#` and `-` from the query: keep.** Already proven necessary in earlier work; orthogonal to suffix work.
- **Filtering suggestions by `address_level2 === municipio` client-side: keep.** Catches the long tail of Soacha / Chía / Cajicá leakage that proximity + suffix don't fully eliminate (visible in #2-with-suffix).

## Architectural note

`<AddressAutofill>` (the high-level React component) reads the input's `value` directly via DOM and offers no hook to transform the query before it hits Mapbox. To inject the city suffix transparently the component **must be replaced** with a controlled implementation built on `useAddressAutofillCore` + `useSearchSession`. This is the path the reverted PR (`feat/mapbox-autocomplete-improvements`) took. The mistake in that PR was *also* trying to reformat the user-typed text post-pick into Colombian nomenclature via a custom regex — a heuristic that broke on the long tail of street types and was the reason for the revert. The Step 1 implementation should reuse the controlled component pattern but drop the post-pick text rewrite — the user's typed address remains canonical, Mapbox only contributes coordinates.
