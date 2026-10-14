---
name: Solopos Editorial Credibility
colors:
  surface: '#faf8ff'
  surface-dim: '#d2d9f4'
  surface-bright: '#faf8ff'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f2f3ff'
  surface-container: '#eaedff'
  surface-container-high: '#e2e7ff'
  surface-container-highest: '#dae2fd'
  on-surface: '#131b2e'
  on-surface-variant: '#42474e'
  inverse-surface: '#283044'
  inverse-on-surface: '#eef0ff'
  outline: '#73777f'
  outline-variant: '#c2c7cf'
  surface-tint: '#3a6189'
  primary: '#002441'
  on-primary: '#ffffff'
  primary-container: '#0a3a60'
  on-primary-container: '#7ea4d0'
  inverse-primary: '#a3caf7'
  secondary: '#904d00'
  on-secondary: '#ffffff'
  secondary-container: '#fe932c'
  on-secondary-container: '#663500'
  tertiary: '#4e0003'
  on-tertiary: '#ffffff'
  tertiary-container: '#770007'
  on-tertiary-container: '#ff786c'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#d1e4ff'
  primary-fixed-dim: '#a3caf7'
  on-primary-fixed: '#001d35'
  on-primary-fixed-variant: '#204970'
  secondary-fixed: '#ffdcc3'
  secondary-fixed-dim: '#ffb77d'
  on-secondary-fixed: '#2f1500'
  on-secondary-fixed-variant: '#6e3900'
  tertiary-fixed: '#ffdad6'
  tertiary-fixed-dim: '#ffb4ab'
  on-tertiary-fixed: '#410002'
  on-tertiary-fixed-variant: '#93000b'
  background: '#faf8ff'
  on-background: '#131b2e'
  surface-variant: '#dae2fd'
  solopos-navy: '#004B87'
  solopos-navy-deep: '#0A3A60'
  vip-amber: '#F59E0B'
  vip-gold: '#D97706'
  edition-red: '#DC2626'
  surface-canvas: '#F8FAFC'
  surface-card: '#FFFFFF'
  surface-border: '#E2E8F0'
  text-muted: '#64748B'
  state-success: '#15803D'
typography:
  display-lg:
    fontFamily: Newsreader
    fontSize: 3rem
    fontWeight: '600'
    lineHeight: 3.5rem
    letterSpacing: -0.02em
  display-lg-mobile:
    fontFamily: Newsreader
    fontSize: 2.25rem
    fontWeight: '600'
    lineHeight: 2.75rem
    letterSpacing: -0.01em
  headline-lg:
    fontFamily: Newsreader
    fontSize: 2rem
    fontWeight: '600'
    lineHeight: 2.5rem
  headline-lg-mobile:
    fontFamily: Newsreader
    fontSize: 1.625rem
    fontWeight: '600'
    lineHeight: 2rem
  headline-md:
    fontFamily: Newsreader
    fontSize: 1.5rem
    fontWeight: '600'
    lineHeight: 1.875rem
  headline-sm:
    fontFamily: Plus Jakarta Sans
    fontSize: 1.125rem
    fontWeight: '600'
    lineHeight: 1.5rem
  body-lg:
    fontFamily: Plus Jakarta Sans
    fontSize: 1.125rem
    fontWeight: '400'
    lineHeight: 1.75rem
  body-md:
    fontFamily: Plus Jakarta Sans
    fontSize: 0.9375rem
    fontWeight: '400'
    lineHeight: 1.5rem
  body-sm:
    fontFamily: Plus Jakarta Sans
    fontSize: 0.8125rem
    fontWeight: '400'
    lineHeight: 1.25rem
  label-md:
    fontFamily: Plus Jakarta Sans
    fontSize: 0.875rem
    fontWeight: '600'
    lineHeight: 1.25rem
    letterSpacing: 0.01em
  label-sm:
    fontFamily: Plus Jakarta Sans
    fontSize: 0.75rem
    fontWeight: '600'
    lineHeight: 1rem
    letterSpacing: 0.02em
  editorial-meta:
    fontFamily: Newsreader
    fontSize: 0.875rem
    fontWeight: '400'
    lineHeight: 1.25rem
    letterSpacing: 0.01em
rounded:
  sm: 0.125rem
  DEFAULT: 0.25rem
  md: 0.375rem
  lg: 0.5rem
  xl: 0.75rem
  full: 9999px
