import { computed, ref, type Ref } from 'vue';

export type AlertType = 'success' | 'error' | 'info' | 'warning';

export interface Alert {
    id: number;
    type: AlertType;
    title?: string;
    message: string;
    autoCloseMs?: number;
}

let alertIdCounter = 0;

// Estado global para las alertas (singleton)
const alerts = ref<Alert[]>([]);

export function useAlert() {
    const pageContentRef: Ref<HTMLElement | null> = ref(null);

    /**
     * Muestra una alerta
     * @param message - Mensaje de la alerta
     * @param type - Tipo de alerta (success, error, info, warning)
     * @param title - Título opcional de la alerta
     * @param autoCloseMs - Tiempo en ms para cerrar automáticamente (default: 4000, 0 = no auto-cerrar)
     */
    function showAlert(message: string, type: AlertType = 'info', title?: string, autoCloseMs: number = 4000): number {
        const id = ++alertIdCounter;

        alerts.value.push({
            id,
            type,
            title,
            message,
            autoCloseMs,
        });

        // Auto-scroll al inicio de la página
        if (pageContentRef.value && typeof pageContentRef.value.scrollTo === 'function') {
            pageContentRef.value.scrollTo({ top: 0, behavior: 'smooth' });
        } else {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // Auto-cerrar si está configurado
        if (autoCloseMs > 0) {
            setTimeout(() => {
                removeAlert(id);
            }, autoCloseMs);
        }

        return id;
    }

    /**
     * Remueve una alerta por ID
     */
    function removeAlert(id: number) {
        const index = alerts.value.findIndex((a) => a.id === id);
        if (index !== -1) {
            alerts.value.splice(index, 1);
        }
    }

    /**
     * Limpia todas las alertas
     */
    function clearAllAlerts() {
        alerts.value = [];
    }

    // Métodos de conveniencia
    function success(message: string, title?: string, autoCloseMs?: number) {
        return showAlert(message, 'success', title || 'Éxito', autoCloseMs);
    }

    function error(message: string, title?: string, autoCloseMs?: number) {
        return showAlert(message, 'error', title || 'Error', autoCloseMs);
    }

    function info(message: string, title?: string, autoCloseMs?: number) {
        return showAlert(message, 'info', title || 'Información', autoCloseMs);
    }

    function warning(message: string, title?: string, autoCloseMs?: number) {
        return showAlert(message, 'warning', title || 'Advertencia', autoCloseMs);
    }

    return {
        // Estado
        alerts,
        pageContentRef,

        // Métodos
        showAlert,
        removeAlert,
        clearAllAlerts,

        // Métodos de conveniencia
        success,
        error,
        info,
        warning,
    };
}

/**
 * Composable simplificado para usar con AlertStack.vue
 * Compatible con el patrón successMessage/errorMessage usado en formularios
 *
 * @example
 * ```typescript
 * const { successMessage, errorMessage, errorType, showSuccess, showError, clearAlerts } = useAlertStack();
 *
 * // En el template
 * <AlertStack
 *   :success-message="successMessage"
 *   :error-message="errorMessage"
 *   :error-type="errorType"
 *   @close-success="clearSuccess"
 *   @close-error="clearError"
 * />
 * ```
 */
export function useAlertStack() {
    const successMessage = ref('');
    const errorMessage = ref('');
    const errorType = ref<AlertType>('error');

    /**
     * Muestra un mensaje de éxito
     */
    function showSuccess(message: string) {
        successMessage.value = message;
        errorMessage.value = '';
    }

    /**
     * Muestra un mensaje de error, advertencia o información
     */
    function showError(message: string, type: AlertType = 'error') {
        errorMessage.value = message;
        errorType.value = type;
        successMessage.value = '';
    }

    /**
     * Muestra un mensaje informativo
     */
    function showInfo(message: string) {
        showError(message, 'info');
    }

    /**
     * Muestra un mensaje de advertencia
     */
    function showWarning(message: string) {
        showError(message, 'warning');
    }

    /**
     * Limpia todos los mensajes
     */
    function clearAlerts() {
        successMessage.value = '';
        errorMessage.value = '';
    }

    /**
     * Limpia solo el mensaje de éxito
     */
    function clearSuccess() {
        successMessage.value = '';
    }

    /**
     * Limpia solo el mensaje de error
     */
    function clearError() {
        errorMessage.value = '';
    }

    /**
     * Indica si hay algún mensaje activo
     */
    const hasAlerts = computed(() => Boolean(successMessage.value || errorMessage.value));

    return {
        // Estado reactivo
        successMessage,
        errorMessage,
        errorType,
        hasAlerts,

        // Métodos para mostrar mensajes
        showSuccess,
        showError,
        showInfo,
        showWarning,

        // Métodos para limpiar mensajes
        clearAlerts,
        clearSuccess,
        clearError,
    };
}

