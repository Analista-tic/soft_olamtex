/**
 * Helper para imprimir el contenido de un elemento por id usando un iframe temporal.
 * Proceso:
 *  - verifica existencia y contenido del elemento
 *  - genera un HTML minimal vinculando los estilos y estilos inline
 *  - crea un iframe con `srcdoc`, espera a cargar y ejecuta `print()` dentro del iframe
 *  - limpia el iframe tras `afterprint` o con un timeout
 */
export type PrintOptions = {
    title?: string;
    styles?: string;
};

export async function printElement(elementOrId: HTMLElement | string, options?: PrintOptions): Promise<void> {
    try {
        const el: HTMLElement | null = typeof elementOrId === 'string' ? document.getElementById(elementOrId) : elementOrId;
        console.debug('[print] element found', !!el);
        if (!el) {
            console.error('[print] element not found', elementOrId);
            return;
        }

        const contentHtml = el.outerHTML;
        if (!contentHtml || contentHtml.trim().length === 0) {
            console.error('[print] element has empty content');
            return;
        }

        // Collect same-origin stylesheet links and inline styles to include in iframe
        const head = document.head;
        const links: string[] = [];
        const inlineStyles: string[] = [];

        Array.from(head.querySelectorAll('link[rel="stylesheet"]')).forEach((link) => {
            const href = (link as HTMLLinkElement).href;
            try {
                const url = new URL(href, location.href);
                if (url.origin === location.origin) links.push(href);
            } catch {
                links.push(href);
            }
        });

        Array.from(head.querySelectorAll('style')).forEach((s) => {
            if (s.textContent) inlineStyles.push(s.textContent);
        });

        // Minimal print CSS to ensure document is visible and A4 sized
        const defaultPrintStyles = `
            @page { size: A4 portrait; margin: 12mm; }
            html, body { height: auto; }
            body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, Arial; color: #111; background: #fff; }
            table { border-collapse: collapse; width: 100%; }
            th, td { border: 1px solid #ddd; padding: 6px; }
            img { max-width: 100%; height: auto; }
        `;

        const headHtmlParts: string[] = [];
        headHtmlParts.push('<meta charset="utf-8">');
        headHtmlParts.push('<meta name="viewport" content="width=device-width,initial-scale=1">');
        links.forEach((href) => headHtmlParts.push(`<link rel="stylesheet" href="${href}">`));
        if (inlineStyles.length) headHtmlParts.push(`<style>${inlineStyles.join('\n')}</style>`);
        headHtmlParts.push(`<style>${defaultPrintStyles}</style>`);
        if (options?.styles) headHtmlParts.push(`<style>${options.styles}</style>`);

        const docHtml = `<!doctype html><html><head>${headHtmlParts.join('\n')}</head><body>${contentHtml}</body></html>`;

        const iframe = document.createElement('iframe');
        iframe.style.position = 'fixed';
        iframe.style.right = '0';
        iframe.style.bottom = '0';
        iframe.style.width = '0px';
        iframe.style.height = '0px';
        iframe.style.border = '0';
        iframe.style.overflow = 'hidden';
        iframe.setAttribute('aria-hidden', 'true');
        (iframe as HTMLIFrameElement).srcdoc = docHtml;

        const cleanup = () => {
            try {
                if (iframe && iframe.parentNode) iframe.parentNode.removeChild(iframe);
            } catch {
                // ignore
            }
        };

        return new Promise<void>((resolve) => {
            let printed = false;

            const onAfterPrint = () => {
                if (printed) return;
                printed = true;
                try {
                    if (iframe.contentWindow) iframe.contentWindow.removeEventListener('afterprint', onAfterPrint);
                } catch {}
                cleanup();
                resolve();
            };

            iframe.onload = () => {
                try {
                    const cw = iframe.contentWindow as Window | null;
                    if (!cw) {
                        setTimeout(() => {
                            try { (iframe.contentWindow || window).print(); } catch (e) { console.error('[print] fallback print failed', e); }
                        }, 500);
                        return;
                    }

                    cw.addEventListener('afterprint', onAfterPrint);

                    try {
                        cw.focus();
                        setTimeout(() => {
                            try {
                                cw.print();
                                setTimeout(() => {
                                    if (!printed) {
                                            printed = true;
                                            try { cw.removeEventListener('afterprint', onAfterPrint); } catch {}
                                            cleanup();
                                            resolve();
                                        }
                                }, 2500);
                            } catch (err) {
                                console.error('[print] error calling print on iframe', err);
                                cleanup();
                                resolve();
                            }
                        }, 120);
                    } catch (err) {
                        console.error('[print] error focusing/printing iframe', err);
                        cleanup();
                        resolve();
                    }
                } catch (err) {
                    console.error('[print] iframe onload handler error', err);
                    cleanup();
                    resolve();
                }
            };

            document.body.appendChild(iframe);

            setTimeout(() => {
                if (!printed && (!iframe.contentWindow || iframe.contentDocument?.readyState !== 'complete')) {
                    try { window.print(); } catch (e) { console.error('[print] fallback window.print failed', e); }
                }
            }, 3000);
        });
    } catch (e) {
        console.error('[print] unexpected error', e);
    }
}

export default printElement;
