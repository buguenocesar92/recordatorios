<x-sitio.documento titulo="Aviso de privacidad de la lista de espera" :borrador="true">
    <p><strong>Responsable:</strong> {{ config('sitio.entidad.nombre') }} (RUT, domicilio y correo de contacto de privacidad: pendientes de completar por el responsable). Versión {{ \App\Waitlist\VersionDelConsentimiento::actual() }}.</p>
    <p><strong>No es un servicio del Estado ni de ningún municipio.</strong> Es un producto privado. No pedimos ClaveÚnica, Clave Tributaria ni claves de bancos, y no recibimos pagos.</p>

    <h2>Qué datos tratamos y para qué</h2>
    <ul>
        <li><strong>Tu correo electrónico</strong> (obligatorio): para mandarte el enlace de confirmación y, si confirmas, avisarte cuando el servicio abra. Se guarda cifrado.</li>
        <li><strong>Tu comuna y qué obligaciones te interesan</strong> (opcionales): para saber qué fechas priorizar.</li>
        <li><strong>Tu respuesta a «¿has pagado multa o intereses por olvido en el último año?»</strong> (opcional): para medir si el problema existe. Solo se usa en cifras agregadas.</li>
        <li><strong>Un seudónimo de tu dirección IP</strong> (un código calculado con una clave secreta, no la IP): para limitar abusos del formulario. Se borra al confirmar.</li>
    </ul>
    <p><strong>Base de licitud:</strong> tu consentimiento, que das marcando la casilla (nunca viene marcada) y confirmando por correo (doble opt-in). Puedes retirarlo cuando quieras.</p>

    <h2>Cuánto tiempo</h2>
    <ul>
        <li>Si no confirmas, borramos la inscripción a las 72 horas.</li>
        <li>Si confirmas, hasta el lanzamiento más seis meses, o hasta que te des de baja. Pasada esa fecha la lista se borra entera de forma automática.</li>
        <li>Los respaldos cifrados del servidor pueden conservar un dato ya borrado hasta que expiran (plazo pendiente de definir).</li>
    </ul>

    <h2>Con quién se comparte</h2>
    <p>No vendemos datos. Los tratan, por encargo nuestro: Contabo (alojamiento del servidor, Unión Europea), Cloudflare (DNS, certificados, protección anti-robots Turnstile; Estados Unidos) y Resend (envío de correo; Estados Unidos). A Turnstile llega tu navegador y tu IP al resolver la comprobación anti-robots. El mecanismo para las transferencias internacionales está pendiente de definir con el abogado.</p>

    <h2>Tus derechos</h2>
    <p>Puedes darte de baja con un clic desde cualquier correo nuestro: eso borra tu inscripción. También puedes pedir acceso, rectificación, supresión u oposición escribiendo al correo de contacto de privacidad; respondemos dentro de 30 días corridos, prorrogables una vez. Puedes reclamar ante la Agencia de Protección de Datos Personales.</p>

    <h2>Cookies y analítica</h2>
    <p>Esta página no usa cookies propias ni analítica de terceros. El widget de Cloudflare Turnstile funciona en su propio marco.</p>

    <h2>Cambios</h2>
    <p>Si este aviso cambia, publicaremos la nueva versión aquí con su fecha.</p>
</x-sitio.documento>
