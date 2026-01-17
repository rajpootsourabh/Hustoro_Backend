<?php
// app/Models/StageDocument.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StageDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_stage_id', 
        'document_id', 
        'is_required', 
        'document_order'
    ];

    public function companyStage()
    {
        return $this->belongsTo(CompanyStage::class);
    }

    public function document()
    {
        return $this->belongsTo(Document::class);
    }
}