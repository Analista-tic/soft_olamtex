<?php

namespace App\Listeners;

use App\Services\Audit\AuditLogService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\Dispatcher;

/**
 * Registra en la auditoría los eventos de autenticación (inicio/cierre de sesión,
 * intentos fallidos, bloqueos y restablecimiento de contraseña).
 */
class AuditAuthenticationEvents
{
    public function __construct(
        protected AuditLogService $logger
    ) {}

    public function handleLogin(Login $event): void
    {
        $this->logForUser('login', 'Inicio de sesión', $event->user);
    }

    public function handleLogout(Logout $event): void
    {
        $this->logForUser('logout', 'Cierre de sesión', $event->user);
    }

    public function handleFailed(Failed $event): void
    {
        $email = (string) ($event->credentials['email'] ?? '');

        $this->logger->logEvent(
            action: 'login_failed',
            description: "Intento de inicio de sesión fallido para '{$email}'.",
            subject: $event->user instanceof Model ? $event->user : null,
            new: ['email' => $email],
            module: 'Settings',
            context: array_merge($this->logger->currentContext(), ['user' => null]),
        );
    }

    public function handleLockout(Lockout $event): void
    {
        $email = (string) $event->request->input('email', '');

        $this->logger->logEvent(
            action: 'lockout',
            description: "Acceso bloqueado temporalmente por demasiados intentos para '{$email}'.",
            new: ['email' => $email],
            module: 'Settings',
            context: array_merge($this->logger->currentContext(), ['user' => null]),
        );
    }

    public function handlePasswordReset(PasswordReset $event): void
    {
        $this->logForUser('password_reset', 'Restablecimiento de contraseña', $event->user);
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'handleLogin',
            Logout::class => 'handleLogout',
            Failed::class => 'handleFailed',
            Lockout::class => 'handleLockout',
            PasswordReset::class => 'handlePasswordReset',
        ];
    }

    protected function logForUser(string $action, string $label, mixed $user): void
    {
        if (!$user instanceof Model) {
            return;
        }

        $name = $user->getAttribute('name') ?? $user->getAttribute('email');

        $this->logger->logEvent(
            action: $action,
            description: "{$label} de {$name}.",
            subject: $user,
            module: 'Settings',
            context: array_merge($this->logger->currentContext(), ['user' => $user]),
        );
    }
}
