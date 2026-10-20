# Sibol design system

**Concept: a field notebook in a contemporary learning studio.** Sibol means "to sprout". The
interface borrows from the things a student carries into a field: a cloth-bound notebook
(the deep forest sidebar), ruled paper (the canvas), specimen labels (source citations), and dated
entries (anything time-based). It should feel calm and trustworthy, never childish, and never like a
generic AI product.

The one memorable element is **margin notes**. When the companion answers from class materials, each
source sits in the right-hand margin next to the passage it supports, labelled like a specimen tag.
Everything else stays quiet so that this reads clearly.

## Colour

All text pairs below meet WCAG 2.2 AA (4.5:1) and were computed with the WCAG 2.x relative-luminance formula.

| Token | Hex | Role |
|---|---|---|
| `--paper` | `#F6F5EE` | App canvas |
| `--paper-raised` | `#FFFEF9` | Panels, inputs, dialogs (a fresh sheet on top of the notebook) |
| `--paper-sunken` | `#ECEBE1` | Wells, table headers, code |
| `--rule` | `#D8DACD` | Hairline dividers (decorative only) |
| `--line-control` | `#6F8574` | Form control borders (3.6:1, meets non-text contrast) |
| `--ink` | `#1A2620` | Body text (14.3:1) |
| `--ink-2` | `#46544C` | Secondary text (7.3:1) |
| `--ink-3` | `#5E6A62` | Metadata (5.2:1 on paper, 4.7:1 on sunken) |
| `--forest` | `#1E3D31` | Sidebar, primary buttons, focus ring, links on dark |
| `--forest-2` | `#2A5242` | Hover/pressed forest |
| `--link` | `#22634A` | Text links (6.5:1) |
| `--leaf` | `#A9D46A` | Lime accent: active navigation tab, progress fill, highlights. Never text on paper. |
| `--leaf-soft` | `#E6F2D3` | Success / "ready" / released backgrounds |
| `--sage-soft` | `#E2E9DF` | Selected rows, neutral chips, citation markers |
| `--amber` / `--amber-soft` | `#8A5A0B` / `#F7EBCF` | Due soon, needs review, in progress |
| `--coral` / `--coral-soft` | `#A93F2A` / `#F7E1DA` | Errors, overdue, destructive actions only |

Rules:
- Status colour is always paired with a word ("Overdue", "Ready"); colour is never the only signal.
- Coral is reserved for things that are wrong or destructive. Amber means "act soon". Leaf means "done / available".
- No gradients, no glass blur, no purple or blue.

## Typography

| Family | Role | Why |
|---|---|---|
| **Besley** (variable, 400–900) | Page titles, empty-state headlines, date numerals in entry lists, the companion's source tags | A Clarendon revival: the face of 19th-century field guides, school primers and specimen labels. Used sparingly. |
| **Atkinson Hyperlegible Next** (variable, 200–800) | All UI and reading text | Designed by the Braille Institute so that easily confused letters (I l 1, O 0) stay distinct, which matters for students. |
| **Atkinson Hyperlegible Mono** | Class join codes, submission receipt numbers, usernames and sign-in slip codes only | Codes are typed by hand, so glyph clarity is functional. Not used for decoration. |

Scale (classic typographic progression, 16px base):

| Token | Size / line-height | Use |
|---|---|---|
| `--step--1` | 13px / 1.45 | Metadata, table captions |
| `--step-0` | 16px / 1.6 | Body, controls |
| `--step-1` | 18px / 1.5 | Lead paragraphs, section headings (sans 700) |
| `--step-2` | 21px / 1.35 | Panel titles (sans 700) |
| `--step-3` | 24px / 1.25 | Secondary display (Besley 600) |
| `--step-4` | clamp(28px, 2.4vw + 12px, 36px) / 1.15 | Page titles (Besley 650) |

- Sentence case everywhere. No all-caps labels, no tracked-out eyebrows.
- Section headings are sans 700 at step 1, never Besley. Besley appears at most once or twice per screen.
- Reading measure is capped at 68ch for prose (lesson content, companion answers, instructions).

## Space, shape, depth, motion

- Spacing scale (4px base): 4, 8, 12, 16, 24, 32, 48, 64 (`--s-1` … `--s-8`).
- Radius by hierarchy: controls and chips 6px, panels and dialogs 10px, pills 999px, tables square inside a rounded panel.
- Depth comes from paper tone and a 1px rule, not from shadows. Only floating layers (menus, popovers, dialogs, toasts) cast a shadow: `0 12px 32px rgba(30, 61, 49, .16)`, tinted forest rather than grey.
- Motion: 120ms for hover and press, 200ms ease-out for drawers, dialogs and popovers. Motion only follows a user action
  or explains a state change (e.g., a streamed answer, a source tag pinning into the margin). `prefers-reduced-motion` disables all of it.

