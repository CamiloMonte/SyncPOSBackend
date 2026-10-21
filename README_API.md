# SyncPOS API

## ¿Qué es esta API?

Una API REST es un servidor que recibe peticiones HTTP y responde con JSON. Esta API es el "cerebro" de SyncPOS: la usan tanto el **panel web (React)** como la **app Android** del punto de venta. Se encarga de autenticar usuarios, administrar productos, registrar ventas, sincronizar ventas hechas sin internet y generar reportes.

Reglas importantes que aplica el servidor:

- Los precios y totales de una venta se calculan siempre en el servidor, nunca se confía en lo que envía el cliente.
- Cada línea de venta guarda una copia del precio de venta y del costo del momento, para que los reportes no cambien si después cambia el precio de compra.
- Los productos no se borran, se desactivan (así se conserva el historial de ventas).

## Puesta en marcha

1. Instalar dependencias:
   ```bash
   composer install
   ```
2. Copiar `.env.example` a `.env`, generar la clave (`php artisan key:generate`) y configurar la conexión a MySQL (`DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`).
3. Crear las tablas y los datos de ejemplo:
   ```bash
   php artisan migrate:fresh --seed
   ```
   > **ADVERTENCIA:** `migrate:fresh` **borra todas las tablas y todos los datos** de la base de datos configurada. Úsalo solo en una base de datos de desarrollo.
4. Iniciar el servidor:
   ```bash
   php artisan serve
   ```
   La API queda en `http://127.0.0.1:8000/api`.

Para ejecutar las pruebas automáticas (usan SQLite en memoria, no tocan MySQL):

```bash
php artisan test
```

## Usuarios de prueba

| Rol    | Correo            | Contraseña |
|--------|-------------------|------------|
| admin  | admin@syncpos.co  | password   |
| cajero | cajero@syncpos.co | password   |

Estas contraseñas son solo para desarrollo; cámbialas en producción.

## Autenticación

Se usa **Laravel Sanctum** con tokens. Primero se hace `POST /api/login`, y el token recibido se envía en cada petición:

```
Authorization: Bearer <token>
Accept: application/json
```

Códigos de respuesta comunes: `401` sin token o token inválido, `403` sin permiso (rol incorrecto), `422` datos inválidos.

## Endpoints

| Método | URL | Rol | Descripción |
|--------|-----|-----|-------------|
| POST | `/api/login` | público | Inicia sesión y devuelve el token |
| POST | `/api/logout` | autenticado | Cierra la sesión (elimina el token actual) |
| GET | `/api/me` | autenticado | Datos del usuario actual |
| GET | `/api/categorias` | admin, cajero | Lista de categorías |
| POST | `/api/categorias` | admin | Crear categoría |
| PUT | `/api/categorias/{id}` | admin | Editar categoría |
| GET | `/api/productos` | admin, cajero | Lista paginada (20). Filtros: `buscar`, `categoria_id`, `solo_activos=1`, `stock_bajo=1` |
| GET | `/api/productos/{id}` | admin, cajero | Detalle de un producto |
| POST | `/api/productos` | admin | Crear producto |
| PUT | `/api/productos/{id}` | admin | Editar producto |
| PATCH | `/api/productos/{id}/estado` | admin | Activar / desactivar producto |
| POST | `/api/ventas` | admin, cajero | Registrar una venta en línea |
| GET | `/api/ventas` | admin, cajero | Historial (el cajero solo ve las suyas). Filtros: `buscar`, `user_id`, `metodo_pago`, `origen`, `desde`, `hasta` |
| GET | `/api/ventas/{id}` | admin, cajero | Detalle de una venta |
| POST | `/api/sync/ventas` | admin, cajero | Sincronizar ventas hechas sin internet (máx. 100) |
| GET | `/api/mis-ventas/resumen` | admin, cajero | Resumen del día de las ventas propias (`fecha` opcional) |
| GET | `/api/reportes/rentabilidad` | admin | Ingresos, costo y ganancia por producto (`desde`, `hasta`; por defecto el mes actual) |
| GET | `/api/reportes/resumen-dia` | admin | Datos del dashboard (`fecha` opcional) |

## Ejemplos

### Login

Petición `POST /api/login`:

```json
{ "email": "cajero@syncpos.co", "password": "password" }
```

Respuesta:

```json
{
  "token": "1|abc123...",
  "user": { "id": 2, "name": "Laura Gómez", "email": "cajero@syncpos.co", "rol": "cajero" }
}
```

### Crear una venta

Petición `POST /api/ventas`. Observa que **no se envía el precio**: el servidor lo toma de la base de datos.

