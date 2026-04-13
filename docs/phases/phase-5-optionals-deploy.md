# Fase 5: Módulos Opcionales y Deploy

> **Estado: EN PROGRESO** — Despliegue completado, módulos opcionales pendientes

## Objetivo

Implementar los módulos opcionales (FUEC, GPS) como funcionalidad latente y preparar el despliegue en producción.

## Requerimientos cubiertos

- **REQ-007** - Generación de FUEC (opcional)
- **REQ-010** - Seguimiento de Ubicación de Vehículos (GPS opcional)

## Dependencias

- Sin bloqueantes fuertes; puede iniciarse en paralelo con Fase 4
- Requiere los servicios y vehículos de Fases 1-2

---

## Tareas

### 5.1 Módulo FUEC (REQ-007)

> Este módulo se entrega **desactivado** inicialmente. La lógica queda implementada para activarse en el futuro.

- Feature flag para activar/desactivar el módulo FUEC
- Validaciones previas a la generación:
  - Contrato vigente
  - Documentos del vehículo vigentes
  - Licencia del conductor vigente
- Generación de PDF con:
  - Datos del contrato, vehículo y conductor
  - Origen y destino del servicio
  - Fecha y hora
  - Código QR de verificación
  - Número consecutivo único
- Página pública de verificación al escanear QR (estado VIGENTE/ANULADO)
- Almacenamiento de PDF en MinIO
- Consecutivo del rango autorizado por MinTransporte

### 5.2 Ubicación GPS opcional (REQ-010)

> El GPS es opcional. El sistema funciona completamente sin él.

- Registro de ubicación por conductor (automático vía geolocalización del navegador o manual)
- Almacenamiento: coordenadas + timestamp + indicador `es_manual`
- Vista de mapa con vehículos activos (solo si tienen ubicación reportada)
- No bloquea ninguna operación si no hay datos GPS

### 5.3 Preparación para deploy ✅

- **Dockerización:**
  - Dockerfile multi-stage con FrankenPHP (4 etapas: composer → base → build → production)
  - `compose.staging.yaml` con perfiles: infraestructura (siempre) + app (perfil `local`)
  - `.dockerignore` optimizado para builds de producción
- **Dokploy:**
  - CI/CD workflow (`deploy-staging.yml`) con redeploy automático vía API de Dokploy
  - Variables de entorno de producción documentadas
  - SSL/HTTPS automático via Dokploy/Caddy
- **Infraestructura:**
  - Laravel Octane con FrankenPHP como servidor de producción
  - Supervisor para Octane + Horizon + Reverb + SSR
  - Config/route caching en entrypoint (runtime env vars)
  - Compresión automática (gzip + brotli + zstd) via FrankenPHP/Caddy
- **Documentación:** Guía completa en [`docs/deployment.md`](../deployment.md)
- **Requerimiento:** [frankenphp-production-docker.md](../requirements/frankenphp-production-docker.md)

### 5.4 Checklist pre-producción

- [x] Seeders de datos iniciales (roles, permisos, tipos de documento, tipos de novedad)
- [ ] Pruebas de carga con datos representativos (100 vehículos, 300 servicios/día)
- [ ] Revisión de seguridad (CSRF, XSS, SQL injection, validaciones)
- [ ] Configurar rate limiting
- [ ] Configurar logs de producción
- [ ] Documentar proceso de respaldo y restauración

---

## Paquetes

| Paquete | Uso |
| ------- | --- |
| `simplesoftwareio/simple-qrcode` | Generación de QR para FUEC |
| `league/flysystem-aws-s3-v3` | Almacenamiento en MinIO (driver S3) |
| `barryvdh/laravel-dompdf` | PDF para FUEC (reutilizado de Fase 4) |

## Criterios de completitud

- [ ] Módulo FUEC implementado y desactivado (feature flag)
- [ ] Generación de PDF con QR funcional
- [ ] Página de verificación QR pública
- [ ] Registro de ubicación GPS funcional (automático y manual)
- [x] Docker multi-stage funcional con FrankenPHP
- [x] Compose de staging con todos los servicios de infraestructura
- [x] CI/CD de deploy a staging configurado (Dokploy)
- [x] SSL/HTTPS configurado (Dokploy/Caddy)
- [ ] Backups automáticos de BD configurados
- [ ] Scheduler de Laravel configurado para alertas de vencimiento
