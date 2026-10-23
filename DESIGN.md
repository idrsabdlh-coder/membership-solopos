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

## MVP Decision Baseline

This section turns the open product and integration questions in PRD v1.2 into a concrete implementation baseline for the Frontend and Backend teams. These are product recommendations, not confirmation that Solopos business, IT, legal, CMS, or payment-provider owners have approved them. Use them for development and sandbox testing; obtain the named owner's approval before production transactions, public pricing, or production personal-data processing. A signed-off decision from the owner supersedes this baseline.

### Product and Commercial Rules

| Decision | MVP baseline |
|---|---|
| Plan periods and term | Weekly 7 days, monthly 30 days, yearly 365 days. No 3- or 6-month plan in MVP. |
| Free Ads & E-Paper prices | Weekly Rp10,000; monthly Rp30,000; yearly Rp300,000, as proposed in PRD §6.2. |
| Free Ads prices | Weekly Rp5,000; monthly Rp15,000; yearly Rp150,000. Provisional implementation values already used by `PlanSeeder`; require business approval before publication. |
| E-Paper prices | Weekly Rp8,000; monthly Rp25,000; yearly Rp250,000. Provisional implementation values already used by `PlanSeeder`; require business approval before publication. |
| Feature and quota matrix | Free Ads & E-Paper: ad-free, E-Paper, clipping. Free Ads: ad-free and clipping, no E-Paper. E-Paper: E-Paper only, no clipping. Clipping quotas for eligible plans: 5/20/200 by term. Download limits for E-Paper plans: 3 per edition and 10 per WIB calendar day. Monthly is recommended. |
| Trial | No free trial in MVP. |
| Voucher | One voucher maximum per ordinary order. Support `percent` (integer 1–100, rounded down to whole IDR) and `fixed` (integer IDR) codes. Validate dates and remaining quota on the server; cap discount at subtotal so the order never goes below zero. Pending orders reserve quota and release it on expiry. Do not combine a voucher with an upgrade credit. |
| Order expiry and refunds | Unpaid order expires 24 hours after creation. No self-service refund; customer support handles refund requests manually under the approved CS policy. |
| Renewal | If renewed while active, the new term starts at the old `end_date`. If expired, it starts at successful payment confirmation time. |
| Upgrade | No mid-term downgrade. An upgrade must add a feature entitlement or extend the term/increase the quota while retaining the current entitlements; Free Ads-only and E-Paper-only are incomparable, so switching between them requires expiry or support rather than an upgrade. Credit = current plan price × unused seconds / total term seconds, rounded down to whole IDR. Charge = max(0, target plan price − credit). On successful payment, the new plan starts immediately for its full term and replaces the current entitlement. Credit is not paid out in cash; upgrade credit cannot be combined with a voucher. |
| Archive and re-download | An edition is accessible only when its publish-date range overlaps the active subscription term. On expiry, reading and re-download stop, including for formerly entitled editions. Previously downloaded files remain on the customer's device. No pre-subscription archive access in MVP. |
| Weekend editions | One combined Saturday-Sunday PDF is one edition, with both dates in `publish_date`–`publish_end_date`. |
| WhatsApp | Phone number is optional; no OTP and no WhatsApp notifications in MVP. Transactional and reminder email are required. Revisit WhatsApp after an approved provider and consent flow exist. |

### Identity, Sessions, and Integrations

