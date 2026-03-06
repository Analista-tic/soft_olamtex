import { computed, ref } from 'vue';

export type AlertType = 'success' | 'error' | 'info' | 'warning';

/**
 * Composable Global para manejar alertas de forma consistente
 * Proporciona una interfaz simple para mostrar alertas en la parte superior central de la pantalla
 *
 * @example
 * ```typescript
 * const { successMessage, errorMessage, errorType, success, error, info, warning, clear } = useGlobalAlert();
 *
 * // En el template
 * <AlertStack
 *   :success-message="successMessage"
 *   :error-message="errorMessage"
 *   :error-type="errorType"
 *   @close-success="clear"
 *   @close-error="clear"
 * />
 *
 * // En el script
 * try {
 *   await api.create(data);
 *   success('Guardado correctamente');
 * } catch (err) {
 *   error('Error al guardar');
 * }
 * ```
 */
export function useGlobalAlert() {
    const successMessage = ref('');
    const errorMessage = ref('');
    const errorType = ref<AlertType>('error');

    /**
     * Muestra un mensaje de éxito
     * Auto-cierra después de 4 segundos
     */
    function success(message: string, title?: string) {
        successMessage.value = message;
        errorMessage.value = '';

        // Auto-cerrar después de 4 segundos
        setTimeout(() => {
            if (successMessage.value === message) {
                successMessage.value = '';
            }
        }, 4000);
    }

    /**
     * Muestra un mensaje de error, advertencia o información
     * Si el tipo es error, se mantiene visible hasta que se cierre manualmente
     */
    function showAlert(message: string, type: AlertType = 'error', title?: string) {
        errorMessage.value = message;
        errorType.value = type;
        successMessage.value = '';

        // Auto-cerrar solo si no es error
        if (type !== 'error') {
            setTimeout(() => {
                if (errorMessage.value === message) {
                    errorMessage.value = '';
                }
            }, 4000);
        }
    }

    /**
     * Muestra un mensaje de error (no auto-cierra)
     */
    function error(message: string, title?: string) {
        showAlert(message, 'error', title);
    }

    /**
     * Muestra un mensaje informativo (auto-cierra)
     */
    function info(message: string, title?: string) {
        showAlert(message, 'info', title);
    }

    /**
     * Muestra un mensaje de advertencia (auto-cierra)
     */
    function warning(message: string, title?: string) {
        showAlert(message, 'warning', title);
    }

    /**
     * Limpia todos los mensajes
     */
    function clear() {
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
        success,
        error,
        info,
        warning,
        showAlert,

        // Métodos para limpiar mensajes
        clear,
        clearSuccess,
        clearError,
    };
}
