# Temporary Composer audit advisory exception — CVE-2026-45070

**Recorded:** 2026-10-28  
**Owner:** Backend/security maintainer  
**Status:** Temporary, review by 2026-11-04  
**Advisory:** `PKSA-wtxr-p26d-nn42` / `CVE-2026-45070` / `GHSA-vqc8-7275-q272`  
**Package/locked version at review:** `symfony/mime v8.0.15`

## Incident

After Phase 1.6 was merged into `main`, GitHub Actions [run 40757018444](https://github.com/dionis1/shipwise-ai/actions/runs/40757018444) passed 48 PHPUnit tests (188 assertions), static analysis, formatting, service configuration checks, migrations and API contract verification. The backend quality job failed at `composer audit`.

On 2026-10-28 the Packagist/Composer advisory entry `PKSA-wtxr-p26d-nn42` classified `symfony/mime v8.0.15` as affected by CVE-2026-45070, reporting an affected range that included all `8.0.x` releases. However the original vulnerability was fixed in **Symfony MIME v8.0.12**, according to both the framework maintainers and the GitHub-reviewed security advisory, published in May 2026:

- https://symfony.com/blog/cve-2026-45070-email-header-injection-via-non-token-characters-in-mime-parameter-names
- https://github.com/advisories/GHSA-vqc8-7275-q272

The project lockfile lists `symfony/mime v8.0.15`, which is newer than the vendor's documented fix. This appears to be an incorrect expanded affected-version range in the *newly returned audit metadata*, rather than a newly identified unfixed flaw in the locked dependency.

## Deliberately narrow mitigation

Until the advisory metadata is corrected, `composer.json` uses the compatible `config.audit.ignore` entry for **this one specific PKSA ID**, setting `apply: audit`, with an explicit rationale. This prevents the inaccurate advisory entry from failing the audit on the Composer 2.x version packaged in our Docker build. Composer's newer `config.policy.advisories.ignore-id` setting was initially attempted but was not honored in CI. The exception is audit-only and does not exempt dependency updates from security blocking. It **does not disable global security audits**, ignore all medium-severity vulnerabilities, or turn off dependency security blocking. New and unrelated advisories must still fail the CI audit.

Do not downgrade Symfony MIME to an actually vulnerable version. Do not remove the audit step, use `--no-audit`, or apply a blanket `policy.advisories.audit=report` override.

## Follow-up before the deadline

1. Re-run `composer audit --locked` and inspect the latest affected/fixed version ranges for `PKSA-wtxr-p26d-nn42`.
2. Check the locked `symfony/mime` version and vendor patch availability. If Symfony issues any additional fix, upgrade dependencies and regenerate the lockfile through Composer.
3. Once the advisory metadata agrees with the official patched ranges and the audit succeeds without an exception, **remove the single-ID ignore** in a focused Conventional Commit.
4. Keep the outcome and actual GitHub Actions links in the verification record. The exception is not a statement that all production security controls are complete; Phase 1.5 remains pending.

**Review guard:** If `symfony/mime` changes to a different major/minor line or an earlier version, re-evaluate this exception immediately. The technical justification above is specific to the reviewed `v8.0.15` lockfile version.
