# Partial Receipt (Split Payment) Functionality - Implementation Summary

## Overview
This implementation adds partial receipt functionality to the PDC_SYSTEM, allowing split payments across multiple transactions per table while tracking paid quantities and generating sequential receipt numbers.

## Database Changes

### 1. Migration Scripts

**Primary Migration:** `scripts/migrate_partial_receipts.php`
**Dependencies:** `scripts/migrate_order_items.php` (ensures order_items table exists)

Run the complete migration suite:
```bash
# Option 1: Run all migrations
php scripts/migrate_all.php

# Option 2: Run individually (ensure order_items migration runs first)
php scripts/migrate_order_items.php
php scripts/migrate_partial_receipts.php
```

**Note**: The `migrate_order_items.php` script must run before partial receipts migration to ensure the `order_items` table exists with all required columns. Use `migrate_all.php` for automatic execution in correct order.

### 2. Schema Changes

#### Counters Table (New)
```sql
CREATE TABLE counters (
    name VARCHAR(50) PRIMARY KEY,
    current_value BIGINT NOT NULL
);
INSERT IGNORE INTO counters (name, current_value) VALUES ('receipt', 0);
```

#### Completed Payments Table (Enhanced)
```sql
ALTER TABLE completed_payments
    ADD COLUMN receipt_number BIGINT UNIQUE AFTER id,
    ADD COLUMN employee_name VARCHAR(100) NULL AFTER payment_method,
    ADD COLUMN items_json MEDIUMTEXT NULL AFTER employee_name,
    ADD COLUMN printed_at TIMESTAMP NULL AFTER paid_at,
    ADD COLUMN reprint_count INT NOT NULL DEFAULT 0 AFTER printed_at;
```

#### Order Items Table (Enhanced)
```sql
ALTER TABLE order_items 
    ADD COLUMN paid_quantity INT NOT NULL DEFAULT 0 AFTER quantity;
```

#### Performance Indexes
```sql
CREATE INDEX idx_order_items_paid ON order_items (order_id, paid_quantity);
CREATE INDEX idx_completed_payments_table ON completed_payments (table_number);
```

## API Endpoints

### 1. GET Bill Information
**Endpoint:** `GET /pizza/api/restaurant-api.php?action=get-bill&table={tableNumber}`

**Response:**
```json
{
    "success": true,
    "data": {
        "table_number": 5,
        "items": [
            {
                "id": 123,
                "src": "pizza",
                "name": "Margherita",
                "unit_price": 250.00,
                "qty_total": 2,
                "qty_paid": 1,
                "qty_outstanding": 1
            }
        ],
        "summary": [
            {
                "name": "Margherita",
                "unit_price": 250.00,
                "qty_total": 2,
                "qty_paid": 1,
                "qty_outstanding": 1,
                "total_outstanding_amount": 250.00
            }
        ],
        "prior_receipts": [
            {
                "receipt_number": 1001,
                "total_amount": 250.00,
                "paid_at": "2025-01-15 14:30:00",
                "printed": true,
                "reprint_count": 0
            }
        ],
        "fully_paid": false
    }
}
```

### 2. Create Receipt (Partial Payment)
**Endpoint:** `POST /pizza/api/restaurant-api.php?action=create-receipt`

**Request:**
```json
{
    "table": 5,
    "payment_method": "cash",
    "employee_name": "Pavla",
    "print": true,
    "items": [
        {
            "src": "order_item",
            "item_id": 123,
            "pay_qty": 1
        },
        {
            "src": "order_item", 
            "item_id": 124,
            "pay_qty": 2
        }
    ]
}
```

**Note:** If `items` array is empty or omitted, the system treats it as full payment of all outstanding items.

**Response:**
```json
{
    "success": true,
    "data": {
        "receipt_number": 1002,
        "table_number": 5,
        "total_amount": 350.00,
        "items_count": 2,
        "items": [
            {
                "name": "Margherita",
                "unit_price": 250.00,
                "quantity": 1,
                "total_price": 250.00
            },
            {
                "name": "Coca Cola",
                "unit_price": 50.00,
                "quantity": 2,
                "total_price": 100.00
            }
        ],
        "payment_method": "cash",
        "employee_name": "Pavla",
        "printed": true,
        "paid_at": "2025-01-15 14:35:00"
    }
}
```

### 3. Reprint Receipt
**Endpoint:** `GET /pizza/api/restaurant-api.php?action=reprint-receipt&receipt_number={receiptNumber}`

**Response:**
```json
{
    "success": true,
    "data": {
        "receipt_number": 1001,
        "table_number": 5,
        "total_amount": 250.00,
        "items_count": 1,
        "items": [...],
        "payment_method": "cash",
        "employee_name": "Pavla",
        "original_paid_at": "2025-01-15 14:30:00",
        "reprinted_at": "2025-01-15 14:40:00",
        "reprint_count": 1
    }
}
```

## Key Features

### ✅ Split Payments
- Multiple partial settlements per table/session supported
- Track paid quantities vs total quantities per item
- Automatic session closure when all items are fully paid

### ✅ Sequential Receipt Numbers
- Independent receipt numbering using `counters` table
- Guaranteed sequential numbers across all receipts
- Never reuses numbers even if receipts are deleted

