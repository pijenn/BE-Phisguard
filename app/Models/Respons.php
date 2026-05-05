<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Respons extends Model
{
    protected $table = 'respons';
    
    protected $fillable = [
        'report_id',
        'hasil_keputusan',
        'kategori',
        'catatan',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function report()
    {
        return $this->belongsTo(Report::class);
    }
}
