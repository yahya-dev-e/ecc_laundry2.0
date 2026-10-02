---
name: awesome-design-md
description: Reference and apply DESIGN.md specifications from 70+ leading tech brands (Linear, Stripe, Vercel, Apple, Claude, Raycast, etc.). Use when styling pages to match an established brand aesthetic or when creating or adhering to a DESIGN.md design system.
---

# Awesome DESIGN.md Protocol & Brand Profiles

The `DESIGN.md` protocol provides a structured, plain-text markdown specification that defines how a project's user interface should look, feel, and behave.

Use this skill to adopt established design languages from 70+ top tech brands or generate a comprehensive `DESIGN.md` for a project.

---

## 1. What is a DESIGN.md?

| File | Read by | What it Defines |
|---|---|---|
| `AGENTS.md` | Coding Agents | Architecture, commands, coding rules |
| `DESIGN.md` | Design & UI Agents | Visual style, color tokens, typography, component rules |

A `DESIGN.md` document captures:
- Design Philosophy & Personality
- Color Palette & Semantic Tokens
- Typography Scale & Font Pairings
- Spacing, Elevation & Corner Radii
- Core Component Patterns (Buttons, Cards, Inputs, Badges, Navigation)
- Motion & Interactive Feedback

---

## 2. Established Brand Archetypes & Profiles

### A. Linear (Dark Precision & High Productivity)
- **Palette**: Deep void black (`#08090a`), dark graphite surface (`#121316`), subtle slate borders (`#22242a`), vibrant purple/indigo accent (`#5e6ad2`).
- **Typography**: Inter or Geist, strict tabular numbers for counters, tight tracking (`tracking-tight`), uppercase micro-labels with wide letter spacing.
- **Components**: Border-subtle cards with inset highlights, keyboard shortcut badges (`kbd`), crisp 1px borders, smooth micro-interactions.

### B. Vercel (Black & White Technical Minimalism)
- **Palette**: Pure black (`#000000`), pure white (`#ffffff`), monochrome grays (`#111111`, `#666666`, `#eaeaea`).
- **Typography**: Geist Sans + Geist Mono. Strict hierarchy, balanced headlines (`text-wrap: balance`).
- **Components**: Razor-sharp or minimal 6px radius, geometric simplicity, high contrast, zero unnecessary decoration.

### C. Claude / Anthropic (Warm Editorial & Humanist)
- **Palette**: Warm cream/stone canvas (`#fbf8f3`), deep charcoal ink text (`#1f1e1d`), terracotta/coral accent (`#d97706` / `#c2410c`).
- **Typography**: Classic editorial serif or refined sans with generous line-height (`leading-relaxed`), spacious reading bands.
- **Components**: Soft pill buttons, warm card backgrounds, subtle amber/terracotta borders.

### D. Stripe (Vibrant Financial Infrastructure)
- **Palette**: Crisp white canvas, rich slate navy (`#0a2540`), vibrant brand violet/cyan/emerald accents (`#635bff`, `#00d4b6`).
- **Typography**: Soehne or clean modern grotesk, bold feature headlines, punchy CTA buttons.
- **Components**: Layered depth with soft ambient colored drop shadows, rich responsive navigation dropdowns, clean tabular layouts.

### E. Apple (Refined Hardware & Glassmorphic Restraint)
- **Palette**: Light silver-gray (`#f5f5f7`), pure white cards (`#ffffff`), dark slate typography (`#1d1d1f`), system blue (`#0071e3`).
- **Typography**: SF Pro / clean neo-grotesk, massive hero headlines with smooth contrast.
- **Components**: Generous border-radius (`rounded-2xl`, `rounded-3xl`), bento tile grids with asymmetric rhythm, subtle backdrop blur filters (`backdrop-blur-md`).

---

## 3. How to Generate or Apply a DESIGN.md

When generating or updating a `DESIGN.md` in any project:
1. Define the **Design Philosophy**: Core aesthetic statement and visual priorities.
2. Specify **Color Tokens**: Exact hex codes for `background`, `surface`, `border`, `text-primary`, `text-secondary`, `accent`, and `status`.
3. Declare **Typography Rules**: Primary font family, monospace font family, font size scale, line heights, and weights.
4. Establish **Spacing & Radii**: Standard padding scale, card corner radii, and container widths.
5. Document **Component Conventions**: Styling specifications for Primary Buttons, Secondary Buttons, Form Controls, Cards, and Badges.
