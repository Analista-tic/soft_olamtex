<?php

namespace Database\Factories\Expenses;

use App\Enums\PurchaseOrderStatus;
use App\Models\Expenses\PurchaseDocument;
use App\Models\MasterData\Contact;
use App\Models\Settings\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory para PurchaseDocument (Órdenes de Compra).
 *
 * Siempre crea documentos con document_type = 'purchase_order'.
 *
 * Uso:
 *   PurchaseDocument::factory()->create();
 *   PurchaseDocument::factory()->approved()->create();
 *   PurchaseDocument::factory()->forContact($contact)->create();
 */
class PurchaseDocumentFactory extends Factory
{
    protected $model = PurchaseDocument::class;

    public function definition(): array
    {
        return [
            'document_type'   => 'purchase_order',
            'document_number' => 'OC-TEST-' . strtoupper(Str::random(6)),
            'contact_id'      => null,
            'provider_name'   => $this->faker->company(),
            'date'            => now()->toDateString(),
            'delivery_date'   => now()->addDays(7)->toDateString(),
            'description'     => $this->faker->sentence(),
            'status'          => PurchaseOrderStatus::PENDING,
            'items'           => $this->defaultItems(),
        ];
    }

    // ─── Estados de negocio ───────────────────────────────────────────────────

    public function pending(): static
    {
        return $this->state(['status' => PurchaseOrderStatus::PENDING]);
    }

    public function approved(): static
    {
        return $this->state(['status' => PurchaseOrderStatus::APPROVED]);
    }

    public function sent(): static
    {
        return $this->state(['status' => PurchaseOrderStatus::SENT]);
    }

    public function invoiced(): static
    {
        return $this->state(['status' => PurchaseOrderStatus::INVOICED]);
    }

    public function rejected(): static
    {
        return $this->state(['status' => PurchaseOrderStatus::REJECTED]);
    }

    // ─── Modificadores ────────────────────────────────────────────────────────

    public function forContact(Contact $contact): static
    {
        return $this->state([
            'contact_id'    => $contact->id,
            'provider_name' => $contact->full_name,
        ]);
    }

    public function createdBy(User $user): static
    {
        return $this->state(['created_by_user_id' => $user->id]);
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
            ],
        ];
    }
}
