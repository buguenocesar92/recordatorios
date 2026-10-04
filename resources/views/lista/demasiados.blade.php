<x-sitio.documento titulo="Demasiados intentos">
    <p role="alert">{{ $mensaje ?? 'Recibimos muchos intentos seguidos desde tu conexión. Espera un minuto y vuelve a intentarlo.' }}</p>
    <p><a href="/#lista">Volver al formulario</a></p>
</x-sitio.documento>