spacing:
  gutter: 1.5rem
  gutter-sm: 1rem
  gutter-lg: 2rem
  margin: 1rem
  margin-md: 2rem
  margin-lg: 4rem
  space-xs: 0.25rem
  space-sm: 0.5rem
  space-md: 1rem
  space-lg: 1.5rem
  space-xl: 2.5rem
---

## Brand & Style

### Brand Personality & Philosophy
This design system embodies the authority, integrity, and regional prestige of a premier journalistic publication. It balances heritage newspaper gravitas with the streamlined convenience of modern digital subscription management. Designed for readers, decision-makers, researchers, and regional community members who value verified news and digital e-paper archives, the interface evokes trustworthiness, intellectual clarity, and curated prestige.

### Aesthetic Direction: Modern Editorial & Tactile Restraint
The design merges modern editorial layout principles with precise, structured digital utility. High-contrast headlines in authoritative typography meet crisp, clean UI elements. Surfaces avoid excessive blurs or heavy skeuomorphic effects, opting instead for tactile paper-adjacent neutrals, hairline borders, structural column rhythms, and deliberate accents that honor classic print layout while delivering frictionless subscriber utility.

## Colors

### Color Hierarchy & Roles
- **Primary (`#0A3A60` / Solopos Deep Navy):** Represents credibility, institutional permanence, and serious journalism. Applied to global navigation bars, major actions, active subscription states, and primary section headings.
- **Secondary (`#D97706` / Amber Gold):** Reserved for VIP tier privileges, active badge highlights, premium locked-content indicators, and conversion upsell banners.
- **Tertiary (`#DC2626` / Editorial Red):** Carries the legacy of urgent press headlines and newspaper stamps. Used for breaking edition tags, PDF watermarked notices, and critical quota alerts.
- **Neutral (`#0F172A` / Deep Slate):** Anchors high-contrast typographic readability on light canvases. Complemented by `#F8FAFC` for page canvas foundations and `#FFFFFF` for elevated modular cards.

### Color Contrast & WCAG Guidelines
All body typography adheres to WCAG 2.1 AA standards (minimum 4.5:1 contrast against `#FFFFFF` and `#F8FAFC`). Accent golds used for text labels default to `#B45309` (Amber 700) on white backgrounds to ensure readable contrast, while `#F59E0B` remains assigned to icons, borders, and dark-backed badges.

## Typography

### Typographic Harmony
The typographic strategy bridges two worlds:
1. **Editorial Serifs (`Newsreader`):** Used across major headlines, package titles, and editorial edition dates. Newsreader lends historic weight, editorial authenticity, and an unmistakable print-like dignity.
2. **Modern Functional Grotesque (`Plus Jakarta Sans`):** Selected for user dashboard telemetry, tabular quotas, form fields, navigation items, and dense microcopy. Its generous x-height and clear apertures guarantee legibility on small mobile displays under varying reading conditions.

### Scaling & Rendering
Display and headline tokens shift down automatically on mobile viewports to prevent awkward line breaks in Indonesian newspaper titles. Strict line-height scales preserve rhythmic visual pacing across multi-column article previews and archive listings.

## Layout & Spacing

### Layout Architecture
- **Grid Philosophy:** A 12-column fluid grid system on desktop (`1280px` max-width container, `2rem` gutters), adapting to an 8-column layout on tablets, and a 4-column layout with `1rem` gutters on mobile.
- **Dashboard Modular Grid:** The membership control center utilizes an asymmetric 8:4 column split on desktop. The 8-column primary pane hosts E-Paper editions, clipping archives, and reading tools; the 4-column secondary pane anchors subscriber status, download quotas, and remaining validity countdowns.
- **Rhythm & Padding:** Component spacing adheres to an 8px base grid. Structural sections maintain generous vertical margins (`space-xl`), while data-dense panels (such as download quota lists) contract to `space-sm` and `space-md` for concise scanning.

## Elevation & Depth

### Tactile Paper Layering
Visual hierarchy avoids heavy, artificial dropshadows. Instead, depth is achieved through:
1. **Surface Tones:** Ground level starts at `#F8FAFC` (Canvas). Cards, tables, and panels sit on `#FFFFFF` (Surface Elevated).
2. **Hairline Structural Borders:** Crisp `1px solid #E2E8F0` borders define component boundaries, echoing the clean column rules of physical print broadsheets.
3. **Subtle Ambient Lift:** Floating interactive elements (dropdowns, modals, and hovered e-paper thumbnails) use an ultra-diffused, cool-tinted shadow: `0 4px 20px -2px rgba(15, 23, 42, 0.06), 0 2px 6px -1px rgba(15, 23, 42, 0.04)`.
4. **Watermark Integration:** Secured PDF and preview overlays maintain a calculated opacity (`rgba(15, 23, 42, 0.08)`) with non-selectable, non-interactive placement that respects layout content without obscuring editorial copy.

