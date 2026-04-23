# Refactor Frontend: Vue 3 + TypeScript con Bounded Contexts

**Status:** 🔵 Planificación (Sin Implementar)  
**Fecha:** 30 de diciembre de 2025  
**Stack:** Laravel 12 + Vue 3 + TypeScript + Pinia

---

## 📋 Requerimiento

Mejorar la arquitectura frontend de Soft Olamtex (Vue 3 + TypeScript) implementando **Bounded Contexts** (inspirado en DDD) para:
- 🎯 Separar tipos por contexto de negocio (Auth, Inventory, Purchases, Fabric, ProductSheet)
- 📦 Introducir patrón **DTO → Mapper → UI Model** para desacoplar API ↔ UI
- 💪 Lograr 100% type-safe (eliminar `any`)
- 🚀 Facilitar mantenimiento y escalabilidad
- 📖 Mejorar documentación y patrones

---

## 🔍 Estado Actual

**Problemas:**
- ❌ Archivo monolítico: `resources/js/types/index.d.ts` (457 líneas)
- ❌ Sin separación por contextos
- ❌ Sin DTOs diferenciados
- ❌ Tipado débil (solo `auth.ts` parcialmente)
- ❌ Alto acoplamiento entre módulos

---

## ✨ Propuesta: Bounded Contexts

### Estructura Propuesta

```
resources/js/domain/
├── auth/
│   ├── index.ts              # Barrel export
│   └── types.ts              # User, Role, Permission, AuthState
├── shared/
│   ├── index.ts
│   └── types.ts              # Pagination, Option, BreadcrumbItem, CompanyInfo, AuditLog
├── inventory/
│   ├── index.ts
│   ├── types.ts              # Item, Warehouse, Tax, Category*, AdditionalField
│   ├── dto.ts                # ItemDto, WarehouseDto (respuestas API)
│   └── mappers.ts            # mapItemDto(), mapWarehouseDto()
├── purchases/
│   ├── index.ts
│   ├── types.ts              # PurchaseRequest, PurchaseOrder, PurchaseInvoice, Contact
│   ├── dto.ts
│   └── mappers.ts
├── fabric/
│   ├── index.ts
│   ├── types.ts              # FabricRoll, FabricOrder, FabricOrderItem
│   ├── dto.ts
│   └── mappers.ts
└── product-sheet/
    ├── index.ts
    ├── types.ts              # ProductSheet, Programmer
    ├── dto.ts
    └── mappers.ts

stores/
├── auth.ts                   # import { User, AuthState } from '@/domain/auth'
├── inventory.ts              # import { Item } from '@/domain/inventory'
├── expenses.ts               # import { PurchaseOrder } from '@/domain/purchases'
└── fabric.ts                 # import { FabricRoll } from '@/domain/fabric'

pages/
└── [componentes con <script setup lang="ts">]
```

### Patrón Clave: DTO → Mapper → UI Model

**Problema:** Si el backend cambia `warehouse_id` → `warehouseId`, rompe toda la UI.

**Solución:** Separar en capas:

```typescript
// 1. DTO (respuesta bruta del API, snake_case)
export interface ItemDto {
    id: number;
    warehouse_id: number;      // ← Exactamente como viene
    stock_min: number;
    extra_fields?: string;
}

// 2. Mapper (transformación, en store)
export function mapItemDto(dto: ItemDto): Item {
    return {
        id: dto.id,
        warehouseId: dto.warehouse_id,  // ← Convertir a camelCase
        stockMin: dto.stock_min,
        extraFields: typeof dto.extra_fields === 'string' 
            ? JSON.parse(dto.extra_fields) 
            : dto.extra_fields,
    };
}

// 3. UI Model (camelCase normalizado)
export interface Item {
    id: number;
    warehouseId: number;       // ← Usar en componentes
    stockMin: number;
    extraFields?: Record<string, any>;
}
```

**Beneficio:** Si backend cambia → solo actualizar DTO + mapper, el resto del código sigue igual ✅

### Barrel Exports

```typescript
// domain/inventory/index.ts
export type {
    Item,
    Warehouse,
    Tax,
} from './types';

export type { ItemDto, WarehouseDto } from './dto';

export { mapItemDto, mapItemsDto } from './mappers';
```

**Uso:** `import { Item, mapItemDto } from '@/domain/inventory'` (limpio)

---

## 📦 Ejemplos Prácticos

### Ejemplo 1: Auth (Simple)

```typescript
// domain/auth/types.ts
export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
}

export interface Role {
    id: number;
    name: string;
}

export interface AuthState {
    user: User | null;
    roles: string[];
    permissions: string[];
}

// domain/auth/index.ts
export type { User, Role, AuthState };
```

