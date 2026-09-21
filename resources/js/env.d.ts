// Ambient typings for Vite import.meta

interface ImportMetaEnv {
    readonly VITE_APP_NAME: string;
    [key: string]: string | boolean | undefined;
}

// eslint-disable-next-line @typescript-eslint/no-unused-vars -- declaración ambiental de tipos
interface ImportMeta {
    readonly env: ImportMetaEnv;
    readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
}

export {};
