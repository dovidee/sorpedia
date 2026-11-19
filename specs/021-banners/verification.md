# 021 Verification

Run in the app (`php -S 127.0.0.1:8099 -t public`, headless Chromium) before the sawdust layer was removed: home banner at 1366 and 390 px, Lys og lykter at 1366 px, Tur og friluft at 390 px, all seven `/tema/*` pages returned 200.

Sawdust layer removed: the `spread1.png` image, its CSS and the file are gone; `lowsaw.png`, `highsaw.png` and the crossfade are unchanged. PHP lint passed on `templates/home.php`, and no reference to the spread files remains in `templates/home.php`, `public/assets/app.css` or `public/assets/app.js`.

Not verified: the home page in the browser after the removal, 820 px, English banners.
