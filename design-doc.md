# AtGlance Design System

This document describes the visual design of the AtGlance management console
(`atGlance-managementGUI`). Use it to build the public site (atglance.live) so
both products look like one product.

Source of truth in the console repo:

- `composer/public/css/atglance.css`: all design tokens and `ag-*` components.
- `composer/resources/views/app.blade.php`: page shell (frame, top bar, sidebar).
- `composer/public/branding/`: logo, icon and favicons.

When this document and `atglance.css` differ, `atglance.css` wins.

---

## 1. Design character

- **Light, calm, soft.** A pale grey-blue page with two soft colour glows, a
  frosted-glass frame, and white cards that float on light shadows.
- **One brand gradient.** Mint to sky (`#71F7D4` to `#4CC9FF`) marks every
  primary action and every active navigation item. It is the only saturated
  colour on most screens.
- **Near-black ink for contrast.** Dark cards and hero banners use ink
  (`#14171B`) with the same mint and sky glows in the corners.
- **Rounded everywhere.** Cards 20px, inputs 12px, buttons and navigation
  pills fully round.
- **Quiet typography.** Headings are weight 500, never bold, with tight
  negative letter-spacing.
- **No borders on cards.** Cards separate from the page by shadow and colour
  only. Hairlines (`#E6E9EE`) appear only inside cards (table rows, dividers).

---

## 2. Colour palette

### 2.1 Brand colours

| Token | Hex | HSL | Use |
|---|---|---|---|
| `--ag-mint` | `#71F7D4` | `164 89% 71%` | Gradient start, focus ring, icon colour on dark tiles |
| `--ag-sky` | `#4CC9FF` | `198 100% 65%` | Gradient end, glows, active-pill shadow |
| `--ag-teal` | `#2CB7D9` | `192 69% 51%` | Links, active icon in side navigation, info badge text |
| `--ag-ink` | `#14171B` | `214 15% 9%` | All text, dark cards, hero banners, dark buttons |
| White | `#FFFFFF` | `0 0% 100%` | Cards, menus, text on ink |

Brand gradient, always at 135 degrees:

```css
--ag-gradient: linear-gradient(135deg, #71F7D4 0%, #4CC9FF 100%);
/* Hover state */
linear-gradient(135deg, #5FF0C9 0%, #2FBAF2 100%);
```

### 2.2 Neutrals and surfaces

| Token | Hex | HSL | Use |
|---|---|---|---|
| `--ag-bg` | `#EEF1F4` | `210 21% 95%` | Page background (under the glows) |
| `--ag-card` | `#FFFFFF` | `0 0% 100%` | Cards, menus, navigation track |
| `--ag-surface` | `#F4F6F8` | `210 22% 96%` | Inputs, table header, ghost buttons, tab track, sidebar |
| `--ag-line` | `#E6E9EE` | `218 19% 92%` | Hairlines inside cards, table row borders |
| `--ag-frame` | `rgba(255,255,255,0.55)` | - | Frosted outer frame |
| Ink hover | `#262A30` | `216 12% 17%` | Hover state of dark buttons |
| Table row hover | `#FAFBFC` | - | Row hover background |

### 2.3 Text

| Token | Hex | Use |
|---|---|---|
| `--ag-text` | `#14171B` | Headings and body text |
| `--ag-subtle` | `#5B626B` | Form labels, secondary body text |
| `--ag-muted` | `#8A9099` | Meta text, table headers, inactive navigation, captions |
| Text on ink | `#FFFFFF` | Headings on dark cards |
| Soft text on ink | `#B7BEC6` | Paragraphs on dark hero banners |

Contrast note: `--ag-muted` on white is about 3.2:1. Use it only for text of
12px and larger that is not essential. Use `--ag-subtle` (about 6:1) for any
paragraph a reader must read. Never use mint or sky as text colour on white.

### 2.4 Status colours

Each status has a strong colour (text, icons) and a soft colour (background).

| Status | Strong | Soft background | Text on soft (alerts) |
|---|---|---|---|
| Success | `#1FA874` | `#E3FAF3` | `#137A54` |
| Danger | `#E45757` | `#FDECEC` | `#B33B3B` |
| Warning | `#D98A0B` | `#FEF4E2` | `#8A5A06` |
| Info | `#2CB7D9` | `#E4F6FF` | `#2CB7D9` |
| Accent soft | `#3FE3BC` (strong accent) | `#E5FCF6` | - |

Danger button hover background: `#F9D6D6`.

