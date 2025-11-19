Implementing resume behavior required careful coordination between the UI and the cache rate limiter.

1. The UI now stores the HTML snapshot of the link tree plus `lastProcessedIndex`/`totalUrls` in `localStorage` so clicking “Check Links” can rehydrate whatever completed pages remain visible while still enqueueing the next batch.
2. Each fetch of `src/sitemap.php` now carries a `resumeIndex`, and the backend normalizes that number before applying the 100‑page (or smaller) rate limit so it does not go back to the first URL when local cache data exists.
3. Clearing the server cache before resuming used to reset rate limits while leaving the UI slate untouched, so we now delete the stored payload and reset the cached indexes whenever the “Clear Local Cache” action finishes. That was the tricky bit: syncing the persisted DOM, the client’s progress pointer, and the server-side rate limiter so everything stays continuable instead of restarting.