- **Authentication:** use Laravel Sanctum's first-party SPA cookie/session authentication; do not put bearer tokens in browser local storage. Require email and password, verification email, password reset links valid for 60 minutes and one use, login rate limiting, CSRF protection, and server-side authorization on every protected API. Google login is post-MVP.
- **Cookie and domain policy:** production frontend and API must use HTTPS on same-site Solopos subdomains (for example, `membership.solopos.com` and `api-membership.solopos.com`). Set the session cookie domain to the approved parent domain (for example, `.solopos.com`) so the SPA can use the XSRF cookie; the session cookie itself remains `HttpOnly`, `Secure`, and `SameSite=Lax`. Configure `SANCTUM_STATEFUL_DOMAINS`, `CORS_ALLOWED_ORIGINS`, CORS credentials, and CSRF bootstrap explicitly. Local defaults allow `localhost:3000` and `127.0.0.1:3000`; bootstrap `/sanctum/csrf-cookie` and use credentialed requests. Do not choose a production domain or enable production cookies until IT approves DNS and deployment.
- **Concurrent sessions:** at most two active sessions per user. Use Laravel's configured database session store as the source of truth; fail login/session-management requests with 503 if the store is not configured for the database driver. Persist the newly authenticated session, then serialize limit enforcement on the user row and revoke the least recently active session when a third login succeeds. `GET /api/auth/sessions` lists only the current user's unexpired sessions with a hashed opaque identifier, device/browser label, IP, WIB activity time, and current-session flag. `DELETE /api/auth/sessions/{session}` revokes only a session belonging to the current user; the current session must use logout instead. Return `meta.previous_session_revoked` from login and notify the user in the UI. The profile settings page lists sessions and allows revoking other devices.
- **Payment gateway:** implement Midtrans Snap first, in sandbox mode, for VA, QRIS, and available e-wallet methods. Only hosted/redirect payment flows are in scope; never receive or store card details. Keep a gateway interface so Xendit can be added later. Production use requires an active merchant account, approved fees/refund process, and verified webhook secret.
- **CMS / Free Ads:** expose authenticated `GET /api/entitlements` as the source of truth. The membership/portal frontend requests entitlement using the first-party session and renders no ad slot for an active Free Ads entitlement. The response is owner-scoped, returns `free_ads: false` without an active subscription, and is marked `private, no-store` to prevent shared caching. Reserve the ad-slot layout while checking to avoid page shift. If the legacy CMS cannot consume this integration, use the PRD's documented frontend flag as a temporary fallback; do not put a shared secret in browser code. A server-to-server CMS adapter is a separate integration if required by the CMS owner.
- **PDF and storage:** master PDFs and temporary watermarked PDFs stay in private storage. Serve only short-lived signed URLs (5 minutes), generate watermarks in a queue, log successful downloads, and clean up temporary files. S3-compatible private storage/CDN is the production target; local private filesystem is acceptable for development.
- **Privacy gate:** collect and version consent, explain email/hash watermarking before download, and implement access/retention/deletion controls. Legal must approve privacy notice, terms, and retention periods before production use.

### API Contract and Frontend State Conventions

These conventions supplement the endpoint list in PRD §13. Backend owns endpoint implementation, validation, authorization, and API examples; Frontend owns typed API clients, form state, navigation, and rendering. Both teams update this contract when an endpoint changes.

