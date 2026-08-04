-- Migration: add per-asset balance columns for BTC and ETH
-- Run this against your existing database to enable per-asset balance editing from admin.

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS btc_balance DECIMAL(18,8) NOT NULL DEFAULT 0.00000000,
    ADD COLUMN IF NOT EXISTS eth_balance DECIMAL(18,8) NOT NULL DEFAULT 0.00000000;
