<?php

namespace Database\Factories;

use App\Models\Persona;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Persona>
 */
class PersonaFactory extends Factory
{
    protected $model = Persona::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $nombres = fake()->firstName();
        $apellidos = fake()->lastName().' '.fake()->lastName();

        // Sin documento por defecto, y no es pereza: es el caso más común en un
        // sistema recién generado —un formulario que pide lo mínimo— y el que
        // más fácil se rompe si alguien asume que el documento siempre está.
        return [
            'nombre_completo' => $nombres.' '.$apellidos,
            'nombres' => $nombres,
            'apellidos' => $apellidos,
            'telefono' => '+56 9 '.fake()->numerify('#### ####'),
            'email' => fake()->unique()->safeEmail(),
        ];
    }

    /** Persona con documento escrito pero SIN verificar contra la cédula. */
    public function conDocumento(?string $documento = null): self
    {
        return $this->state(fn (): array => [
            'tipo_documento' => 'RUN',
            'nro_documento' => $documento ?? fake()->unique()->numerify('1#.###.###-#'),
            'documento_acreditado' => false,
        ]);
    }

    /** Documento verificado por un funcionario: el único que debería salir al maestro. */
    public function acreditada(?string $documento = null): self
    {
        return $this->conDocumento($documento)->state(fn (): array => [
            'documento_acreditado' => true,
        ]);
    }
}
