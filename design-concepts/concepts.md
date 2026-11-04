# Chilli 鍋 — Design Concepts (exploration only, nothing live changed)

These are **standalone prototype files** — they do not touch any `.php`, `.css`, or `.js`
in the real app. Open each HTML file in a browser to compare. Real brand content
(menu names, prices, ingredients) is pulled from `database/sample_menus.sql` /
`sample_ingredients.sql` so the comparison feels true to the actual product.

No external image URLs are used (can't verify what you'd actually get), so
"food photography" is mocked with warm gradient/photo-placeholder blocks labelled
accordingly — once you pick a direction, real photos replace these 1:1.

How to look: `design-concepts/index.html` → click into each concept.

---

## Concept A — Japanese Modern (refined evolution of current style)

1. **Direction**: Calm, editorial, restaurant-brochure quality. Keeps your current
   warm/red palette but adds typographic elegance and breathing room so it reads
   premium instead of "Bootstrap default."
2. **Colors**: `#FAF6F2` bg, `#FFFFFF` surface, `#C8261B` chili red primary,
   thin `#B8860B` gold hairline accent, `#2A1F1D` ink, `#7A6E68` muted.
3. **Typography**: Headings in **Shippori Mincho** (elegant JP serif), body in
   Noto Sans JP. Wide letter-spacing on short labels, large hero title.
4. **Buttons**: Rectangular, 6px radius, confident single red CTA per screen,
   everything else outline/ghost. No pill shapes.
5. **Cards**: Minimal border + soft shadow, 4:3 photo, thin gold rule between
   name and price, generous internal padding (24px not 12px).
6. **Navigation**: Centered logo, slim top bar, understated underline on active
   link — no color-block nav.
7. **Food images**: Fixed 4:3, warm color grading, no filters/stickers over photos.
8. **Ingredient UI**: Quiet checklist rows with a circular check mark, category
   as a simple text tab row, no bright chips shouting for attention.
9. **Example screen**: Hero → 3-step explainer → menu grid → footer.
10. **Why it fits**: Japanese diners read understatement as *quality*. This
    direction signals "real restaurant," reassures older/returning customers,
    and differentiates you from generic delivery apps.

---

## Concept B — Modern Food Delivery

1. **Direction**: Borrows the usability DNA of Uber Eats / Demae-can / Wolt —
   optimized for fast mobile ordering, not browsing.
2. **Colors**: White bg, light-gray surface `#F7F7F8`, energetic red-orange
   primary `#FF4B3E`, yellow `#FFC700` for badges/highlights.
3. **Typography**: Noto Sans JP, bold weights everywhere, big tabular price
   numbers, no serif.
4. **Buttons**: Fully rounded pill, bold, big tap targets (48px+), a sticky
   bottom price bar on mobile.
5. **Cards**: 16–20px rounded corners, strong shadow, image bleeds to card
   edge, floating heart icon, stepper appears directly on hover/tap.
6. **Navigation**: App-like top bar with a prominent search field; persistent
   bottom tab bar even on desktop-width previews.
7. **Food images**: Square/round crops, punchy contrast, badges overlaid
   directly on the photo (人気, 期間限定).
8. **Ingredient UI**: Tile grid, thumbnail + name + price + inline `− 1 +`
   stepper; selected tile gets a colored ring; running total bar is sticky
   and always visible while scrolling.
9. **Example screen**: Search bar → horizontal category chips → menu tiles →
   sticky "¥1,280 カートに追加" bar.
10. **Why it fits**: Matches the mental model Japanese users already have from
    daily delivery-app use — zero learning curve, very fast to order, great
    for repeat/lunch-rush customers on mobile.

---

## Concept C — Premium Asian Restaurant

1. **Direction**: Dark, moody, brand-first — feels like a restaurant's own
   site, not a marketplace listing. Less "app," more "destination."
2. **Colors**: Near-black bg `#15100F`, dark surface `#231A18`, deep red
   `#8B1E1E`, gold accent `#C9A05C`, warm cream text `#EDE4DA`.
3. **Typography**: Large serif headings (Shippori Mincho / Noto Serif JP),
   thin body sans, wide-tracked English subtitle ("MALATANG · 麻辣香鍋").
4. **Buttons**: Mostly gold outline, fills on hover — restrained, no loud CTAs.
5. **Cards**: Borderless, image-forward, thin gold hairline instead of shadow,
   small-caps captions.
6. **Navigation**: Transparent over a full-bleed hero photo, solidifies to
   dark on scroll, logo centered, hamburger menu for a minimal top bar.
7. **Food images**: Full-bleed, cinematic, slight dark vignette so text sits
   on top directly — fewer, larger images rather than many small ones.
8. **Ingredient UI**: Editorial list with a gold check mark instead of chips;
   spice level shown as filled/outline chili glyphs, not colorful buttons.
9. **Example screen**: Full-screen hero photo + logo → short brand story line
   → curated 3-item "おすすめ" row → reservation/order CTA.
10. **Why it fits**: Positions Chilli 鍋 above generic malatang chains for
    customers who read "premium" as dark + serif + gold — useful if you also
    want to attract shop-owner franchisees who care about brand perception.

---

## Concept D — Friendly Japanese Casual

1. **Direction**: Warm, approachable, zero intimidation — best for first-time
   customers who've never ordered customizable 麻辣湯 before.
2. **Colors**: Cream/yellow bg `#FFF8EC`, friendly orange primary `#FF8A3D`,
   fresh green accent `#5BA85B` for vegetables, soft shadows only.
3. **Typography**: **M PLUS Rounded 1c** for headings (soft, friendly), Noto
   Sans JP body, bigger base font size than the other concepts.
4. **Buttons**: Very rounded pill, soft shadow, generous padding, playful but
   not childish.
5. **Cards**: 20px+ rounded corners, pastel category tag chips, small
   illustrative icons alongside photos (e.g. 🌶 for spice) rather than text-only.
6. **Navigation**: Simple top bar + always-visible bottom tab bar with icon +
   label, one obvious "はじめる" (get started) CTA.
7. **Food images**: Rounded-corner photo cards, no dark overlays — keeps
   everything bright and appetizing rather than moody.
8. **Ingredient UI**: Numbered guided steps ("① 味を選ぶ → ② 具材を選ぶ →
   ③ 辛さを選ぶ"), big friendly flame icons for spice level with words next
   to them (やさしい / ふつう / からい / 激辛), large +/− buttons.
9. **Example screen**: "はじめての方へ" banner → 3-step visual guide → menu →
   guided customize flow.
10. **Why it fits**: Lowers the barrier for Japanese customers unfamiliar with
    麻辣湯/麻辣香鍋 customization — explains the product instead of assuming
    they already know how to order it.

---

## Mix-and-match is expected

You can say things like "Concept A's colors and typography, Concept B's
sticky price bar and ingredient tiles, Concept D's numbered guide for
first-time customers" — the components are intentionally built the same way
(header → hero → menu grid → ingredient UI → footer) so pieces can be swapped.