- Base URL is configured by environment; local default is `http://127.0.0.1:8000/api`. JSON uses `application/json`, UTF-8, `snake_case`, integer IDR amounts (never floating-point currency), and ISO 8601 timestamps with an explicit `+07:00` offset. Dates without time use `YYYY-MM-DD`.
- Successful resources use `{ "data": ... }`; collection pagination uses `{ "data": [...], "meta": { "current_page": 1, "last_page": 1, "per_page": 20, "total": 1 } }`. Empty collections return `data: []` with HTTP 200.
- Application errors use `{ "error": { "code": "stable_machine_code", "message": "Pesan untuk pengguna", "fields": { "field_name": ["Pesan validasi"] }, "request_id": "..." } }`. `fields` and `request_id` may be omitted when not applicable. Use HTTP 401 unauthenticated, 403 forbidden, 404 missing resource, 409 invalid state/conflict, 422 field/business validation, 429 rate limited, and 5xx unexpected server/gateway failures. Do not return stack traces or provider secrets.
- Validation and authorization are server-side even when the UI disables an action. Protected routes use the Sanctum session cookie and CSRF token; the payment-provider webhook is public but must pass signature validation and idempotency checks.
- Existing catalog endpoint: `GET /api/plans?type=&period=` → HTTP 200 `{ "data": [{ "id": 1, "type": "free_ads_epaper", "type_label": "Free Ads & E-Paper", "name": "...", "billing_period": "weekly", "period_label": "Mingguan", "duration_days": 7, "price": 10000, "clipping_quota": 5, "download_limit_per_edition": 3, "download_limit_per_day": 10, "includes_free_ads": true, "includes_epaper": true, "is_recommended": false }] }`. Invalid filters return 422; no matching plans return an empty collection. The catalog is public and cached for at most one minute.
- Auth endpoints follow PRD §13. `POST /api/auth/register` accepts `{ "name", "email", "password", "password_confirmation", "whatsapp"?: string }`, normalizes email to lowercase, and returns 201 with a user resource and verification-pending state. `GET /api/auth/email/verify/{id}/{hash}` accepts only a valid signed, expiring link; `POST /api/auth/email/verification-notification` resends verification without revealing whether an account exists. `POST /api/auth/login` establishes the Sanctum SPA session only for verified users, applies a per-email/IP rate limit, and returns the user resource; `POST /api/auth/logout` revokes the active session/token and returns 204. Browser clients bootstrap CSRF at `/sanctum/csrf-cookie` and send credentialed requests from an approved Sanctum stateful domain.
- `POST /api/auth/forgot-password` always returns a generic response whether or not the email exists; reset links expire after 60 minutes and are single-use. The link targets `FRONTEND_URL/reset-password` and submits `{ "token", "email", "password", "password_confirmation" }` to `POST /api/auth/reset-password`. Protected `GET`/`PATCH /api/auth/profile` return/update the current user without password or storage path fields; profile updates accept `name`, an optional `whatsapp` in `+62` format, and an optional JPEG/PNG/WebP avatar up to 2 MB. Email is read-only. `PATCH /api/auth/password` requires `current_password`, `password`, and `password_confirmation`. All protected endpoints use `auth:sanctum`, keeping business routes independent of the IT-approved session/token/domain configuration.
- `POST /api/vouchers/validate` requires authentication and accepts `{ "plan_id": number, "voucher_code": string }`; it returns `{ "data": { "subtotal", "discount", "total", "voucher": { "code", "type", "value" } } }` without reserving quota. `POST /api/orders` accepts `{ "plan_id": number, "voucher_code"?: string }`; the server reloads the active plan, validates at most one voucher, calculates all amounts, reserves voucher quota transactionally, and returns 201 `{ "data": { "id", "order_number", "plan": { ... }, "voucher": { ... } | null, "subtotal", "discount", "total", "status": "pending", "expires_at" } }`. A voucher reservation counts against `quota` while the order is pending and is released when it expires. An unpaid order expires 24 hours after creation; `orders:expire-pending` is scheduled hourly, while create/status requests also expire overdue orders as needed. `GET /api/orders/{id}` is owner-scoped and returns the latest payment attempt when one exists. `POST /api/orders/{id}/pay` accepts `{ "method": "bank_transfer" | "qris" | "gopay" | "shopeepay" }`; the server owns the amount, allows up to three attempts, and reuses an existing pending Snap redirect on retry. Payment creation returns 201 (or 200 for that retry) with `{ "data": { "id", "gateway": "midtrans", "method", "amount", "status", "redirect_url", "paid_at" } }`; card data and the Snap token are never returned. `GET /api/orders/{id}/payment-status` is owner-scoped, reconciles pending payments with Midtrans, and returns `{ "data": { "order_status", "payment": { ... } } }`. Provider failures return 502; the UI polls status and must not trust a browser redirect alone.
- Configure `MIDTRANS_SERVER_KEY`, `MIDTRANS_CLIENT_KEY`, and `MIDTRANS_IS_PRODUCTION=false` for sandbox. Configure the provider notification URL as `https://<api-host>/api/payments/webhook`. Snap uses a hosted redirect for VA, QRIS, and available e-wallet methods; production transactions require merchant, finance, IT, and legal approval.
- `POST /api/payments/webhook` is public but accepts only a valid Midtrans signature; invalid signatures return 403 and malformed signed payloads return 422. A valid first delivery and a duplicate both return HTTP 200 after durable receipt. Webhook processing uses a queue, verifies IDR and the server-stored amount before applying status transitions, and is idempotent; successful payment dispatches `OrderPaid` once for the subscription lifecycle to consume. Run `php artisan queue:work` in environments using the database queue. Never store card details.
- A successful paid order provisions exactly one subscription. Its term starts at the payment confirmation time, or at the end of the latest active/scheduled term for a renewal; the term length comes from the purchased plan. Future renewal terms remain pending until their start time. `subscriptions:transition` activates due terms and expires ended terms every minute; run `php artisan schedule:run` every minute. `GET /api/subscriptions` requires authentication and returns the owner's current subscription and paginated history, including WIB dates and the plan's Free Ads, E-Paper, clipping, and download entitlements. Upgrade pricing/credit and its order flow remain in `member-history-upgrade`; ordinary orders are treated as renewals, not inferred upgrades.
- `GET /api/entitlements` requires the same authenticated first-party session and returns the current user's `free_ads`, `epaper`, clipping and download limits, active subscription dates, and plan. It returns explicit false/zero values when no subscription is active and must not be exposed through a shared cache. This endpoint is for the first-party membership/portal integration; a legacy CMS needing server-to-server verification requires a separately approved adapter.
- E-Paper asynchronous generation returns 202 with `{ "data": { "status": "processing", "retry_after_seconds": 2 } }`; a ready file returns a short-lived `download_url` and filename. Locked editions are represented as metadata with `access: "locked"` and never include a page or PDF URL. Quota exhaustion returns 409 with code `download_limit_reached`.
- Clipping endpoints require authentication. `POST /api/clippings` accepts `{ "article_ref", "title", "url" }`; the server requires an active plan with clipping quota, prevents duplicate article references per user, enforces quota per active subscription transactionally, and returns 201 with the saved clipping and remaining quota. A duplicate or exhausted quota returns 409 with `duplicate_article` or `clipping_quota_exceeded`; plans without clipping return 403 `clipping_not_entitled`. `GET /api/clippings?search=&per_page=` returns only the current user's records, supports title/reference search and pagination, and reports quota usage; each result indicates whether it is read-only. Clipping attached to ended subscriptions remains visible read-only for 90 days after the term end, including after renewal to a different term. `DELETE /api/clippings/{id}` returns 204 and frees quota only for a clipping attached to the current active subscription; ended-term records cannot be changed or deleted. Duplicate prevention is per user and continues while the original clipping record is retained.

