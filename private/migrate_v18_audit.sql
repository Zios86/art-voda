-- v18: системные события импорта/восстановления могут относиться ко всей базе.
-- Выполнять один раз на существующей базе после полной резервной копии.
ALTER TABLE kiosk_audit
    MODIFY kiosk_id INT UNSIGNED NULL,
    MODIFY action VARCHAR(40) NOT NULL;
