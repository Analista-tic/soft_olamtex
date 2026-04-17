<?php

namespace Database\Factories\MasterData;

use App\Models\MasterData\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory para Contact.
 *
 * Uso:
 *   Contact::factory()->create();
 *   Contact::factory()->proveedor()->create();
 *   Contact::factory()->cliente()->create();
 */
class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        return [
            'contact_type' => 'proveedor',
            'id_type'      => 'cc',
            'id_number'    => $this->faker->unique()->numerify('##########'),
            'full_name'    => $this->faker->company(),
            'person_type'  => 'persona_juridica',
            'email'        => $this->faker->unique()->companyEmail(),
            'phone'        => null,
            'address'      => null,
            'municipality' => null,
            'department'   => null,
            'dv'           => null,
            'groups'       => null,
        ];
    }

    public function proveedor(): static
    {
        return $this->state(['contact_type' => 'proveedor']);
    }

    public function cliente(): static
    {
        return $this->state(['contact_type' => 'cliente']);
    }

    public function nit(): static
    {
        return $this->state([
            'id_type'     => 'nit',
            'person_type' => 'persona_juridica',
        ]);
    }
}
