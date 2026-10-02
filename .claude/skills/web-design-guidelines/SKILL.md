---
name: web-design-guidelines
description: Review and audit UI code for Vercel Web Interface Guidelines compliance. Use when asked to "review UI", "check accessibility", "audit design", "review UX", or check web interfaces against modern standards.
metadata:
  author: vercel
  version: "1.0.0"
  argument-hint: <file-or-pattern>
---

# Web Interface Guidelines

Review UI components and frontend code for compliance with standard Web Interface Guidelines. Output findings grouped by severity (Critical, Warning, Suggestion) with concise `file:line` references.

---

## 1. Accessibility (a11y)

* **Icon-only buttons**: Always provide an `aria-label` (e.g. `<button aria-label="Fermer le menu">`).
* **Form controls**: Every `<input>`, `<select>`, and `<textarea>` must have an associated `<label>` (via `for`/`id`) or `aria-label`.
* **Keyboard navigation**: Interactive elements must support keyboard events (`Enter`, `Space`, `Escape`, arrow keys where applicable).
* **Semantic HTML**:
  - Use `<button>` for actions that trigger events.
  - Use `<a>` / `<Link>` for navigation to a URL. Never `<div onclick>`.
* **Images**: Every `<img>` requires an `alt` attribute (provide descriptive text or `alt=""` if strictly decorative).
* **Decorative icons**: Mark with `aria-hidden="true"`.
* **Live regions**: Announce async updates, toasts, or validation messages with `aria-live="polite"`.
* **Heading hierarchy**: Use `<h1>` through `<h6>` in logical semantic order without skipping levels.

---

## 2. Focus States

* **Visible Focus Indicators**: Interactive elements must have clear focus rings, e.g. `focus-visible:ring-2 focus-visible:ring-[#00897b] focus-visible:outline-none`.
* **Never bare outline-none**: Do not write `outline: none` or `outline-none` without providing an explicit focus indicator replacement.
* **Use `:focus-visible`**: Target `:focus-visible` instead of `:focus` to keep clicks clean while keeping keyboard tab navigation accessible.
* **Sticky Elements**: Ensure sticky navbars and modal overlays do not obscure currently focused elements.

---

## 3. Forms & User Input

* **Autocomplete**: Provide standard `autocomplete` attributes (e.g. `autocomplete="name"`, `autocomplete="email"`).
* **Appropriate Input Types**: Use `type="email"`, `type="tel"`, `type="date"`, or `inputmode="numeric"`.
* **Never Block Paste**: Do not disable `onPaste` on inputs.
* **Clickable Labels**: Clicking a `<label>` must focus or activate its input.
* **Inline Errors**: Display validation errors immediately adjacent to the relevant input field.
* **Submit States**: Keep submit buttons interactive until the request starts, then disable and show a loading spinner.

---

## 4. Animation & Motion

* **Reduced Motion**: Respect `prefers-reduced-motion: reduce` by dampening or disabling decorative animations.
* **GPU Composited**: Animate only `transform` and `opacity` for 60fps smoothness.
* **Explicit Transitions**: Avoid generic `transition: all`; specify target properties (e.g. `transition: transform 0.2s ease, opacity 0.2s ease`).

---

## 5. Typography & Punctuation

* **Ellipsis**: Use proper character `…` instead of three separate periods `...`.
* **Widow Prevention**: Use `text-wrap: balance` or `text-wrap: pretty` on headlines.
* **Tabular Numbers**: Use `font-variant-numeric: tabular-nums` or `font-mono` for clock times, financial tables, counters, and statistics to prevent layout jitter.

---

## 6. Content Handling & Robustness

* **Overflow & Truncation**: Use `truncate`, `line-clamp-*`, or `break-words` on user-generated or dynamic text containers.
* **Flexbox Child Truncation**: Apply `min-w-0` to flex child containers so text truncation works properly.
* **Empty States**: Never display raw empty blocks or broken layout when data lists are empty. Provide a clean, helpful empty state card.

---

## 7. Images & Performance

* **CLS Prevention**: Set explicit `width` and `height` (or aspect-ratio) on images to avoid Cumulative Layout Shift.
* **Lazy Loading**: Apply `loading="lazy"` on below-the-fold assets; prioritize above-the-fold critical assets.
