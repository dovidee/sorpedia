# 019 Chat panel redesign: Søren as a glass panel that grows out of his head

## Goal

The Søren chat is small, light and round and sits on the page instead of covering it. The launcher is Søren's head in a round badge, low in the corner. The panel is frosted glass, so the page shows through, and it uses the store's own tokens, so it follows light and dark mode and the category accent of the page.

## Scope

Presentation of the launcher (`templates/layout.php`), the panel markup built in `public/assets/app.js` and the chat rules in `public/assets/app.css`. Not in scope: the chat backend, conversation state, streaming, the product guide, the language model, guardrails and the Søren figure and its animations (`public/assets/soren.js`), which stay unchanged.

## Acceptance criteria

1. The launcher is Søren's head in a round badge with a red ring, with no text label (his name is shown inside the chat). It sits low in the bottom right corner. It has an accessible name and a visible focus ring.
2. The panel is a rounded glass surface (blurred backdrop) built on the colour tokens, so it reads in light and dark mode and takes the category accent where the customer's own messages are tinted. Without backdrop blur support it falls back to the solid surface.
3. Opening: the panel grows out of the head, spinning open from a point. Closing: it spins and collapses back into the head. The transform origin is set on the launcher. With reduced motion the panel appears and disappears without animation.
4. Compact type (body around .84rem, line height 1.45) and round bubbles. No all caps labels, no middle dot separators.
5. Product cards in an answer show only name, price, stock and whether a guide exists, from the database.
6. The context field shows the page Søren sees and that he gets product info only. It is hidden when there is no page context.
7. Input: a round field with a round red send button with an accessible name. Enter sends.
8. On larger screens the panel is 360 by 520 px (capped by the viewport) and sits above the launcher. Below 768 px the open panel fills the screen, keeps the input visible and hides the launcher.
9. Focus goes to the input when the panel opens and back to the launcher when it closes. Clicking the head again closes the panel.
10. No sideways scroll at 390, 820 and 1366 px.
