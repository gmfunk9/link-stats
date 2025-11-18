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

    .summary-link-count {
        font-weight: normal;
        font-size: .9em;
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
    <input type="text" id="sitemapUrl" placeholder="Enter sitemap URL" value="https://funkpd.com/sitemap.xml">
    <button id="checkLinksButton">Check Links</button>
    <hr>
    <button id="toggle200">Toggle 200 Status</button>
    <button id="toggleExternal">Toggle External Links</button>
    <button id="expandAll">Expand/Collapse All</button>
    <button id="clearCache">Clear Local Cache</button>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const toggleButton200 = document.getElementById('toggle200');
        const toggleExternalLinks = document.getElementById('toggleExternal');
        const toggleexpandAll = document.getElementById('expandAll');

        toggleButton200.addEventListener('click', () => {
            document.body.classList.toggle('body-hide-200');
        });

        toggleExternalLinks.addEventListener('click', () => {
            document.body.classList.toggle('body-hide-external');
        });
        toggleexpandAll.addEventListener('click', () => {
            const detailsElements = document.querySelectorAll('details');
            const allClosedInitially = Array.from(detailsElements).every(detail => !detail.open);

            // First, close all details
            detailsElements.forEach(detail => {
                detail.open = false;
            });

            // If all were closed initially, open them all
            if (allClosedInitially) {
                detailsElements.forEach(detail => {
                    detail.open = true;
                });
            }
        });

    });
    </script>


    <p id="feedback"></p>
    <div id="urlList"></div>
    <script>
    class LinkChecker {
        constructor() {
            this.urlList = document.getElementById('urlList');
            this.feedbackElement = document.getElementById('feedback');
            this.config = {
                apiUrl: 'get_sitemap.php',
                linkCheckUrl: 'check_links.php',
                clearCacheUrl: 'clear_cache.php'
            };
            this.urlQueue = [];
            this.currentlyChecking = false;
            this.baseDomain = "";
            this.linkCounts = new Map();
            this.initEventListeners();
        }
        initEventListeners() {
            const checkLinksButton = document.getElementById('checkLinksButton');

            if (checkLinksButton) {
                checkLinksButton.addEventListener('click', () => {
                    this.fetchUrlsAndCheckLinks();
                });
            }

            const clearCacheButton = document.getElementById('clearCache');

            if (clearCacheButton) {
                clearCacheButton.addEventListener('click', () => {
                    this.clearLocalCache();
                });
            }
        }
        async fetchUrlsAndCheckLinks() {
            const sitemapUrl = document.getElementById('sitemapUrl').value.trim();
            if (!sitemapUrl) {
                alert('Please enter a valid sitemap URL.');
                return;
            }
            try {
                this.baseDomain = new URL(sitemapUrl).hostname; // Extract base domain from sitemap URL
                this.feedbackElement.textContent = 'Fetching URLs...';
                const response = await fetch(this.config.apiUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'url=' + encodeURIComponent(sitemapUrl)
                });
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                const result = await response.json();
                if (!result) {
                    throw new Error('Empty response payload.');
                }
                if (typeof result !== 'object') {
                    throw new Error('Unexpected response format.');
                }
                if (result.error) {
                    throw new Error(result.error);
                }
                let batch = [];
                if (Array.isArray(result.urls)) {
                    batch = result.urls;
                }
                let message = 'No status provided.';
                if (typeof result.message === 'string') {
                    message = result.message;
                }
                this.feedbackElement.textContent = message;
                if (batch.length === 0) {
                    return;
                }
                this.displayInitialUrls(batch);
                this.enqueueUrls(batch);
            } catch (error) {
                this.handleError(error, 'Failed to fetch URLs');
            }
        }

        displayInitialUrls(urls) {
            urls.forEach(url => {
                this.createDetailElement(url, 'Queued', this.urlList);
            });
        }
        enqueueUrls(urls) {
            this.urlQueue = this.urlQueue.concat(urls);
            this.processQueue();
        }
        async clearLocalCache() {
            this.feedbackElement.textContent = 'Clearing cache...';

            try {
                const response = await fetch(this.config.clearCacheUrl, {
                    method: 'POST'
                });

                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }

                const result = await response.json();

                if (!result) {
                    throw new Error('Empty response payload.');
                }

                if (result.error) {
                    throw new Error(result.error);
                }

                let message = 'Cache cleared.';

                if (typeof result.message === 'string') {
                    message = result.message;
                }

                this.feedbackElement.textContent = message;
            } catch (error) {
                this.handleError(error, 'Failed to clear cache');
            }
        }
        processQueue() {
            if (this.urlQueue.length > 0) {
                this.processUrl(this.urlQueue.shift());
            } else {
                this.feedbackElement.textContent = 'All URLs processed.';
            }
        }
        async processUrl(url) {
            this.updateStatus(url, 'Working');
            try {
                const response = await fetch(`${this.config.linkCheckUrl}?url=${encodeURIComponent(url)}`);
                const text = await response.text(); // Get raw response text
                // // console.log("Raw response:", text); // Log raw response for debugging
                if (!text.trim()) { // Check if the text is empty or whitespace only
                    throw new Error('Empty response received');
                }
                let data = JSON.parse(text); // Parse text as JSON
                this.handleResponse(data, url); // Handle the parsed JSON
            } catch (error) {
                console.error('Error processing URL:', url, 'Error:', error);
                this.handleError(error, 'Failed to process URL');
                this.updateStatus(url, 'Error: ' + url + error.message);
                this.processQueue();
            }
        }
        handleResponse(data, url) {
            // // console.log("Handling response for URL:", url, "Data received:", data);  // Log received data
            this.updateStatus(url, 'Complete');
            if (data.error) {
                this.updateStatus(url, 'Error: ' + data.error);
            }
            if (data.interlinks) {
                // console.log("data.interlinks", data.interlinks);
                // console.log(data.interlinks);
                Object.entries(data.interlinks).forEach(([linkUrl, statusData]) => {
                    // console.log("Adding link for parent URL:", url, "Link URL:", linkUrl, "Status Data:", statusData);  // Log each link data
                    this.addFoundLink(linkUrl, statusData, url);
                });
            }
            this.processQueue();
        }


        updateStatus(url, status) {
            const summaryElement = this.getSummaryElement(url);

            if (!summaryElement) {
                return;
            }

            const parentNode = summaryElement.parentNode;

            if (!parentNode) {
                return;
            }

            if (!(parentNode instanceof HTMLElement)) {
                return;
            }

            parentNode.classList.remove('queued', 'working', 'complete');
            parentNode.classList.add(this.getStatusClass(status));
            this.updateSummaryContent(summaryElement, url, status);
        }
        createDetailElement(url, status, parentElement) {
            const detailsElement = document.createElement('details');
            const summaryElement = document.createElement('summary');
            summaryElement.setAttribute('data-url', url);
            summaryElement.setAttribute('data-level', '0');
            detailsElement.appendChild(summaryElement);
            parentElement.appendChild(detailsElement);
            this.updateSummaryContent(summaryElement, url, status);
            return summaryElement;
        }
        addFoundLink(url, statusData, parentUrl) {
            const parentSummary = this.getSummaryElement(parentUrl);

            if (!parentSummary) {
                console.error('Failed to find the parent summary for URL:', parentUrl);
                return;
            }

            const parentElement = parentSummary.parentNode;

            if (!parentElement) {
                console.error('Failed to find the parent element for URL:', url);
                return;
            }

            if (!(parentElement instanceof HTMLElement)) {
                console.error('Invalid parent container for URL:', url);
                return;
            }

            let isExternal = false;
            try {
                const sanitizedUrl = url.replace('\\', '/');
                const urlObject = new URL(sanitizedUrl);
                isExternal = !urlObject.hostname.includes(this.baseDomain);
            } catch (error) {
                console.error('Invalid URL:', url);
                isExternal = true;
            }

            const detailsElement = document.createElement('details');
            detailsElement.setAttribute('data-url', url);
            detailsElement.setAttribute('data-http-status', statusData.status);

            const formattedLoadTime = this.formatLoadTime(statusData?.loadTime);

            if (formattedLoadTime !== '') {
                detailsElement.setAttribute('data-load-time', formattedLoadTime);
            }

            detailsElement.classList.add(this.getStatusClass('Status: ' + statusData.status));

            if (isExternal) {
                detailsElement.setAttribute('data-external', 'true');
            }

            const summaryElement = document.createElement('summary');
            summaryElement.setAttribute('data-url', url);
            detailsElement.appendChild(summaryElement);
            this.updateSummaryContent(summaryElement, url, 'Status: ' + statusData.status);

            const finalUrlDiv = document.createElement('div');
            let finalUrlText = 'Unknown';

            if (typeof statusData.finalUrl === 'string') {
                finalUrlText = statusData.finalUrl;
            }

            finalUrlDiv.textContent = `Final URL: ${finalUrlText}`;
            detailsElement.appendChild(finalUrlDiv);

            const loadTimeDiv = document.createElement('div');
            let loadTimeText = 'N/A';

            if (formattedLoadTime !== '') {
                loadTimeText = formattedLoadTime + ' seconds';
            }

            loadTimeDiv.textContent = `Load Time: ${loadTimeText}`;
            detailsElement.appendChild(loadTimeDiv);

            if (statusData.title) {
                const titleDiv = document.createElement('div');
                titleDiv.textContent = `Title: ${statusData.title}`;
                detailsElement.appendChild(titleDiv);
            }

            if (statusData.contentLength) {
                const contentLengthDiv = document.createElement('div');
                contentLengthDiv.textContent = `Content Length: ${statusData.contentLength} bytes`;
                detailsElement.appendChild(contentLengthDiv);
            }

            parentElement.appendChild(detailsElement);
            this.incrementLinkCount(url);
        }
        handleError(error, message) {
            console.error('Error:', error, 'Message:', message);
            this.feedbackElement.textContent = message + ': ' + error.message;
        }
        buildUrlSelector(url) {
            const escapedQuotes = url.replace(/"/g, '\"');

            if (typeof CSS === 'undefined') {
                return escapedQuotes;
            }

            if (typeof CSS.escape !== 'function') {
                return escapedQuotes;
            }

            return CSS.escape(url);
        }
        getSummaryElements(url) {
            const selector = this.buildUrlSelector(url);
            return document.querySelectorAll(`summary[data-url="${selector}"]`);
        }
        getSummaryElement(url) {
            const elements = this.getSummaryElements(url);

            if (elements.length === 0) {
                return null;
            }

            for (const element of elements) {
                const level = element.getAttribute('data-level');

                if (level !== '0') {
                    continue;
                }

                return element;
            }

            return elements[0];
        }
        ensureLinkCountEntry(url) {
            if (this.linkCounts.has(url)) {
                return;
            }

            this.linkCounts.set(url, 0);
        }
        getSummaryTextElement(summaryElement) {
            let textElement = summaryElement.querySelector('.summary-text');

            if (!textElement) {
                textElement = document.createElement('div');
                textElement.classList.add('summary-text');
                summaryElement.prepend(textElement);
            }

            return textElement;
        }
        getSummaryCountElement(summaryElement) {
            let countElement = summaryElement.querySelector('.summary-link-count');

            if (!countElement) {
                countElement = document.createElement('div');
                countElement.classList.add('summary-link-count');
                summaryElement.appendChild(countElement);
            }

            return countElement;
        }
        formatLinkCountText(url) {
            this.ensureLinkCountEntry(url);
            const count = this.linkCounts.get(url);
            return `Incoming links: ${count}`;
        }
        updateSummaryContent(summaryElement, url, statusText) {
            this.ensureLinkCountEntry(url);
            const textElement = this.getSummaryTextElement(summaryElement);
            textElement.textContent = `${url} - ${statusText}`;
            const countElement = this.getSummaryCountElement(summaryElement);
            countElement.textContent = this.formatLinkCountText(url);
        }
        refreshLinkCountDisplays(url) {
            const summaries = this.getSummaryElements(url);
            const countText = this.formatLinkCountText(url);
            summaries.forEach(summary => {
                const countElement = this.getSummaryCountElement(summary);
                countElement.textContent = countText;
            });
        }
        incrementLinkCount(url) {
            this.ensureLinkCountEntry(url);
            const currentCount = this.linkCounts.get(url);
            const nextCount = currentCount + 1;
            this.linkCounts.set(url, nextCount);
            this.refreshLinkCountDisplays(url);
        }
        formatLoadTime(loadTimeValue) {
            if (typeof loadTimeValue !== 'number') {
                return '';
            }

            return loadTimeValue.toFixed(3);
        }
        getStatusClass(status) {
            if (status === 'Queued') {
                return 'queued';
            } else if (status === 'Working') {
                return 'working';
            } else {
                return 'complete';
            }
        }
    }
    document.addEventListener('DOMContentLoaded', () => {
        new LinkChecker();
    });
    </script>
</body>

</html>
