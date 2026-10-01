const express = require("express");
const crypto = require("crypto");

const { scrapeAliExpress, scrapeAliExpressProduct } = require("./aliexpress");
const { scrapeAmazon, scrapeAmazonProduct } = require("./amazon");
const { closeBrowser } = require("./browser");

const app = express();
const PORT = 3000;
const API_KEY = process.env.API_KEY || "";

if (API_KEY.length < 32) {
    console.error("API_KEY must be configured with at least 32 characters.");
    process.exit(1);
}

const MARKETPLACES = {
    aliexpress: { search: scrapeAliExpress, product: scrapeAliExpressProduct },
    amazon: { search: scrapeAmazon, product: scrapeAmazonProduct }
};

app.use(express.json({ limit: "1mb" }));

// ---------------------------------------------------------
// Optional API key (set API_KEY in the environment, then send
// the header "x-api-key" from n8n)
// ---------------------------------------------------------

app.use((req, res, next) => {
    if (req.path === "/health") return next();
    const provided = req.get("x-api-key") || "";
    const expectedBuffer = Buffer.from(API_KEY);
    const providedBuffer = Buffer.from(provided);
    if (providedBuffer.length === expectedBuffer.length && crypto.timingSafeEqual(providedBuffer, expectedBuffer)) return next();
    return res.status(401).json({ success: false, error: "Unauthorized" });
});

function readMarketplace(req, res) {
    const marketplace = String(req.body?.marketplace || "aliexpress").toLowerCase().trim();
    if (!MARKETPLACES[marketplace]) {
        res.status(400).json({
            success: false,
            error: `Unsupported marketplace: ${marketplace}`,
            supported_marketplaces: Object.keys(MARKETPLACES)
        });
        return null;
    }
    return marketplace;
}

function sendError(res, marketplace, extra, error) {
    console.error(`\nScraper error [${marketplace}]:`, error?.message || error);

    const blocked = error?.code === "blocked";

    return res.status(blocked ? 503 : 500).json({
        success: false,
        source: marketplace,
        ...extra,
        error_code: blocked ? "blocked" : "scrape_failed",
        error: error?.message || String(error),
        retryable: blocked
    });
}

// =========================================================
// SEARCH
// =========================================================

app.post("/search", async (req, res) => {
    const marketplace = readMarketplace(req, res);
    if (!marketplace) return;

    const query = String(req.body?.query || "").trim();
    if (!query) {
        return res.status(400).json({ success: false, error: "Missing query" });
    }

    let limit = Number(req.body?.limit || 5);
    if (Number.isNaN(limit)) limit = 5;
    limit = Math.max(1, Math.min(limit, 20));

    const started = Date.now();
    console.log(`\n[search] ${marketplace} "${query}" limit=${limit}`);

    try {
        const products = await MARKETPLACES[marketplace].search(query, limit, {
            domain: req.body?.domain,
            includeSponsored: !!req.body?.include_sponsored,
            // Amazon returns the review count on its search cards, AliExpress
            // does not, so it is fetched from the review API (1 call per product)
            includeReviews: req.body?.include_reviews !== false
        });

        return res.json({
            success: true,
            source: marketplace,
            query,
            count: products.length,
            elapsed_ms: Date.now() - started,
            products
        });
    } catch (error) {
        return sendError(res, marketplace, { query }, error);
    }
});

// =========================================================
// PRODUCT DETAILS + REVIEWS
// =========================================================

