IMPORTANT UPDATE — SØREN ANIMATION AND TRANSPARENCY

Our website's primary background is #221C1C.

The existing MP4s have solid backgrounds, so simply placing them inside the interface will not produce proper transparency.

Use the following approach:

Preserve all original MP4s and the original 3×3 Søren pose grid.
Inspect the MP4s frame by frame using FFmpeg.
Use them as visual references for recreating the original Søren character animations.
Søren is our own original character. Preserve his exact visual identity, proportions, expressions, movements and personality.
Recreate the animations without third-party branding or watermarks, using our original character assets and creative direction.
Produce clean transparent animation assets. Prefer WebM with alpha where supported.
Do not use solid white or black backgrounds as substitutes for transparency.
Do not use CSS blend modes to simulate transparency.
Do not overwrite, delete or modify the original source files.
Test transparency against our actual #221C1C dark theme and existing light theme.
SØREN STATE MAPPING

Implement the following animation selection:

Application context	Søren animation
Frontpage	Default
Chat greeting	Wave
Processing/thinking	Thinking
Uncertain question	Shrug
Successful interaction	Celebrating
Explaining/recommending	Pointing
Empty shopping cart	Cart
404 page	Hammer
Empty inbox	Lumber

In the chatbot, select Wave, Thinking, Shrug, Celebrating or Pointing according to the conversation context.

Use sensible fallback behaviour if an animation is unavailable.

IMPLEMENTATION

Build one reusable Søren animation component.

Requirements:

Dark and light theme support.
Transparent backgrounds.
Responsive sizing.
Smooth animation transitions.
Reduced-motion support.
Static fallback.
No white rectangular backgrounds.
No visible Luma branding.
No changes to existing working application functionality.
IMPORTANT WORKFLOW

Before implementation:

A. Inventory all existing assets. B. Generate chronological contact sheets from the MP4 frames. C. Identify the correct animation for each pose. D. Demonstrate one successfully recreated transparent animation against #221C1C. E. Only after approval, process the remaining animations.

Do not regenerate Søren from scratch.

Do not replace the original animation references.

The objective is to preserve our character and animation performance while producing clean, transparent, production-ready Sørpedia assets.

The Luma videos were generated using our character and creative direction. We did not use Luma's own characters or animations



Use SOREN\_MP4.zip; if you need additional help to recreate them, use SOREN\_SPRITE.zip as a backup (read SOREN\_SPRITE.md first before using it).

