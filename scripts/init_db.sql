-- TheRightRing Portal — Postgres schema for Railway deployment.
-- Run once on a fresh database: psql $DATABASE_URL -f scripts/init_db.sql

CREATE TABLE IF NOT EXISTS users (
    id             SERIAL PRIMARY KEY,
    email          TEXT UNIQUE NOT NULL,
    phone_last4    TEXT,
    password_hash  TEXT,
    full_name      TEXT,
    order_id       TEXT,
    created_at     TIMESTAMPTZ DEFAULT now(),
    last_login     TIMESTAMPTZ
);
CREATE INDEX IF NOT EXISTS idx_users_email_lower ON users(LOWER(email));

CREATE TABLE IF NOT EXISTS orders (
    order_id                    TEXT PRIMARY KEY,
    customer_name               TEXT,
    email                       TEXT,
    phone                       TEXT,
    address                     TEXT,
    ring_choices_json           TEXT DEFAULT '[]',
    status                      TEXT DEFAULT 'Design Review',
    timeline_note               TEXT,
    estimated_completion        TEXT,
    project_update              TEXT,
    total_estimate              NUMERIC DEFAULT 0,
    deposit_paid                NUMERIC DEFAULT 0,
    progress_deposit_due        NUMERIC DEFAULT 0,
    final_payment_due           NUMERIC DEFAULT 0,
    final_payment_enabled       BOOLEAN DEFAULT false,
    amount_paid_total           NUMERIC DEFAULT 0,
    versions_json               TEXT DEFAULT '[]',
    approved_version_id         TEXT,
    tracking_number             TEXT,
    skip_resin_requested        TEXT,
    ring_approved_notification  TEXT,
    facetime_requested          TEXT,
    care_plan_purchased         TEXT,
    care_plan_amount            TEXT,
    charge_tax                  TEXT DEFAULT '1',
    estimate_json               TEXT,
    shipping_charge             NUMERIC DEFAULT 0,
    created_at                  TIMESTAMPTZ DEFAULT now(),
    updated_at                  TIMESTAMPTZ DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_orders_email ON orders(email);

CREATE TABLE IF NOT EXISTS media (
    media_id      TEXT PRIMARY KEY,
    order_id      TEXT NOT NULL,
    uploader      TEXT,
    filename      TEXT,
    drive_file_id TEXT,
    thumbnail_url TEXT,
    caption       TEXT,
    uploaded_at   TIMESTAMPTZ DEFAULT now(),
    deleted       BOOLEAN DEFAULT false
);
CREATE INDEX IF NOT EXISTS idx_media_order ON media(order_id) WHERE deleted = false;

CREATE TABLE IF NOT EXISTS tokens (
    token      TEXT PRIMARY KEY,
    email      TEXT NOT NULL,
    expires_at TIMESTAMPTZ NOT NULL,
    used       BOOLEAN DEFAULT false,
    created_at TIMESTAMPTZ DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_tokens_email ON tokens(email);