### ✅ Partial Quantity Tracking
- Each order item tracks `paid_quantity` vs `quantity`
- Outstanding quantities computed as `quantity - paid_quantity`
- Validation prevents overpayment of any item

### ✅ Receipt Storage & Reprinting
- JSON snapshot of paid items stored in `completed_payments.items_json`
- Optional printing controlled by `print` flag
- Reprint capability with tracking via `reprint_count`
- `printed_at` timestamp tracks when receipt was printed

### ✅ Bill Summary & History
- Raw item list with individual IDs and quantities
- Aggregated summary by item name and unit price  
- Complete history of prior receipts per table
- Fully paid status detection

### ✅ Input Validation & Error Handling
- Validates payment quantities don't exceed outstanding amounts
- Checks for valid payment methods ('cash', 'card')
- Comprehensive error messages for debugging
- Transaction rollback on any validation failure

## Integration Notes

### Existing Payment System Compatibility
The new partial receipt system works alongside the existing `pay-items` endpoint. The key differences:

- **Old system:** Marks entire items as 'paid' status
- **New system:** Tracks partial quantities with `paid_quantity` field

Both systems can coexist, but for best results, use the new `create-receipt` endpoint for all future payments.

### Frontend Integration
Update the billing interface (`pizza/billing.html`) to:

1. Replace calls to `session-bill` with `get-bill` for outstanding item calculation ✅ COMPLETED
2. Replace calls to `pay-items` with `create-receipt` for payment processing ✅ COMPLETED
3. Add UI for partial quantity selection per item ✅ COMPLETED - Unified Payment Modal
4. Display prior receipt history ✅ COMPLETED
5. Add reprint functionality ✅ COMPLETED

### Unified Payment Modal (Updated Implementation)
The billing interface now uses a unified payment modal with the following features:

- **Aggregated Item Display**: Items with same name, price, and note are aggregated for cleaner UI
- **Full vs Partial Payment Toggle**: Users can switch between full payment (pays all outstanding) or partial payment (select specific quantities)
- **Payload Formats**:
  - Full payment: `{ items: [] }` (empty array means pay all outstanding)
  - Partial payment: `{ items: [{ src: 'order_item', item_id: X, pay_qty: Y }] }`
- **Always Print**: Receipts are always printed (`print: true`), no checkbox needed
- **Document Type**: Includes `doc_type: 'receipt'` for printer routing
- **Employee Name**: Pre-filled from localStorage with session API fallback
- **Fully Paid Tables**: Tables with all items paid show "Uhrazeno" badge and offer reprint/close options

### Database Performance
The new indexes ensure optimal performance:
- `idx_order_items_paid` - Fast lookup of paid/unpaid items per order
- `idx_completed_payments_table` - Fast receipt history lookup per table

## Testing

### Manual Testing Steps
1. Run migration: `php scripts/migrate_partial_receipts.php`
2. Create some test orders with multiple items
3. Test `get-bill` to see outstanding items
4. Test unified payment modal with full payment mode (empty items array)
5. Test unified payment modal with partial payment mode (specific quantities)
6. Verify receipt numbering is sequential
7. Test reprint functionality on fully paid tables
8. Verify fully paid tables show "Uhrazeno" badge
9. Test employee name persistence in localStorage

### Test Checklist (from PR requirements)
- [ ] Partial platba (několik itemů se stejným názvem i různou note) → správná alokace pay_qty
- [ ] Full platba (Vybrat vše) => items: [] na drátě (ověřit v Network devtools)
- [ ] Sekvenční dvě partial platby + závěrečná full → stůl badge Uhrazeno
- [ ] Změna platební metody (cash/card) se propíše do receipt a tisk payloadu
- [ ] Reprint poslední účtenky funguje; zvýší reprint_count
- [ ] Stůl bez outstanding se neztrácí ihned (zobrazen s Uhrazeno, zmizí až po refreshu / další logice mimo scope)
- [ ] Odstraněny legacy funkce – ve zdroji není showSplitModal ani payAll
- [ ] Edge race: mezitím zaplaceno jiným klientem -> backend chyba -> front-end reload + toast

### Test Files
- `/tmp/test_partial_receipts.php` - Unit tests for logic
- `/tmp/partial_receipts_test.html` - Web interface for testing
- `/tmp/validate_requirements.php` - Requirements compliance check

## Error Handling

Common error scenarios handled:
- Table has no active session
- Item ID not found or invalid
- Attempting to pay more than outstanding quantity
- Invalid payment method
- Receipt number not found (for reprinting)
- Database transaction failures

All errors return structured JSON responses with descriptive messages for debugging.

## Security Considerations

- SQL injection prevention via prepared statements
- Input validation on all parameters
- Transaction isolation to prevent race conditions
- No sensitive data exposure in error messages
- Receipt numbers are predictable but not exploitable

## Future Enhancements

Potential future additions:
- VAT breakdown support (when business becomes VAT payer)
- Discount and tip functionality
- Email receipt sending
- Receipt printing integration
- Payment method specific handling (card transaction IDs)
- Multi-currency support