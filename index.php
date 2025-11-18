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
    <input type="text" id="sitemapUrl" placeholder="Enter sitemap URL" value="https://funkpd.com/sitemap.xml">
    <button id="checkLinksButton">Check Links</button>
    <hr>
    <button id="toggle200">Toggle 200 Status</button>
    <button id="toggleExternal">Toggle External Links</button>
    <button id="expandAll">Expand/Collapse All</button>

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
                linkCheckUrl: 'check_links.php'
            };
            this.urlQueue = [];
            this.currentlyChecking = false;
            this.baseDomain = "";
            this.rateLimit = 10;
            this.storageKey = 'linkCheckerProgress';
            this.initEventListeners();
        }
        initEventListeners() {
            document.getElementById('checkLinksButton').addEventListener('click', () => {
                this.fetchUrlsAndCheckLinks();
            });
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
                const urls = await response.json();
                if (!Array.isArray(urls)) {
                    let errorMessage = 'Unexpected response format';
                    if (urls) {
                        if (typeof urls === 'object') {
                            if (urls.error) {
                                errorMessage = urls.error;
                            }
                        }
                    }
                    throw new Error(errorMessage);
                }
                const limitResult = this.applyRateLimit(urls, sitemapUrl);
                this.feedbackElement.textContent = limitResult.message;
                if (limitResult.urls.length === 0) {
                    return;
                }
                this.displayInitialUrls(limitResult.urls);
                this.enqueueUrls(limitResult.urls);
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
            let element = document.querySelector(`summary[data-url="${url}"]`);
            if (element) {
                const detailsElement = element.parentNode;
                detailsElement.classList.remove('queued', 'working', 'complete');
                detailsElement.classList.add(this.getStatusClass(status));
                element.textContent = `${url} - ${status}`;
            }
        }
        createDetailElement(url, status, parentElement) {
            const detailsElement = document.createElement('details');
            const summaryElement = document.createElement('summary');
            summaryElement.setAttribute('data-url', url);
            summaryElement.textContent = `${url} - ${status}`;
            detailsElement.appendChild(summaryElement);
            parentElement.appendChild(detailsElement);
            return summaryElement;
        }
        addFoundLink(url, statusData, parentUrl) {
            const parentElement = document.querySelector(`summary[data-url="${parentUrl}"]`).parentNode;
            if (parentElement) {
                let isExternal = false;
                try {
                    const urlObject = new URL(url.replace('\\', '/'));
                    isExternal = !urlObject.hostname.includes(this.baseDomain);
                } catch (error) {
                    console.error(`Invalid URL: ${url}`);
                    isExternal = true; // or set it to false, depending on your requirements
                }

                const detailsElement = document.createElement('details');
                detailsElement.setAttribute('data-url', url);
                detailsElement.setAttribute('data-http-status', statusData.status);
                detailsElement.setAttribute('data-load-time', statusData?.loadTime?.toFixed(3));

                detailsElement.classList.add(this.getStatusClass('Status: ' + statusData.status));

                if (isExternal) {
                    detailsElement.setAttribute('data-external', 'true');
                }

                const summaryElement = document.createElement('summary');
                summaryElement.textContent = `${url} - Status: ${statusData.status}`;
                detailsElement.appendChild(summaryElement);

                const finalUrlDiv = document.createElement('div');
                finalUrlDiv.textContent = `Final URL: ${statusData.finalUrl}`;
                detailsElement.appendChild(finalUrlDiv);

                const loadTimeDiv = document.createElement('div');
                loadTimeDiv.textContent = `Load Time: ${statusData?.loadTime?.toFixed(3)} seconds`;
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
            } else {
                console.error("Failed to find the parent element for URL:", url);
            }
        }
        handleError(error, message) {
            console.error('Error:', error, 'Message:', message);
            this.feedbackElement.textContent = message + ': ' + error.message;
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
        getToday() {
            const now = new Date();
            return now.toISOString().slice(0, 10);
        }
        loadProgressStore() {
            const raw = localStorage.getItem(this.storageKey);
            if (!raw) {
                return {};
            }
            try {
                const parsed = JSON.parse(raw);
                if (typeof parsed !== 'object') {
                    return {};
                }
                if (parsed === null) {
                    return {};
                }
                return parsed;
            } catch (error) {
                console.error('Failed to parse progress cache', error);
                return {};
            }
        }
        saveProgressStore(store) {
            try {
                localStorage.setItem(this.storageKey, JSON.stringify(store));
            } catch (error) {
                console.error('Failed to save progress cache', error);
            }
        }
        createProgressEntry() {
            return {
                index: 0,
                processedToday: 0,
                date: this.getToday()
            };
        }
        applyRateLimit(urls, sitemapUrl) {
            const store = this.loadProgressStore();
            const entry = store[sitemapUrl] || this.createProgressEntry();
            const today = this.getToday();
            if (entry.date !== today) {
                entry.date = today;
                entry.processedToday = 0;
            }
            if (typeof entry.index !== 'number') {
                entry.index = 0;
            }
            if (typeof entry.processedToday !== 'number') {
                entry.processedToday = 0;
            }
            if (entry.index >= urls.length) {
                store[sitemapUrl] = entry;
                this.saveProgressStore(store);
                return {
                    urls: [],
                    message: 'All URLs processed. Nothing left to crawl.'
                };
            }
            const remainingToday = this.rateLimit - entry.processedToday;
            if (remainingToday <= 0) {
                store[sitemapUrl] = entry;
                this.saveProgressStore(store);
                return {
                    urls: [],
                    message: 'Daily page limit reached. Continue tomorrow.'
                };
            }
            const pendingCount = urls.length - entry.index;
            const allowedCount = Math.min(remainingToday, pendingCount);
            const batch = urls.slice(entry.index, entry.index + allowedCount);
            entry.index = entry.index + batch.length;
            entry.processedToday = entry.processedToday + batch.length;
            store[sitemapUrl] = entry;
            this.saveProgressStore(store);
            const message = `Checking ${batch.length} URLs (${entry.index}/${urls.length})`;
            return {
                urls: batch,
                message
            };
        }
    }
    document.addEventListener('DOMContentLoaded', () => {
        new LinkChecker();
    });
    </script>
</body>

</html>
