# Payment Management vs Sales Management

## Overview

Payment management and sales management are similar but serve different purposes in the system:

- **Sales Management**: Tracks revenue from guests (money coming IN)
- **Payment Management**: Tracks payments to casts (money going OUT)

## Key Differences

### Sales Management
- **Purpose**: Track revenue from guest payments
- **Data Source**: Guest payment transactions
- **Direction**: Money coming INTO the business
- **Focus**: Revenue tracking, guest spending patterns
- **Interface**: Shows guest names, amounts paid, payment methods

### Payment Management  
- **Purpose**: Track payments made to casts
- **Data Source**: Cast payment transactions
- **Direction**: Money going OUT to casts
- **Focus**: Cast compensation, payroll tracking
- **Interface**: Shows cast names, amounts paid, payment status

## Database Structure

### Payments Table
```sql
CREATE TABLE payments (
    id BIGINT PRIMARY KEY,
    user_id BIGINT,           -- Cast ID or Guest ID
    user_type ENUM('guest', 'cast'), -- Distinguishes payment type
    amount INTEGER,            -- Amount in yen
    status ENUM('pending', 'paid', 'failed', 'refunded'),
    payment_method ENUM('card', 'convenience_store', 'bank_transfer', 'linepay', 'other'),
    description TEXT,          -- Payment description
    paid_at TIMESTAMP,        -- When payment was completed
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### Cast Payments vs Guest Payments

**Cast Payments (Payment Management)**:
- `user_type = 'cast'`
- `user_id` references `casts.id`
- Tracks money paid TO casts
- Used for payroll, compensation tracking

**Guest Payments (Sales Management)**:
- `user_type = 'guest'`  
- `user_id` references `guests.id`
- Tracks money received FROM guests
- Used for revenue tracking

## Enhanced Payment Management Features

### Summary Dashboard
- **Total Payment Amount**: Sum of all cast payments
- **Payment Status Counts**: Paid, pending, failed, refunded
- **Unique Casts**: Number of casts who received payments
- **Payment Methods**: Distribution of payment methods used

### Data Fields
```typescript
interface CastPayment {
    id: number;
    cast_id: number;
    cast_name: string;
    amount: number;
    status: 'pending' | 'paid' | 'failed' | 'refunded';
    payment_method: 'card' | 'convenience_store' | 'bank_transfer' | 'linepay' | 'other';
    description?: string;
    paid_at?: string;
    created_at: string;
    updated_at: string;
    payjp_charge_id?: string;
    metadata?: any;
}
```

### Filtering & Search
- **Cast Name Search**: Find payments by cast name
- **Status Filter**: Filter by payment status
- **Payment Method Filter**: Filter by payment method
- **Date Range**: Filter by payment date

## API Endpoints

### Get Cast Payments
```
GET /api/admin/payments/cast
Query Parameters:
- search: string (cast name search)
- status: string (payment status filter)
- payment_method: string (payment method filter)
- page: number (pagination)
```

### Create Cast Payment
```
POST /api/admin/payments/cast
Body:
{
    "cast_id": number,
    "amount": number,
    "payment_method": string,
    "description": string,
    "status": string
}
```

### Update Cast Payment
```
PUT /api/admin/payments/cast/{paymentId}
Body:
{
    "status": string,
    "description": string
}
```

## Business Logic

### Payment Status Management
- **Pending**: Payment created but not yet processed
- **Paid**: Payment successfully completed
- **Failed**: Payment processing failed
- **Refunded**: Payment was refunded

### Cast Points Integration
When a cast payment is marked as "paid":
1. Cast's points are increased by the payment amount
2. Payment timestamp is recorded
3. Payment status is updated

### PAY.JP Integration
- Payment processing through PAY.JP
- Charge ID tracking for reconciliation
- Webhook handling for payment status updates

## Relationship with Sales Data

### Revenue vs Expenses
- **Sales Data**: Revenue from guest payments
- **Payment Data**: Expenses for cast compensation

### Financial Reporting
- **Net Revenue**: Sales - Payments
- **Cast Compensation**: Total payments to casts
- **Profit Margin**: Revenue minus cast payments

### Data Correlation
- Guest payments (sales) may correlate with cast payments
- High guest spending periods may require more cast compensation
- Cast performance metrics can be compared with payment amounts

## Use Cases

### For Administrators
1. **Payroll Management**: Track cast compensation
2. **Financial Planning**: Monitor payment expenses
3. **Cast Performance**: Correlate payments with cast activity
4. **Budget Control**: Manage cast payment budgets

### For Cast Members
1. **Payment History**: View their payment records
2. **Payment Status**: Check payment processing status
3. **Points Balance**: See updated points after payments

## Implementation Notes

### Frontend Components
- Payment list with filtering
- Payment creation/editing forms
- Payment status badges
- Summary dashboard cards
- Payment method icons

### Backend Services
- Payment data retrieval with filtering
- Payment creation and updates
- Cast points integration
- PAY.JP payment processing
- Payment status management

### Data Validation
- Cast ID must exist in casts table
- Payment amounts must be positive
- Payment methods must be valid
- Status transitions must be logical

This payment management system provides comprehensive tracking of cast payments while maintaining clear separation from sales/revenue data, enabling proper financial management and reporting. 