<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venta extends Model
{
    protected $fillable = [
        'user_id',
        'total',
        'metodo_pago',
        'origen',
        'offline_referencia',
        'vendida_en',
        'sincronizada_en',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'vendida_en' => 'datetime',
            'sincronizada_en' => 'datetime',
        ];
    }

    // El cajero que realizó la venta.
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleVenta::class);
    }
}
