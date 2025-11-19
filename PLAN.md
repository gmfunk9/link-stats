# PLAN

1. Review the current sitemap/link-check flow (`index.php`, `assets/js/scripts.js`, `check_links.php`, `src/*`) to confirm where cached link data is stored and where each HTTP timeout is raised.
2. Update the front-end logic so every new scan clears the previous summaries, tracks any link errors, and surfaces them in both the summary statuses and the feedback panel; mirror the new state handling in the CSS so failures stand out.
3. Tighten the back-end logging inside `src/interlinks.php` so every interlink fetch failure or timeout writes a clear entry to `errlog.log`, which makes live deployments easier to debug.
4. Validate by triggering a fresh sitemap scan from the UI, noting that clearing the cache still works, and confirming the feedback panel now reports when links fail because of a timeout or other fetch error.
