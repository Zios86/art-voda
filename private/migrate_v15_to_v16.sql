-- Миграция v15 → v16: добавляет историю карточек без изменения существующих автоматов.
CREATE TABLE IF NOT EXISTS kiosk_versions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 kiosk_id INT UNSIGNED NOT NULL,
 action VARCHAR(30) NOT NULL,
 snapshot_json JSON NOT NULL,
 admin_name VARCHAR(100) NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (id),
 KEY idx_version_kiosk_created (kiosk_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