## Structural devices (each one encodes information)

- **Entry dates.** Anything ordered in time (due work, calendar lists, the audit log, recent activity) uses a left
  margin column with the weekday and a Besley day numeral, like a dated notebook entry.
- **Specimen tags.** Source citations: a small bordered label with document title, page or section, and a quoted line.
  Numbered markers appear only where they match a source.
- **Leaf tab.** The active navigation item has a lime tab on the notebook spine (sidebar edge). It marks location, not decoration.
- **Rules.** Hairline rules separate rows of a list. Panels are used only when a group is a real object (a class, a
  conversation, a dialog). Most of a page is plain sections on the canvas.

## Layout

```
≥1080px  ┌ spine 248 ┬ topbar: title · search · notifications · account ──────────┐
         │ nav       │ page head (Besley title, one-sentence lead, actions)        │
         │           │ main column (fluid, max 1180)    │ side column (320–360)    │
         └───────────┴──────────────────────────────────────────────────────────────┘
760–1079 icon rail 76 (labels as tooltips + accessible names), single column, side column flows below
<760     top bar (logo, search, notifications, menu) + bottom tab bar (4 role destinations + More)
         lists replace tables; tables that must stay tabular scroll inside their own frame
```

- Content is left-aligned. Numbers in tables are right-aligned with tabular figures.
- The companion: sessions list (280) │ conversation (≤ 72ch) │ source margin (300). Below 1320px the margin folds
  into tags under each answer; below 760px the sessions list becomes a sheet.
- Breakpoints: 760, 1080, 1320.

## Components

Buttons (primary forest, secondary outlined, quiet text, danger coral), fields with label, hint and error wired via
`aria-describedby`, select, textarea, checkbox and radio rows, segmented control, badges/status words, tabs (links,
`aria-current`), data table with sortable headers (server-side), filter bar, pagination, entry-date list, timeline,
empty state (Besley headline + one sentence + one action), toast region (`aria-live`), confirm dialog (native
`<dialog>`), popover menu, skeleton lines, progress meter (`<meter>`/SVG with text value), inline SVG charts with a text summary.

Icons: Lucide, 1.75px stroke, 20px in navigation, 16px inline, always paired with text or an accessible name.

**Printed sign-in slips** (`shared/slips.php`) are the one paper artefact. Each slip is a fresh sheet
(`--paper-raised` on screen, white in print) with a dashed `--line-control` border that doubles as the cut line
and a scissors icon in its corner; the seal and school name head it, the person's name is in Besley, and the
username and code are in the mono face. The QR code is pure black on white with a four-module quiet zone, the only
colours outside the palette, because scanners need full contrast. Print styles drop the app chrome, start each
section on a new page and fit six slips to an A4 page in two columns.

## School identity

Sibol is the product; **B.E.S.T. College of Polomolok** is the school it serves. The school's own seal and a
photograph of its students carry the identity, and the field-notebook system frames them without competing.

- **Seal.** Use the cut-out files in `public/assets/school` (`partials/seal.php` picks the right size). Never
  recolour, crop, outline or place it on a busy background except where it is pinned to the photograph.
  Smallest size 24 px. It is the logo in the sidebar, the phone header, the setup page and the favicon; it sits
  next to the school's name, so it is decorative (`alt=""`). A logo uploaded in **School settings** replaces it.
- **Photograph.** The sign-in page shows the school photograph uncovered: no text on faces, no colour wash.
  It is cropped with `object-fit: cover`, focused on the students (`object-position` ≈ 64% 42% beside the page,
  58% 38% as a phone banner). The alt text describes the scene; replace it (`SCHOOL_PHOTO_ALT`) if the photo changes.
  Use only photographs the school has consent to publish.
- **The pinned seal** is the one memorable device on the sign-in page: the seal straddles the seam between the
  photograph and the page (the vertical seam on wide screens, the photo's lower edge on phones), like a seal
  joining two sheets. It carries the only drop shadow on the page (`0 3px 6px` forest at 28%).
- **Motto tag.** The school motto and founding year sit on a paper label at the photo's lower left, styled like a
  source tag (paper, hairline border, forest left rule), placed over the blurred foreground, never over a face.
  Hidden on phones, where the seal already shows both.
- **Copy.** The school's name and place come from `SCHOOL_*` settings, never hard-coded in views. The motto is
  quoted exactly as the school writes it.
- **Replacing images.** Put new originals in `tools/school-assets/` (`campus.jpg`, `seal.jpg` on white) and run
  `node tools/school-assets.mjs`; it re-encodes them (dropping camera and location metadata), cuts the seal out of
  its background and writes every size.

## Voice

Plain verbs, sentence case, no filler. A button says what happens ("Release feedback"), and the toast echoes it
("Feedback released"). Errors say what happened and how to fix it. Empty states invite the next action. The
companion is warm but brief; it never claims certainty it does not have.
