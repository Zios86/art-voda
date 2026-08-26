SET NAMES utf8mb4;
-- Одноразовая миграция старой базы v9: добавляет новые поля без удаления автоматов.
ALTER TABLE kiosks
  ADD COLUMN metro VARCHAR(100) NOT NULL DEFAULT '' AFTER schedule,
  ADD COLUMN landmark VARCHAR(150) NOT NULL DEFAULT '' AFTER metro,
  ADD COLUMN photo_url VARCHAR(255) NOT NULL DEFAULT '' AFTER landmark;
