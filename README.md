# SyncPOS — Backend (API REST con Laravel)

API de **SyncPOS**, un sistema de punto de venta con control de inventario, registro de ventas **sin conexión** (desde la app Android) y análisis de rentabilidad. Proyecto SENA.

Esta API la usan dos clientes:

| Cliente | Repositorio |
|---|---|
| Panel web (React) | [CamiloMonte/SyncPOSWeb](https://github.com/CamiloMonte/SyncPOSWeb) |
| App Android del cajero | Proyecto Android (Kotlin + Jetpack Compose) |

**Tecnologías:** Laravel 11 · PHP 8.2 · MySQL (XAMPP) · Laravel Sanctum (tokens) · PHPUnit.

La documentación completa de los endpoints (con ejemplos JSON) está en **[README_API.md](README_API.md)**.

---

## Instalación en otro computador

### 1. Programas necesarios (una sola vez)

| Programa | Para qué | Versión |
|---|---|---|
| **Git** | Descargar el proyecto | Cualquiera |
| **XAMPP** | PHP y MySQL | Con **PHP 8.2 o superior** |
| **Composer** | Instalar las librerías de Laravel | La última |

### 2. Base de datos

1. Abre **XAMPP** y enciende **Apache** y **MySQL**.
2. Entra a **phpMyAdmin** (`http://localhost/phpmyadmin`) y crea una base de datos vacía llamada **`syncpos`**.

### 3. Descargar y configurar

En **Git Bash**:

```bash
cd /c/xampp/htdocs
git clone https://github.com/CamiloMonte/SyncPOSBackend.git SyncPOS
cd SyncPOS
composer install
cp .env.example .env
php artisan key:generate
```

Abre el archivo **`.env`** con un editor y deja así las líneas de la base de datos (si empiezan con `#`, quítalo: Laravel 11 viene configurado para SQLite):

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=syncpos
DB_USERNAME=root
DB_PASSWORD=
APP_TIMEZONE=America/Bogota
```

> `APP_TIMEZONE=America/Bogota` hace que "las ventas de hoy" se cuenten con la hora de Colombia. Sin esa línea, después de las 7 p. m. las ventas aparecen como del día siguiente.

### 4. Crear las tablas y encender la API

```bash
php artisan migrate --seed   # crea las tablas y los datos de ejemplo
php artisan serve            # deja la API en http://127.0.0.1:8000 (no cierres esta terminal)
```

Para comprobar que funciona, abre `http://127.0.0.1:8000/api/estado` en el navegador: debe responder `"estado": "ok"`.

> ⚠️ `php artisan migrate:fresh --seed` **borra todos los datos** y vuelve a crear la base desde cero. Úsalo solo en desarrollo.

### Usuarios de prueba

| Rol | Correo | Contraseña |
|---|---|---|
| Administrador | `admin@syncpos.co` | `password` |
| Cajero | `cajero@syncpos.co` | `password` |

Son solo para desarrollo: cambia las contraseñas desde el panel web (menú **Usuarios**) antes de usar el sistema de verdad.

### Errores comunes

| Error | Solución |
|---|---|
| `php` o `composer` "no se reconoce como comando" | Agrega `C:\xampp\php` al PATH de Windows o usa la consola **Shell** de XAMPP |
| `SQLSTATE[HY000] [1049] Unknown database 'syncpos'` | Falta crear la base de datos `syncpos` en phpMyAdmin |
| `SQLSTATE[HY000] [2002]` (no se puede conectar) | MySQL no está encendido en XAMPP |
| `No application encryption key has been specified` | Falta `php artisan key:generate` |

### Uso diario (después de instalarlo)

1. XAMPP con **MySQL** encendido.
2. En la carpeta del proyecto: `php artisan serve`.

---

## Pruebas automáticas

```bash
php artisan test
```

Usan una base SQLite en memoria (no tocan tu MySQL).

## Estructura principal

```
routes/api.php            todas las rutas de la API (agrupadas por módulo y rol)
app/Http/Controllers/     un controlador por módulo (Auth, Producto, Venta, SyncVenta, Reporte, User...)
app/Http/Requests/        validaciones de cada formulario
app/Http/Resources/       forma del JSON que devuelve la API
app/Services/VentaService la regla de "crear una venta" (venta en línea y sincronización offline)
app/Http/Middleware/      EnsureRole: bloquea rutas según el rol (403)
database/migrations/      tablas
database/seeders/         roles, usuarios y productos de ejemplo
tests/Feature/            pruebas de cada módulo
```
