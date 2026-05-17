# Changelog

All notable changes to the NakoPay Magento 2 plugin will be documented in this file.

## [1.0.0] - 2026-05-01

### Added
- Payment method with redirect to NakoPay hosted checkout
- Admin configuration (API key, webhook secret, coin, expiry, test mode)
- HMAC-SHA256 webhook signature verification
- Webhook handler for invoice.paid, invoice.expired, invoice.canceled
- Dual base URL strategy (Supabase primary, api.nakopay.com fallback)
- Encrypted API key storage
- Dedicated logger (var/log/nakopay.log)
- Checkout JS component with Magento UI integration
