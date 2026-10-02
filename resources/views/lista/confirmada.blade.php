<x-sitio.documento titulo="¡Listo, estás en la lista!">
    <p role="status">Tu inscripción quedó confirmada. Te escribiremos cuando el servicio abra.</p>

    <form method="POST" action="{{ $encuesta }}" class="mt-6 rounded-lg border border-borde bg-superficie p-5">
        <fieldset>
            <legend class="font-medium">Una pregunta opcional: ¿has pagado multa o intereses por olvido en el último año?</legend>
            <div class="mt-3 flex flex-wrap gap-3">
                <label class="flex min-h-11 items-center gap-2 rounded-md border border-borde px-3"><input type="radio" name="paid_penalty_last_year" value="1" required class="size-5 accent-(--sitio-acento)"> Sí</label>
                <label class="flex min-h-11 items-center gap-2 rounded-md border border-borde px-3"><input type="radio" name="paid_penalty_last_year" value="0" class="size-5 accent-(--sitio-acento)"> No</label>
            </div>
        </fieldset>
        <button type="submit" class="mt-4 inline-flex min-h-11 items-center rounded-md bg-acento px-5 font-medium text-sobre-acento hover:bg-acento-fuerte">Enviar respuesta</button>
    </form>

    <p class="text-sm text-atenuado">¿Te arrepentiste? <a href="{{ $baja }}">Darme de baja y borrar mi inscripción</a>.</p>
</x-sitio.documento>
