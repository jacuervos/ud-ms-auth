<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use App\Models\Rol;
use App\Models\State;
use App\Models\TypeIdentification;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'phone' => substr($this->faker->phoneNumber(), 0, 15),
            'identification' => $this->faker->unique()->numerify('##########'),
            'type_identification_id' => TypeIdentification::inRandomOrder()->first()->id ?? 1, // Obtiene un ID real o usa un valor por defecto
            'rol_id' => Rol::inRandomOrder()->first()->id ?? 1, // Obtiene un ID real o usa un valor por defecto
            'email' => $this->faker->unique()->safeEmail(),
            'state_id' => State::inRandomOrder()->first()->id ?? 1, // Obtiene un ID real o usa un valor por defecto
            'image' => $this->faker->imageUrl(200, 200, 'people'),
            'password' => static::$password ??= Hash::make('password'),
            'points' => $this->faker->numberBetween(0, 100),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
