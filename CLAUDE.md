@AGENTS.md
@AGENTS.local.md

## Claude Code notes

- Workflows referenced in AGENTS.md live in `.github/skills/` (not `.claude/skills/`): read [add-module-string](.github/skills/add-module-string/SKILL.md) or [new-config-feature](.github/skills/new-config-feature/SKILL.md) before such tasks.
- Release steps: [.github/prompts/release.prompt.md](.github/prompts/release.prompt.md).
- `php -l` runs automatically after Edit/Write via [.claude/settings.json](.claude/settings.json) (same script as the VS Code hook); fix reported syntax errors immediately.
