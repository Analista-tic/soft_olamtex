import { ref } from 'vue';

type AlertSeverity = 'success' | 'error' | 'info' | 'warning';

export interface AlertMessagesConfig {
    entityLabel?: string;
    entityArticle?: string;
    successActions?: Record<string, string>;
    defaultSuccessMessage?: string;
    defaultErrorMessage?: string;
    warningMatcher?: RegExp;
}

export function useAlertMessages(config: AlertMessagesConfig = {}) {
    const successMessage = ref('');
    const errorMessage = ref('');
    const errorType = ref<AlertSeverity>('error');

    const buildEntityLabel = () =>
        [config.entityArticle?.trim(), config.entityLabel?.trim()].filter(Boolean).join(' ').trim();

    const showSuccess = (actionKey?: string, customMessage?: string) => {
        if (customMessage) {
            successMessage.value = customMessage;
            return;
        }

        if (actionKey) {
            const template = config.successActions?.[actionKey];
            if (template) {
                const entity = buildEntityLabel();
                successMessage.value = entity ? `${entity} ${template}` : template;
                return;
            }
        }

        successMessage.value = config.defaultSuccessMessage ?? 'Operacion exitosa.';
    };

    const clearSuccess = () => {
        successMessage.value = '';
    };

    const showError = (message?: string, severity?: AlertSeverity) => {
        const text = message || config.defaultErrorMessage || 'Ocurrio un error.';
        const matcher = config.warningMatcher ?? /obligatori|complet[ao]s? los campos/i;
        const type = severity ?? (text && matcher.test(text) ? 'warning' : 'error');

        errorType.value = type;
        errorMessage.value = text;
    };

    const showWarning = (message?: string) => {
        showError(message, 'warning');
    };

    const clearError = () => {
        errorMessage.value = '';
    };

    const clearAll = () => {
        clearSuccess();
        clearError();
    };

    return {
        successMessage,
        errorMessage,
        errorType,
        showSuccess,
        showError,
        showWarning,
        clearSuccess,
        clearError,
        clearAll,
    };
}
