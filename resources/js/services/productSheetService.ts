/**
 * Re-export of ProductSheet API from Production domain.
 * Kept for backward compatibility. Prefer importing from @/domain/Production.
 */
export {
    convertSampleToProduct,
    createProductSheet,
    deleteProductSheet,
    listProductSheets,
    showProductSheet,
    updateProductSheet,
} from '@/domain/Production/service';