### 2.5 Colour proportions

On a typical screen: about 70% pale background and white cards, 20% ink
(text, one dark card or banner), under 10% brand gradient (one primary button,
the active navigation item). Do not fill large areas with the gradient.

---

## 3. Page background

The body background is three layers: a sky glow in the top-right corner, a
mint glow in the bottom-left corner, and the flat pale background. The layers
stay fixed when the page scrolls.

```css
body {
  background:
    radial-gradient(900px 520px at 100% 0%, rgba(76, 201, 255, 0.22) 0%, rgba(76, 201, 255, 0) 65%),
    radial-gradient(900px 600px at 0% 100%, rgba(113, 247, 212, 0.35) 0%, rgba(113, 247, 212, 0) 60%),
    #EEF1F4;
  background-attachment: fixed;
  -webkit-font-smoothing: antialiased;
}
```

### Frosted frame

All page content sits inside one large frosted panel:

```css
.frame {
  max-width: 1440px;
  margin: 24px auto;
  padding: 20px 24px 8px;
  background: rgba(255, 255, 255, 0.55);
  border: 1px solid rgba(255, 255, 255, 0.8);
  border-radius: 28px;
  box-shadow: 0 8px 30px rgba(17, 20, 24, 0.06);
  backdrop-filter: blur(12px);
  min-height: calc(100vh - 48px);
}
```

Below 900px the frame fills the screen: no margin, no radius, padding 14px.

---

## 4. Typography

### Font family

```css
--ag-font: 'Neue Haas Grotesk Text', 'Neue Haas Grotesk Display', 'NHaasGroteskTXPro',
           'neue-haas-grotesk-text', 'Inter', 'Helvetica Neue', Helvetica, Arial, sans-serif;
```

Neue Haas Grotesk is a licensed font (Adobe Fonts or self-hosted). Inter is
the free web fallback, loaded from Google Fonts with weights 400, 500, 600
and 700:

```html
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
```

For code, commands and terminal output, use a monospace font (the console
uses the browser default). JetBrains Mono is a good fit for the public site.

### Type scale

| Role | Size | Weight | Letter-spacing | Line-height | Colour |
|---|---|---|---|---|---|
| Hero title (on ink) | 40px (30px below 900px) | 500 | -0.02em | default | `#FFFFFF` |
| Hero paragraph | 16px | 400 | 0 | 1.7 | `#B7BEC6` |
| Page title | 30px | 500 | -0.02em | default | ink |
| Big number (stat) | 36px | 400 | -0.02em | 1.1 | ink |
| Brand wordmark text | 20px (18px in app top bar) | 600 | -0.01em | - | ink |
| Card title / feature title | 15px | 500 (600 in feature cards) | 0 | - | ink |
| Body / buttons / inputs / nav | 14px | 400 (500 for buttons and nav) | 0 | - | ink |
| Label | 13px | 500 | 0 | - | `#5B626B` |
| Feature text | 13px | 400 | 0 | 1.6 | `#8A9099` |
| Caption / stat label / table header / badge | 12px | 500 (400 captions) | 0 | - | `#8A9099` |
| Micro meta (version, menu footer) | 11px | 400 | 0 | - | `#8A9099` |

Rules:

- Headings use weight 500. Do not use 700 or 800 for headings.
- Large text (30px and up) always gets `letter-spacing: -0.02em`.
- Use sentence case for titles, buttons and navigation labels.

---

## 5. Shape, depth and spacing

### Radius

| Token | Value | Use |
|---|---|---|
| Frame | 28px | Outer frosted frame |
| `--ag-radius-card` | 20px | Cards, hero, banners, sidebar |
| Chip / menu | 16px | User chip, drop-down menu, small nested cards |
| Icon button | 14px | Square icon buttons (42 x 42px) |
| `--ag-radius-input` | 12px | Inputs, alerts, side-nav links, table header ends |
| Icon tile | 10px (36px tile), 8px (28px tile) | Icon tiles in cards |
| Badge | 8px | Badges, trend chips |
| `--ag-radius-pill` | 999px | Buttons, navigation pills, tabs |

### Shadows

