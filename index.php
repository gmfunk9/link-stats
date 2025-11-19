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
        <section class="app__panel app__panel--info">
            <p class="info-headline">What this tool does</p>
            <p class="info-text">
                The Link Checker crawls every URL exposed by the sitemap you give it.
                It labels HTTP statuses and surfaces final URLs with load times so you can spot
                redirect chains or broken pages before they impact users.
            </p>
            <p class="info-text">
                Enter any sitemap URL, hit <strong>Check Links</strong>,
                and keep an eye on the summary cards that appear below. Toggle the filters if you
                only want green paths or are looking for external references.
            </p>
            <div class="info-callout">
                <p class="info-callout__title">More power is coming</p>
                <p class="info-callout__text">
                    A sign-up page is arriving soon so you can request a much higher page allowance
                    for crawling. Watch this space to upgrade your quota and keep scanning larger
                    sitemaps without hitting limits.
                </p>
            </div>
        </section>
        <section class="app__panel app__panel--primary">
            <div class="field-group">
                <label for="sitemapUrl">Sitemap URL</label>
                <div class="field-group__controls">
                    <input type="text" id="sitemapUrl" placeholder="Enter website URL" value="https://funkpd.com">
                    <button id="checkLinksButton" class="btn btn--primary">Check Links</button>
                </div>
            </div>
        </section>
        <section class="app__panel app__panel--toolbar">
            <div class="toolbar-headline">
                <p class="toolbar-headline__text">
                    These quick tools keep the results readable—hide healthy pages,
                    show only externals, or expand the tree with one click.
                </p>
            </div>
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
