<?php

namespace App\Services;

use App\Models\DetalleVenta;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Servicio: clase que concentra una regla de negocio.
 *
 * La creación de una venta vive en UN solo lugar porque la usan dos caminos:
 * la venta en línea (VentaController) y la sincronización offline (SyncVentaController).
 * Así ambos aplican exactamente las mismas reglas (precios desde la base de datos,
 * total calculado en el servidor, descuento de stock).
 */
class VentaService
{
    /**
     * Crea la venta con sus líneas y descuenta el stock.
     * IMPORTANTE: debe llamarse dentro de DB::transaction() para que, si algo falla,
     * no quede una venta a medias.
     *
     * @param  array<int, array{producto_id: int, cantidad: int}>  $items
     *
     * @throws ValidationException si un producto no existe, está inactivo o (venta online) no hay stock.
     */
    public function crearVenta(
        User $usuario,
        string $metodoPago,
        array $items,
        string $origen = 'online',
        ?Carbon $vendidaEn = null,
        ?string $offlineReferencia = null,
        bool $permitirStockNegativo = false,
    ): Venta {
        $items = $this->unirDuplicados($items);

        // lockForUpdate(): bloquea las filas de los productos hasta terminar la transacción,
        // así dos ventas simultáneas no leen el mismo stock y lo descuentan mal.
        $productos = Producto::whereIn('id', array_keys($items))
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        // Primero se valida todo; solo después se escribe en la base de datos.
        foreach ($items as $productoId => $cantidad) {
            $producto = $productos->get($productoId);

            if (! $producto) {
                $this->fallar("El producto con id {$productoId} no existe.");
            }

            if (! $producto->activo) {
                $this->fallar("El producto \"{$producto->nombre}\" está inactivo.");
            }

            // Una venta offline ya ocurrió físicamente: no se rechaza por falta de stock.
            if (! $permitirStockNegativo && $producto->stock < $cantidad) {
                $this->fallar("Stock insuficiente para \"{$producto->nombre}\": disponible {$producto->stock}, solicitado {$cantidad}.");
            }
        }

        $venta = Venta::create([
            'user_id' => $usuario->id,
            'total' => 0, // se actualiza al final con la suma de los subtotales
            'metodo_pago' => $metodoPago,
            'origen' => $origen,
            'offline_referencia' => $offlineReferencia,
            'vendida_en' => $vendidaEn ?? now(),
            'sincronizada_en' => $origen === 'offline' ? now() : null,
        ]);

        $total = 0;

        foreach ($items as $productoId => $cantidad) {
            $producto = $productos->get($productoId);

            // Los precios salen SIEMPRE de la base de datos, nunca del cliente.
            $subtotal = round((float) $producto->precio_venta * $cantidad, 2);
            $total += $subtotal;

            DetalleVenta::create([
                'venta_id' => $venta->id,
                'producto_id' => $producto->id,
                'cantidad' => $cantidad,
                'precio_unitario' => $producto->precio_venta,
                'costo_unitario' => $producto->precio_compra, // instantánea del costo
                'subtotal' => $subtotal,
            ]);

            $producto->decrement('stock', $cantidad);
        }

        $venta->update(['total' => round($total, 2)]);

        return $venta;
    }

    // Si el mismo producto viene en dos líneas, se suman sus cantidades.
    private function unirDuplicados(array $items): array
    {
        $unidos = [];

        foreach ($items as $item) {
            $id = (int) $item['producto_id'];
            $unidos[$id] = ($unidos[$id] ?? 0) + (int) $item['cantidad'];
        }

        return $unidos;
    }

    private function fallar(string $mensaje): never
    {
        throw ValidationException::withMessages(['items' => $mensaje]);
    }
}
