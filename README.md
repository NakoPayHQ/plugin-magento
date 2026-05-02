# NakoPay for Magento 2

Accept Bitcoin, Lightning, Litecoin, Monero and more on your Magento 2 store with NakoPay.

## Requirements

- Magento 2.4.x
- PHP 8.1+
- A NakoPay account ([sign up](https://nakopay.com))

## Installation

```bash
composer require nakopay/magento2
php bin/magento module:enable NakoPay_Payments
php bin/magento setup:upgrade
php bin/magento cache:flush
```

## Configuration

1. Go to **Stores > Configuration > Sales > Payment Methods > NakoPay**
2. Set **Enabled** to Yes
3. Enter your **Secret API Key** (`sk_live_*` or `sk_test_*`) from [nakopay.com/dashboard/api-keys](https://nakopay.com/dashboard/api-keys)
4. Enter your **Webhook Secret** (`whsec_*`) - created when you register a webhook endpoint
5. Choose your default cryptocurrency and invoice expiry time
6. Save and flush cache

## Webhook Setup

Register a webhook endpoint in your NakoPay dashboard pointing to:

```
https://your-store.com/nakopay/payment/webhook
```

Enable these events:
- `invoice.paid`
- `invoice.expired`
- `invoice.canceled`

All webhooks are verified using HMAC-SHA256 signatures before processing.

## How It Works

1. Customer selects "Pay with Bitcoin & Crypto" at checkout
2. Magento creates the order and redirects to NakoPay's hosted checkout page
3. Customer pays with their chosen cryptocurrency
4. NakoPay sends a webhook to update the order status automatically
5. Customer is redirected back to the success page

## Test Mode

Use a `sk_test_*` API key to test without real payments. Test invoices behave identically but don't require actual crypto.

## Security

- API keys are stored encrypted using Magento's built-in `Encrypted` backend model
- Webhook payloads are verified with HMAC-SHA256 timing-safe signature comparison
- CSRF protection is properly handled on the webhook endpoint
- No raw SQL - uses Magento's repository and search criteria APIs

## Logging

Logs are written to `var/log/nakopay.log`. Check this file for debugging webhook and API issues.

## Support

- [Documentation](https://nakopay.com/docs/integrations/magento)
- [GitHub Issues](https://github.com/NakoPayHQ/plugin-magento/issues)
- [Contact Support](https://nakopay.com/contact)

## License

MIT
