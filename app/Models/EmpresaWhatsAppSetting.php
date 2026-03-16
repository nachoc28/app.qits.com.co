<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmpresaWhatsAppSetting extends Model
{
    protected $table = 'empresa_whatsapp_settings';

    protected $fillable = [
        'empresa_id',
        'whatsapp_business_phone',
        'whatsapp_phone_number_id',
        'whatsapp_access_token',
        'whatsapp_verify_token',
        'destination_phone',
        'send_text_enabled',
        'send_pdf_enabled',
        'save_attachments',
        'is_active',
    ];

    /**
     * whatsapp_access_token se cifra en reposo con APP_KEY (requiere Laravel >= 8.12).
     * Si el APP_KEY cambia, los tokens existentes quedarán ilegibles.
     */
    protected $casts = [
        'send_text_enabled' => 'boolean',
        'send_pdf_enabled'  => 'boolean',
        'save_attachments'  => 'boolean',
        'is_active'         => 'boolean',
        'whatsapp_access_token' => 'encrypted',
    ];

    protected $hidden = [
        'whatsapp_access_token',
        'whatsapp_verify_token',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }
}
