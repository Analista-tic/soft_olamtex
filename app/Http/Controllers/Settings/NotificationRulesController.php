<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateNotificationRuleRequest;
use App\Services\Settings\NotificationRuleService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Configuración de quién, cómo y cuándo se notifica. Solo superadmin (ver rutas). */
class NotificationRulesController extends Controller
{
    public function __construct(protected NotificationRuleService $service) {}

    public function index(): Response
    {
        return Inertia::render('Configuration/NotificationRules/Index', [
            'events' => $this->service->list(),
            'options' => $this->service->options(),
        ]);
    }

    public function update(UpdateNotificationRuleRequest $request, string $eventKey): JsonResponse
    {
        $this->service->update($eventKey, $request->validated());

        $event = collect($this->service->list())->firstWhere('key', $eventKey);

        return response()->json([
            'message' => 'Regla de notificación guardada correctamente.',
            'data' => $event,
        ]);
    }
}