```typescript
// stores/auth.ts
import type { AuthState, User } from '@/domain/auth';

export const useAuthStore = defineStore('auth', {
    state: (): AuthState => ({
        user: null,
        roles: [],
        permissions: [],
    }),
    
    actions: {
        async fetchUser(): Promise<void> {
            const { data } = await axios.get('/user');
            this.user = data.user;
            this.roles = data.roles;
            this.permissions = data.permissions;
        },
    },
});
```

```vue
<!-- Componente -->
<script setup lang="ts">
import { useAuthStore } from '@/stores/auth';
import type { User } from '@/domain/auth';

const authStore = useAuthStore();

const handleLogout = async (): Promise<void> => {
    await axios.post('/logout');
    authStore.$reset();
};
</script>
```

### Ejemplo 2: Inventory (Con DTO + Mapper)

```typescript
// domain/inventory/dto.ts
export interface ItemDto {
    id: number;
    name: string;
    code: string;
    warehouse_id: number;
    stock_min: number;
    stock_max: number;
    extra_fields?: string;
    created_at: string;
    updated_at: string;
}

// domain/inventory/types.ts
export interface Item {
    id: number;
    name: string;
    code: string;
    warehouseId: number;
    stockMin: number;
    stockMax: number;
    extraFields?: Record<string, any>;
    createdAt: string;
    updatedAt: string;
}

// domain/inventory/mappers.ts
export function mapItemDto(dto: ItemDto): Item {
    return {
        id: dto.id,
        name: dto.name,
        code: dto.code,
        warehouseId: dto.warehouse_id,
        stockMin: dto.stock_min,
        stockMax: dto.stock_max,
        extraFields: typeof dto.extra_fields === 'string' 
            ? JSON.parse(dto.extra_fields) 
            : dto.extra_fields,
        createdAt: dto.created_at,
        updatedAt: dto.updated_at,
    };
}

export function mapItemsDto(dtos: ItemDto[]): Item[] {
    return dtos.map(mapItemDto);
}

// domain/inventory/index.ts
export type { Item } from './types';
export type { ItemDto } from './dto';
export { mapItemDto, mapItemsDto } from './mappers';
```

```typescript
// stores/inventory.ts
import type { Item } from '@/domain/inventory';
import { mapItemsDto } from '@/domain/inventory';

interface InventoryState {
    items: Item[];
    loading: boolean;
}

export const useInventoryStore = defineStore('inventory', {
    state: (): InventoryState => ({
        items: [],
        loading: false,
    }),
    
    actions: {
        async fetchItems(): Promise<void> {
            this.loading = true;
            try {
                const { data } = await axios.get('/inventory/items');
                this.items = mapItemsDto(data.data);  // ← Mapear aquí
            } finally {
                this.loading = false;
            }
        },
    },
});
```

```vue
<!-- pages/Inventory/Item/Index.vue -->
<script setup lang="ts">
import { useInventoryStore } from '@/stores/inventory/inventory';
import type { Item } from '@/domain/inventory';

const inventoryStore = useInventoryStore();
const items = computed<Item[]>(() => inventoryStore.items);

const handleDelete = async (item: Item): Promise<void> => {
    await axios.delete(`/items/${item.id}`);
    await inventoryStore.fetchItems();
};
</script>
```

### Ejemplo 3: Composable Tipado

```typescript
// composables/usePurchaseForm.ts
import type { PurchaseOrder } from '@/domain/purchases';

interface UsePurchaseFormOptions {
    onSuccess?: (order: PurchaseOrder) => Promise<void>;
}

export function usePurchaseForm(options: UsePurchaseFormOptions = {}) {
    const form = ref<PurchaseOrder>({
        contactId: null,
        date: new Date().toISOString().split('T')[0],
        deliveryDate: new Date().toISOString().split('T')[0],
        supplierOrderNumber: '',
        items: [],
    });

    const submit = async (): Promise<void> => {
        const { data } = await axios.post('/orders', form.value);
        if (options.onSuccess) {
            await options.onSuccess(mapPurchaseOrderDto(data));
        }
    };

    return { form: computed(() => form.value), submit };
}
```

---

## 📋 Plan de Implementación (6 Fases)

| Fase | Tarea | Tiempo | Status |
|------|-------|--------|--------|
| 1 | Crear estructura `domain/` + barrel exports | 30 min | ⏳ |
| 2 | Refactor Auth (POC para validar patrón) | 1-2h | ⏳ |
| 3 | Refactor Inventory (DTOs + mappers) | 2-3h | ⏳ |
| 4 | Refactor Expenses/Purchases (polimórfico) | 3-4h | ⏳ |
| 5 | Refactor Fabric & ProductSheet | 1-2h | ⏳ |
| 6 | Cleanup: eliminar `index.d.ts` monolítico | 30 min | ⏳ |
| **Total** | - | **8-12h** | ⏳ |