```json
{
  "metodo_pago": "efectivo",
  "items": [
    { "producto_id": 1, "cantidad": 2 },
    { "producto_id": 9, "cantidad": 1 }
  ]
}
```

Respuesta `201`:

```json
{
  "data": {
    "id": 15,
    "numero": "#V-15",
    "cajero": { "id": 2, "name": "Laura Gómez" },
    "metodo_pago": "efectivo",
    "origen": "online",
    "total": "31500.00",
    "vendida_en": "2026-10-19T15:20:00+00:00",
    "sincronizada_en": null,
    "items": [
      { "producto_id": 1, "nombre": "Café Sello Rojo 500 g", "cantidad": 2, "precio_unitario": "13500.00", "subtotal": "27000.00" },
      { "producto_id": 9, "nombre": "Gaseosa Postobón 1,5 L", "cantidad": 1, "precio_unitario": "4500.00", "subtotal": "4500.00" }
    ]
  }
}
```

Si no hay stock suficiente responde `422` con un mensaje como `Stock insuficiente para "Leche Alquería 1 L": disponible 4, solicitado 10.` y no se guarda nada.

### Sincronización offline

La app Android puede vender sin internet. Cada venta hecha sin conexión se guarda en el teléfono con un **`offline_referencia`**: un UUID que la app genera en ese momento. Cuando vuelve la conexión, la app envía todas las ventas pendientes en un solo lote.

¿Para qué sirve `offline_referencia`? Si la conexión se corta justo después de enviar, la app no sabe si el servidor recibió el lote y lo reenvía. El servidor reconoce el UUID, responde `duplicada` y **no** crea la venta ni descuenta el stock otra vez. A esto se le llama operación *idempotente*.

Petición `POST /api/sync/ventas`:

```json
{
  "ventas": [
    {
      "offline_referencia": "9b2f3c1e-6a41-4f7d-8c55-0d1e2f3a4b5c",
      "metodo_pago": "nequi",
      "vendida_en": "2026-10-19 10:42:00",
      "items": [{ "producto_id": 4, "cantidad": 5 }]
    }
  ]
}
```

Respuesta `200`:

```json
{
  "resultados": [
    {
      "offline_referencia": "9b2f3c1e-6a41-4f7d-8c55-0d1e2f3a4b5c",
      "estado": "sincronizada",
      "venta_id": 16,
      "stock_negativo": true,
      "mensaje": "Stock negativo en: Leche Alquería 1 L.",
      "productos_stock_negativo": ["Leche Alquería 1 L"]
    }
  ]
}
```

Estados posibles de cada venta:

- `sincronizada`: se guardó. Si `stock_negativo` es `true`, el inventario quedó por debajo de cero y el administrador debe revisarlo (la venta ya ocurrió físicamente, por eso no se rechaza).
- `duplicada`: ya se había sincronizado antes; se devuelve el `venta_id` existente y no cambia nada.
- `rechazada`: el producto no existe o está inactivo; el campo `mensaje` explica el motivo. Las demás ventas del lote se procesan normalmente.

**Cómo debe usarlo la app Android:**

1. Al vender sin internet, guardar la venta en la base local con un UUID nuevo y el estado "pendiente".
2. Al recuperar la conexión, enviar las pendientes a `/api/sync/ventas` (máximo 100 por petición).
3. Por cada resultado `sincronizada`, `duplicada` o `rechazada`, marcar la venta local como procesada (las rechazadas se muestran al usuario con su mensaje). Nunca reenviar con un UUID distinto la misma venta.
4. Si la petición falla por red, conservar las ventas pendientes y reintentar: es seguro.

## Llamar a la API desde React

```js
const API = 'http://127.0.0.1:8000/api';

// 1. Iniciar sesión y guardar el token
async function login(email, password) {
  const res = await fetch(`${API}/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ email, password }),
  });
  const datos = await res.json();
  if (!res.ok) throw new Error(datos.message);
  localStorage.setItem('token', datos.token);
  return datos.user;
}

// 2. Usar el token en las demás peticiones
async function listarProductos() {
  const res = await fetch(`${API}/productos?solo_activos=1`, {
    headers: {
      Accept: 'application/json',
      Authorization: `Bearer ${localStorage.getItem('token')}`,
    },
  });
  return res.json();
}
```

## CORS

CORS es la regla del navegador que decide si una página (por ejemplo, React en `localhost:5173`) puede llamar a otro origen (esta API en `localhost:8000`). Laravel 11 ya lo maneja por defecto para las rutas `api/*` mediante `config/cors.php`. Si necesitas cambiar los orígenes permitidos, publica el archivo con `php artisan config:publish cors` y edítalo.
