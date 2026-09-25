import axios from 'axios';

// Sincroniza catálogos entre pestañas del navegador y entre equipos distintos.
// Los caches de catálogo (grupos, subgrupos, impuestos, ítems, colores, etc.) viven en
// memoria por pestaña: si el usuario crea un registro, las demás pestañas/equipos ya
// abiertos no se enteran y siguen mostrando la lista vieja hasta que se recarga la
// página. Dos vías, combinadas:
//   1) BroadcastChannel — instantáneo, pero solo entre pestañas del MISMO navegador.
//   2) Polling a /catalog-sync — más lento (hasta POLL_INTERVAL_MS de retraso), pero
//      funciona entre equipos/usuarios distintos y no requiere WebSockets ni procesos
//      persistentes en el servidor (funciona en hosting compartido).
const CHANNEL_NAME = 'olamtex:catalog-sync';
const POLL_INTERVAL_MS = 20_000;

type Listener = () => void;

let channel: BroadcastChannel | null = null;
const listenersByKey = new Map<string, Set<Listener>>();

function ensureChannel(): BroadcastChannel | null {
    if (typeof window === 'undefined' || typeof BroadcastChannel === 'undefined') return null;
    if (channel) return channel;

    channel = new BroadcastChannel(CHANNEL_NAME);
    channel.onmessage = (event: MessageEvent<{ key?: string }>) => {
        const key = event.data?.key;
        if (!key) return;
        notifyListeners(key);
    };
    return channel;
}

function notifyListeners(key: string): void {
    listenersByKey.get(key)?.forEach((listener) => listener());
}

// --- Polling entre equipos ---------------------------------------------------

// Última marca de tiempo vista por catálogo. `undefined` = todavía no se consultó
// (para no disparar los listeners en el primer poll solo por no tener referencia previa).
const lastSeenByKey = new Map<string, string | null>();
let pollTimer: ReturnType<typeof setInterval> | null = null;
let pollInFlight = false;

async function pollOnce(): Promise<void> {
    if (pollInFlight) return;
    const keys = Array.from(listenersByKey.entries())
        .filter(([, listeners]) => listeners.size > 0)
        .map(([key]) => key);
    if (keys.length === 0) return;

    pollInFlight = true;
    try {
        const { data } = await axios.get<Record<string, string | null>>('/catalog-sync', { params: { keys: keys.join(',') } });
        for (const key of keys) {
            const serverValue = data?.[key] ?? null;
            const previousValue = lastSeenByKey.has(key) ? lastSeenByKey.get(key)! : undefined;
            lastSeenByKey.set(key, serverValue);

            if (previousValue !== undefined && serverValue !== previousValue) {
                notifyListeners(key);
            }
        }
    } catch {
        // Si falla un poll no pasa nada grave: se reintenta en el siguiente tick.
    } finally {
        pollInFlight = false;
    }
}

function ensurePolling(): void {
    if (typeof window === 'undefined' || pollTimer) return;
    pollTimer = setInterval(() => void pollOnce(), POLL_INTERVAL_MS);
    void pollOnce();
}

function stopPollingIfIdle(): void {
    const hasActiveListeners = Array.from(listenersByKey.values()).some((listeners) => listeners.size > 0);
    if (hasActiveListeners || !pollTimer) return;
    clearInterval(pollTimer);
    pollTimer = null;
}

/**
 * Avisa a las demás pestañas/equipos que el catálogo `key` cambió (se creó/editó/
 * eliminó un registro), para que invaliden su caché en memoria y refresquen lo que ya
 * tengan montado. Llamar junto a la invalidación local existente, no en su reemplazo.
 */
export function broadcastCatalogInvalidated(key: string): void {
    ensureChannel()?.postMessage({ key });
    void axios.post('/catalog-sync/touch', { key }).catch(() => {
        // El polling de otros equipos puede tardar un poco más, pero no es crítico.
    });
}

/**
 * Escucha invalidaciones del catálogo `key` desde otras pestañas (instantáneo) y desde
 * otros equipos (por polling, con hasta POLL_INTERVAL_MS de retraso). Se suele
 * registrar una sola vez a nivel de módulo (no dentro de cada instancia de
 * componente/composable) para no acumular listeners duplicados. Devuelve una función
 * para desuscribirse.
 */
export function onCatalogInvalidatedElsewhere(key: string, listener: Listener): () => void {
    ensureChannel();
    if (!listenersByKey.has(key)) listenersByKey.set(key, new Set());
    listenersByKey.get(key)!.add(listener);
    ensurePolling();

    return () => {
        listenersByKey.get(key)?.delete(listener);
        stopPollingIfIdle();
    };
}
