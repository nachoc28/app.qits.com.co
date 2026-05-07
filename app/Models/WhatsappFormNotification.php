<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappFormNotification extends Model
{
    protected $table = 'whatsapp_form_notifications';

    protected $fillable = [
        'empresa_id',
        'form_forwarding_rule_id',
        'source_system',
        'source_record_id',
        'status',
        'destination_phone',
        'whatsapp_message_id',
        'raw_payload_json',
        'normalized_payload_json',
        'message_payload_json',
        'provider_response_json',
        'failure_reason',
        'attempts',
        'last_attempt_at',
        'queued_at',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_at',
        'expired_at',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'form_forwarding_rule_id' => 'integer',
        'raw_payload_json' => 'array',
        'normalized_payload_json' => 'array',
        'message_payload_json' => 'array',
        'provider_response_json' => 'array',
        'attempts' => 'integer',
        'last_attempt_at' => 'datetime',
        'queued_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'failed_at' => 'datetime',
        'expired_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function publicLink()
    {
        return $this->hasOne(FormNotificationPublicLink::class, 'whatsapp_form_notification_id');
    }
}
