# Server status pill + user management

## Objective
1. Make the web "Servidor Conectado (MySQL)" pill reflect the real server and database state.
2. Let the admin manage user accounts (cashiers and admins) from the web panel.

## Why
- The pill was a static label: it said "connected" even with Laravel stopped (the spec asks for a status indicator).
- Users only existed through the seeder; the admin could not create cashier accounts.

## Scope (authorized by the user on 2026-10-26: "arregla la pildora y haz la gestion de usuarios")
- Laravel: `GET /api/estado` (public health check with a real DB query); admin-only users endpoints (list, show, create, update, toggle active).
- Web (`C:\Users\monte\OneDrive\Desktop\syncpos-web`, no git): live pill in the Header; new admin page `/usuarios`; cashier filter back in Historial using the users list.
- Out of scope: Android user management, hard delete of users.

## Decisions
- No hard delete: users with sales are deactivated (sales FK is restrictOnDelete), same as products.
- An admin cannot deactivate their own account or remove their own admin role (avoid locking everyone out).
- Deactivating a user or changing their password revokes all their Sanctum tokens.
- Roles are sent as `rol: "admin" | "cajero"` (mapped to role_id on the server).

## Tasks
- [x] U1 `GET /api/estado` + tests (delegated writer) (commit 673b254)
- [x] U2 Users API: UserController, FormRequests, UserResource, routes, tests (delegated writer) (commit 6f35727)
- [x] U3 Web: live status pill in Header (delegated writer)
- [x] U4 Web: `/usuarios` page (list, search, create/edit modal, toggle) + Historial cashier filter (delegated writer)
- [x] U5 Parent verification: php artisan test, npm build/lint, browser E2E (pill online/offline, create cashier, login as new cashier, deactivate -> session revoked)

## Checks
- TDD: off. Runners: `php artisan test` (SQLite memory), `npm run build` + `npm run lint`.
- Backend repo: one Conventional Commit per backend task, authored by the repo-local identity (Camilo Monte). No push without the user asking.

## Progress
- 2026-10-26: Plan created.
- 2026-10-26: U1-U4 done by delegated writer. Observed: `php artisan test` 44 passed (195 assertions); pint clean; route:list shows /api/estado and 5 /api/usuarios routes; web `npm run build` and `npm run lint` pass. Comment accent check clean in new lines. U5 (browser E2E) pending with parent.
- 2026-10-26: U5 parent verification. Re-ran `php artisan test` -> 44 passed; web build OK. Browser E2E against the API on a temporary SQLite DB: pill "Servidor Conectado (MySQL)" -> stopped Laravel -> "Sin conexion con el servidor" after 20 s without reload -> restarted -> back to connected after 16 s. /usuarios listed 3 users with ventas_count (9 + 10 = 19 sales); own toggle disabled; create with duplicate email -> server 422 "Ya existe un usuario con ese correo."; short password -> 422; valid cashier created; new cashier logged in (/me 200); admin deactivated her with confirm -> her open token /me 401 and re-login 403 "Usuario inactivo."; PATCH own estado -> 422 "No puedes desactivar tu propia cuenta."; Historial cashier filter (Carlos) -> 9 sales, all his. Not pushed (waiting for the user).