| Token | Value | Use |
|---|---|---|
| `--ag-shadow` | `0 8px 30px rgba(17, 20, 24, 0.06)` | Default for cards, nav track, chips |
| `--ag-shadow-strong` | `0 12px 40px rgba(17, 20, 24, 0.12)` | Menus, hovered cards, mobile sheets |
| Active pill | `0 4px 14px rgba(76, 201, 255, 0.35)` | Active navigation pill (sky glow) |
| Button hover | `0 6px 18px rgba(76, 201, 255, 0.35)` | Primary button hover |
| Selected tab | `0 2px 10px rgba(20, 23, 27, 0.08)` | Active tab, active side link |
| Focus ring | `0 0 0 4px rgba(113, 247, 212, 0.3)` + border `#71F7D4` | Focused inputs |

### Spacing

Base unit is 4px. Common values: 4, 8, 10, 12, 14, 16, 20, 24, 28, 32, 40, 48.

- Grid gap between cards: 16px. Gap between major columns: 24px.
- Card padding: 20px (feature cards 22px, auth card 28px).
- Page padding inside the content card: 28px top, 32px sides (20px 16px on mobile).
- Hero padding: 48px 40px (32px 24px on mobile).
- Space under a page header: 24px.

---

## 6. Layout

### Console shell (signed in)

```
+--------------------------------------------------------------+  frosted frame
| [logo | org name]                   [workspace v] [user chip]|  top bar
|                                                              |
| +-----------+  +-------------------------------------------+ |
| | sidebar   |  | white content card (radius 20)            | |
| | 250px     |  |                                           | |
| | surface bg|  |  page title                               | |
| | links     |  |  cards / tables                           | |
| |           |  |                                           | |
| | licence   |  +-------------------------------------------+ |
| | card      |        footer (12px, muted, centred)          |
| +-----------+                                                |
+--------------------------------------------------------------+
```

- Sidebar: 250px, `#F4F6F8` background, radius 20, sticky. Links are 14px,
  weight 500, muted; the active link is a white pill with the selected-tab
  shadow and a teal icon.
- Main area: one white content card that holds the page.

### Public / guest layout (use this for the marketing site)

The console's guest pages use a top bar with a centred **pill navigation**,
then a two-column grid: content on the left, a 400px sticky card on the right
(sign-in form in the console; a call-to-action card on the site).

- Pill navigation: a white track (padding 5px, radius 999, default shadow)
  holding links of 14px / 500 / muted, padding 9px 16px, fully round. The
  active link has the brand gradient, ink text and the sky active-pill shadow.
  Hover: surface background, ink text.
- Content column: a dark hero, then a three-column feature grid.
- Below 900px: one column, the side card moves above the content, the
  navigation collapses behind a menu icon button.

### Breakpoints

| Width | Change |
|---|---|
| <= 1560px | Nav pills tighten (padding 9px 13px), nav icons hide |
| <= 1280px | User chip hides name and email |
| <= 900px | Frame fills screen; nav becomes a drop-down sheet; 3- and 4-column grids become 2 columns; hero title 30px |
| <= 560px | All grids become 1 column; org name next to logo hides; logo 26px high |

---

## 7. Components

Class names are from `atglance.css`. Rebuild them as React components or
Tailwind classes on the site.

### 7.1 Buttons (`ag-btn`)

All buttons: pill radius, 14px, weight 500, padding 10px 18px, gap 8px
between icon and label, no border, label never wraps, transition 0.2s.

| Variant | Background | Text | Hover |
|---|---|---|---|
| Primary (default) | brand gradient | ink `#14171B` | darker gradient + sky glow shadow |
| Dark (`--dark`) | ink `#14171B` | white | `#262A30`, no shadow |
| Ghost (`--ghost`) | `#F4F6F8` | ink | `#E6E9EE` |
| Danger (`--danger`) | `#FDECEC` | `#E45757` | `#F9D6D6` |
| Small (`--sm`) | - | 13px | padding 7px 14px |

Text on the gradient is always ink, never white.

### 7.2 Inputs (`ag-input`, `ag-select`, `ag-textarea`)

- Background `#F4F6F8`, transparent 1px border, radius 12, padding 11px 14px, 14px text.
- Focus: white background, mint border, 4px mint ring at 30% opacity, no outline.
- Label above: 13px, weight 500, `#5B626B`, 6px margin below.

### 7.3 Cards (`ag-card`)

- White, radius 20, default shadow, padding 20px.
- `--dark`: ink background, white text. Icon tiles inside use `rgba(255,255,255,0.12)`.
- `--flat`: surface background, no shadow (nested panels).
- Card header: 28px icon tile + 15px / 500 title, gap 10px, 16px below.
- Icon tile (`ag-card-icon`): 28 x 28px, radius 8, ink background, white 12px icon.
- Interactive list cards lift on hover: `translateY(-2px)` and strong shadow.

