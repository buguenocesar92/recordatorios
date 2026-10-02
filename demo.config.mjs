/**
 * Configuración del motor de videos-tutorial (demo-engine).
 *
 * Personaliza estos valores según tu sistema:
 * - baseURL: URL donde corre tu aplicación (debe permitir localhost/red privada)
 * - marca: identidad visual (nombre, color, logo)
 * - actores: usuarios que aparecen en los videos
 * - login: selectores CSS del formulario de autenticación
 *
 * Documentación completa (contrato, CLI, privacidad, auditoría): el README de
 * demo-engine. Se instala desde https://github.com/muni-graneros/demo-engine (ver package.json),
 * así que por ahora vive en node_modules/demo-engine/README.md tras `npm install`.
 */

export default {
  // URL base del sistema (desarrollo: localhost)
  baseURL: process.env.BASE_URL ?? 'http://127.0.0.1:8000',

  // Identidad visual: aparece en portadas y cierres de video
  marca: {
    nombre: 'Mi Sistema',                    // Nombre que aparece en los videos
    color: '#1e3a8a',                        // Azul oscuro por defecto (hexadecimal)
    // escudo: './public/escudo.png',        // PNG 200x200, opcional. Descomenta
    //                                       // CUANDO el archivo exista: apuntar a
    //                                       // uno que no está deja la portada sin
    //                                       // escudo y sin decir por qué.
  },

  // Autenticación global: selectores CSS del formulario de login.
  // Este bloque es el defecto para TODOS los actores. Cada actor puede
  // pisar selectores parcialmente (útil si tienes varios formularios login).
  login: {
    url: '/admin/login',                     // Ruta del formulario
    usuario: 'input[id="form.email"]',       // Campo correo (Filament Livewire 4)
    clave: 'input[wire\\:model="data.password"]', // Campo contraseña (Alpine aún no hidrata)
    enviar: 'button[type="submit"]',         // Botón enviar
    codigo: 'input[name=code]',              // Campo TOTP si hay MFA (opcional)
    comprobar: 'h1',                         // Selector que prueba éxito (busca este elemento)
  },

  // Actores: usuarios que aparecen en los videos.
  // Cada uno es una sesión separada. El actor debe existir en la BD.
  actores: {
    administrador: {
      email: 'admin@example.com',
      // En local, el seeder del scaffold deja `password` (bloqueado en producción,
      // ver SeedersSinCredencialesEnProduccionTest). DEMO_CLAVE la sobrescribe si
      // este guión llega a correr contra un entorno con datos reales.
      password: process.env.DEMO_CLAVE ?? 'password',
      // totp: 'SECRETO_BASE32',             // Si el usuario tiene MFA activada (sin definir = sin MFA)
    },
    // Agrega más actores si tu sistema tiene varios roles:
    // funcionario: {
    //   email: 'funcionario@sistema.cl',
    //   password: 'password123',
    // },
  },

  // Rutas de archivos
  guiones: './demo/guiones',                 // Dónde buscar los .mjs de guiones
  salida: './docs/manual',                   // Dónde guardar MP4, VTT, PDF

  // Video: resolución y tiempos
  video: {
    ancho: 1600,                             // Píxeles (pantalla ancha)
    alto: 1000,                              // Píxeles
    pausaMinima: 1200,                       // Milisegundos entre pasos
  },

  // Voz: síntesis de audio (optional; sin esto, solo subtítulos)
  voz: {
    motor: 'kokoro',                         // Motor: 'kokoro' | 'piper' (defecto: kokoro)
    voz: 'ef_dora',                          // Voz de Kokoro: ef_dora | em_alex | em_santa
    // vozRespaldo: 'es_ES-davefx-medium',   // Voz de Piper si se cae al respaldo (nombre del .onnx)
    respaldo: 'piper',                       // Motor de fallback si el principal no está disponible
    // venv: null,                           // Ruta del venv Python (si está en otro lado)
    // voces: null,                          // Ruta de los modelos .onnx (si está en otro lado)
  },

  // Comandos shell: ejecutados antes y después de grabar
  // Útiles para resetear la BD o limpiar sesiones
  // sembrar: 'npm run seed',                // Antes de grabar (opcional)
  // limpiar: 'npm run clean',               // Después de grabar (opcional)

  // Auditoría: detección de privacidad (opcional, requiere OCR)
  // Descomenta si tienes un servicio OCR disponible
  // auditoria: {
  //   ocr: 'http://127.0.0.1:8110/ocr',     // Endpoint OCR (sin defecto)
  //   patron: '\\d{7,8}-[\\dkK]',            // Patrón de identificador (RUT-like)
  //   cada: 10,                              // Un frame cada N segundos
  //   maximo: 20,                            // Tope de frames por video
  // },

  // Pack de contexto (`demo contexto` / `demo todo`): un screenshot por pantalla,
  // para alimentar los generadores de manual/video. `aislar`/`mostrar` ocultan y
  // restauran la PII real (idempotente). Ver demo/CONTEXTO-Y-SEEDER.md.
  contexto: {
    salida: './demo/contexto',
    // aislar: 'docker compose exec -T app php artisan demo:preparar-contexto --aislar',
    // mostrar: 'docker compose exec -T app php artisan demo:preparar-contexto --mostrar',
    pantallas: [
      { id: 'pub-01-login', url: '/admin/login', actor: null },   // público (sin sesión)
      { id: 'app-01-inicio', url: '/admin', actor: 'administrador' }, // con la sesión del actor
    ],
  },
};
