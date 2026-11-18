# Vending Machine

## REST API

Set `JWT_SECRET` in `.env` to a randomly generated value of at least 32 bytes before using token login or protected API routes. Generate one with:

```sh
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Obtain a Bearer token with `POST /api/auth/login` using an `application/json` body:

```json
{"email":"user@example.com","password":"your-password"}
```

Send the returned `access_token` in the `Authorization: Bearer <token>` header for protected routes. API JSON write requests must use the `application/json` content type.
Tokens are audience-bound to `vending-machine-api`; protected requests also check the account's current database role, so deleted accounts are rejected and role changes take effect immediately. Log in again after deploying this token format change.

| Method | Endpoint | Access |
| --- | --- | --- |
| `POST` | `/api/auth/login` | Public |
| `GET` | `/api/products` | Public; supports `page`, `per_page`, `sort_by`, and `sort_direction` |
| `GET` | `/api/products/{id}` | Public |
| `POST` | `/api/products` | Admin |
| `PUT` | `/api/products/{id}` | Admin |
| `DELETE` | `/api/products/{id}` | Admin |
| `POST` | `/api/products/{id}/purchase` | User |
| `GET` | `/api/transactions` | User sees their own history; admin sees all transactions |

Product create and update bodies require `name`, `price`, and `quantity_available`; send `price` as a decimal string to preserve its exact value:

```json
{"name":"Water","price":"1.250","quantity_available":8}
```

Purchase bodies require a positive integer `quantity`. Only normal user accounts can purchase products; admins manage products and inventory. Product deletion returns `409 Conflict` when transaction history references the product.

## Web authentication

The HTML sign-in and registration forms are available at `/auth/login` and `/auth/register`. The shared navigation includes a CSRF-protected sign-out button at `/auth/logout`. The existing `/login` and `/register` endpoints remain JSON endpoints.