**Características:**
- ✅ Cada fase es independiente
- ✅ Puede validarse por separado
- ✅ Sin breaking changes

---

## ✅ Validación por Fase

### Fase 1: Estructura Base
- [ ] Carpeta `domain/` creada
- [ ] 6 subcarpetas: auth, shared, inventory, purchases, fabric, product-sheet
- [ ] Cada una con: `index.ts`, `types.ts`, `dto.ts` (si aplica), `mappers.ts` (si aplica)
- [ ] Barrel exports funcionales
- [ ] `tsconfig.json` con aliases `@/domain/*`
- [ ] Sin breaking changes

### Fase 2: Auth
- [ ] `domain/auth/types.ts` con User, Role, Permission, AuthState
- [ ] `domain/auth/index.ts` con barrel export
- [ ] `stores/auth.ts` refactorizado con `state: (): AuthState`
- [ ] Componentes actualizados (>=2)
- [ ] `npm run lint` sin errores

### Fase 3: Inventory
- [ ] `domain/inventory/types.ts` (Item, Warehouse, Tax, etc.)
- [ ] `domain/inventory/dto.ts` (ItemDto, WarehouseDto)
- [ ] `domain/inventory/mappers.ts` (mapItemDto, mapItemsDto)
- [ ] `stores/inventory.ts` nuevo con `mapItemsDto(data.data)`
- [ ] Componentes actualizados (>=2)
- [ ] `npm run lint` sin errores

### Fase 4: Purchases
- [ ] `domain/purchases/types.ts` (PurchaseRequest, PurchaseOrder, PurchaseInvoice)
- [ ] DTOs + mappers
- [ ] `stores/expenses.ts` nuevo
- [ ] Componentes actualizados (>=2)
- [ ] `npm run lint` sin errores

### Fase 5: Fabric & ProductSheet
- [ ] Similar a Inventory y Purchases
- [ ] Menos crítico

### Fase 6: Cleanup
- [ ] Eliminar/reemplazar `resources/js/types/index.d.ts`
- [ ] Todos los imports desde `@/domain/*`
- [ ] `npm run build` exitoso
- [ ] Tests de regresión pasados

---

## 🎯 Reglas de Oro

✅ **HACER:**
- Imports desde domain: `import type { Item } from '@/domain/inventory'`
- Mappers en stores, NUNCA en componentes
- State tipado: `state: (): StateInterface => ({...})`
- Return types explícitos: `const handleClick = (): void => {...}`
- `<script setup lang="ts">` en componentes

❌ **NO HACER:**
- `any` en código propio
- Omitir tipado en componentes
- Imports directo desde API (mapear en store)
- DTOs fuera de mappers
- Cambios sin validar `npm run lint`

---

## 📊 Beneficios Esperados

| Métrica | Antes | Después | Mejora |
|---------|-------|---------|--------|
| Archivos de tipos | 1 (457 líneas) | 6 contextos (~50-100 líneas c/u) | -81% |
| Acoplamiento | Alto | Bajo | ↓ Modular |
| Mantenimiento | Difícil | Fácil (DTO+mapper) | ✅ |
| Escalabilidad | Tediosa | Rápida (nueva carpeta) | ✅ |
| Type Safety | Parcial (any) | 100% | ✅ |
| DX (IDE hints) | Débil | Preciso | ✅ |

---

## ⚠️ Riesgos Identificados

| Riesgo | Mitigation |
|--------|-----------|
| Quebrante de componentes | Refactorizar por fases |
| Aliases en tsconfig.json | Validar en IDE antes |
| Mappers incompletos | Code review |
| DTOs desfasados | Documentar cambios API |

---

## 🚀 Próximos Pasos

1. ✅ Revisión de estructura actual
2. ✅ Análisis y planificación
3. ⏳ **APROBACIÓN** para iniciar Fase 1
4. ⏳ Implementación iterativa (fases 1-6)
5. ⏳ Validación final

---

## 📚 Documentación

- **Copilot Instructions:** Sección "Frontend: Vue 3 + TypeScript Typing Strategy" en [.github/copilot-instructions.md](.github/copilot-instructions.md)
- **Este documento:** Resumen completo del requerimiento y propuesta

---

**Estado:** 🔵 Planificación - Aguardando aprobación para implementación  
**Estimado:** 8-12 horas de desarrollo (6 fases)
