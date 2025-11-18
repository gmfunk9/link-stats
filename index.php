<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Link Checker</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
    <main class="app">
        <header class="app__header">
            <p class="eyebrow">Diagnostics</p>
            <h1>Link Checker</h1>
            <p class="lede">Track sitemap URLs, monitor background jobs, and drill into link health at a glance.</p>
        </header>
        <section class="app__panel app__panel--primary">
            <div class="field-group">
                <label for="sitemapUrl">Sitemap URL</label>
                <div class="field-group__controls">
                    <input type="text" id="sitemapUrl" placeholder="Enter sitemap URL" value="https://funkpd.com/sitemap.xml">
                    <button id="checkLinksButton" class="btn btn--primary">Check Links</button>
                </div>
            </div>
        </section>
        <section class="app__panel app__panel--toolbar">
            <div class="button-group">
                <button id="toggle200" class="btn btn--ghost">Toggle 200 Status</button>
                <button id="toggleExternal" class="btn btn--ghost">Toggle External Links</button>
                <button id="expandAll" class="btn btn--ghost">Expand/Collapse All</button>
                <button id="clearCache" class="btn btn--ghost">Clear Local Cache</button>
            </div>
        </section>
        <section class="app__panel app__panel--results">
            <p id="feedback" class="feedback"></p>
            <div id="urlList" class="url-list"></div>
        </section>
    </main>
    <script src="assets/js/scripts.js"></script>
</body>

</html>
