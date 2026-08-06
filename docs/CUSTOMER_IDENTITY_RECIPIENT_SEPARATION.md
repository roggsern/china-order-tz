# Customer identity vs delivery recipient

## Domain rule

Customer account identity and shipping recipient are separate:

| Concern | Storage | Who may change it |
|---------|---------|-------------------|
| Account identity | `users.first_name`, `users.last_name`, `users.name` | Registration or explicit `PATCH /api/v1/profile` with name fields |
| Delivery recipient | `user_addresses.recipient_name`, `delivery_addresses.recipient_name`, order `shipping_addresses` snapshot | Address book / checkout address selection |

Checkout must never overwrite account identity from a recipient name.

## Identifying previously affected customers

No automated recovery command is shipped (prelaunch customer/order data was cleaned).

If historical rows remain, candidates for **manual** review match:

```sql
SELECT id, email, first_name, last_name, name, updated_at
FROM users
WHERE first_name IS NOT NULL
  AND last_name IS NOT NULL
  AND first_name = last_name
  AND name = CONCAT(first_name, ' ', last_name);
```

Example: `first_name = 'Robert'`, `last_name = 'Robert'`, `name = 'Robert Robert'`.

**Do not** auto-correct from `user_addresses.recipient_name` or `delivery_addresses.recipient_name` — those may be a different person (e.g. Mama Asha) or already polluted.

Restore from backup, support ticket, or customer confirmation only.

## Checkout behaviour (current)

1. Address selection maps shipping fields and optional phone only — not first/last name.
2. `runBackendCheckoutFlow` may sync **phone only** to `/api/profile`.
3. Selected address is set as default so `delivery_addresses.recipient_name` feeds the order shipping snapshot.
4. Payment is not involved in identity protection; identity is simply never written from checkout names.
