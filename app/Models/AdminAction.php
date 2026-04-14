<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminAction extends Model
{
    protected $fillable = [
    'report_id',
    'action',
    'priority',
    'sla'
];
    public function report()
{
    return $this->belongsTo(Report::class);
}
}
