# EMCP Tools 3.17.0 preparation

Status: plugin release published on 2026-09-21 (Asia/Karachi), explicitly authorized by the user.

This release combines the earlier History improvements with all subsequent builder and Widget Builder work.

## Contents

- History creation undo, page settings and CSS recording, conflict guards and verified rollback restoration.
- Twelve standalone builder integrations including Elementor and BeBuilder, plus Gutenberg and five independently enabled block packs. Vendor feature coverage and supported versions are integration-specific; refer to each integration guide and bundled skill.
- Completed Oxygen workflows and added Kirki, WPBakery, Beaver Builder, Visual Composer, Blocksy separation and Otter Free/Pro support.
- Widget Builder query, shortcode and remote-source data controls, remote-key management, registry synchronization and reserved Elementor control-name validation. Changes made with Claude are retained.
- Runtime and downloadable skills, native API probes, MCP fixtures and regression tests.
- Templates navigation now follows its module toggle even when another builder is selected.
- Shared agent guidance stays in AGENTS.md, imported by CLAUDE.md. Existing widget QA screenshots are retained under docs/qa/2026-09-widget-builder, outside the installable archives.

## Checks

The preparation runs both PHPUnit suites, Node proxy tests, syntax checks of changed PHP, whitespace validation, manifest completeness and both release ZIP verifiers. ZIPs are built after committing Pro first and updating the parent gitlink, because the free archive is derived from committed HEAD. No local credentials or MCP configuration are included.

Pre-build results: 2,888 Pro tests and 12,654 assertions, with six existing skips; 232 public tests and 602 assertions; all 17 proxy tests passed. All 70 changed PHP files passed syntax checks. Manifest review added coverage for three pre-existing Pro-only files: the WooCommerce adapter, FunnelKit adapter and Pro usage client.

Build command: `bash pro/tools/build-release.sh`. Outputs: `releases/emcp-tools-3.17.0.zip` and `releases/emcp-pro-3.17.0.zip`. Local package hashes are recorded in `releases/SHA256SUMS-3.17.0.txt` after verification.

## Scope limits

Live integration results describe the particular installed vendor versions and fixtures; not every premium add-on or vendor feature has an authoring API. Visual Composer needs paired native data/render artifacts. Otter static blocks require native Gutenberg save markup, while Atomic Wind uses its own browser CSS compiler. Individual guides record further exclusions. The six pre-existing skipped Pro tests remain skipped, so a passing run is not full coverage of those paths.

The integration pages were tested on the local test site through fresh MCP processes. Some recent integrations used process-local source bridges rather than a complete plugin deployment; archive checks are additional packaging validation, not a new installed-ZIP acceptance test.

## Publication handoff

After explicit authorization, push the private Pro commit before the public parent, then perform the established tag, free GitHub ZIP release and premium Freemius upload workflow. Keep Freemius's generated free build unreleased. The normal release-finish social copy and website changelog rebuild occur only as part of that authorized publication workflow.

## Website preparation

The sibling website now includes 15 new builder/block-pack integration pages and tool references, a compatibility guide, theme/block separation, refreshed generated changelog and catalog counts, and a draft 3.17.0 article with a cover. See `website/docs/RELEASE-3.17.0.md` in the companion repository for validation and publication-day steps. Website changes remain local and the article is excluded from production until release approval.

## Publication record

- Public tag `v3.17.0` targets `3c6b78d`, including the Modules, Page Builders, Tools menu order.
- GitHub release: https://github.com/msrbuilds/elementor-mcp/releases/tag/v3.17.0
- GitHub asset: `emcp-tools-3.17.0.zip` only.
- Freemius product 30577, deployment 154381: `release_mode=released`; existing `is_released=false` preserved. Only the verified Pro package was uploaded.
- Fresh ZIPs passed archive checks and exact menu-source comparison after line-ending normalization. Checksums are in `releases/SHA256SUMS-3.17.0.txt`.
- Facebook launch post and banner copy: `docs/releases/social-post-3.17.0.md`, with a session-memory copy in `.remember/`. Social copy is prepared, not posted.
- Website release article and cover were converted from preview wording. Astro check passed on 414 files with zero errors, warnings or hints; production build passed.
