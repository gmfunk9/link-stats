<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Link Checker</title>
    <style>
    #urlList,
    details,
    ul {
        margin-top: 0
    }

    details {
        margin-left: 20px;
        margin-top: 10px;
        padding-left: 20px;
        max-height: 80vh;
        overflow: hidden auto;
        border-left: 1px solid #000;
        border-bottom: 5px solid #000
    }

    details details {
        margin-left: 0px;
        margin-top: 0px;
        border-bottom: 1px solid #000
    }

    summary {
        cursor: pointer;
        font-weight: bold
    }

    ul {
        list-style-type: none;
        padding-left: 20px
    }

    .hidden {
        display: none;
    }

    /* Status coloring */
    details[data-http-status="200"] {
        color: green;
    }

    details[data-http-status="404"] {
        color: red;
    }

    details[data-http-status="500"] {
        color: orange;
    }

    details[data-http-status="301"] {
        color: blue;
    }

    details[data-http-status="302"] {
        color: magenta;
    }

    /* Background colors */
    details.queued summary {
        background-color: gray
    }

    details.working summary {
        background-color: orange;
        animation: blink 1s infinite
    }

    details.complete summary {
        background-color: lightgreen
    }

    /* Hide functions using body classes */
    .body-hide-200 details[data-http-status="200"],
    .body-hide-external details[data-external="true"] {
        display: none;
    }

    /* Animation for blinking */
    @keyframes blink {
        0% {
            opacity: 1
        }

        50% {
            opacity: .5
        }

        100% {
            opacity: 1
        }
    }
    </style>
</head>

