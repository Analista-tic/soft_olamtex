<?php

namespace Database\Factories\Expenses;

use App\Enums\PurchaseRequestStatus;
use App\Models\Expenses\PurchaseRequest;
use App\Models\Settings\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory para PurchaseRequest.
 *
 * Uso:
 *   PurchaseRequest::factory()->create();
 *   PurchaseRequest::factory()->pending()->create();
 *   PurchaseRequest::factory()->withRequester($user)->pending()->create();
 *   PurchaseRequest::factory()->withItems(3)->create();
 */
class PurchaseRequestFactory extends Factory
{
    protected $model = PurchaseRequest::class;

    public function definition(): array
    {
        return [
            'provider_name'     => $this->faker->company(),
            'requester_user_id' => null,
            'date'              => now()->toDateString(),
            'destination'       => null,
            'description'       => $this->faker->sentence(),
            'status'            => PurchaseRequestStatus::DRAFT,
            'items'             => $this->defaultItems(),
        ];
    }

    // ─── Estados de negocio ───────────────────────────────────────────────────

    public function draft(): static
    {
        return $this->state(['status' => PurchaseRequestStatus::DRAFT]);
    }

    public function pending(): static
    {
        return $this->state(['status' => PurchaseRequestStatus::PENDING]);
    }

    public function approved(): static
    {
        return $this->state(['status' => PurchaseRequestStatus::APPROVED]);
    }

    public function processed(): static
    {
        return $this->state(['status' => PurchaseRequestStatus::PROCESSED]);
    }

    public function rejected(): static
    {
        return $this->state(['status' => PurchaseRequestStatus::REJECTED]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => PurchaseRequestStatus::CANCELLED]);
    }

    // ─── Modificadores ────────────────────────────────────────────────────────

    public function withRequester(User $user): static
    {
        return $this->state(['requester_user_id' => $user->id]);
    }

    public function withItems(int $count = 3): static
    {
        return $this->state(['items' => $this->multipleItems($count)]);
    }

    public function withoutProvider(): static
    {
        return $this->state(['provider_name' => null]);
    }

    // ─── Helpers privados ─────────────────────────────────────────────────────

    private function defaultItems(): array
    {
        return [
            [
                'uid'         => (string) Str::uuid(),
                'item_id'     => null,
                'name'        => 'Ítem de prueba',
                'price'       => 10000,
                'quantity'    => 2,
                'description' => 'Descripción del ítem de prueba',
                'total'       => 20000,
                'image_path'  => null,
            ],
        ];
    }

    private function multipleItems(int $count): array
    {
        return array_map(fn () => [
            'uid'         => (string) Str::uuid(),
            'item_id'     => null,
            'name'        => $this->faker->words(2, true),
            'price'       => $this->faker->numberBetween(5000, 100000),
            'quantity'    => $this->faker->numberBetween(1, 10),
            'description' => $this->faker->sentence(),
            'total'       => 0,
            'image_path'  => null,
        ], range(1, $count));
    }
}
