# Avada integration plan

## Target

Installed Avada theme 7.16.1, Avada Builder 3.16.1 and Avada Core 5.16.1.
Divi remains deferred. Avada receives its own selector card, Tools tab and AI
Chat group. Gutenberg remains available alongside the selected builder.

## Implementation and verification

1. Discover native element maps on demand, enabled elements, field definitions,
   responsive variants, allowed post types and role-manager permissions.
2. Provide page/layout discovery, draft creation, nested shortcode trees and
   add/update/move/remove operations. Scope authoring to ordinary containers,
   rows, columns and basic content elements; expose unsupported elements as
   discovery-only. Do not author forms, executable code, global instances or
   layout conditions in the first adapter.
3. Guard saves with per-post permissions, current hashes and locks; retain prior
   revisions, preserve page status and use WordPress saves and Avada cache hooks.
   Refuse unparseable content rather than silently discard it.
4. Register writes disabled by default. Test selection, hidden tool preferences,
   malformed layouts, nesting, unknown fields, stale saves and permissions.
5. Verify a disposable draft in the frontend and native editor. Bundle an EMCP
   skill in discovery and both download formats. Record tested scope and results.

## Research

No usable Avada-specific public authoring skill found in the initial search.
Builder.io Fusion skills concern a different product. Native sources:
`fusion-builder/inc/{helpers,shortcodes,class-fusion-builder}.php`,
`inc/class-awb-role-manager.php`, `shortcodes/`, and
`inc/lib/inc/fusion-app/class-fusion-app.php`.

Official product documentation: https://avada.com/documentation/

## Implemented scope

- 14 abilities in five Avada sections: Discovery, Pages, Elements, Templates &
  Design, and Avada Core. Eight read tools enabled; six writes disabled by default.
- 15 authorable element types: basic containers/rows/columns (including nested
  columns), title, text, button, image frame, separator, icon, menu anchor, plus
  Core Portfolio, FAQ and Avada Slider. Other native types remain discovery-only.
- Core content discovery lists accessible Portfolio/FAQ items and their taxonomy
  slugs, and Slider groups. Slider embedding requires an existing group slug.
  Core display queries are restricted to published source content. Source item,
  slide, global option and layout-condition writes are not part of this adapter.
- Native option definitions initialize on demand for API requests. Composite
  controls expose their serializable attributes. Native scalar defaults preserve
  legacy column attributes; identical duplicate attributes emitted by the Live
  Builder are accepted, while conflicting duplicates are rejected.
- Saves use per-page capabilities, native role-manager checks, content hashes,
  edit/save locks, prior revisions, WordPress saves and native cache invalidation.
  A failing save hook returns a recovery revision and releases the lock.
- `emcp-avada` is included in injected skill discovery and both downloadable
  formats. This is EMCP-authored guidance; no third-party skill code is bundled.

## Validation on msrplugins.test

- 43 live checks through registered WordPress abilities: native schemas, Core
  discovery, Portfolio/FAQ rendering with disposable content, all page mutations,
  native column attributes, revisions, stale edits, invalid content, save locks,
  failing save hooks, disabled tools, role permissions and builder exclusivity.
- Browser: Avada selected; its tab shows all 14 controls across five sections,
  with Gutenberg alongside it. Native Live Builder loads the draft, accepts an
  inline heading edit and saves it. Desktop and mobile previews render; the
  small-screen heading alignment is applied. Initial fresh-theme typography
  errors stopped after the native options screen initialized the theme.
- Native controls can add unsupported elements to a page. Such mixed content is
  intentionally not editable through this first adapter; it must never be silently
  discarded. Populated Slider visual presentation has not been verified.
- Automated public suite: 232 tests / 602 assertions. Pro suite: 2,722 tests /
  10,367 assertions, six skips. Skill validation and both download formats passed.
- Disposable drafts and Core fixtures were removed after verification. Avada is
  selected on the dev site; six editing tools remain disabled by default.
- External MCP transport on the dev site was not exercised: the session's MCP
  connector targets the separate elementor-mcp.test site. Tests used the same
  registered ability execution path locally; no files were copied to that site.
