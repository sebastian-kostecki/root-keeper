---
date: 2026-09-28T07:37:32+02:00
researcher: Claude (Opus 5.5) for Sebastian Kostecki
git_commit: d7352a6548db4abd1ffea7304b4bc718c2a0bc4d
branch: main
repository: root-keeper
topic: "Graphical tree view designs in other genealogy applications (reference: PL-SOFT Drzewo Genealogiczne)"
tags: [research, ui-reference, graphical-tree-view, S-09, FR-004]
status: partial
last_updated: 2026-09-28
last_updated_by: Claude (Opus 5.5)
---

# Research: graphical tree view designs in other genealogy apps

**Date**: 2026-09-28T07:37:32+02:00
**Researcher**: Claude (Opus 5.5) for Sebastian Kostecki
**Git Commit**: d7352a6
**Branch**: main
**Repository**: root-keeper

## Research Question

Find graphical-tree-view designs (from screenshots and documentation) in other genealogy
applications, with **PL-SOFT „Drzewo Genealogiczne”** as the primary reference, for roadmap
slice S-09 `graphical-tree-view` (FR-004: „Użytkownik może oglądać swoje drzewo w widoku
graficznym i przechodzić z niego do osób”). The research used the Exa web search.

## Summary

- **PL-SOFT Drzewo Genealogiczne has two separate tree surfaces** (per PL-SOFT's product page):
  1. **Formularz rodzinny**, the main navigation screen. It is a fixed 4-row grid: *Związek*
     (the reference row: husband + wife), *Rodzice*, *Dziadkowie*, *Dzieci*. Buttons switch
     between the person's unions (*związki*) and step to further generations.
  2. **Widok drzewa**, a tree generated automatically from the data. It comes in these types:
     ancestors, descendants, ancestors + descendants, and a fan. Its layout can be horizontal,
     vertical or mirrored, with colour presets („zestawy”). Output is PDF, SVG or a plotter print.
- **Nearly every inspected product uses the same pattern** (PL-SOFT, Gramps, Ancestris,
  MyHeritage, FamilySearch, Ancestry, webtrees): the view is centred on one person, each person
  is a card, clicking a card either re-centres the tree or opens a side panel, and a separate
  action opens the full profile.
- **Multiple marriages.** No inspected product draws every spouse of every person by default.
  The patterns found, in order of how often they appeared:
  1. show one spouse and switch to the others (PL-SOFT switch buttons, Ancestris „+” button,
     Ancestry "preferred spouse", MyHeritage spouse switch);
  2. each marriage is its own "union node" with its children under it, side by side
     (webtrees Full Diagram, Ekmansoft, famtreejs, @memoir/tree);
  3. an option to show all spouses (Ancestry vertical view toggle „Always view all spouses”).
- **Child with one known parent.** The web libraries model this as a union with an empty partner
  slot: famtreejs `[parent, null]`, Cedrine "omit partner", @memoir/tree "unknown-parent
  placeholder". Gramps and MyHeritage show an "empty/add parent" card in the pedigree.
- **Gap (why `status: partial`).** Exa returns text only. The PL-SOFT screenshot images could
  not be pulled into this document, so the URLs where they can be viewed are listed under
  *PL-SOFT screenshots* below. The design description is based on PL-SOFT's own text (product
  page and changelog), not on looking at the images.

## Detailed Findings

### 1. PL-SOFT „Drzewo Genealogiczne” (primary reference)

Sources: product page https://www.plsoft.pl/produkt/drzewo-genealogiczne/ and changelog
https://www.plsoft.pl/update/wersja.php?id=DG (both checked on 2026-09-28).

