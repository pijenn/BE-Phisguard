<?php

namespace App\Models;
use App\Models\MlResult;
use App\Models\AdminAction;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
protected $fillable = [
    'ticket',
    'channel_chat',
    'sender_account',
    'chat_text',
    'url',
    'reporter_name',
    'region',
    'modus_type',
    'evidence_text',
    'user_segment',
    'incident_summary',
];

public static function generateTicket()
{
    $today = now()->format('Ymd');

    $last = self::whereDate('created_at', now())
        ->orderBy('id', 'desc')
        ->first();

    $number = $last ? ((int) substr($last->ticket, -4)) + 1 : 1;

    return 'PH-' . $today . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);
}

public function mlResult()
{
    return $this->hasOne(MlResult::class);
}

public function adminActions()
{
    return $this->hasMany(AdminAction::class);
}
}
