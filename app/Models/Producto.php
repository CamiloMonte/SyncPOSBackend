<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Producto extends Model
{
    use HasFactory;

    protected $fillable = [
        'codigo_barras',
        'nombre',
        'categoria_id',
        'precio_compra',
        'precio_venta',
        'stock',
        'stock_minimo',
        'activo',
    ];

    // Hace que "stock_bajo" aparezca siempre al convertir el modelo a JSON.
    protected $appends = ['stock_bajo'];

    // casts: convierte los valores de la base de datos a tipos de PHP.
    protected function casts(): array
    {
        return [
            'precio_compra' => 'decimal:2',
            'precio_venta' => 'decimal:2',
            'stock' => 'integer',
            'stock_minimo' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleVenta::class);
    }

    // Accessor: atributo calculado $producto->stock_bajo.
    public function getStockBajoAttribute(): bool
    {
        return $this->stock <= $this->stock_minimo;
    }
}
