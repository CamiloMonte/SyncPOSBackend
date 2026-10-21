# SyncPOS API (Laravel 11)

## Objective
Turn the Laravel project into the single REST API (modular monolith) consumed by the React web panel and the Android POS app.

## Problem / Why
- Two user tables (`users` and `usuarios`); `ventas.usuario_id` points to `users`, which has no role.
- `onDelete('cascade')` on financial data deletes sales history.
- `detalle_ventas` does not snapshot unit cost, so profit reports would change when purchase prices change.
- Ambiguous / mixed-language column names.
- No API routes, auth or controllers yet.

## Scope (authorized by the user on 2026-10-19)
- Fix migrations, models, seeders.
- Sanctum token auth + role middleware (admin / cajero).
- Endpoints: auth, categorías, productos, ventas (online), sync de ventas offline, historial, reportes, resumen del cajero.
- Feature tests on SQLite in memory. Spanish README for the API.

## Constraints
- Never run migrations or seeders against the user's MySQL; never read `.env`.
- Beginner-friendly code: Spanish comments, standard Laravel conventions (FormRequest, API Resources, Eloquent relations).
- Laravel `users` table keeps Laravel column names (`name`, `email`, `password`); every other column in Spanish.

## Decisions
- Single `users` table with `role_id` + `activo`; `usuarios` table removed.
- `restrictOnDelete()` on catalog/user FKs; deactivate instead of delete. `detalle_ventas.venta_id` cascades (lines belong to their sale).
- Offline sales already happened physically: sync accepts them even if stock drops below zero (`stock` is signed) and flags `stock_negativo` so the admin reviews inventory. Duplicates detected by `offline_referencia` (UUID).
- Totals and snapshots (`precio_unitario`, `costo_unitario`) are always computed on the server, never trusted from the client.

## Tasks
- [x] T1 Migrations, models, relations, seeders (route: delegated writer — 2+ non-trivial files)
- [x] T2 `install:api` (Sanctum), login/logout/me, role middleware (delegated writer)
- [x] T3 Categorías + productos endpoints with validation and Resources (delegated writer)
- [x] T4 Ventas online: transaction, stock decrement, snapshots; historial + detalle (delegated writer)
- [x] T5 Sync offline batch, idempotent (delegated writer)
- [x] T6 Reportes: rentabilidad, resumen del día, resumen del cajero (delegated writer)
- [x] T7 Feature tests for T2–T6 + `README_API.md` (delegated writer)

## Acceptance criteria
- `php artisan test` passes on SQLite in memory.
- `php artisan route:list --path=api` lists every endpoint.
- Re-sending the same offline sale does not duplicate it nor decrement stock twice.

## Checks
- TDD: off (no project configuration found). Runner: `php artisan test` (PHPUnit).
- No git repository in this folder: work-unit commits are pending until the user initializes git.

## Progress
- 2026-10-19: Exploration done, plan created.
- 2026-10-19: T1-T7 implemented by one delegated writer. Observed: `php artisan test` -> 30 passed (113 assertions) on SQLite memory; `pint --test` passed; `route:list --path=api` shows 18 routes. Migrations/seeders NOT run against MySQL (user runs `migrate:fresh --seed`).

- 2026-10-19: Parent spot check. Re-ran `php artisan test` -> 30 passed. Migrations + seeders verified on a temporary SQLite file (2 users with roles, 10 products, 4 low stock), file deleted. Reviewed VentaService + SyncVentaController. Fix: concurrent retry of the same offline sale raised UniqueConstraintViolationException (500, whole batch lost); now reported as "duplicada". Tests + pint pass after the fix. No test covers the concurrent case (hard to reproduce in SQLite memory).

- 2026-10-20: `POST /sync/ventas` accepts optional `ventas.*.origen` (`online`|`offline`, default `offline`) so the Android app can mark sales sent right after registering as online. 2 new tests in `SyncTest`; `php artisan test` -> 32 passed; pint passed. Verified end to end from the Android emulator.

## Next step
User runs `php artisan migrate:fresh --seed` on a dev MySQL database and tries the endpoints; then connect the React app (replace mockData with fetch calls).
