<?php

namespace App\Models\Expenses;

use App\Enums\PurchaseRequestStatus;
use App\Models\Settings\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class PurchaseRequest extends Model implements \App\Contracts\HasAuditLabel
{
    use HasFactory, SoftDeletes;

    public static function auditLabel(): string
    {
        return 'Solicitud de compra';
    }

    protected $fillable = [
        'request_number',
        'provider_name',
        'requester_user_id',
        'date',
        'destination',
        'estimated_amount',
        'description',
        'items',
        'document_path',
        'status',
        'approved_by_user_id',
        'approved_at',
        'rejected_by_user_id',
        'rejected_at',
        'rejection_reason',
        'processed_at',
        'processed_by_user_id',
    ];

    protected $casts = [
        'date' => 'date',
        'estimated_amount' => 'decimal:2',
        'items' => 'array',
        'status' => PurchaseRequestStatus::class,
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    /**
     * Prepare the model for array/JSON serialization.
     * Convierte el enum status a string para la serialización JSON.
     */
    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function toArray()
    {
        // Obtener el array base
        $array = parent::toArray();
        
        // Convertir enum status a string si existe
        // Esto es necesario porque parent::toArray() puede devolver el enum directamente
        if (isset($array['status'])) {
            if ($array['status'] instanceof PurchaseRequestStatus) {
                $array['status'] = $array['status']->value;
            } elseif (!is_string($array['status']) && $array['status'] !== null) {
                // Si no es string ni null, intentar convertirlo
                $array['status'] = (string) $array['status'];
            }
        }
        
        // También verificar el atributo directamente por si acaso
        if (isset($this->status) && $this->status instanceof PurchaseRequestStatus) {
            $array['status'] = $this->status->value;
        }
        
        return $array;
    }

    /**
     * Convert the model instance to an array for JSON serialization.
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Cast an attribute to a native PHP type.
     * Maneja valores antiguos como 'processed' que ahora es 'approved'.
     */
    protected function castAttribute($key, $value)
    {
        return parent::castAttribute($key, $value);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by_user_id');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by_user_id');
    }

    public function orders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\Expenses\PurchaseDocument::class, 'purchase_request_id');
    }

    // Métodos de estado
    public function isDraft(): bool
    {
        return $this->status === PurchaseRequestStatus::DRAFT;
    }

    public function isPending(): bool
    {
        return $this->status === PurchaseRequestStatus::PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === PurchaseRequestStatus::APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === PurchaseRequestStatus::REJECTED;
    }

    public function isCancelled(): bool
    {
        return $this->status === PurchaseRequestStatus::CANCELLED;
    }

    public function isProcessed(): bool
    {
        return $this->status === PurchaseRequestStatus::PROCESSED;
    }

    // Métodos de validación
    public function canEdit(): bool
    {
        return $this->status->canEdit();
    }

    public function canApprove(): bool
    {
        return $this->status->canApprove();
    }

    public function canReject(): bool
    {
        return $this->status->canReject();
    }

    public function canSendToApproval(): bool
    {
        return $this->status->canSendToApproval();
    }

    public function canCancel(): bool
    {
        return $this->status->canCancel();
    }

    // Métodos de transición de estado
    public function sendToApproval(?int $userId = null): void
    {
        if (!$this->canSendToApproval()) {
            throw new \RuntimeException('No se puede enviar a aprobación desde el estado actual.');
        }

        $this->update([
            'status' => PurchaseRequestStatus::PENDING->value, // Convertir enum a string
        ]);
    }

    public function approve(int $userId, ?string $reason = null): void
    {
        if (!$this->canApprove()) {
            throw new \RuntimeException('No se puede aprobar desde el estado actual.');
        }

        $this->update([
            'status' => PurchaseRequestStatus::APPROVED->value, // Convertir enum a string
            'approved_by_user_id' => $userId,
            'approved_at' => now(),
        ]);
    }

    public function reject(int $userId, string $reason): void
    {
        if (!$this->canReject()) {
            throw new \RuntimeException('No se puede rechazar desde el estado actual.');
        }

        $this->update([
            'status' => PurchaseRequestStatus::REJECTED->value, // Convertir enum a string
            'rejected_by_user_id' => $userId,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }

    public function cancel(?int $userId = null): void
    {
        if (!$this->canCancel()) {
            throw new \RuntimeException('No se puede cancelar desde el estado actual.');
        }

        $this->update([
            'status' => PurchaseRequestStatus::CANCELLED->value, // Convertir enum a string
        ]);
    }


    public function markAsProcessed(?int $userId = null): void
    {
        if (!$this->isApproved()) {
            throw new \RuntimeException('Solo se pueden procesar solicitudes que estén aprobadas.');
        }

        $this->update([
            'status' => PurchaseRequestStatus::PROCESSED->value,
            'processed_at' => now(),
            'processed_by_user_id' => $userId ?? (Auth::check() ? Auth::id() : null),
        ]);
    }
}