## Shapes

### Shape Language
This system specifies a **Soft (`1`)** roundedness profile (`0.25rem` / `4px` base radius). 

- **Rationale:** Razor-sharp corners (`0`) feel overly brutalist and unyielding for a paid consumer service, while rounded and pill shapes (`2` and `3`) erode the formal authority of an established press institution. 
- **Application:**
  - Standard cards, table frames, inputs, and buttons utilize `4px` corners.
  - Dialog windows, subscription pricing packages, and thumbnail wrappers scale slightly up to `rounded-lg` (`0.5rem` / `8px`).
  - Circular components (such as user avatar containers, download progress rings, and numeric step badges) are explicitly marked as full circles (`rounded-full`).

## Components

### 1. Subscription & Pricing Cards
- **Structure:** Encased in a `1px` border with `0.5rem` rounded corners. Default cards utilize `#FFFFFF` surface with `#E2E8F0` border.
- **Recommended / VIP Tier:** Wrapped with an active `#D97706` hairline accent border and a subtle `#FFFBEB` header tint. Displays a top-anchored banner: uppercase, tracked `label-sm` with gold background and white bold typography.
- **Pricing Breakdown:** Features clear price display (`display-lg-mobile` in Newsreader), duration intervals, and explicit bullet checklists with high-contrast checkmark indicators.

### 2. Progress Ring (Subscription Active Days)
- **Visual Presentation:** A circular SVG meter showing remaining days against the total billing cycle.
- **Tokens:** Stroke width of 6px; background track `#E2E8F0`; active value stroke uses `#0A3A60` (or shifts to `#D97706` when validity drops below 5 days).
- **Center Telemetry:** Bold numerical countdown in `headline-md` centered over a subtle `label-sm` text label (e.g., "18 Hari Tersisa").

### 3. VIP & Status Badges
- **Variants:**
  - *VIP Member:* Amber background (`#FEF3C7`), text `#B45309`, paired with a solid amber star/crown micro-icon.
  - *Regular Active:* Solopos navy tint (`#E0F2FE`), text `#0369A1`.
  - *Expired / Alert:* Soft red tint (`#FEE2E2`), text `#B91C1C`.
- **Form:** Compact, `0.25rem` border-radius, uppercase tracked typography (`label-sm`).

### 4. E-Paper Edition Covers & Cards
- **Format:** 3:4 aspect ratio newspaper cover thumbnail mimicking a printed front page.
- **Edition Badge:** Positioned at the top-left corner, featuring `#DC2626` background with white text displaying release date and issue number.
- **Access States:**
  - *Unlocked:* Clean thumbnail preview with an overlaid "Baca E-Paper" button on hover.
  - *Locked:* Semi-opaque slate scrim with a padlock icon and clear "Langganan untuk Akses" prompt.

### 5. Download Button with Quota Counter
- **States:**
  - *Idle:* Primary navy button with download icon and remaining counter tag (`label-sm` showing e.g., "Unduh PDF (Sisa 6/10)").
  - *Processing:* Disabled state with subtle animated spinner and label "Menyiapkan Watermark...".
  - *Limit Reached:* Muted slate background (`#F1F5F9`), text `#94A3B8`, icon disabled with tooltip explaining quota exhaustion.

### 6. Buttons
- **Primary:** Background `#0A3A60`, text `#FFFFFF`, soft radius `0.25rem`. Hover state transitions to `#004B87`.
- **Secondary / Outlined:** Transparent surface, `1.5px` border `#0A3A60`, text `#0A3A60`. Hover fills with `#F8FAFC`.
- **Destructive:** Background `#DC2626`, text `#FFFFFF`. Hover state `#B91C1C`.

### 7. Form Inputs & Search Fields
- **Container:** Hairline border `#CBD5E1`, surface `#FFFFFF`, height `2.75rem`, padding `0 0.875rem`.
- **Focus State:** Border color transitions crisply to `#0A3A60` with a 2px outer focus halo of `rgba(10, 58, 96, 0.15)`. No dramatic shape shifts.