-- QR Designer visual settings. Safe for existing records: NULL means classic defaults.
ALTER TABLE `qr_codes`
    ADD COLUMN `design_json` JSON NULL AFTER `background_color`,
    ADD COLUMN `logo_path` VARCHAR(255) NULL AFTER `design_json`;
