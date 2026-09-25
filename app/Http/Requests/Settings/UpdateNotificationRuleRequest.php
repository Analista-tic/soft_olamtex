<?php

namespace App\Http\Requests\Settings;

use App\Models\Settings\NotificationEventRecipient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNotificationRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasRole('superadmin');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'channel_database' => ['required', 'boolean'],
            'channel_mail' => ['required', 'boolean'],
            'exclude_actor' => ['required', 'boolean'],
            'recipients' => ['present', 'array', 'max:50'],
            'recipients.*.type' => ['required', 'string', Rule::in([
                NotificationEventRecipient::TYPE_ROLE,
                NotificationEventRecipient::TYPE_AREA,
                NotificationEventRecipient::TYPE_USER,
                NotificationEventRecipient::TYPE_DYNAMIC,
            ])],
            'recipients.*.value' => ['required', 'string', 'max:100'],
            'recipients.*.permission_filter' => ['nullable', 'string', 'max:150'],
        ];
    }

    public function messages(): array
    {
        return [
            'recipients.*.type.in' => 'El tipo de destinatario no es válido.',
            'recipients.*.value.required' => 'Falta elegir el destinatario.',
        ];
    }
}
