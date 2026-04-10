/**
 * Composable: useFlatSketch
 *
 * Encapsula toda la lógica del dibujo plano (flat sketch): preview, carga,
 * limpieza, resolución de URLs y validación de tipo MIME.
 */
import { computed, ref } from 'vue';

const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

export function useFlatSketch() {
    const flatSketchFile = ref<File | null>(null);
    const flatSketchPreviewUrl = ref<string | null>(null);
    const flatSketchLoadFailed = ref(false);

    // ── URLs ──────────────────────────────────────────────────────────────────

    function resolveImageUrl(rawPath?: string | null): string | null {
        const path = typeof rawPath === 'string' ? rawPath.trim() : '';
        if (!path) return null;
        if (/^https?:\/\//i.test(path)) return path;
        if (path.startsWith('/storage-file/')) return path;
        if (path.startsWith('/')) return `/storage-file/${path.replace(/^\/+(?:storage\/)?/, '')}`;
        return `/storage-file/${path.replace(/^\/?storage\//, '')}`;
    }

    function createResolvedUrl(flatSketchPath: string) {
        return computed(() => resolveImageUrl(flatSketchPath));
    }

    function createVisualUrl(flatSketchPath: string) {
        return computed(() => {
            if (flatSketchPreviewUrl.value) return flatSketchPreviewUrl.value;
            if (flatSketchLoadFailed.value) return null;
            return resolveImageUrl(flatSketchPath);
        });
    }

    const canClearFlatSketch = (flatSketchPath: string) =>
        computed(() => Boolean(flatSketchFile.value || flatSketchPath));

    // ── Acciones ──────────────────────────────────────────────────────────────

    function revokeFlatSketchPreview(): void {
        if (flatSketchPreviewUrl.value) {
            URL.revokeObjectURL(flatSketchPreviewUrl.value);
            flatSketchPreviewUrl.value = null;
        }
    }

    function resetFlatSketchSelection(): void {
        revokeFlatSketchPreview();
        flatSketchFile.value = null;
        flatSketchLoadFailed.value = false;
    }

    function handleFlatSketchImageError(): void {
        if (flatSketchPreviewUrl.value) return;
        flatSketchLoadFailed.value = true;
    }

    /**
     * Valida y carga el archivo seleccionado con file input.
     * @returns null si éxito, string con mensaje de error si falla.
     */
    function handleFileChange(event: Event): string | null {
        const input = event.target as HTMLInputElement;
        const file = input?.files?.[0] ?? null;

        if (file && !ALLOWED_MIME_TYPES.includes(file.type)) {
            if (input) input.value = '';
            return 'Formato de imagen no válido. Solo se permiten JPG, PNG o WEBP.';
        }

        revokeFlatSketchPreview();
        flatSketchLoadFailed.value = false;
        flatSketchFile.value = file;

        if (file) {
            flatSketchPreviewUrl.value = URL.createObjectURL(file);
        }

        return null;
    }

    function openFlatSketchPreview(event: MouseEvent, visualUrl: string | null): void {
        event.stopPropagation();
        if (!visualUrl) return;
        if (typeof window !== 'undefined') {
            window.open(visualUrl, '_blank', 'noopener');
        }
    }

    // ── Upload ────────────────────────────────────────────────────────────────

    function inferImageExtension(mimeType: string): string {
        const n = mimeType.toLowerCase();
        if (n.includes('png')) return 'png';
        if (n.includes('webp')) return 'webp';
        return 'jpg';
    }

    async function resolveFlatSketchFileForUpload(): Promise<File | null> {
        if (flatSketchFile.value instanceof File) return flatSketchFile.value;

        const previewUrl = flatSketchPreviewUrl.value ?? '';
        if (!previewUrl.startsWith('blob:')) return null;

        try {
            const response = await fetch(previewUrl);
            const blob = await response.blob();
            if (!blob || !blob.type.startsWith('image/')) return null;
            return new File([blob], `flat-sketch.${inferImageExtension(blob.type)}`, { type: blob.type });
        } catch (error) {
            console.error('No se pudo convertir preview blob a File:', error);
            return null;
        }
    }

    function isValidMimeType(type: string): boolean {
        return ALLOWED_MIME_TYPES.includes(type);
    }

    return {
        // Estado
        flatSketchFile,
        flatSketchPreviewUrl,
        flatSketchLoadFailed,

        // URLs
        createResolvedUrl,
        createVisualUrl,
        canClearFlatSketch,

        // Acciones
        resetFlatSketchSelection,
        handleFlatSketchImageError,
        handleFileChange,
        openFlatSketchPreview,

        // Upload
        resolveFlatSketchFileForUpload,
        isValidMimeType,
    };
}
