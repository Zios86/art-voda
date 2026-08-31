# QA: исправления безопасности 31.08.2026

Проверяемая основа: GitHub HEAD `f4c536e5ebfee3cf7c1e54ca9ce878f23b9ff745` и локальный патч v18-dev.

## Пройдено локально

- синтаксис всех JavaScript-файлов через `node --check`;
- разбор `kiosks.json` и `manifest.webmanifest`;
- разбор `.github/workflows/security.yml` и `.github/dependabot.yml`;
- отсутствие inline `onsubmit` в админке;
- наличие внешнего `admin-confirm.js` на трёх опасных формах;
- поиск распространённых форматов приватных ключей, GitHub-токенов и AWS-ключей;
- 96 из 96 контрольных сумм `SHA256SUMS`.

## Ожидается после публикации коммита

- GitHub Actions: PHP lint, JavaScript, JSON, Gitleaks и CodeQL JavaScript.

## Не проверено в этой среде

- PHP lint: интерпретатор PHP отсутствует;
- MySQL и миграции;
- Apache `.htaccess` и CSP в браузере;
- реальный rate limit повторного пароля;
- почтовый cooldown;
- корзина фотографий и проверка backup на настоящей базе;
- расчёт памяти GD на конфигурации Timeweb.

Эти пункты необходимо проверить на Windows/Open Server, затем на тестовом поддомене Timeweb. До этого v18-dev не считается готовой к production.
