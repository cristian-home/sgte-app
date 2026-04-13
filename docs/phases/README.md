# Plan de Implementación - SGTE

Documentación técnica de las fases de desarrollo del Sistema de Gestión de Transporte Especial.

## Fases

| Fase | Nombre | Estado | REQs principales |
| ---- | ------ | ------ | ---------------- |
| 1 | [Fundamentos y Datos Maestros](fase-1-fundamentos.md) | ✅ Completada | REQ-004, REQ-005, REQ-006, REQ-014 |
| 2 | [Core Operativo](fase-2-operaciones.md) | ✅ Completada | REQ-001, REQ-002, REQ-003, REQ-008, REQ-009 |
| 3 | [Conductor y Novedades](fase-3-conductor-novedades.md) | ✅ Completada | REQ-012, REQ-013 |
| 4 | [Facturación y Auditoría](fase-4-facturacion-reportes.md) | ⬜ Pendiente (siguiente) | REQ-011, REQ-009 |
| 5 | [Opcionales y Deploy](fase-5-opcionales-deploy.md) | 🔶 En progreso (deploy listo) | REQ-007, REQ-010 |

## Dependencias entre fases

```
Fase 1 ✅ ──► Fase 2 ✅ ──► Fase 3 ✅
                       └──► Fase 4 ⬜ (siguiente)
                                 └──► Fase 5 🔶
```

- **Fase 2** requiere las migraciones y CRUDs de Fase 1
- **Fase 3** requiere el formulario de servicio de Fase 2
- **Fase 4** requiere los estados del día de Fase 2; Fase 3 recomendada
- **Fase 5** no tiene bloqueantes fuertes; deploy ya completado, módulos opcionales pendientes

## Stack tecnológico

| Capa | Tecnología |
| ---- | ---------- |
| Backend | Laravel 12 (PHP 8.5) |
| Frontend | React 19 + Inertia.js v2 + shadcn/ui |
| Gantt / Calendario | Componentes React custom (sin librerías externas) |
| BD | PostgreSQL 18 |
| Almacenamiento | MinIO (S3-compatible) |
| Búsqueda | Laravel Scout + Typesense |
| Tiempo real | Laravel Reverb + Laravel Echo |
| Servidor | FrankenPHP (Laravel Octane) |
| Hosting | Dokploy + VPS Linux |

## Referencia

- [SRS completo](../SRS.md)
- [Modelo de datos](../modelo-datos.md)
- [Navegación](../navegacion.md)
- [Mockups](../mockups.md)
- [Guía de despliegue](../deployment.md)
- [Requerimientos detallados](../requirements/)
- [ADRs](../adr/)