### 7.4 Stat block

Big number 36px / 400 / -0.02em, label 12px muted with 6px gap. Optional
trend chip beside it (`ag-trend--up`: success soft/strong, `--down`: danger soft/strong,
radius 8, 12px / 500, padding 3px 8px).

### 7.5 Hero and banner (`ag-hero`, `ag-banner`)

The signature dark block. Ink background, white text, radius 20, overflow
hidden, two blurred glows placed with pseudo-elements:

```css
.hero { position: relative; overflow: hidden; background: #14171B; color: #fff;
        border-radius: 20px; padding: 48px 40px; }
.hero::after  { content: ''; position: absolute; right: -120px; top: -120px;
                width: 360px; height: 360px;
                background: radial-gradient(circle, rgba(76,201,255,0.35) 0%, rgba(76,201,255,0) 70%); }
.hero::before { content: ''; position: absolute; left: -140px; bottom: -160px;
                width: 420px; height: 420px;
                background: radial-gradient(circle, rgba(113,247,212,0.35) 0%, rgba(113,247,212,0) 70%); }
.hero > * { position: relative; z-index: 1; }
.hero h1 { font-size: 40px; font-weight: 500; letter-spacing: -0.02em; margin: 0 0 16px; }
.hero p  { max-width: 640px; color: #B7BEC6; font-size: 16px; line-height: 1.7; }
```

Sky glow top-right, mint glow bottom-left: the same placement as the page
background. Put the primary gradient button on the hero for the main call to action.

### 7.6 Feature grid (`ag-feature-grid`, `ag-feature`)

- Three columns, gap 16px, 24px above.
- Each feature: white card, padding 22px, radius 20, default shadow.
- Icon tile: 36 x 36px, radius 10, ink background, **mint** icon, 14px below.
- Title 15px / 600; text 13px / 1.6 / muted.

### 7.7 Tabs (`ag-tabs`)

Segmented control: surface track (padding 5px, pill radius). Tabs are 14px /
500 / muted, padding 9px 18px. Active tab: white pill, ink text, selected-tab
shadow. Hover: `rgba(255,255,255,0.7)`. Vertical variant: 240px column, radius
20 track, 12px-radius tabs, active icon teal.

### 7.8 Badges (`ag-badge`)

Radius 8, padding 4px 10px, 12px / 500, no wrap. Default: surface background,
subtle text. Variants use the status soft background with the strong colour
as text (success, danger, warning, info).

### 7.9 Tables (`ag-table`)

- Header row: surface background, 12px / 500 muted text, left aligned; first
  and last header cells rounded 12px so the header reads as a pill.
- Body cells: padding 14px 16px, 1px `#E6E9EE` bottom border, none on the last row.
- Row hover: `#FAFBFC`.

### 7.10 Alerts (`ag-alert`)

Radius 12, padding 12px 16px, 14px, icon and text in one row with gap 10px.
Status soft background with the dark "text on soft" colour from section 2.4.

### 7.11 Menus and chips

- Drop-down menu: white, radius 16, padding 8px, strong shadow, 8px below its
  trigger. Items: 14px, padding 10px 12px, radius 10, hover surface.
- User chip: white, radius 16, default shadow, 36px avatar with radius 12 on
  an ink background.
- Icon button: 42 x 42px, radius 14, white, default shadow.

### 7.12 Footer

Centred, 12px, muted. Links are black. Items separated by ` | `:
About, Contact, FAQ, all on atglance.live.

---

## 8. Icons

Font Awesome 6 (solid style), from cdnjs:

```html
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
```

Icons the console uses, for matching feature icons on the site: `fa-chart-line`
(dashboard), `fa-server` (systems), `fa-file-code` (config backups),
`fa-users`, `fa-sitemap` (workspaces), `fa-bell` (notifications),
`fa-sliders-h`, `fa-building` (enterprise), `fa-cog`, `fa-user-circle`,
`fa-key` (licence), `fa-arrow-up-right-dots` (upgrade), `fa-lock`.

Sizes: 13-14px in navigation, 12px in 28px tiles. If the site uses Lucide
(shadcn default), pick the matching outline icon and keep the same sizes.

---

## 9. Logo and brand assets

Files in `atGlance-managementGUI/composer/public/branding/`:

| File | Use |
|---|---|
| `atglance-logo.png` | Wordmark. 34px high (26px below 560px), max 180px wide |
| `atglance-icon.png` | Square mark |
| `favicon.ico`, `favicon-32.png` | Favicons |
| `apple-touch-icon.png` | iOS home screen |