<body>
    <h1>Link Checker</h1>
    <input type="text" id="sitemapUrl" placeholder="Enter sitemap URL"
        value="http://funkpd.local/sitemap_index.xml">
    <button id="checkLinksButton">Check Links</button>
    <hr>
    <button id="toggle200">Toggle 200 Status</button>
    <button id="toggleExternal">Toggle External Links</button>
    <button id="expandAll">Expand/Collapse All</button>

    <script>
    function initToggleControls() {
        const toggle200 = document.getElementById('toggle200');
        const toggleExternal = document.getElementById('toggleExternal');
        const expandAll = document.getElementById('expandAll');

        toggle200.addEventListener('click', () => {
            document.body.classList.toggle('body-hide-200');
        });

        toggleExternal.addEventListener('click', () => {
            document.body.classList.toggle('body-hide-external');
        });

        expandAll.addEventListener('click', () => {
            const detailsList = document.querySelectorAll('details');
            let hasOpenDetail = false;
            detailsList.forEach(detail => {
                if (detail.open) {
                    hasOpenDetail = true;
                }
            });
            if (hasOpenDetail) {
                detailsList.forEach(detail => {
                    detail.open = false;
                });
                return;
            }
            detailsList.forEach(detail => {
                detail.open = true;
            });
        });
    }

    class LinkChecker {
        constructor() {
            this.urlList = document.getElementById('urlList');
            this.feedbackElement = document.getElementById('feedback');
            this.urlQueue = [];
            this.baseDomain = '';
            this.initEventListeners();
        }

        initEventListeners() {
            const button = document.getElementById('checkLinksButton');
            button.addEventListener('click', () => {
                this.fetchUrlsAndCheckLinks();
            });
        }

        setFeedback(message) {
            this.feedbackElement.textContent = message;
        }

        extractDomain(url) {
            try {
                const parsed = new URL(url);
                return parsed.hostname;
            } catch (error) {
                return '';
            }
        }

        getSitemapInput() {
            const input = document.getElementById('sitemapUrl');
            return input.value.trim();
        }

        async fetchUrlsAndCheckLinks() {
            const sitemapUrl = this.getSitemapInput();
            if (!sitemapUrl) {
                alert('Missing sitemap URL. Add one.');
                return;
            }
            this.baseDomain = this.extractDomain(sitemapUrl);
            this.setFeedback('Fetching sitemap...');
            try {
                const urls = await this.collectSitemapUrls(sitemapUrl);
                if (!urls.length) {
                    this.setFeedback('No URLs found in sitemap.');
                    return;
                }
                this.setFeedback('Checking URLs...');
                this.displayInitialUrls(urls);
                this.enqueueUrls(urls);
            } catch (error) {
                this.handleError(error, 'Failed to fetch URLs');
            }
        }

        async collectSitemapUrls(sitemapUrl) {
            const xmlText = await this.fetchText(sitemapUrl);
            const xmlDoc = this.parseXml(xmlText);
            const rootName = xmlDoc.documentElement.nodeName;
            if (rootName === 'sitemapindex') {
                return await this.expandSitemapIndex(xmlDoc);
            }
            if (rootName === 'urlset') {
                return this.extractUrlEntries(xmlDoc);
            }
            throw new Error(`Unsupported sitemap root: ${rootName}`);
        }

        async expandSitemapIndex(xmlDoc) {
            const sitemapUrls = this.extractLocValues(xmlDoc, 'sitemap');
            const allUrls = [];
            for (const childUrl of sitemapUrls) {
                const nestedUrls = await this.collectSitemapUrls(childUrl);
                nestedUrls.forEach(url => {
                    allUrls.push(url);
                });
            }
            return allUrls;
        }

        extractUrlEntries(xmlDoc) {
            return this.extractLocValues(xmlDoc, 'url');
        }

        extractLocValues(xmlDoc, parentTag) {
            const nodes = xmlDoc.getElementsByTagName(parentTag);
            const urls = [];
            for (const node of nodes) {
                const locNodes = node.getElementsByTagName('loc');
                if (!locNodes.length) {
                    continue;
                }
                const value = locNodes[0].textContent.trim();
                if (!value) {
                    continue;
                }
                urls.push(value);
            }
            return urls;
        }

        async fetchText(url) {
            let response;
            try {
                response = await fetch(url);
            } catch (error) {
                throw new Error(`Failed to fetch ${url}: ${error.message}`);
            }
            if (!response.ok) {
                throw new Error(`Sitemap fetch failed for ${url}. Status ${response.status}`);
            }
            return await response.text();
        }

        parseXml(xmlText) {
            const parser = new DOMParser();
            const doc = parser.parseFromString(xmlText, 'application/xml');
            const parserError = doc.querySelector('parsererror');
            if (parserError) {
                throw new Error('Sitemap XML is invalid.');
            }
            return doc;
        }

        displayInitialUrls(urls) {
            urls.forEach(url => {
                this.createDetailElement(url, 'Queued', this.urlList);
            });
        }

        enqueueUrls(urls) {
            urls.forEach(url => {
                this.urlQueue.push(url);
            });
            this.processQueue();
        }

        processQueue() {
            if (!this.urlQueue.length) {
                this.setFeedback('All URLs processed.');
                return;
            }
            const nextUrl = this.urlQueue.shift();
            this.processUrl(nextUrl);
        }

        async processUrl(url) {
            this.updateStatus(url, 'Working');
            try {
                const pageData = await this.fetchPage(url);
                this.renderMainLinkMeta(url, pageData);
                const interlinks = await this.inspectLinks(pageData.doc,
                    pageData.finalUrl);
                this.appendInterlinks(interlinks, url);
                this.updateStatus(url, 'Complete');
            } catch (error) {
                this.handleError(error, `Failed to process URL ${url}`);
                this.updateStatus(url, `Error: ${error.message}`);
            }
            this.processQueue();
        }

        async fetchPage(url) {
            const resource = await this.fetchResource(url);
            const doc = this.parseHtml(resource.body);
            const metadata = this.extractHtmlMetadata(doc,
                resource.body.length);
            return {
                status: resource.status,
                finalUrl: resource.finalUrl,
                loadTime: resource.loadTime,
                doc: doc,
                title: metadata.title,
                contentLength: metadata.contentLength
            };
        }

        async fetchResource(url) {
            const startTime = performance.now();
            let response;
            try {
                response = await fetch(url);
            } catch (error) {
                throw new Error(`Network error for ${url}: ${error.message}`);
            }
            const loadTime = (performance.now() - startTime) / 1000;
            const body = await response.text();
            return {
                status: response.status,
                finalUrl: response.url,
                loadTime: loadTime,
                body: body
            };
        }

        parseHtml(htmlText) {
            const parser = new DOMParser();
            return parser.parseFromString(htmlText, 'text/html');
        }

        extractHtmlMetadata(doc, contentLength) {
            const metadata = {
                contentLength: contentLength
            };
            const titleElement = doc.querySelector('title');
            if (titleElement) {
                metadata.title = titleElement.textContent.trim();
            }
            return metadata;
        }

        async inspectLinks(doc, baseUrl) {
            const links = this.extractLinks(doc, baseUrl);
            const interlinks = {};
            for (const linkUrl of links) {
                interlinks[linkUrl] = await this.fetchLink(linkUrl);
            }
            return interlinks;
        }

        extractLinks(doc, baseUrl) {
            const anchors = doc.querySelectorAll('a[href]');
            const uniqueLinks = new Set();
            anchors.forEach(anchor => {
                const href = anchor.getAttribute('href');
                const normalized = this.normalizeLink(href, baseUrl);
                if (!normalized) {
                    return;
                }
                uniqueLinks.add(normalized);
            });
            return Array.from(uniqueLinks);
        }

        normalizeLink(rawHref, baseUrl) {
            if (!rawHref) {
                return '';
            }
            const trimmed = rawHref.trim();
            if (!trimmed) {
                return '';
            }
            if (trimmed.startsWith('#')) {
                return '';
            }
            if (trimmed.startsWith('javascript:')) {
                return '';
            }
            if (trimmed.startsWith('mailto:')) {
                return '';
            }
            try {
                const urlObject = new URL(trimmed, baseUrl);
                urlObject.hash = '';
                return urlObject.href;
            } catch (error) {
                return '';
            }
        }

        async fetchLink(linkUrl) {
            try {
                const resource = await this.fetchResource(linkUrl);
                const doc = this.parseHtml(resource.body);
                const metadata = this.extractHtmlMetadata(doc,
                    resource.body.length);
                return {
                    status: resource.status,
                    finalUrl: resource.finalUrl,
                    loadTime: resource.loadTime,
                    title: metadata.title,
                    contentLength: metadata.contentLength
                };
            } catch (error) {
                return {
                    status: 'error',
                    finalUrl: linkUrl,
                    loadTime: null,
                    error: error.message
                };
            }
        }

        appendInterlinks(interlinks, parentUrl) {
            Object.entries(interlinks).forEach(([linkUrl, statusData]) => {
                this.addFoundLink(linkUrl, statusData, parentUrl);
            });
        }

        renderMainLinkMeta(url, pageData) {
            const parentElement = this.getParentDetail(url);
            if (!parentElement) {
                return;
            }
            const existingMeta = parentElement.querySelector('.page-meta');
            if (existingMeta) {
                existingMeta.remove();
            }
            const metaContainer = document.createElement('div');
            metaContainer.className = 'page-meta';
            this.appendDetailRow(metaContainer, 'Final URL',
                pageData.finalUrl);
            this.appendDetailRow(metaContainer, 'Status',
                String(pageData.status));
            this.appendDetailRow(metaContainer, 'Load Time',
                `${this.formatLoadTime(pageData.loadTime)} seconds`);
            if (pageData.title) {
                this.appendDetailRow(metaContainer, 'Title', pageData.title);
            }
            if (pageData.contentLength) {
                this.appendDetailRow(metaContainer, 'Content Length',
                    `${pageData.contentLength} bytes`);
            }
            parentElement.appendChild(metaContainer);
        }

        getParentDetail(url) {
            const summary = document.querySelector(`summary[data-url="${url}"]`);
            if (!summary) {
                return null;
            }
            return summary.parentNode;
        }

        appendDetailRow(container, label, value) {
            if (value === 0) {
                // Allow zero values
            } else if (!value) {
                return;
            }
            const row = document.createElement('div');
            row.textContent = `${label}: ${value}`;
            container.appendChild(row);
        }

        addFoundLink(url, statusData, parentUrl) {
            const parentElement = this.getParentDetail(parentUrl);
            if (!parentElement) {
                return;
            }
            const detailsElement = document.createElement('details');
            let statusValue = 'unknown';
            if (statusData.status) {
                statusValue = statusData.status;
            }
            detailsElement.setAttribute('data-url', url);
            detailsElement.setAttribute('data-http-status', statusValue);
            const loadTimeValue = this.formatLoadTime(statusData.loadTime);
            if (loadTimeValue !== 'n/a') {
                detailsElement.setAttribute('data-load-time', loadTimeValue);
            }
            if (this.isExternal(url)) {
                detailsElement.setAttribute('data-external', 'true');
            }
            detailsElement.classList.add('complete');
            const summaryElement = document.createElement('summary');
            summaryElement.setAttribute('data-url', url);
            summaryElement.textContent = `${url} - Status: ${statusValue}`;
            detailsElement.appendChild(summaryElement);
            this.appendDetailRow(detailsElement, 'Final URL',
                statusData.finalUrl);
            this.appendDetailRow(detailsElement, 'Load Time',
                `${loadTimeValue} seconds`);
            this.appendDetailRow(detailsElement, 'Title',
                statusData.title);
            if (statusData.contentLength) {
                this.appendDetailRow(detailsElement, 'Content Length',
                    `${statusData.contentLength} bytes`);
            }
            if (statusData.error) {
                this.appendDetailRow(detailsElement, 'Error',
                    statusData.error);
            }
            parentElement.appendChild(detailsElement);
        }

        isExternal(url) {
            if (!this.baseDomain) {
                return false;
            }
            try {
                const urlObject = new URL(url);
                return !urlObject.hostname.includes(this.baseDomain);
            } catch (error) {
                return true;
            }
        }

        formatLoadTime(loadTime) {
            if (typeof loadTime !== 'number') {
                return 'n/a';
            }
            return loadTime.toFixed(3);
        }

        handleError(error, message) {
            console.error('Error:', error);
            this.setFeedback(`${message}: ${error.message}`);
        }

        updateStatus(url, status) {
            const element = document.querySelector(`summary[data-url="${url}"]`);
            if (!element) {
                return;
            }
            const detailsElement = element.parentNode;
            detailsElement.classList.remove('queued');
            detailsElement.classList.remove('working');
            detailsElement.classList.remove('complete');
            detailsElement.classList.add(this.getStatusClass(status));
            element.textContent = `${url} - ${status}`;
        }

        createDetailElement(url, status, parentElement) {
            const detailsElement = document.createElement('details');
            const summaryElement = document.createElement('summary');
            summaryElement.setAttribute('data-url', url);
            summaryElement.textContent = `${url} - ${status}`;
            detailsElement.appendChild(summaryElement);
            detailsElement.classList.add(this.getStatusClass(status));
            parentElement.appendChild(detailsElement);
            return summaryElement;
        }

        getStatusClass(status) {
            if (status === 'Queued') {
                return 'queued';
            }
            if (status === 'Working') {
                return 'working';
            }
            if (status.startsWith('Error')) {
                return 'error';
            }
            return 'complete';
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        initToggleControls();
        new LinkChecker();
    });
    </script>

    <p id="feedback"></p>
    <div id="urlList"></div>
</body>

</html>
