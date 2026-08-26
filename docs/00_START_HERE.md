# С чего начать Claude

## Первые 15 минут

1. Найти и прочитать `CLAUDE.md` и `PROJECT_STATUS.md`.
2. Построить фактический список файлов командой поиска, не обходя вручную весь проект.
3. Найти `AGENTS.md`, прежний `PROJECT_MAP.md`, release notes и результаты QA, если они сохранились.
4. Сверить фактические пути с `docs/03_FILE_MAP.md`.
5. Определить текущую версию по release notes и имени последнего архива.
6. Проверить состояние Git. Если Git отсутствует, предложить инициализацию, но не выполнять без разрешения владельца.
7. Не начинать исправления, пока не определены границы задачи и способ проверки.

## Команды первичного осмотра

```bash
rg --files
rg -n "TODO|FIXME|onsubmit|onclick|ADMIN_|API_KEY|password|status|payment" .
git status --short
```

На Windows эти команды запускаются в терминале Claude Code/Git Bash. Если `rg` отсутствует, допустим PowerShell `Get-ChildItem` + `Select-String`.

## Как выбрать документ

- структура и поток запросов — `02_ARCHITECTURE.md`;
- поиск нужного файла — `03_FILE_MAP.md`;
- обычная правка — `04_DEVELOPMENT_WORKFLOW.md`;
- SQL или импорт — `05_DATABASE_AND_MIGRATIONS.md`;
- вход, формы, файлы — `06_SECURITY.md`;
- проверка — `07_TESTING_AND_QA.md`;
- Open Server — `08_LOCAL_WINDOWS.md`;
- Timeweb и ZIP — `09_RELEASE_AND_DEPLOYMENT.md`;
- список долгов — `10_BACKLOG.md`.
- правила постоянного обновления документов — `13_DOCUMENTATION_MAINTENANCE.md`.