The wordmark already reads "AtGlance"; do not repeat the name in text beside
it. A secondary name (for example an organisation) sits after a 1px `#E6E9EE`
divider with 12px left padding, 16px, `#5B626B`.

---

## 10. Motion

- Colour and background transitions: `0.2s ease`.
- Card hover: lift 2px and switch to the strong shadow, `0.2s ease`.
- Tab content enters with a fade and 6px rise:

```css
@keyframes ag-fade {
  from { opacity: 0; transform: translateY(6px); }
  to   { opacity: 1; transform: translateY(0); }
}
/* animation: ag-fade 0.25s ease; */
```

- No bounces, no long animations, nothing over 0.3s for interface feedback.
  Respect `prefers-reduced-motion` on the site.

---

## 11. Implementation for the public site (React + Tailwind + shadcn)

The site currently uses a dark theme with a gold accent (`design_guidelines.json`,
`frontend/src/index.css`). To match the console, replace those tokens as below.

### 11.1 Raw tokens (`frontend/src/index.css`)

```css
@import url("https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap");

:root {
  --ag-sky: #4cc9ff;
  --ag-teal: #2cb7d9;
  --ag-mint: #71f7d4;
  --ag-ink: #14171b;

  --ag-bg: #eef1f4;
  --ag-bg-glow: rgba(113, 247, 212, 0.35);
  --ag-bg-glow-2: rgba(76, 201, 255, 0.22);
  --ag-frame: rgba(255, 255, 255, 0.55);
  --ag-card: #ffffff;
  --ag-card-dark: var(--ag-ink);
  --ag-surface: #f4f6f8;
  --ag-line: #e6e9ee;

  --ag-accent: var(--ag-mint);
  --ag-accent-strong: #3fe3bc;
  --ag-accent-soft: #e5fcf6;
  --ag-gradient: linear-gradient(135deg, var(--ag-mint) 0%, var(--ag-sky) 100%);
  --ag-gradient-hover: linear-gradient(135deg, #5ff0c9 0%, #2fbaf2 100%);

  --ag-text: var(--ag-ink);
  --ag-muted: #8a9099;
  --ag-subtle: #5b626b;

  --ag-danger: #e45757;
  --ag-danger-soft: #fdecec;
  --ag-warning: #d98a0b;
  --ag-warning-soft: #fef4e2;
  --ag-success: #1fa874;
  --ag-success-soft: #e3faf3;

  --ag-radius-card: 20px;
  --ag-radius-input: 12px;
  --ag-radius-pill: 999px;
  --ag-shadow: 0 8px 30px rgba(17, 20, 24, 0.06);
  --ag-shadow-strong: 0 12px 40px rgba(17, 20, 24, 0.12);

  --ag-font: 'Neue Haas Grotesk Text', 'Inter', 'Helvetica Neue', Helvetica, Arial, sans-serif;
}
```

### 11.2 shadcn variables (HSL, same file, `@layer base`)

```css
@layer base {
  :root {
    --background: 210 21% 95%;          /* #EEF1F4 */
    --foreground: 214 15% 9%;           /* #14171B */
    --card: 0 0% 100%;
    --card-foreground: 214 15% 9%;
    --popover: 0 0% 100%;
    --popover-foreground: 214 15% 9%;
    --primary: 164 89% 71%;             /* mint; primary buttons use the gradient */
    --primary-foreground: 214 15% 9%;
    --secondary: 210 22% 96%;           /* #F4F6F8 surface */
    --secondary-foreground: 214 15% 9%;
    --muted: 210 22% 96%;
    --muted-foreground: 216 7% 57%;     /* #8A9099 */
    --accent: 164 79% 94%;              /* #E5FCF6 */
    --accent-foreground: 214 15% 9%;
    --destructive: 0 72% 62%;           /* #E45757 */
    --destructive-foreground: 0 0% 100%;
    --border: 218 19% 92%;              /* #E6E9EE */
    --input: 210 22% 96%;
    --ring: 164 89% 71%;
    --radius: 0.75rem;                  /* 12px; cards use rounded-[20px] */
    --chart-1: 164 89% 71%;             /* mint */
    --chart-2: 198 100% 65%;            /* sky */
    --chart-3: 192 69% 51%;             /* teal */
    --chart-4: 214 15% 9%;              /* ink */
    --chart-5: 216 7% 57%;              /* muted */
  }
}
```

