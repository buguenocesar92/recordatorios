---
name: auditoria-seguridad
description: Auditoría defensiva de seguridad para proyectos Laravel + Livewire + Filament de César (Municipalidad de Graneros, KraftDo, muni-kit). Usar al revisar seguridad de un sistema propio, antes de desplegar, al auditar código heredado o de terceros, al revisar autenticación, permisos, subida de archivos, endpoints públicos, manejo de datos personales, o cuando se pida un análisis de vulnerabilidades, hardening o cumplimiento de Ley 21.719.
---

# Auditoría defensiva de seguridad

Alcance: **sistemas propios de César**, revisados por lectura de código y
verificación en local o staging. Nunca contra producción. Nunca contra
sistemas de terceros.

## Regla de alcance

Antes de empezar, confirmar contra qué se audita:

- Código en el repo → siempre permitido.
- Entorno local o staging propio → permitido, con datos ficticios.
- **Producción → NO.** Ni escaneos, ni pruebas de carga, ni intentos de
  explotación. Si un hallazgo solo se puede confirmar en producción, se
  reporta como "no verificado" y se propone cómo reproducirlo en staging.
- Sistemas municipales: la revisión de código es parte del trabajo. Cualquier
  prueba activa contra un entorno desplegado requiere autorización escrita de
  la jefatura, con alcance y ventana definidos.

## Flujo

### 1. Contexto
```
grep -A1 '"name": "livewire/livewire"' composer.lock
grep -A1 '"name": "filament/filament"' composer.lock
php artisan --version
```
Livewire 3 y 4 difieren en superficie de ataque. Confirmar antes de aplicar
reglas.

### 2. Mapa de superficie
Enumerar, en este orden:
- Rutas públicas (sin `auth`): `php artisan route:list --except-vendor`
- Componentes Livewire y sus **métodos públicos** y **propiedades públicas**
- Recursos y acciones de Filament, con sus policies
- Endpoints de API y webhooks
- Puntos de subida de archivos
- Comandos artisan que tocan datos
- Jobs y listeners que procesan input externo

### 3. Análisis
Leer `references/livewire-filament.md` (superficie específica del stack) y
`references/owasp-laravel.md` (categorías generales). Revisar cada punto del
mapa contra ambos.

### 4. Verificación
Para cada hallazgo, intentar confirmarlo en local o staging. Un hallazgo
verificado vale diez sospechas. Si no se pudo verificar, decirlo.

### 5. Informe
Por hallazgo:
- **Qué**: la vulnerabilidad en una línea.
- **Dónde**: archivo:línea.
- **Severidad**: crítica / alta / media / baja.
- **Impacto real en este sistema**: no genérico. "Un vecino podría ver las
  licencias de otro" pesa distinto que "teóricamente hay IDOR".
- **Verificado**: sí / no, y cómo.
- **Arreglo**: el cambio concreto, con código.

Ordenar por severidad. No inflar el informe con hallazgos teóricos para que
se vea completo: el ruido hace que se ignoren los hallazgos reales.

## Prioridades por contexto

- **Sistemas municipales**: datos personales de vecinos. Control de acceso y
  Ley 21.719 primero. Una fuga aquí es un problema legal, no solo técnico.
- **KraftDo**: datos de clientes y pagos. Integridad de transacciones y
  autenticación primero.
- **muni-kit**: es código distribuido a otros municipios. Una vulnerabilidad
  se multiplica por cada instalación. El estándar es más alto, no más bajo.

## Qué NO hacer

- No escribir exploits, malware ni herramientas ofensivas.
- No probar contra sistemas que no sean de César.
- No tocar producción.
- No reportar como vulnerabilidad algo que no lo es para inflar el informe.
- No asumir que algo está bien porque "así viene en Laravel".
