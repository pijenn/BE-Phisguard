<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MlResult extends Model
{
    protected $fillable = [
    'report_id',
    'label',
    'risk_score',
    'priority',
    'reason'
];

   public function report()
{
    return $this->belongsTo(Report::class);
}
}
