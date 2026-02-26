# Plan de Implementación - SGTE

Documentación técnica de las fases de desarrollo del Sistema de Gestión de Transporte Especial.

## Fases

| Fase | Nombre | Descripción | REQs principales |
| ---- | ------ | ----------- | ---------------- |
| 1 | [Fundamentos y Datos Maestros](fase-1-fundamentos.md) | Setup, auth, migraciones, CRUDs base | REQ-004, REQ-005, REQ-006, REQ-014 |
| 2 | [Core Operativo](fase-2-operaciones.md) | Calendario, Gantt, servicios, estados | REQ-001, REQ-002, REQ-003, REQ-008, REQ-009 |
| 3 | [Conductor y Novedades](fase-3-conductor-novedades.md) | Interfaz conductor, novedades, notificaciones | REQ-012, REQ-013 |
| 4 | [Facturación y Auditoría](fase-4-facturacion-reportes.md) | Facturación, inmutabilidad, auditoría | REQ-011, REQ-009 |
| 5 | [Opcionales y Deploy](fase-5-opcionales-deploy.md) | FUEC, GPS, despliegue | REQ-007, REQ-010 |

## Dependencias entre fases

```
Fase 1 ──► Fase 2 ──► Fase 3
                  └──► Fase 4
                            └──► Fase 5
```

- **Fase 2** requiere las migraciones y CRUDs de Fase 1
- **Fase 3** requiere el formulario de servicio de Fase 2
- **Fase 4** requiere los estados del día de Fase 2
- **Fase 5** no tiene bloqueantes fuertes; puede iniciarse en paralelo con Fase 4

## Stack tecnológico

| Capa | Tecnología |
| ---- | ---------- |
| Backend | Laravel (PHP) |
| Frontend | React + Inertia.js + shadcn/ui |
| Gantt | Frappe Gantt o DHTMLX (componente React) |
| BD | PostgreSQL |
| Almacenamiento | MinIO (S3-compatible) |
| Hosting | Dockploy + VPS Linux |

## Referencia

- [SRS completo](../SRS.md)
- [Modelo de datos](../modelo-datos.md)
- [Navegación](../navegacion.md)
- [Mockups](../mockups.md)
