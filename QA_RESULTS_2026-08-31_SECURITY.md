# QA: исправления безопасности 31.08.2026

Проверяемый коммит: `daf27b220f0cf1db3c2625404be666f85b77a912`.

## Пройдено локально

- синтаксис всех JavaScript-файлов через `node --check`;
- разбор `kiosks.json` и `manifest.webmanifest`;
- разбор `.github/workflows/security.yml` и `.github/dependabot.yml`;
- отсутствие inline `onsubmit` в админке;
- наличие внешнего `admin-confirm.js` на трёх опасных формах;
- поиск распространённых форматов приватных ключей, GitHub-токенов и AWS-ключей;
- 96 из 96 контрольных сумм `SHA256SUMS`.

## Пройдено в GitHub Actions

- PHP lint всех PHP-файлов;
- JavaScript и JSON;
- Gitleaks: опубликованных секретов не найдено;
- CodeQL JavaScript: workflow завершён успешно.

## Не проверено в этой среде

- MySQL и миграции;
- Apache `.htaccess` и CSP в браузере;
- реальный rate limit повторного пароля;
- почтовый cooldown;
- корзина фотографий и проверка backup на настоящей базе;
- расчёт памяти GD на конфигурации Timeweb.

Эти пункты необходимо проверить на Windows/Open Server, затем на тестовом поддомене Timeweb. До этого v18-dev не считается готовой к production.
