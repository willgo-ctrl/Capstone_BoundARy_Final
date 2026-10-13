<?php

namespace App\Models;

use Database\Factories\PropertyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'name',
    'parcel_reference',
    'survey_plan_reference',
    'tie_latitude',
    'tie_longitude',
    'courses',
    'boundary',
    'closure_error_meters',
    'ocr_text',
    'survey_document',
    'document_name',
    'reference_boundary',
    'reference_engineer_name',
    'reference_engineer_license',
    'reference_deviation_meters',
])]
class Property extends Model
{
    /** @use HasFactory<PropertyFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'tie_latitude' => 'float',
            'tie_longitude' => 'float',
            'courses' => 'array',
            'boundary' => 'array',
            'closure_error_meters' => 'float',
            'reference_boundary' => 'array',
            'reference_deviation_meters' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
