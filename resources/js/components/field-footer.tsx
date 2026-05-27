import type { ReactNode } from 'react';

/**
 * Slot único bajo cada input que SIEMPRE reserva una línea de alto
 * (≈20px). Renderiza con prioridad: error > hint > vacío (espaciador
 * invisible). Sirve para que las celdas vecinas en una misma row de
 * grid (`md:grid-cols-2/3`) tengan exactamente la misma altura aunque
 * solo una tenga error o texto de ayuda.
 *
 * Reemplaza al patrón histórico `{hint && <p>...</p>}` + `<InputError />`,
 * que provocaba desbalance cuando el hint o el error existían solo en
 * algunas celdas. Mantén copy/errores a una línea — si la validación
 * puede producir mensajes largos, mejora el `messages()` del FormRequest.
 *
 * Uso:
 *   <FieldFooter error={errors.foo}>Texto de ayuda corto.</FieldFooter>
 *   <FieldFooter error={errors.bar} />  // sin hint, solo error
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
        return (
            <p className="min-h-5 text-sm text-destructive">{error}</p>
        );
    }
    return (
        <p
            className="min-h-5 text-xs text-muted-foreground italic"
            aria-hidden={children ? undefined : true}
        >
            {children ?? ' '}
        </p>
    );
}