Keep the site light only. The console has no dark mode; dark areas are
individual ink cards and banners, not a page theme. Remove the `dark` class
from `<html>` if the site sets it.

### 11.3 Tailwind extension (`frontend/tailwind.config.js`)

```js
theme: {
  extend: {
    colors: {
      ag: {
        sky: '#4CC9FF', teal: '#2CB7D9', mint: '#71F7D4', ink: '#14171B',
        bg: '#EEF1F4', surface: '#F4F6F8', line: '#E6E9EE',
        muted: '#8A9099', subtle: '#5B626B', 'ink-hover': '#262A30',
        success: '#1FA874', 'success-soft': '#E3FAF3',
        danger: '#E45757', 'danger-soft': '#FDECEC',
        warning: '#D98A0B', 'warning-soft': '#FEF4E2',
        'info-soft': '#E4F6FF', 'accent-soft': '#E5FCF6',
      },
    },
    fontFamily: {
      sans: ['"Neue Haas Grotesk Text"', 'Inter', '"Helvetica Neue"', 'Helvetica', 'Arial', 'sans-serif'],
      mono: ['"JetBrains Mono"', 'ui-monospace', 'monospace'],
    },
    borderRadius: { card: '20px', frame: '28px', input: '12px' },
    boxShadow: {
      ag: '0 8px 30px rgba(17, 20, 24, 0.06)',
      'ag-strong': '0 12px 40px rgba(17, 20, 24, 0.12)',
      'ag-glow': '0 6px 18px rgba(76, 201, 255, 0.35)',
      'ag-pill': '0 4px 14px rgba(76, 201, 255, 0.35)',
      'ag-tab': '0 2px 10px rgba(20, 23, 27, 0.08)',
      'ag-focus': '0 0 0 4px rgba(113, 247, 212, 0.3)',
    },
    backgroundImage: {
      'ag-gradient': 'linear-gradient(135deg, #71F7D4 0%, #4CC9FF 100%)',
      'ag-gradient-hover': 'linear-gradient(135deg, #5FF0C9 0%, #2FBAF2 100%)',
    },
    letterSpacing: { heading: '-0.02em' },
  },
},
```

### 11.4 Tailwind recipes

```
Primary button   rounded-full bg-ag-gradient px-[18px] py-2.5 text-sm font-medium text-ag-ink
                 whitespace-nowrap transition hover:bg-ag-gradient-hover hover:shadow-ag-glow
Dark button      rounded-full bg-ag-ink px-[18px] py-2.5 text-sm font-medium text-white hover:bg-ag-ink-hover
Ghost button     rounded-full bg-ag-surface px-[18px] py-2.5 text-sm font-medium text-ag-ink hover:bg-ag-line
Card             rounded-card bg-white p-5 shadow-ag
Feature card     rounded-card bg-white p-[22px] shadow-ag
Feature icon     inline-flex h-9 w-9 items-center justify-center rounded-[10px] bg-ag-ink text-ag-mint mb-3.5
Input            w-full rounded-input border border-transparent bg-ag-surface px-3.5 py-[11px] text-sm
                 focus:bg-white focus:border-ag-mint focus:shadow-ag-focus focus:outline-none
Label            mb-1.5 block text-[13px] font-medium text-ag-subtle
Page title       text-[30px] font-medium tracking-heading text-ag-ink
Nav track        flex gap-1 rounded-full bg-white p-[5px] shadow-ag
Nav link         rounded-full px-4 py-[9px] text-sm font-medium text-ag-muted hover:bg-ag-surface hover:text-ag-ink
Nav link active  rounded-full bg-ag-gradient px-4 py-[9px] text-sm font-medium text-ag-ink shadow-ag-pill
Badge success    rounded-lg bg-ag-success-soft px-2.5 py-1 text-xs font-medium text-ag-success
```

---

## 12. Do and don't

Do:

- Use the gradient for one primary action per view and for the active nav item.
- Put ink text on the gradient and on mint.
- Use ink cards with corner glows to break up long light pages.
- Keep cards borderless and separated by shadow.
- Keep headings at weight 500 with negative letter-spacing.

Don't:

- Don't use the old gold accent (`#F59E0B`) or the near-black page theme.
- Don't put white text on the gradient, mint or sky.
- Don't use mint, sky or `#8A9099` for paragraph text on white.
- Don't add borders around cards or heavy drop shadows.
- Don't use square corners or bold (700+) headings.
- Don't add new brand colours. For charts use mint, sky, teal, ink, muted in that order.
