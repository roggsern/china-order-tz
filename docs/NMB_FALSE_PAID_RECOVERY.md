# NMB false-paid recovery

Use after a payment was incorrectly marked Successful/Paid while MPGS was still
pending authentication (e.g. `gatewayCode=PENDING`, `AUTHENTICATION_PENDING`,
zero authorized/captured amounts).

## Command

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec -T api \
  php artisan payments:revert-false-nmb-paid \
  --payment-transaction=<PAYMENT_TRANSACTION_UUID> \
  --force \
  --confirm=REVERT_FALSE_PAID_NMB
```

Or by order:

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec -T api \
  php artisan payments:revert-false-nmb-paid \
  --order=<ORDER_UUID> \
  --force \
  --confirm=REVERT_FALSE_PAID_NMB
```

## Effects

- `payment_transactions.status`: Successful → Processing (`completed_at` cleared)
- `orders.status`: Paid → `pending_payment` (`paid_at` cleared) when applicable
- Appends a **new** `order_status_history` row explaining the recovery
- Does **not** delete prior history/activity logs

## After recovery

1. Customer retries Hosted Checkout (fresh session endpoint).
2. Confirm MPGS shows CAPTURED/AUTHORIZED with sufficient amounts before treating as paid.
3. Take a fresh DB backup after correction.
