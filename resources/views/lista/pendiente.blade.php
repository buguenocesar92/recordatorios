<x-sitio.documento titulo="Revisa tu correo">
    <p role="status">{{ $mensaje }}</p>
    <p>El enlace vence en {{ config('waitlist.confirm_ttl_hours') }} horas. Si no llega, revisa la carpeta de spam. Por seguridad no reenviamos otro correo a la misma dirección antes de 24 horas.</p>
    <p><a href="/">Volver al inicio</a></p>
</x-sitio.documento>
