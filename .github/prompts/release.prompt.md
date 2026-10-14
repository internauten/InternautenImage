---
description: "Prepare and tag a release of the internautenimage module: preflight checks, version/README consistency, local tag via tag-release.sh"
argument-hint: "Optional: target version (e.g. 1.4.0) or bump type (major|minor|patch)"
agent: "agent"
---
Prepare a release of the module. Release mechanics are described in [AGENTS.md](../../AGENTS.md#build--release); the tag is created by [scripts/tag-release.sh](../../scripts/tag-release.sh) and built by [release.yml](../workflows/release.yml).

Stop and report at the first failing step — do not "fix" by skipping checks.

1. **Version**: read `$this->version` in [internautenimage.php](../../internautenimage/internautenimage.php) and the latest tag (`git tag --sort=-version:refname | head -1`).
   - If an argument was given, set the version accordingly (bump type → derive from the latest tag).
   - If no argument and the version equals the latest tag, list commits since that tag (`git log <tag>..HEAD --oneline`), propose a bump (feature → minor, fix → patch) and ask before changing it.
   - The version must be greater than the latest tag.
2. **README**: if the release adds a feature, [README.md](../../README.md) must have a German `## Neue Funktion …` section mentioning `(Modul vX.Y.Z)`. If missing, draft it and show it for approval.
3. **Checks** (all must pass):
   - `php -l internautenimage/internautenimage.php`
   - `php .github/skills/add-module-string/scripts/check-translations.php` exits 0
   - `php -l` on both files in `internautenimage/translations/`
4. **Commit**: if steps 1–2 changed files, show the diff and ask before committing (message style: `Bump version to X.Y.Z and …`). The working tree must be clean afterwards (`git status --porcelain` empty); never commit `config*.xml`.
5. **Tag locally**: run `./scripts/tag-release.sh --local-only`.
6. **Push**: do **not** push. Finish with a summary (version, tag, commits included) and the exact commands for the user:
   ```bash
   git push origin <branch>
   git push origin vX.Y.Z
   ```