#### Required UI states

| Flow | States and behavior |
|---|---|
| Pricing | Loading skeleton; loaded cards; empty catalog with retry/contact link; API error with retry. Never render an API error as an empty successful catalog. |
| Register/login/profile | Idle, submitting, success, field-level 422 errors, unauthenticated/session-expired, rate-limited 429, and recoverable network/server error. Preserve safe form values; never echo passwords. |
| Checkout | Loading order, editable voucher, submitting, pending payment instructions/countdown, provider redirect, payment confirmation, expired/cancelled, failed/retry, and general error. Refresh/poll order status; do not mark paid from the redirect alone. |
| Member dashboard | Loading skeleton, active subscription, no subscription, expired subscription, and API error. Display WIB dates and IDR values consistently. |
| Clipping | Loading, list, no clips, search no-results, saving, saved with updated quota, full quota, duplicate article, read-only expired state, and retryable error. |
| E-Paper | Loading, edition list, no editions, unlocked/locked edition, viewer loading/error, download preparing/ready/limit reached, signed URL expired/retry, and offline/server error. Do not expose PDF controls for locked editions. |

All controls are keyboard accessible, announce asynchronous status to screen readers, and preserve WCAG 2.1 AA contrast. Error copy uses Indonesian and describes a recovery action where possible.