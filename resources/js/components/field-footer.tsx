import type { ReactNode } from 'react';

/**
 * Slot opcional bajo cada input que muestra error con prioridad sobre
 * hint. Renderiza `null` cuando no hay nada que mostrar — el componente
 * NO reserva altura por sí mismo.
 *
 * El balance entre celdas vecinas en filas multi-columna se logra con
 * CSS subgrid en el padre: `md:grid-rows-[auto_auto_auto]` arriba y
 * `md:row-span-3 md:grid-rows-subgrid` en cada celda. La row 3 (del
 * footer) mide max(contenido entre celdas), así que si una celda muestra
 * error y otra no, ambas crecen lo mismo. Si ninguna muestra nada, la
 * row colapsa a 0 — sin gap fantasma.
 *
 * Para columnas únicas no es necesario subgrid: la fila simplemente
 * crece cuando aparece el error (mismo patrón que shadcn / Chakra).
 *
 * Uso:
 *   <FieldFooter error={errors.foo}>Texto de ayuda corto.</FieldFooter>
 *   <FieldFooter error={errors.bar} />
 *   <FieldFooter>Solo hint, sin error</FieldFooter>
 */
export default function FieldFooter({
    error,
    children,
}: {
    error?: string;
    children?: ReactNode;
}) {
    if (error) {
        return <p className="text-sm text-destructive">{error}</p>;
    }
    if (children) {
        return (
            <p className="text-xs text-muted-foreground italic">{children}</p>
        );
    }
    return null;
}
