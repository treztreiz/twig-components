---
name: release
description: Cut and publish a new release of treztreiz/twig-components. Use when the user wants to release, tag, bump the version, or publish to Packagist. Handles the composer.json version bump, CHANGELOG, git tag, and push in lockstep.
---

# Release

Cut a new tagged release of this package and push it so Packagist picks it up.

**Critical invariant:** Packagist ignores any tag whose `composer.json` `version`
field does not exactly match the tag name (without the `v` prefix). The version
field, the CHANGELOG heading, and the git tag must all agree. This is the step
that is easy to get wrong — verify it explicitly.

## Steps

1. **Pre-flight.** Run `composer check` (cs:check + phpstan + phpunit). Abort the
   release if anything fails. Confirm the working tree is clean apart from
   intended release edits, and that you are on `main`.

2. **Determine the version.** Read the `## [Unreleased]` section of
   `CHANGELOG.md`. Pick the SemVer bump from its contents:
   - new `Added`/`Changed` entries → minor bump
   - only `Fixed`/`Documentation` → patch bump
   - any breaking change → major bump

   Read the current version from `composer.json` to compute `X.Y.Z`. If
   Unreleased is empty, stop and tell the user there is nothing to release.

3. **Edit three files in lockstep:**
   - `composer.json`: set `"version": "X.Y.Z"` (no `v` prefix).
   - `CHANGELOG.md`: rename `## [Unreleased]` to `## [X.Y.Z] - YYYY-MM-DD`
     (today's date) and insert a fresh empty `## [Unreleased]` above it.
   - `CHANGELOG.md` compare links at the bottom: add
     `[X.Y.Z]: .../compare/<prev>...vX.Y.Z` and repoint
     `[Unreleased]: .../compare/vX.Y.Z...HEAD`.

4. **Verify agreement.** Confirm the `composer.json` version, the new CHANGELOG
   heading, and the tag you are about to create are the same `X.Y.Z`. Do not
   proceed if they differ.

5. **Commit, tag, push:**
   ```bash
   git add composer.json CHANGELOG.md
   git commit -m "chore(release): vX.Y.Z"
   git tag vX.Y.Z
   git push origin main
   git push origin vX.Y.Z
   ```
   Push the tag with its own `git push origin vX.Y.Z` — lightweight tags are not
   carried by `--follow-tags`.

6. **Report.** Tell the user the released version and that Packagist will sync on
   its next webhook (or they can force-update on the package page). The package
   is at https://packagist.org/packages/treztreiz/twig-components.

## If re-tagging is needed

If a tag was already pushed pointing at the wrong commit (e.g. a version
mismatch), fix `composer.json`, commit, then move the tag and force-push it:
`git tag -f vX.Y.Z && git push -f origin vX.Y.Z`.
