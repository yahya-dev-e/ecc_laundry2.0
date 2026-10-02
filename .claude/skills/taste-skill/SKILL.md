---
name: taste-skill
description: Anti-slop frontend design framework for landing pages, web apps, portfolios, and redesigns. Use when creating or refining UI to avoid generic AI templates, ensuring distinct typography, thoughtful color calibration, asymmetric layouts, and premium aesthetics.
---

# Taste Skill: Anti-Slop Frontend Framework

Anti-slop frontend guidelines for landing pages, web applications, portfolios, and redesigns.
Every rule below is contextual. First read the brief, then apply what fits the product.

---

## 0. Brief Inference (Read the Room First)

Before writing UI code, infer what the project and user actually need:

1. **Page kind**: Landing page (SaaS, consumer, agency, event), web application dashboard, portfolio, redesign, editorial.
2. **Vibe cues**: "Minimalist", "Linear-style", "Awwwards", "brutalist", "premium consumer", "Apple-like", "playful", "serious B2B", "editorial", "glassy", "dark tech".
3. **Audience**: Technical buyers vs design-conscious consumers vs enterprise admin. The audience dictates the aesthetic.
4. **Anti-Default Discipline**: Never default to AI-purple gradients, centered hero over dark mesh, three identical feature cards, generic glassmorphism on everything, or Inter + slate-900.

---

## 1. The Three Dials

Calibrate these 3 dials based on the design context:

* **`DESIGN_VARIANCE` (1 to 10)**: 1 = strict symmetry, 10 = artsy asymmetry.
* **`MOTION_INTENSITY` (1 to 10)**: 1 = static, 10 = cinematic physics.
* **`VISUAL_DENSITY` (1 to 10)**: 1 = airy art gallery, 10 = dense cockpit data.

| Context | Variance | Motion | Density |
|---|---|---|---|
| Minimalist / clean / Linear-style | 5-6 | 3-4 | 2-3 |
| Premium consumer / Apple-like / Brand | 7-8 | 5-7 | 3-4 |
| Playful / Agency / Experimental | 9-10 | 8-10 | 3-4 |
| SaaS / Web App Dashboard | 5-7 | 4-6 | 5-7 |
| Trust-first / Public-sector | 3-4 | 2-3 | 4-5 |

---

## 2. Typography & Font Discipline

* **Display / Headlines**: `text-4xl md:text-6xl tracking-tight font-bold`.
* **Body / Paragraphs**: `text-base text-slate-600 leading-relaxed max-w-[65ch]`.
* **Sans Font Choices**:
  - Prefer modern character: `Geist`, `Outfit`, `Cabinet Grotesk`, `Satoshi`, `Plus Jakarta Sans`.
  - Avoid generic Inter unless specifically asked for neutral enterprise B2B.
* **Serif Discipline**:
  - Do NOT default to serif simply to feel "premium" or "creative".
  - Use serif only when the brand explicitly calls for editorial/heritage luxury.
  - Never mix random serif words inside a sans headline for emphasis—use italic or bold of the *same* font family.
* **Descender Clearance**:
  - When italic is used in large headlines and contains descenders (`g, j, p, q, y`), ensure `leading-[1.1]` minimum to prevent clipping.

---

## 3. Color Calibration & Contrast

* **Single Accent Color**: Pick one cohesive accent color and maintain it across the entire page/flow.
* **No AI Gradient Slop**: Eliminate unnecessary purple/blue neon glows or floating mesh blobs.
* **Neutral Foundations**: Use curated neutral scales (Zinc, Slate, or warm Stone) paired with a high-contrast accent (Emerald, Electric Indigo, Burnt Amber, Deep Teal).
* **WCAG AA Contrast**:
  - Normal text: minimum 4.5:1 contrast against background.
  - Large text (18px+ bold): minimum 3:1 contrast.
  - Never use light gray text on light backgrounds or low-contrast placeholder text.

---

## 4. Layout Mechanics & Hero Rules

* **Hero Viewport Fit**:
  - Hero must comfortably fit in the desktop viewport without requiring immediate scroll to find primary CTAs.
  - Max 2 lines for main headline; subtext under 25 words.
* **Stack Discipline**:
  - Hero should have at most 4 elements: (1) optional eyebrow badge, (2) headline, (3) concise subtext, (4) primary & secondary CTA.
  - Social proof logos ("Trusted by") belong *under* the hero, not crammed inside it.
* **Bento Grids & Rhythm**:
  - Avoid 6 identical cards in a row. Vary sizes: combine 2-col with 1-col spans, asymmetric cards, and tinted backgrounds.
  - Do not leave empty cells in a grid.
* **Mobile Responsiveness**:
  - Standardize breakpoints (`sm: 640px`, `md: 768px`, `lg: 1024px`, `xl: 1280px`).
  - Declare explicit mobile fallbacks for multi-column layouts (`grid grid-cols-1 md:grid-cols-3`).

---

## 5. Component States & Polish

* **Interactive States**:
  - Buttons: Provide hover transitions, focus rings (`focus-visible:ring-2`), and tactile active states (`active:scale-[0.98]` or `active:translate-y-[1px]`).
  - Single line CTA text: Button text must never wrap into multiple awkward lines on desktop.
* **Feedback States**:
  - Always design loading skeletons that mirror content shape, meaningful empty states with clear action triggers, and inline contextual error messages.
