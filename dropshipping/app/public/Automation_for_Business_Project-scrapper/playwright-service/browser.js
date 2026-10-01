const { chromium } = require("playwright");

const MAX_CONCURRENCY = Math.max(1, Number(process.env.MAX_CONCURRENCY) || 2);
const JOB_TIMEOUT_MS = Number(process.env.JOB_TIMEOUT_MS) || 110000;

const USER_AGENTS = [
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36",
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36"
];

let browserPromise = null;

function proxyConfig() {
    if (!process.env.PROXY_SERVER) return undefined;
    return {
        server: process.env.PROXY_SERVER,
        username: process.env.PROXY_USERNAME || undefined,
        password: process.env.PROXY_PASSWORD || undefined
    };
}

async function getBrowser() {
    if (browserPromise) {
        const existing = await browserPromise.catch(() => null);
        if (existing && existing.isConnected()) return existing;
        browserPromise = null;
    }

    console.log("Launching shared Chromium...");

    browserPromise = chromium.launch({
        headless: true,
        proxy: proxyConfig(),
        args: [
            "--no-sandbox",
            "--disable-setuid-sandbox",
            "--disable-dev-shm-usage",
            "--disable-gpu",
            "--no-first-run",
            "--disable-blink-features=AutomationControlled"
        ]
    });

    return browserPromise;
}

// ---------------------------------------------------------
// Concurrency limiter
// ---------------------------------------------------------

let active = 0;
const waiting = [];

function acquire() {
    return new Promise((resolve) => {
        if (active < MAX_CONCURRENCY) {
            active++;
            resolve();
        } else {
            waiting.push(resolve);
        }
    });
}

function release() {
    const next = waiting.shift();
    if (next) next();
    else active--;
}

/**
 * Runs fn(page, context) inside a fresh isolated browser context.
 * - limited concurrency (MAX_CONCURRENCY)
 * - hard timeout (JOB_TIMEOUT_MS): closing the context aborts pending operations
 */
async function withPage(contextOptions, fn) {
    await acquire();

    let context;
    let timer;

    try {
        const browser = await getBrowser();

        context = await browser.newContext({
            viewport: { width: 1440, height: 900 },
            locale: "en-US",
            userAgent: USER_AGENTS[Math.floor(Math.random() * USER_AGENTS.length)],
            ...contextOptions
        });

        context.setDefaultTimeout(30000);
        context.setDefaultNavigationTimeout(60000);

        // Save bandwidth/time: media and fonts are never needed
        await context.route("**/*", (route) => {
            const type = route.request().resourceType();
            if (type === "media" || type === "font") return route.abort();
            return route.continue();
        });

        await context.addInitScript(() => {
            Object.defineProperty(navigator, "webdriver", { get: () => undefined });
        });

        const page = await context.newPage();

        timer = setTimeout(() => {
            console.error("Job timeout reached, closing context");
            context.close().catch(() => {});
        }, JOB_TIMEOUT_MS);

        return await fn(page, context);
    } finally {
        clearTimeout(timer);
        if (context) await context.close().catch(() => {});
        release();
    }
}

async function closeBrowser() {
    if (!browserPromise) return;
    const b = await browserPromise.catch(() => null);
    if (b) await b.close().catch(() => {});
    browserPromise = null;
}

module.exports = { withPage, closeBrowser };
