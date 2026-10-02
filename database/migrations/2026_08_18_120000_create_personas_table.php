<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La persona: el vecino sobre el que el sistema trata datos.
 *
 * Va en el scaffold —y no en cada proyecto cuando alguien se acuerda— porque
 * sin una entidad que represente a la persona no se puede implementar el
 * contrato de titular de datos que exige la **Ley 21.719**, y porque los
 * sistemas del ecosistema que no la tenían terminaron con el mismo vecino
 * escrito tantas veces como módulos lo mencionaban: nadie podía contestar «qué
 * tienen ustedes sobre mí» ni borrarlo.
 *
 * ## `User` también es una persona, y aun así no reemplaza a esta tabla
 *
 * La cuenta de un funcionario es dato personal y su titular tiene los mismos
 * derechos. Pero es OTRO titular: al funcionario se le tratan datos por la
 * relación laboral y al vecino por la función municipal, con finalidades,
 * plazos y vías de ejercicio distintas. Un sistema que atiende público necesita
 * las dos entidades; confundirlas mezcla dos regímenes legales en una fila.
 *
 * ## Qué identifica a una persona, y por qué el documento es NULO
 *
 * Se eligió el criterio más amplio del ecosistema a propósito, porque el
 * scaffold no sabe qué va a recoger el sistema que se genere con él:
 *
 * - `nro_documento` es **nulo** y su unicidad es **parcial**: única cuando hay
 *   valor, libre cuando no. Lo sostiene el índice único sobre
 *   `nro_documento_norm`, que en MariaDB admite tantos nulos como haga falta.
 * - Un sistema que atiende con cédula en el mesón puede exigirlo por
 *   validación. Uno con formularios públicos **no debe pedirlo**: si la
 *   finalidad se cumple con un teléfono, exigir un identificador nacional «por
 *   si acaso» es recoger de más —lo que la ley llama minimización— y crear un
 *   padrón de identificadores que no hacía falta.
 * - **Sin documento, el sistema no afirma identidad.** Dos personas del mismo
 *   nombre son dos filas: fusionarlas sería declarar que son la misma sin que
 *   nadie lo haya comprobado, y equivocarse significa atribuirle a una los
 *   datos de la otra.
 * - Lo que sí queda registrado es el **canal** —teléfono o correo—, normalizado
 *   e indexado para que se pueda consultar y no solo mostrar. Quien controla el
 *   canal puede acreditarse por él, igual que en un «olvidé mi contraseña»,
 *   cuando el sistema implemente los derechos ARCOP.
 *
 * ## `documento_acreditado`
 *
 * Distingue el documento que alguien escribió en un formulario del que un
 * funcionario verificó contra la cédula. Solo el segundo debería salir hacia el
 * registro federado de personas del municipio: empujar al padrón central a
 * quien únicamente dejó una consulta es un tratamiento que nadie declaró.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personas', function (Blueprint $tabla): void {
            $tabla->id();

            // Se guarda el nombre tal como la persona lo dio, además del
            // desglose: partir un nombre por el espacio es una heurística que
            // falla con apellidos compuestos, y el dato original no se recupera.
            $tabla->string('nombre_completo', 300);
            $tabla->string('nombres', 150)->nullable();
            $tabla->string('apellidos', 150)->nullable();

            $tabla->string('tipo_documento', 10)->nullable();
            $tabla->string('nro_documento', 20)->nullable();

            // Normalizada por la BASE, no por PHP: si la calcula la aplicación,
            // cada camino de escritura puede normalizar distinto y el índice
            // único deja de proteger. NULLIF devuelve null con el documento
            // vacío, que es lo que permite la unicidad parcial.
            $tabla->string('nro_documento_norm', 20)
                ->storedAs("NULLIF(UPPER(REPLACE(REPLACE(REPLACE(nro_documento,'.',''),'-',''),' ','')),'')")
                ->nullable();

            $tabla->boolean('documento_acreditado')->default(false);

            $tabla->string('telefono', 60)->nullable();
            $tabla->string('telefono_norm', 30)
                ->storedAs("NULLIF(REGEXP_REPLACE(telefono,'[^0-9]',''),'')")
                ->nullable();
            $tabla->string('email', 200)->nullable();

            $tabla->date('fecha_nacimiento')->nullable();
            $tabla->string('direccion')->nullable();

            // Sello del write-through al registro federado. Lo escribe la clase
            // base compartida tras un POST exitoso; nulo significa «todavía no
            // se confirmó», no «no corresponde».
            $tabla->timestamp('sincronizado_maestro_at')->nullable();

            $tabla->softDeletes();
            $tabla->timestamps();

            $tabla->unique('nro_documento_norm');
            $tabla->index('telefono_norm');
            $tabla->index('email');
            $tabla->index('nombre_completo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personas');
    }
};
