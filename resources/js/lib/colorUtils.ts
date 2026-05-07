/**
 * Convierte un color hexadecimal (#RRGGBB) a su representación
 * RGB en formato "R,G,B" (sin espacios, sin "rgb()").
 * Retorna null si el valor no es un hex válido de 6 dígitos.
 */
export function hexToRgb(hex: string): string | null {
    const n = hex.trim().replace('#', '');
    if (!/^[0-9a-fA-F]{6}$/.test(n)) return null;
    return `${parseInt(n.slice(0, 2), 16)},${parseInt(n.slice(2, 4), 16)},${parseInt(n.slice(4, 6), 16)}`;
}
