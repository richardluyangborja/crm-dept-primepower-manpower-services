# 13 — Git Workflow (finalized policy: where to push, where to merge)

## 1. Branch roles (read this before pushing anything)
| Branch | Role | May you `git push` directly? |
|---|---|---|
| `main` | Production truth. Only moves via release merges from `develop`. | **NO — never.** Only merge commits land here (maintainer merges `develop` → `main`). |
| `develop` | Integration truth. Only moves via feature merges. | **NO — never.** Only merge commits land here (maintainer merges `feature/*` → `develop`). |
| `feature/crm-<step>-<slug>` | The ONLY place new commits go (e.g. `feature/crm-pipeline-kanban`). | **YES — push freely.** One feature = one branch = one PR. |

Rules:
- Never commit directly to `main`/`develop`. Never `--force` (or `--force-with-lease`) on `main`/`develop`. Force-push is allowed **only on your own unmerged `feature/*` branch** (e.g. after a rebase).
- Never commit secrets: real `.env` files are gitignored at root, `backend/`, and `frontend/`. Only `.env.example` files are tracked.

## 2. Everyday loop (every step)
```bash
git checkout develop && git pull origin develop
git checkout -b feature/crm-<step>-<slug>
# …build backend + frontend, update BUILD_TRACKER.md…
git add -A && git status && git diff --staged --stat
git commit -m "feat(<scope>): <what>"
git push -u origin feature/crm-<step>-<slug>
# open PR with base = develop (no `gh` CLI here — open it on GitHub web).
# PR must include: what/why, spec touched, light+dark screenshots, migrate --seed proof.
# maintainer merges PR → develop, deletes the feature branch, updates tracker.
```
- Commit style: Conventional Commits (`feat/fix/docs/chore/refactor/test`), concise, present tense.
- After merge, sync your clone: `git checkout develop && git pull origin develop`.

## 3. Releases (`develop` → `main`)
```bash
git checkout main && git pull origin main
git merge develop -m "release: <what's in>"
git push origin main
git checkout develop && git merge main   # converge; both branches point together
git push origin develop
```
- Tag milestones: `crm-v1.0.0` etc. Tag notes list modules + mock contracts changed.
- Prod migrations run via CI (`migrate --force`) — never manual SQL in prod.

## 4. Merge conflicts
```bash
git fetch origin
# on a feature branch: prefer rebase
git rebase origin/develop
# resolve <<<<<<< markers, keep both logic where needed, re-verify:
php artisan test            # backend/
npm run build               # frontend/
git add -A && git rebase --continue && git push --force-with-lease  # own feature branch ONLY
# on main/develop: resolve with a merge commit, never rebase shared branches.
```

## 5. Files policy — what lives where
- `docker-compose.yml` lives in **ALL branches (including `main`) — deliberately.** It defines only the *local dev* Postgres (dummy password `crm_secret`, no real secrets); CI uses its own service definition and prod uses the deployment-managed Postgres via env. Keeping it out of `main` is technically possible but rejected: every `develop` → `main` merge would then fight over adding/deleting it, and fresh clones of `main` couldn't run the documented setup. Rule: dev-only conveniences with no secrets stay tracked everywhere.
- Real `.env` files: never tracked, any branch. Templates (`.env.example`): tracked everywhere.
- Precedent (2026-09-19): an accidental feature→`main` merge was reconciled by merging `develop`'s flow over it (one `BUILD_TRACKER.md` conflict, newer entry kept) and converging both branches. If it happens again: do NOT revert — merge the correct flow forward.
