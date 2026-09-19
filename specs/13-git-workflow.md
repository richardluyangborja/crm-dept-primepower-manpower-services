# 13 — Git Workflow (push/pull every feature, conflicts handled)

## 1. Branches
- `main` (deployable, protected), `develop` (integration), `feature/crm-<module>-<slug>` (e.g. `feature/crm-pipeline-kanban`, `feature/crm-survey-templates`).
- One feature = one branch = one PR. Never commit directly to `main`/`develop`.

## 2. Everyday loop (every feature)
```bash
git checkout develop && git pull origin develop
git checkout -b feature/crm-<module>-<slug>
# …code + specs update…
git add -A && git status && git diff --staged --stat
git commit -m "feat(pipeline): kanban drag-drop with optimistic update"
git push -u origin feature/crm-<module>-<slug>
gh pr create --base develop --title "feat(pipeline): kanban" --body "Closes #<n>. Screens: …"
# after review:
git checkout develop && git pull origin develop
git branch -d feature/crm-<module>-<slug>
```
- Commit style: Conventional Commits (`feat/fix/docs/chore/refactor/test`). Messages concise, present tense.
- PR must include: what/why, spec file touched, screenshots (light+dark), `migrate --seed` result.

## 3. Merge conflicts (expected with 10 depts)
```bash
git fetch origin && git rebase origin/develop   # or merge if preferred
# resolve <<<<<<< markers, keep both logic where needed, re-run:
php artisan test --filter=Crm  # backend
npm run typecheck && npm run test  # frontend
git add -A && git rebase --continue && git push --force-with-lease
```
- Never `--force` on `main`/`develop`; never commit secrets (`.env` ignored). If hooks reject, fix and make a *new* commit (don't amend shared history).

## 4. Releases
- `develop` → PR → `main` tagged `crm-v0.<n>.0`. Tag notes list modules + mock contracts changed. Neon/migrations run via CI (`migrate --force`) — never manual SQL in prod.