app.post("/product", async (req, res) => {
    const marketplace = readMarketplace(req, res);
    if (!marketplace) return;

    const url = String(req.body?.url || "").trim();
    const productId = String(req.body?.product_id || "").trim();

    if (!url && !productId) {
        return res.status(400).json({
            success: false,
            error: "Provide url or product_id"
        });
    }

    let reviewsLimit = Number(req.body?.reviews_limit ?? 10);
    if (Number.isNaN(reviewsLimit)) reviewsLimit = 10;
    reviewsLimit = Math.max(0, Math.min(reviewsLimit, 50));

    const started = Date.now();
    console.log(`\n[product] ${marketplace} ${productId || url} reviews=${reviewsLimit}`);

    try {
        const product = await MARKETPLACES[marketplace].product(
            { url, product_id: productId },
            reviewsLimit,
            { domain: req.body?.domain }
        );

        return res.json({
            success: true,
            source: marketplace,
            elapsed_ms: Date.now() - started,
            reviews_returned: product.reviews.length,
            product
        });
    } catch (error) {
        return sendError(res, marketplace, { url, product_id: productId }, error);
    }
});

// =========================================================
// FORWARD WORKFLOW RESULTS TO WORDPRESS
// =========================================================

app.post("/results", async (req, res) => {
    const productResult = req.body?.product_result && typeof req.body.product_result === "object"
        ? req.body.product_result
        : req.body?.body && typeof req.body.body === "object"
            ? req.body.body
            : req.body;
    const runId = String(req.body?.run_id || productResult?.run_id || "").trim();

    if (
        !productResult ||
        typeof productResult !== "object" ||
        Array.isArray(productResult) ||
        !Number.isInteger(Number(productResult.rank)) ||
        typeof productResult.product?.title !== "string"
    ) {
        return res.status(400).json({
            success: false,
            error: "Expected a product result with rank and product.title"
        });
    }

    const wordpressResultsUrl = process.env.WORDPRESS_RESULTS_URL || "";
    const wordpressResultsToken = process.env.WORDPRESS_RESULTS_TOKEN || "";

    if (!wordpressResultsUrl || wordpressResultsToken.length < 32) {
        return res.status(503).json({
            success: false,
            error: "WordPress results forwarding is not configured"
        });
    }

    try {
        const wordpressResponse = await fetch(wordpressResultsUrl, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-DSA-Callback-Token": wordpressResultsToken
            },
            body: JSON.stringify({ run_id: runId, product_result: productResult })
        });

        if (!wordpressResponse.ok) {
            return res.status(502).json({
                success: false,
                error: "WordPress rejected the results",
                wordpress_status: wordpressResponse.status
            });
        }

        const result = await wordpressResponse.json();
        return res.status(200).json({ success: true, wordpress: result });
    } catch (error) {
        console.error("WordPress results forwarding failed:", error?.message || error);
        return res.status(502).json({
            success: false,
            error: "Could not reach WordPress"
        });
    }
});

// =========================================================
// HEALTH / ROOT
// =========================================================

app.get("/health", (req, res) => {
    res.json({
        success: true,
        service: "multi-marketplace-playwright",
        status: "ok",
        marketplaces: Object.keys(MARKETPLACES)
    });
});

app.get("/", (req, res) => {
    res.json({
        service: "Multi-Marketplace Playwright Scraper",
        status: "running",
        marketplaces: Object.keys(MARKETPLACES),
        endpoints: {
            health: "GET /health",
            search: "POST /search {marketplace, query, limit, include_reviews}",
            product: "POST /product {marketplace, url | product_id, reviews_limit}",
            results: "POST /results {run_id?, rank, product, validation, score, product_analysis, source}"
        }
    });
});

// =========================================================
// START / SHUTDOWN
// =========================================================

const server = app.listen(PORT, "0.0.0.0", () => {
    console.log("========================================");
    console.log("Multi-Marketplace scraper running");
    console.log(`Port: ${PORT}`);
    console.log(`API key protection: ${API_KEY ? "on" : "off"}`);
    console.log(`Marketplaces: ${Object.keys(MARKETPLACES).join(", ")}`);
    console.log("========================================");
});

async function shutdown() {
    console.log("Shutting down...");
    server.close();
    await closeBrowser();
    process.exit(0);
}

process.on("SIGTERM", shutdown);
process.on("SIGINT", shutdown);
