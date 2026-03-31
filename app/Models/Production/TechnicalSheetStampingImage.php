<?php

namespace App\Models\Production;

use Illuminate\Database\Eloquent\Model;

/**
 * Imágenes de referencia de una sección de estampado/lavandería en la ficha técnica.
 *
 * Almacena las dos imágenes editables del diálogo StampingLaundryDialog:
 *   - stamping_image: imagen del estampado/bordado (arte, muestra fotográfica).
 *   - drawing_image:  dibujo plano técnico del estampado/bordado.
 *
 * Un registro único por (technical_sheet_id, section).
 *
 * @property int         $id
 * @property int         $technical_sheet_id
 * @property string      $section              stamping|laundry
 * @property string|null $stamping_image_name
 * @property string|null $stamping_image_data  data-URL base64
 * @property string|null $drawing_image_name
 * @property string|null $drawing_image_data   data-URL base64
 */
class TechnicalSheetStampingImage extends Model
{
    protected $table = 'technical_sheet_stamping_images';

    protected $fillable = [
        'technical_sheet_id',
        'section',
        'stamping_image_name',
        'stamping_image_data',
        'drawing_image_name',
        'drawing_image_data',
    ];

    public const SECTION_STAMPING = 'stamping';
    public const SECTION_LAUNDRY  = 'laundry';

    // ─── Relaciones ───────────────────────────────────────

    public function technicalSheet()
    {
        return $this->belongsTo(TechnicalSheet::class);
    }

    // ─── Helpers ──────────────────────────────────────────

    public function hasStampingImage(): bool
    {
        return !empty($this->stamping_image_data);
    }

    public function hasDrawingImage(): bool
    {
        return !empty($this->drawing_image_data);
    }
}
