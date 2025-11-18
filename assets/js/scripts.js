'use strict';

document.addEventListener('DOMContentLoaded', () => {
    initToggleButtons();
    new LinkChecker();
});

function initToggleButtons() {
    const toggleStatusButton = document.getElementById('toggle200');
    if (toggleStatusButton) {
        toggleStatusButton.addEventListener('click', () => {
            document.body.classList.toggle('body-hide-200');
        });
    }
    const toggleExternalButton = document.getElementById('toggleExternal');
    if (toggleExternalButton) {
        toggleExternalButton.addEventListener('click', () => {
            document.body.classList.toggle('body-hide-external');
        });
    }
    const expandAllButton = document.getElementById('expandAll');
    if (expandAllButton) {
        expandAllButton.addEventListener('click', () => {
            const detailsElements = document.querySelectorAll('details');
            const detailsList = Array.from(detailsElements);
            const allClosed = detailsList.every(detail => detail.open === false);
            detailsList.forEach(detail => {
                detail.open = false;
            });
            if (allClosed) {
                detailsList.forEach(detail => {
                    detail.open = true;
                });
            }
        });
    }
}

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
        this.baseDomain = '';
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
        const urlInput = document.getElementById('sitemapUrl');
        if (!urlInput) {
            return;
        }
        const sitemapUrl = urlInput.value.trim();
        if (!sitemapUrl) {
            alert('Please enter a valid sitemap URL.');
            return;
        }
        try {
            const parsedUrl = new URL(sitemapUrl);
            this.baseDomain = parsedUrl.hostname;
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
        if (this.urlQueue.length === 0) {
            this.feedbackElement.textContent = 'All URLs processed.';
            return;
        }
        const nextUrl = this.urlQueue.shift();
        this.processUrl(nextUrl);
    }

    async processUrl(url) {
        this.updateStatus(url, 'Working');
        try {
            const response = await fetch(`${this.config.linkCheckUrl}?url=${encodeURIComponent(url)}`);
            const rawText = await response.text();
            if (!rawText.trim()) {
                throw new Error('Empty response received');
            }
            const payload = JSON.parse(rawText);
            this.handleResponse(payload, url);
        } catch (error) {
            console.error('Error processing URL:', url, 'Error:', error);
            this.handleError(error, 'Failed to process URL');
            this.updateStatus(url, 'Error: ' + url + error.message);
            this.processQueue();
        }
    }

    handleResponse(data, url) {
        this.updateStatus(url, 'Complete');
        if (data.error) {
            this.updateStatus(url, 'Error: ' + data.error);
        }
        if (data.interlinks) {
            Object.entries(data.interlinks).forEach(entry => {
                const linkUrl = entry[0];
                const statusData = entry[1];
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
        parentNode.classList.remove('queued');
        parentNode.classList.remove('working');
        parentNode.classList.remove('complete');
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
            isExternal = urlObject.hostname.includes(this.baseDomain) === false;
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
        const escapedQuotes = url.replace(/"/g, '\\"');
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
        }
        if (status === 'Working') {
            return 'working';
        }
        return 'complete';
    }
}
