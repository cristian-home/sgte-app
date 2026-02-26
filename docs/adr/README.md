# Architecture Decision Records (ADR)

Registro de decisiones arquitectonicas del proyecto SGTE.

## Indice

| ID | Titulo | Estado | Fecha |
|----|--------|--------|-------|
| - | - | - | - |

## Formato

Cada ADR debe seguir esta estructura:

```markdown
# ADR-NNN: Titulo de la decision

**Estado:** Propuesto | Aceptado | Deprecado | Supersedido por ADR-XXX
**Fecha:** YYYY-MM-DD

## Contexto

Descripcion del problema o situacion que requiere una decision.

## Decision

La decision tomada y su justificacion.

## Consecuencias

Impacto positivo y negativo de la decision.
Compromisos (trade-offs) aceptados.
```

## Guia de uso

- Numerar secuencialmente: ADR-001, ADR-002, etc.
- Un ADR por archivo: `ADR-001-titulo-de-la-decision.md`
- No modificar ADRs aceptados; si cambia la decision, crear uno nuevo que lo superseda
- Actualizar el indice de este README al agregar cada ADR
