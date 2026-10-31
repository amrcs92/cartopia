# Cartopia: Store Redesign Design

- **Date:** 2026-10-31
- **Status:** Approved in brainstorming, awaiting written-spec review
- **Sub-project:** 2 of 4. The order is: 1 core store (done, merged in amrcs92/cartopia#1), **2 store redesign (this spec)**, 3 payments with Stripe and PayPal, 4 Filament admin panel.
- **Design reference:** the canvas [Cartopia Store Redesign](https://claude.ai/artifact/GMbSbdvDMisyhnSpqQUmkq). Its **Final store** page holds every page in light mode with its dark twin directly below. Its **Brand** page holds the logo, colours, typography, components, voice, photos, motion and icons. The **Explorations** page is history only and is not part of this spec.

## 1. Goal and context

The core store works but still wears the Vue starter kit's stock look and the 2018 clip-art logo. This sub-project gives Cartopia its own identity, the "market stall" look, in light and dark mode, on every page and at every screen size. It also adds the small catalogue features the new design needs: aisles (categories), featured products and extra shop filters.

Success means a visitor sees a store that looks designed rather than templated, every page matches its canvas mockup, and nothing that worked before breaks.

### Scope

**In:**
- The design system: colours (light and dark), fonts, type scale, outlines, shadows, corners, spacing, motion, icons, logo, favicon and app icon.
- A light, dark and device-setting theme switch.
- Every store page rebuilt to its mockup, plus the login, register, password and settings screens restyled to match.
- New home page sections, the featured carousel, the "added to basket" panel, empty and not-found states, and phone layouts.
- Categories (aisles) and a featured flag on products, with the shop filters that use them.

**Out (later sub-projects):**
- Paying by card or PayPal, and the receipts for those payments. They are designed on the canvas and arrive with sub-project 3. Until then checkout offers the wallet only, and wallet top-ups stay free credit.
- Managing categories, featured products and photos from a screen. That is the admin panel, sub-project 4. Until then they come from the seeder.
- Order tracking, a wishlist and live search suggestions.

## 2. Design system

All values below are on the Brand page of the canvas. In code they become CSS custom properties in `resources/css/app.css`, defined once for light and once under the `.dark` class, and exposed to Tailwind 4 through `@theme`. Components only ever use the names, never raw hex values.

### 2.1 Colours

| Name | Used for | Light | Dark |
|---|---|---|---|
| `paper` | Page background | `#EDF2E4` | `#111915` |
| `surface` | Cards, panels, inputs, secondary buttons | `#FFFFFF` | `#1A251F` |
| `field` | Form fields, table headers | `#F5F8F0` | `#223029` |
| `well` | Behind product photos | `#F5F8F0` | `#E9E4D6` |
| `ink` | Main text and icons | `#1A2B21` | `#F1EEE3` |
| `outline` | Thick sticker outlines | `#1A2B21` | `#E9E3D0` |
| `body` | Paragraph text | `#3D4F43` | `#C9D3CB` |
| `muted` | Secondary text, captions | `#4D5E52` | `#B3C0B6` |
| `line` | Light borders, dividers | `#D3DCC8` | `#344A3D` |
| `line-strong` | Input and chip borders | `#C5D0B9` | `#4A6455` |
| `green` | Primary actions, awning, ribbons | `#1F5A3D` | `#2E7A54` |
| `green-deep` | Wallet band, success banner | `#1F5A3D` | `#1F4D38` |
| `marigold` | Prices, main calls to action | `#F5B42E` | `#F5B42E` |
| `on-marigold` | Text and icons on marigold | `#1A2B21` | `#14201A` |
| `shadow` | Hard shadow under buttons | `#1A2B21` | `#2E7A54` |
| `shadow-panel` | Hard shadow under panels and cards | `#1F5A3D` | `#2E7A54` |
| `cream` | Featured panel, icon circles | `#FFF4D6` | `#1A251F` |
| `selected` | Chosen option card | `#FFF6DE` | `#2A3426` |
| `chip` / `chip-ink` | Status chips | `#E6EFE4` / `#1F5A3D` | `#223029` / `#8FD3AE` |
| `danger` | Remove, errors, low balance | `#8A3A26` | `#F08A6E` |
| `link` | Text links | `#1F5A3D` | `#F5B42E` |
| `footer` | Footer background | `#1F5A3D` | `#0B110E` |
| `announce` / `announce-ink` | Top announcement bar | `#1A2B21` / `#F4F1E6` | `#F5B42E` / `#14201A` |

Fixed in both modes: chalkboard `#22382B`, wood `#B98146`, chalk `#F4F1E6`, rating star `#E09A12`.

Aisle tints cycle through three pairs (light / dark): `#FFE4D6` / `#3A2620`, `#DCEBF5` / `#1E2E3A`, `#FCE7B0` / `#3A3220`. A category's tint is chosen by its position in the aisle list, so new aisles need no colour setting.

Product photos always sit on `well`, which stays light in dark mode, so white-background photos never show as bright boxes. Where a photo sits on an aisle tint in dark mode, it gets a rounded `well` backing.

Body text meets 4.5:1 contrast against its background in both modes.

### 2.2 Typography

| Typeface | Role | Weights |
|---|---|---|
| Young Serif | Headings, wordmark, prices, product names | 400 |
| Figtree | Everything else: body, buttons, forms, navigation, tables | 400, 600, 700, 800 |
| Caveat | Hand-lettering only: chalkboards, hanging signs, short notes | 600, 700 |

The fonts are installed with `@fontsource` packages and bundled by Vite, not loaded from Google at runtime.

| Style | Desktop | Phone |
|---|---|---|
| Display | Young Serif 68 / 1.02, letter-spacing -1.5px | 40 |
| Heading 1 | Young Serif 52 / 1.1, -1px | 34 |
| Heading 2 | Young Serif 44 / 1.1 | 28 |
| Heading 3 | Young Serif 24 to 26 / 1.2 | 19 to 20 |
| Price | Young Serif 22 to 40 | 19 to 26 |
| Body | Figtree 17 / 1.6 | 16 |
| Button | Figtree 16 to 18, weight 800 | 15 to 17 |
| Small | Figtree 15 / 1.5, weight 600 | 13 to 14 |
| Chalk | Caveat 26 to 50, weight 700 | 27 to 38 |

Inputs never go below 16px, so phones don't zoom when they're tapped. Money in tables and receipts uses tabular figures. Prices always show two decimals and a dollar sign.

### 2.3 Shape, depth and spacing

- **Outlines:** 1.5px `line` for quiet cards; 2px `outline` for secondary buttons and chips; 2.5px `outline` for "sticker" elements (primary buttons, panels, the add-to-basket bar, selected options).
- **Hard shadows (no blur):** 3px for buttons, 4px for large buttons, 5px for cards and tiles, 8px for hero panels and the chalkboard.
- **Corners:** 10 to 14px for buttons and inputs, 22 to 28px for cards and panels, round for chips and dots. Price tags use a 6px left side and a 14px right side.
- **Spacing scale:** 4, 8, 12, 16, 24, 32, 48, 72. Sections are 72px apart on desktop, 32px on phones. Page content is at most 1200px wide with 24px side padding (16px on phones).
- **Touch targets:** at least 44 × 44px.

### 2.4 Motion

One easing curve everywhere: `cubic-bezier(0.22, 0.8, 0.24, 1)`. Nothing moves on its own.

| What | How | Time |
|---|---|---|
| Button press | Slides 3 to 4px into its shadow; the shadow shrinks to zero | 120ms |
| Hover | Colour change only | 200ms |
| FAQ | The plus rotates into a cross | 200ms |
| Added-to-basket panel | Slides in from the right; page dims | 300ms |
| Phone menu / filters sheet | Slides from the left / rises from the bottom | 300ms |
| Featured carousel | Row glides one card per click | 500ms |
| Toasts | Fade in, stay 4s, fade out | 200ms |

Under `prefers-reduced-motion: reduce`, every transition is instant.

### 2.5 Logo, icons and favicons

- The logo is a stall in miniature (a green band, a striped awning, a marigold crate on two wheels) next to the "Cartopia" wordmark in Young Serif. It ships as a Vue component, `AppLogo`, with `mark` and `full` variants. Colours follow the theme: in dark mode the outlines and stripes turn cream.
- Header size: 44px mark and 28px wordmark. Phone header: 34px and 23px. Clear space equals the crate's width.
- App icon and favicons: the mark on stall green with rounded corners. The files are `public/favicon.svg`, `favicon-32.png`, `favicon-16.png` (simplified: no stripes or outlines), `apple-touch-icon.png` (180px) and `icon-512.png`. The old `public/images/cartopia_logo.png` is removed.
- Icons are line icons on a 24px grid with 2 to 2.4px strokes and round caps: basket-add, basket, search, menu, close, moon, sun, remove, filters, pickup, delivery, and chevrons. Every icon-only button has an accessible label. No emoji.

### 2.6 Theme switching

The starter kit's `useAppearance` composable already supports light, dark and system, sets the `dark` class on `<html>`, and keeps the choice in a cookie so server-rendered pages don't flash. It is reused as is.

- **Desktop header:** a single button shows a moon in light mode ("Switch to dark mode") and a sun in dark mode.
- **Phone menu:** a three-way control (Light, Dark, Device) at the bottom.
- **Settings:** the existing Appearance settings page stays and is restyled.

## 3. Data changes

### 3.1 Categories (aisles)

New table `categories`:

| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `name` | string | unique |
| `slug` | string | unique; used in URLs and filters |
| `image_path` | string, nullable | path on the `public` disk |
| timestamps | | |

`products` gains `category_id`: nullable, a foreign key to `categories`, `nullOnDelete`, indexed. A product can exist without an aisle; deleting an aisle leaves its products uncategorised.

`Category` model: `hasMany(Product)`, a `withProductCount` scope, and an `imageUrl` accessor. That accessor returns the category's own image, or else the primary image of its newest product, or else `null`. `Product` gains `belongsTo(Category)`.

### 3.2 Featured products

`products` gains `is_featured`, a boolean, default `false`, indexed. A `featured` scope returns featured products newest first.

### 3.3 Factories and seeder

- `CategoryFactory`, plus `ProductFactory` states `featured()` and `inCategory(Category)`.
- The seeder creates Fruit and veg (Apple), Drinks (Water, Beer) and Dairy (Cheese Triangle), and features all four products.

## 4. Behaviour, page by page

The mockup each item matches is named in brackets; every one has a dark twin on the canvas.

### 4.1 Shared parts

- **Header [Header]:**
  - announcement bar ("Free pickup on every order. New accounts start with $100 of credit.")
  - logo, search and Shop link
  - for signed-in users: Orders and the wallet balance
  - for guests: Log in
  - the theme button and Basket with its count
  - the awning with its scalloped edge
  - The current section is marked.
- **Phone header [Phone header]:** a menu button, the centred logo, the basket with a count badge, and a full-width search bar under it. The menu opens the slide-out panel [Phone: menu]: wallet balance with a Top up button, links, aisle chips, the theme control and Log out.
- **Footer [Footer]:** logo and line, then Shop, Your account and Help link columns, and the copyright.
- **Featured carousel [Featured carousel]:**
  - Shows up to 8 featured products, newest first: 3 at a time on desktop, 2 on tablets, 1 on phones.
  - Arrows and dots move it, and it wraps at the ends.
  - Cards outside the view are skipped by the keyboard, and the position is announced to screen readers.
  - If no product is featured, the section is not rendered.
  - On phones the row is swipeable, with the next card peeking in.
- **Product card:** photo on `well`, price tag, name, rating or "No ratings yet". On desktop, a joined quantity-and-basket bar; on phones, a full-width "Add" button.

### 4.2 Home [Home]

The order is: hero with the "Today's prices" chalkboard; Featured at the stall; Shop by aisle; On the counter today; How shopping works; the wallet band; Getting it home (delivery options); Questions (FAQ); footer.

- **Chalkboard:** lists the four newest products with their prices; each row links to the product.
- **Shop by aisle:**
  - Shows at most 6 categories that have products, ordered by product count (descending) and then name, with a "See all aisles" link to the shop.
  - Each tile shows the aisle name, its product count, its photo (per `imageUrl`) and its tint.
  - The section is hidden when no category has products.
- **On the counter today:** the four newest products, as today.
- **Delivery:** both options come from the shipping methods table: Pickup (free) and UPS delivery ($5.00).
- **FAQ:** four questions, written in the component. The first is open by default.

### 4.3 Shop [Shop, Shop: no results, Phone: shop]

- **Sidebar filters:**
  - search
  - **Aisle:** checkboxes with counts
  - **Price:** shortcut buttons "Under $1", "$1 to $2" and "$2 to $5", plus min and max fields
  - **Minimum rating:** radios
  - **Only rated products**
  - **Featured products only**
  - **Sort**
  - **Clear filters**
- **New query parameters:**
  - `aisle[]`: category slugs. Each must exist; at most 20.
  - `rated`: boolean. Products with at least one rating.
  - `featured`: boolean.
  - Validation follows the existing `ProductIndexRequest` style. An unknown aisle returns a validation message, never a server error.
- **Price shortcuts:** these only fill in min and max on the client; no new backend.
- **Below the grid:** pagination, then the featured carousel.
- **No results:** the active filters show as removable chips with "Clear all", tips for a better search, aisle shortcuts, and the featured carousel.
- **On phones:** a Filters button opens a bottom sheet holding the same filters, ending in "Show N products". There is a sort dropdown and a swipeable row of aisle chips.

### 4.4 Product [Product]

- A breadcrumb: Shop / aisle / product. The aisle part is omitted when the product has no category.
- Then the large photo, name, rating, the large price tag, description, quantity and "Add to basket", and the "Your rating" stars.
- The featured carousel sits below, excluding the product being viewed.

### 4.5 Added to basket [Added to basket]

- Adding a product from any page opens a panel that slides in from the right:
  - the item with quantity and line total, and the basket item count and total
  - "View basket and check out" and "Keep shopping"
  - "Goes well with": up to 2 featured products not already in the basket, each with a quick add button
- **How it gets its data:** the add-to-basket endpoint keeps redirecting back. It flashes an `addedToBasket` payload with the product, quantity, line total and basket summary, plus the suggestions. The panel opens when that flash is present.
- **Closing:** Escape, the close button, or a click on the dimmed page closes it, and focus returns to the button that opened it.
- **Phones:** the panel is full width.

### 4.6 Basket and checkout [Basket, Basket: wallet too low, Basket: empty, Phone: basket]

- **Layout:**
  - Line items, each with quantity and remove.
  - An order summary with "How do you want it?" (option cards for the shipping methods) and "Pay with".
  - The total and "Place order and pay $X".
- **Pay with, in this sub-project:** it lists the wallet only, as an option card showing the balance. The layout is built so that sub-project 3 adds Stripe and PayPal as extra option cards without changes.
- **Wallet too low:** when the balance is below the total, the wallet card is disabled and reads "Balance $X. You need $Y more.", with a "Top up your wallet" link. Until payments exist, "Place order" is disabled in this state.
- **Empty basket:** a basket illustration, "Your basket is empty", a "Browse the stall" button, and the featured carousel.
- **Phones:** the total and "Place order" sit in a bar pinned to the bottom of the screen.

### 4.7 Orders and receipt [Orders, Receipt: wallet]

- **Orders list:** each order shows its product thumbnails, number, date, item count, delivery, a "Paid with wallet" tag, the status chip and the total.
- **Empty orders state:** "Nothing bagged yet." with a "Browse the stall" button.
- **Receipt:**
  - The success banner (shown right after checkout) and the order details.
  - A paper receipt with a torn edge, listing the items, subtotal, delivery, total and the wallet balance afterwards.
  - Card and PayPal receipts are designed but arrive with sub-project 3.

### 4.8 Wallet [Wallet]

- The balance on a chalkboard.
- "Add credit": quick amounts ($10, $25, $50, $100), the amount field and the button.
- The history table: date, what, amount and balance after.
- **In this sub-project:** top-ups stay free credit, so the "Pay with" choice is not shown and the button reads "Add $X to my wallet". Sub-project 3 adds the payment choice exactly as drawn.

### 4.9 Log in, account and settings [Log in]

- **Log in:** the form card beside a "Why sign up?" chalkboard.
- **Same treatment:** register, forgot password, reset password, two-factor challenge and confirm password.
- **Settings pages** (profile, security, appearance): restyled with the same tokens and components.

### 4.10 Page not found [Page not found]

- Any 404 renders an Inertia page: a tilted "Closed" chalkboard reading "This stall has packed up for the day", then "We can't find that page", and buttons to the home page and the shop.
- It's wired through the exception handler for `NotFoundHttpException` and for missing models.

### 4.11 Breakpoints

- **Phone:** below 640px. Phone header, two-column grids, swipeable rows, bottom sheets.
- **Tablet:** 640 to 979px. Carousel shows 2 cards; the shop sidebar stacks above the grid.
- **Desktop:** 980px and up.

No page scrolls sideways at 360px wide.

## 5. Front-end structure

This follows the existing conventions: `common` for shared building blocks, `modules` for feature code, `pages` for Inertia pages, and `lib/shadcn` for third-party code.

- `common/`:
  - `AppLogo`, `StallButton` (marigold, green, outline and link variants, with the press motion)
  - `PriceTag`, `Chalkboard`, `SectionHeading`, `OptionCard`, `Chip`, `StatusChip`, `QuantityBasketBar`
  - `Sheet` (slide-in panel: right, left, bottom), `IconButton`, `ThemeToggle`
  - The existing input, select, dialog, pagination and toast wrappers are restyled.
- `modules/layout/`: `StoreHeader`, `PhoneHeader`, `PhoneMenu`, `StoreFooter`, `Awning`.
- `modules/products/`:
  - existing: `ProductCard`, `ProductFilters`, `RatingInput`, `StarRating`
  - new: `FeaturedCarousel`, `AisleTile`, `FilterSheet`, `ActiveFilterChips`
- `modules/cart/`:
  - existing: `CartLineItem`, `CartSummary`, `CheckoutPanel`, `QuantityStepper`
  - new: `AddedToBasketPanel`, `PayWithOptions`
- `modules/home/`: `HowItWorks`, `DeliveryOptions`, `FaqList`.
- `pages/`: the existing pages are rebuilt, plus `errors/NotFoundPage.vue`.

The mockup HTML on the canvas is a visual reference, not code to copy. Components are written in the project's own Vue, TypeScript and Tailwind style.

## 6. Voice and copy

From the Brand page: plain first, a pinch of market flavour (never in buttons or errors), and exact about money.

The interface uses these words: **basket** (not cart), wallet, **top up**, **aisle**, order, receipt, pickup, **log in**. URLs and route names stay as they are (`/cart` and so on). Only the visible text changes.

## 7. Product photos

For admins in sub-project 4, and for the seeder now:
- square, at least 1200 × 1200px
- JPG or WebP, under 1 MB
- one product, centred, filling 70 to 85% of the frame
- a plain light background
- no text added on top
- up to 5 photos per product, the first being the main one

## 8. Accessibility

- Visible focus on everything (a 3px marigold outline).
- Real buttons, links and labels.
- Every icon-only button is labelled.
- The carousel has carousel roles and announces its position.
- Panels and sheets trap focus, close on Escape, and return focus when closed.
- Colour is never the only signal; for example, a selected option also has a thicker outline.
- Reduced motion is respected.

## 9. Testing

- **Feature tests (Pest):**
  - **Categories:**
    - the product relationship
    - `nullOnDelete`
    - `imageUrl` falls back to a product photo, then to `null`
  - **Home page props:**
    - aisles capped at 6, ordered by product count, empty ones hidden
    - featured products: only featured, newest first, at most 8, and an empty list when none are featured
  - **Shop filters:**
    - aisle with one or several slugs
    - an unknown slug gives a validation error, not a 500
    - `rated` and `featured`
    - each combined with the existing filters
  - **Product page:**
    - the breadcrumb with and without a category
    - the carousel excludes the current product
  - **Added to basket:**
    - the flash payload is present after adding
    - suggestions are featured products not already in the basket, at most 2
  - **Checkout:**
    - when the wallet is too low, placing an order is refused, as today, and the page shows the disabled wallet state
  - **404s:** an unknown URL renders the not-found page with status 404.
- **Browser tests (Pest browser plugin):**
  - Update the existing checkout and top-up journeys to the new screens.
  - **New:**
    - the carousel's next button moves the row
    - picking an aisle filters the grid
    - the added-to-basket panel opens, and closes on Escape
    - the theme button switches to dark mode, and it survives a reload
    - no sideways scroll at 360px
- **Quality:** Pint, Larastan level 7, Oxlint and vue-tsc stay green, locally and in CI.
- **README:** screenshots retaken in light and dark mode.

## 10. Definition of done

- Every page in section 4 matches its canvas mockup in light and dark mode, at desktop and phone widths.
- All tests pass on SQLite and on MySQL.
- A manual walkthrough at `https://cartopia.test` works in both themes.
- The README is updated.
- The branch `store-redesign` goes into `main` through a pull request, with no AI attribution in commits or the PR.

## 11. Decisions and risks

- **Theme switching reuses the starter kit's `useAppearance`** rather than new code. Risk: low; it is already tested by the kit.
- **The added-to-basket panel is driven by a flash payload** instead of a separate JSON endpoint, so the existing redirect-back flow stays. Risk: the panel can't show for a product added somewhere that doesn't redirect back; today none exist.
- **Checkout stays wallet-only until sub-project 3.** When the balance is too low, the shopper must top up first, so the "Place order" button is disabled rather than offering card or PayPal.
- **Aisle tints come from list position, not data.** Risk: an aisle's tint changes if the aisle order changes. Accepted as harmless.
- **The fonts add roughly 150 KB to the first load.** Mitigation: only the weights in section 2.2 are bundled, using `font-display: swap`.
