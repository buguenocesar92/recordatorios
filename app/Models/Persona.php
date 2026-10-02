<?php

namespace App\Models;

use Database\Factories\PersonaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Muni\Shared\Privacidad\Contratos\TitularDeDatos;

/**
 * El vecino sobre el que este sistema trata datos.
 *
 * Es el titular de datos de la Ley 21.719, y es distinto del `User`: la cuenta
 * de un funcionario también es dato personal, pero se trata por la relación
 * laboral y no por la función municipal. Ver el docblock de la migración.
 *
 * @property int $id
 * @property string $nombre_completo
 * @property string|null $nombres
 * @property string|null $apellidos
 * @property string|null $tipo_documento
 * @property string|null $nro_documento
 * @property string|null $nro_documento_norm columna GENERADA por la base
 * @property bool $documento_acreditado
 * @property string|null $telefono
 * @property string|null $telefono_norm columna GENERADA por la base
 * @property string|null $email
 * @property Carbon|null $fecha_nacimiento
 * @property string|null $direccion
 * @property Carbon|null $sincronizado_maestro_at
 */
class Persona extends Model implements TitularDeDatos
{
    /** @use HasFactory<PersonaFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * `nro_documento_norm` y `telefono_norm` NO se listan: las calcula la base
     * como columnas generadas. Incluirlas acá haría que Eloquent intentara
     * escribirlas y MariaDB rechazaría el INSERT.
     */
    protected $fillable = [
        'nombre_completo',
        'nombres',
        'apellidos',
        'tipo_documento',
        'nro_documento',
        'documento_acreditado',
        'telefono',
        'email',
        'fecha_nacimiento',
        'direccion',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'documento_acreditado' => 'boolean',
            'fecha_nacimiento' => 'date',
            'sincronizado_maestro_at' => 'datetime',
        ];
    }

    /**
     * Busca por documento comparando contra la columna NORMALIZADA.
     *
     * Compara la forma normalizada de los dos lados, y no el texto tal cual,
     * porque «12.345.678-9» y «123456789» son el mismo documento escrito
     * distinto: buscar por el valor crudo devuelve vacío y parece que la
     * persona no existe. Es el error que ya se cometió en el ecosistema.
     *
     * @param  Builder<Persona>  $query
     * @return Builder<Persona>
     */
    public function scopePorDocumento(Builder $query, string $documento): Builder
    {
        return $query->where('nro_documento_norm', self::normalizarDocumento($documento));
    }

    /**
     * Personas alcanzables por un canal —teléfono o correo—, que es como se
     * acredita a quien nunca dejó un documento.
     *
     * @param  Builder<Persona>  $query
     * @return Builder<Persona>
     */
    public function scopePorCanal(Builder $query, string $canal): Builder
    {
        $soloDigitos = preg_replace('/[^0-9]/', '', $canal) ?? '';

        return $query->where(function (Builder $q) use ($canal, $soloDigitos): void {
            $q->where('email', mb_strtolower(trim($canal)));

            if ($soloDigitos !== '') {
                $q->orWhere('telefono_norm', $soloDigitos);
            }
        });
    }

    /**
     * La misma normalización que aplica la columna generada de la base.
     *
     * Está duplicada a propósito y hay que mantenerlas iguales: la de la base
     * es la que sostiene el índice único, y ésta solo sirve para construir la
     * consulta. Si divergen, la búsqueda deja de encontrar lo que el índice
     * considera duplicado — falla silenciosa y difícil de ver.
     */
    public static function normalizarDocumento(string $documento): string
    {
        return mb_strtoupper(str_replace(['.', '-', ' '], '', trim($documento)));
    }

    /**
     * ── Derechos de la persona sobre sus datos (Ley 21.719) ────────────────
     *
     * Lo que sigue es lo que el módulo de privacidad le pregunta a este sistema
     * para poder atender una solicitud de acceso, rectificación, cancelación u
     * oposición. El módulo no conoce este esquema: pide, y acá se decide qué
     * significa cada cosa en «personas».
     */
    public function titularNombre(): string
    {
        $nombre = trim($this->nombres.' '.$this->apellidos);

        return $nombre !== '' ? $nombre : (string) $this->nombre_completo;
    }

    public function titularDocumento(): string
    {
        return (string) ($this->nro_documento ?? '');
    }

    /**
     * Todo lo que este sistema guarda de la persona, para el derecho de acceso
     * y el de portabilidad.
     *
     * Se listan los campos uno a uno en vez de volcar el registro entero: así
     * una columna interna que se agregue mañana —una marca de sincronización,
     * un identificador de otro sistema— no termina en el documento que se le
     * entrega a un vecino sin que nadie lo haya decidido.
     *
     * @return array<string, mixed>
     */
    public function exportarDatosPersonales(): array
    {
        return [
            'nombres' => $this->nombres,
            'apellidos' => $this->apellidos,
            'tipo_documento' => $this->tipo_documento,
            'numero_documento' => $this->nro_documento,
            'documento_acreditado' => (bool) $this->documento_acreditado,
            'telefono' => $this->telefono,
            'correo' => $this->email,
            'fecha_nacimiento' => $this->fecha_nacimiento?->toDateString(),
            'direccion' => $this->direccion,
            'registrado_el' => $this->created_at?->toDateTimeString(),
        ];
    }

    /**
     * Supresión de verdad: la ley pide borrar, no ocultar.
     *
     * Los datos de contacto y de identificación se van del registro. Lo que
     * queda —el identificador y las fechas— no permite reconocer a nadie y es
     * lo que sostiene la trazabilidad de que la supresión ocurrió, que la misma
     * ley exige poder demostrar.
     *
     * Un sistema que además guarde archivos de esta persona debe borrarlos acá:
     * un expediente en disco es dato personal igual que una fila.
     */
    public function purgarDatosSensibles(): void
    {
        $this->forceFill([
            'nombres' => null,
            'apellidos' => null,
            // Cadena vacía y no null: la columna es NOT NULL y tiene índice, así
            // que ponerla en null hacía que la supresión REVENTARA con un error
            // de integridad. O sea que el derecho de supresión no funcionaba, y
            // nadie lo sabía porque este método no estaba probado. Vaciarla
            // cumple igual —no queda dato que identifique a nadie— y no obliga a
            // migrar el esquema de los sistemas ya desplegados.
            'nombre_completo' => '',
            'tipo_documento' => null,
            'nro_documento' => null,
            'telefono' => null,
            'email' => null,
            'fecha_nacimiento' => null,
            'direccion' => null,
            'documento_acreditado' => false,
        ])->save();
    }

    /**
     * Deja el registro sin forma de volver a la persona, conservando lo que
     * sirve para estadística comunal.
     *
     * Acá se conserva el año de nacimiento y nada más: permite decir «tantas
     * personas de tal tramo etario usaron el servicio» sin permitir señalar a
     * ninguna.
     */
    public function anonimizar(): void
    {
        $anio = $this->fecha_nacimiento?->format('Y');

        $this->purgarDatosSensibles();

        if ($anio !== null) {
            $this->forceFill(['fecha_nacimiento' => $anio.'-01-01'])->save();
        }
    }

    /**
     * Lo que la persona puede corregir por derecho de rectificación.
     *
     * No es un cheque en blanco sobre el registro: alguien corrige su apellido
     * mal escrito o su teléfono, no la marca de que su documento fue acreditado
     * en el mesón —eso lo constata el funcionario, no el titular—.
     *
     * @return list<string>
     */
    public function camposRectificables(): array
    {
        return ['nombres', 'apellidos', 'telefono', 'email', 'direccion', 'fecha_nacimiento'];
    }

    /**
     * La fecha de nacimiento acreditada, o null si este sistema no la tiene.
     *
     * «null» NO significa adulto: significa que la edad no está acreditada.
     * Quién es niño, niña o adolescente lo decide el módulo, que conoce la
     * regla legal; acá solo se entrega el dato.
     */
    public function fechaNacimientoTitular(): ?\DateTimeInterface
    {
        return $this->fecha_nacimiento;
    }
}
