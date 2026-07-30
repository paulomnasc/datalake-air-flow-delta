<?php

namespace App\Models;

use CodeIgniter\Model;

class ActivityLogModel extends Model
{
    protected $table = 'activity_logs';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'user_id',
        'method',
        'uri',
        'controller',
        'action',
        'route_alias',
        'ip_address',
        'user_agent',
        'session_id',
        'created_at',
    ];

    protected $useTimestamps = false;
}