**Formularz rodzinny** (the "family sheet", which is PL-SOFT's main tree navigation):
- Four rows: Związek (reference row), Rodzice, Dziadkowie, Dzieci. This covers 3 generations
  around one couple, plus the children.
- Buttons switch between the person's unions and move to the next or previous generation. This
  lets the user move along the main line and the side lines (product page, section „Formularz
  rodzinny”).
- Since v4.26 (2023-10-06), an unmarried person (*panna, kawaler*) can open on their parents'
  sheet, shown in the *Dzieci* row and highlighted in green (changelog v4.26.0.0).
- Since v4.25 (2023-08-22), the person form has two buttons: „Formularz rodzinny” and „Widok
  drzewa wybranej osoby”. The user can therefore go person → tree and tree → person
  (changelog v4.25.0.0).

**Widok drzewa** (the generated graphic):
- Types: przodków, potomków, przodków i potomków, wachlarz (product page).
- Each view has its own appearance settings: colour scheme, horizontal/vertical/mirrored
  layout, and data range. Named presets („Zestawy parametrów graficznych”) were added in
  v4.21 and v4.22 (changelog).
- The context menu includes „Pokaż/Ukryj wybraną osobę” (v4.22.0.0), so individual people can
  be hidden from the view.
- Editing a person from the tree view rebuilds the tree automatically (v4.25.0.0).
- The main output is print-oriented: PDF, SVG (dobreprogramy description) and a plotter print
  from A4 up to A0 or roll paper.

**Sister programs from the same vendor.** These show PL-SOFT's visual conventions for the
tree card (*ramka*), taken from the changelogs at https://www.plsoft.pl/update/wersja.php?id=FR
(Drzewo Krewnych) and https://www.plsoft.pl/update/wersja.php?id=MP (Moi Przodkowie):
- Deceased people are marked graphically "the same as in DG" with either a stripe in the
  frame's right corner or one of 3 symbols (Drzewo Krewnych v1.11.0.0).
- Gender avatars: separate images for male, female and unknown, plus one for a union
  (*związek*) (DG v4.26.0.0 „Awatary”).
- The frame can show: photo on top or beside the text, surname first, surname in capitals,
  maiden name only, and birth/death places on a separate line (Drzewo Krewnych v1.2 – v1.12).
- Lines to ancestors can be straight or curved (Moi Przodkowie v1.4.1.0).
- There is an option to place spouses who have no ancestors directly next to their partner
  (Drzewo Krewnych v1.7.0.0 „Optymalizuj położenie osób bez przodków w związku”).
- In Moi Przodkowie, pedigree collapse (*ubytek przodków*, the same ancestor reached twice) is
  shown as a reference number instead of a duplicated card (v2.1.0.0).
- A "shortest connection between the proband and another person" tree type exists
  (Drzewo Krewnych v1.10.0.0).

**PL-SOFT screenshots (view them manually; they could not be extracted as text):**
- Gallery: https://www.programosy.pl/galeria,drzewo-genealogiczne.html
- Product page with embedded tutorial videos: https://www.plsoft.pl/produkt/drzewo-genealogiczne/
- Tutorial list, including „ODCINEK 2 – Formularz rodzinny”, „ODCINEK 4 – Karty: Związki,
  Multimedia, Drzewa” and „Dodawanie kolejnego związku dla osoby”:
  https://www.plsoft.pl/pomoc-techniczna/pomoc-drzewo-genealogiczne/
- Moi Przodkowie (pedigree layouts: *widok probanta*, *widok małżeński*):
  https://www.youtube.com/watch?v=Oq-1698tcDg
- Drzewo Krewnych (relatives tree with the proband highlighted in red):
  https://www.youtube.com/watch?v=sjnFP2HVWMs

### 2. Gramps (desktop, open source) and Gramps Web

- **Pedigree View.** Up to 5 generations of ancestors, depending on window size. Each person is
  a box with name, birth/death data and an optional photo. From each box, the top line goes to
  the father and the bottom line to the mother. Solid lines mean birth relations; dashed lines
  mean adoption, step-parent and similar. Right-click menu: Spouses, Siblings, Children,
  Parents. Greyed-out entries mean no data.
  https://gramps-project.org/gramps-manual/gramps-manual.1/2.2/en/ch02s01.html
- **Graph View addon.** Ancestors and descendants in one view, with the active person
  highlighted. Clicking a person re-centres the tree on them. Right-clicking opens an editor.
  Pan by dragging, scroll to zoom. Families are drawn as separate "family nodes". There is a
  „Show path to home person” action. The default is 3 ancestor and 10 descendant generations,
  capped at 1000 people. https://gramps-project.org/wiki/index.php/Addon:Graph_View
- **Gramps Web.** Five chart tabs: Ancestor, Descendant, Hourglass, Relationship graph and Fan.
  The docs state: "The same person may appear more than once… when someone has been married
  multiple times… This is intentional." https://www.grampsweb.org/user-guide/tree/

### 3. Ancestris (open source, has Polish docs)

https://docs.ancestris.org/books/podrecznik-uzytkownika/page/drzewo-dynamiczne
- A "double tree": ancestors on one side of the main person, descendants on the other.
- **Multiple unions.** Only one spouse is fully visible at a time. The person's frame has a
  clickable „+” that cycles through the spouses. Ancestors' other spouses are not shown.
- Frame border colours: yellow = selected, brown = tree root, blue = male, pink = female,
  dark grey = unknown, light grey = family. A union symbol (double circle) sits between the
  spouses. A toggle shows or hides spouses and families.

### 4. MyHeritage / Family Tree Builder

https://www.myheritage.com/help/en/articles/12852432-what-are-the-myheritage-tree-views-and-how-can-they-be-useful-for-me
- 4 views: Family View (cards joined by lines, including siblings and spouses), Pedigree (direct
  ancestors only), List and Fan.
- Clicking a card opens a **profile panel on the left** that lists immediate relatives who are
  not shown in the pedigree (siblings, spouses, children). Clicking one of them re-roots the
  tree. https://www.myheritage.com/help/en/articles/12852559-what-is-the-pedigree-view-for-family-trees
- The pedigree shows 6 generations in Family Tree Builder. "Add Father/Mother" placeholder
  cards appear where a parent is missing.
  https://education.myheritage.com/article/editing-a-tree-in-pedigree-view/

### 5. FamilySearch

https://www.familysearch.org/en/help/helpcenter/article/what-are-the-different-pedigree-views-in-family-tree
- Landscape: the person in the centre, descendants to the left, ancestors to the right. It
  shows children's spouses and indicates when a person has multiple spouses or parents.
- Portrait: vertical, with ancestors above. Fan: up to 7 generations. Descendancy: a vertical
  list of up to 4 generations.

### 6. Ancestry

- Horizontal view: direct ancestors only. Vertical view: everyone connected. Fan: up to 7
  generations. https://help.ancestry.com/hc/en-us/articles/53933302401299
- **Multiple spouses.** A per-person spouse menu and a global „Always view all spouses” toggle.
  At least one spouse always stays visible. The horizontal and fan views show one "preferred"
  set of parents. https://help.ancestry.com/hc/en-us/articles/53933288390931
- In the vertical view, a person who holds two roles (for example cousin + spouse) is drawn
  twice. https://help.ancestry.com/hc/en-us/articles/53933287735187

### 7. webtrees (open-source web genealogy, PHP)

- **Interactive tree.** Generations run left to right and show names plus lifespan years.
  Spouses are included. Dragging reveals more generations. A forum reply explains why
  horizontal works better: fitting 16 to 64 people in one row makes the chart very wide
  (webtrees forum #116764).
- **Full Diagram module** (AGPL-3.0): a MyHeritage Family View clone built on D3 + ELK
  (Sugiyama layered layout). Couples are joined through invisible "union" nodes; the rows are
  snapped to a generation grid. Connectors are drawn as bus lines: a couple bar, a vertical
  drop, then a horizontal child rail. https://git.bocken.org/Alexander/WebtreesFullDiagram

### 8. Web and React libraries that implement these patterns (for the plan, not endorsements)

| Library | Multiple marriages | One known parent | Notes |
| --- | --- | --- | --- |
| famtreejs (React) | partnerships are first-class; children belong to a partnership | `partnerIds: [parent, null]` | pan/zoom, `onPersonClick`, `centerOnPerson`, ARIA keyboard navigation, CSS variables, light/dark themes — https://github.com/alexbrand/famtreejs |
| @memoir/tree (React) | `partnershipGroups` + `groupId` on child links | "unknown-parent placeholder" without a spouse bar | app-owned data; each person rendered once — https://github.com/4everlabs/tree |
| family-chart (D3, React wrapper) | yes | yes | widely used; parts are Premium — https://github.com/donatso/family-chart |
| Cedrine (D3) | each partnership is a separate branch | "omit partner" | `focusOn`, `highlight` path — https://jsr.io/@phinnaeus/cedrine |
| Ekmansoft family-tree-framework (React) | marriages side by side, in chronological order | — | GEDCOM oriented — https://github.com/Ekmansoft/family-tree-framework |

None of these libraries is in `package.json` today. Adding one would be a **new dependency**,
which the project's CLAUDE.md rule says needs approval.

## Code References (mapping to root-keeper's model)

- `app/Models/Person.php:64-69` `parents()`: `parent_child_relations` links a child to each
  parent **individually**.
- `app/Models/Person.php:74-79` `children()`: the same pivot, read from the parent's side.
- `app/Models/Person.php:84-87` `marriages()`: a marriage is an `Event` of type `Marriage`,
  with participants stored in `event_participants`.
- Consequence for the layout (inference): in the inspected models, a child is not attached to a
  specific marriage. A union-based layout (patterns 2 and 3 above) must work out "the child's
  union" as the marriage event whose two participants are both the child's parents. If only
  one parent is known, the child hangs from a single-parent union (FR-011).

## Architecture Insights

- **Two-surface split (PL-SOFT, MyHeritage).** A small, focused family card for navigation plus
  a larger auto-generated tree. For S-09's goal of "watch and go to a person", a person-centred
  view with click-to-open the person preview (`resources/js/pages/persons/show`) matches the
  common pattern.
- **Union nodes** are the standard way the inspected web implementations handle several
  marriages (webtrees Full Diagram, famtreejs, @memoir/tree).
- **Out of scope in the roadmap:** showing the "confirmation state" (whether a fact has a
  source) in the tree view is parked (`context/foundation/roadmap.md:340`, PRD Non-Goals
  `context/foundation/prd.md:146`). So PL-SOFT-style source or GQI badges on cards must not be
  copied.
- **Genealogy card conventions** seen in at least 3 products: gender colour or avatar, a
  deceased marker, name + birth–death years, the selected or root person highlighted.

## Historical Context (from prior changes)

- Not applicable. This is the first research for `graphical-tree-view`; `change.md` has
  status `new`. S-06 (parent–child) and S-07 (marriages) are archived as done
  (`context/foundation/roadmap.md:362-364`).

## Related Research

- None found under `context/changes/**/research.md` for tree views. Not searched in
  `context/archive/**`.

## Open Questions (for `/10x-plan` or the user)

1. **Which PL-SOFT surface to copy for S-09:** the Formularz rodzinny style (fixed 3-generation
   grid around a couple, with a union switch), or a Widok drzewa style pan/zoom tree? This is
   a product choice; the evidence supports either.
2. **Multiple marriages** (the roadmap's S-09 Unknown): show one spouse with a switch
   (PL-SOFT/Ancestris) or all unions side by side (webtrees Full Diagram)?
3. **Single known parent:** empty partner slot, or a placeholder card?
4. **Library or hand-rolled SVG/CSS grid?** Any library is a new dependency and needs approval.
5. **Gap:** someone should look at the PL-SOFT screenshots at the URLs above to confirm the
   visual details (colours, frame shape), which this document describes only from text.
