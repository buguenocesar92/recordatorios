# Superficie de ataque — Livewire + Filament

Lo que un auditor genérico no sabe de este stack.

## 1. Todo método público es un endpoint

Cualquier método `public` de un componente Livewire **es invocable desde el
navegador**, con los argumentos que el cliente quiera. No importa si no hay
ningún botón que lo llame.

Revisar en cada componente:
- ¿Hay métodos públicos que deberían ser `protected` o `private`?
- ¿Cada método público que modifica datos verifica autorización **dentro del
  método**, no solo en `mount()`?
- Métodos como `delete()`, `approve()`, `export()`, `impersonate()`: ¿validan
  que el usuario actual pueda hacerlo sobre ese registro concreto?

**El error clásico:** autorizar en `mount()` y asumir que las acciones
posteriores están cubiertas. No lo están: cada petición Livewire es
independiente.

```php
// MAL — mount() autoriza, delete() no
public function mount(Solicitud $solicitud) {
    $this->authorize('view', $solicitud);
    $this->solicitud = $solicitud;
}
public function delete() {
    $this->solicitud->delete();   // ← cualquiera puede llamarlo
}

// BIEN
public function delete() {
    $this->authorize('delete', $this->solicitud);
    $this->solicitud->delete();
}
```

## 2. Toda propiedad pública es modificable por el cliente

El estado del componente viaja al navegador y vuelve. El cliente puede
cambiarlo.

- IDs, montos, estados, flags de permiso: **`#[Locked]`** obligatorio.
- `public $esAdmin`, `public $precio`, `public $userId` sin `#[Locked]` son
  vulnerabilidades directas.
- Modelos Eloquent como propiedad pública: solo se serializa la clave, pero
  la clave es modificable. Revisar que se re-verifique la propiedad.
- Nunca guardar en propiedades públicas: hashes, tokens, datos de otro
  usuario, resultados de queries sin filtrar por permisos.

## 3. Middleware persistente (Livewire 4)

Todo middleware propio que proteja el panel necesita
`Livewire::addPersistentMiddleware()`. Sin eso, la petición inicial pasa por
el middleware y **las peticiones Livewire subsiguientes no**.

Este bug dejó el MFA sin proteger ninguna acción del panel en 9 sistemas.
Revisarlo **siempre**, primero, en todo proyecto.

Verificación: iniciar sesión sin completar MFA e intentar disparar una acción
Livewire directamente. Si responde, está roto.

## 4. Validación

- `wire:model` sin validación en el servidor no valida nada. Las reglas del
  cliente son sugerencias.
- Validar en cada método que persiste, no solo en `submit()`.
- Arrays públicos + `fill()` o `update($this->datos)` = mass assignment.
  Revisar `$fillable` / `$guarded` y preferir listas explícitas.

## 5. Subida de archivos

- `TemporaryUploadedFile`: validar `mimes` **y** tamaño en el servidor.
- No confiar en la extensión ni en el `Content-Type` enviado por el cliente.
- Almacenar fuera de `public/` y servir por una ruta que autorice.
- Nombres generados por el servidor, nunca el nombre original del cliente.
- Revisar path traversal en cualquier ruta que reciba un nombre de archivo.

## 6. Filament

- **Policies por recurso**: un recurso sin policy es un recurso sin control
  de acceso. Verificar que existan y que cubran `viewAny`, `view`, `create`,
  `update`, `delete`, `restore`, `forceDelete`.
- **Bulk actions**: autorizan por registro o solo a nivel de página. Revisar
  que un bulk delete no borre registros que el usuario no puede tocar.
- **Shield + Octane**: el builder de nomenclatura va en `register()` +
  `extend()`, no en `boot()`. Verificar permisos en el navegador, nunca en
  tinker: tinker no reproduce el estado de Octane.
- **Widgets del dashboard**: ¿filtran por permisos del usuario, o muestran
  agregados de todo el sistema a cualquiera con acceso al panel?
- **Filtros y búsquedas**: un filtro por `user_id` que viene del request es
  un IDOR.
- **Exportaciones**: es la fuga más común. Un export que ignora el scope de
  permisos entrega la base completa en un CSV.

## 7. Eventos y listeners

- Los eventos de Livewire despachados desde el cliente llevan payload
  controlado por el cliente. Tratarlos como input no confiable.
- `#[On('evento')]` en un método que modifica datos: autorizar dentro.

## 8. SSRF

Ningún dato del cliente puede decidir a qué host o URL conecta el servidor.
Revisar: generación de PDF desde URL, previsualizaciones de enlaces, webhooks
configurables, importación desde URL, integraciones con APIs donde el
endpoint viene de la base de datos editable por el usuario.

El host va en configuración de servidor, no en el request.

## 9. Datos personales (Ley 21.719)

- RUT, dirección, teléfono, correo, datos de salud o discapacidad: revisar
  que usen `EncryptedSeguro` donde corresponda.
- Logs: verificar que no se escriban datos personales en texto plano.
  Revisar `Log::info()` con objetos completos.
- Trazabilidad: `spatie/laravel-activitylog` en todo acceso y modificación de
  datos de vecinos.
- Antes de enviar datos a cualquier servicio externo (incluida una API de
  LLM): pseudonimizar. Documentar la base de licitud.
- Retención: ¿hay datos que deberían haberse borrado y siguen ahí?

## 10. Features con LLM

- El input del usuario va **delimitado**, y la instrucción se **re-afirma
  después** del input. Es dato, no instrucción.
- Probar con un intento de inyección real antes de dar por bueno.
- Nunca meter en el prompt: datos de otro usuario, secretos, PII sin
  pseudonimizar.
- Responder solo desde fuente curada. Si no alcanza, "no lo encuentro".
