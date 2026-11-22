# 031 Verification

Run in the app with Playwright (Chromium, software WebGL) as admin on `/ansatt/tarn`, with a MutationObserver on `#tw-sync` recording every change of `hidden` and `class`.

Normal motion: the panel was shown 472 ms after Gulv was opened, got the class `done` at 8832 ms and was hidden after that. Over 25 seconds with polls every 5 seconds it was not shown again, and switching to Transport and back to Gulv did not show it (short resync only). The sequence took longer than its 5.2 seconds of animation time because software WebGL renders slowly; the timeline is driven by `performance.now()`, so slow frames shorten the animation instead of stretching it, and the wait was the data step.

Reduced motion (`emulateMedia({ reducedMotion: 'reduce' })`): the panel was never shown, and the floor showed its 7 station labels.

Rejected: sampling `#tw-sync` with `page.evaluate` every 200 ms. Under software WebGL the main thread was busy and the samples only resolved after the sequence, so they always read hidden. The observer records the changes as they happen.

Known weaknesses: not checked on a real GPU in this round; the 900 ms resync scan was confirmed by code reading and by the absence of the full panel, not timed.
