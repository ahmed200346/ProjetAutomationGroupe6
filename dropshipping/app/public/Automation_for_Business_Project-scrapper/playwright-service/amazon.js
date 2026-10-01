const { withPage } = require("./browser");
const {
    BlockedError,
    cleanText,
    sleep,
    jitter,
    parseCount,
    parseRating,
    parsePrice,
    detectCurrency,
    saveDebug,
    dedupeReviews
} = require("./utils");

const DEFAULT_DOMAINS = (process.env.AMAZON_DOMAINS || "amazon.com,amazon.co.uk,amazon.de")
    .split(",")
    .map((d) => d.trim())
    .filter(Boolean);

const DOMAIN_CURRENCY = {
    "amazon.com": "USD",
    "amazon.co.uk": "GBP",
    "amazon.de": "EUR",
    "amazon.fr": "EUR",
    "amazon.it": "EUR",
    "amazon.es": "EUR"
};

const CARD_SELECTOR = 'div[data-component-type="s-search-result"][data-asin]';

// =========================================================
// Helpers
// =========================================================

async function assertNotBlocked(page) {
    const title = await page.title().catch(() => "");
    const html = await page.content();

    const blocked =
        /Robot Check|Enter the characters you see below|validateCaptcha|api-services-support@amazon/i.test(html) ||
        /Robot Check|Service Unavailable/i.test(title) ||
        html.includes("cs_503");

    if (blocked) {
        await saveDebug(`amazon-blocked-${Date.now()}.html`, html);
        throw new BlockedError("Amazon returned a captcha / error page", { url: page.url(), title });
    }
}

/**
 * Strict review counter. Requires an explicit count next to a ratings/reviews
 * word, so a star widget label ("4.5 out of 5 stars") can never be mistaken
 * for a review count.
 */
function parseReviewCountStrict(label) {
    if (!label) return null;
    const m = String(label).match(/([0-9][0-9.,]*\s*(?:[KkMm])?)\s*(?:global\s+)?(?:ratings?|reviews?)/i);
    if (!m) return null;
    const value = parseCount(m[1]);
    return value != null && value > 0 ? value : null;
}

/**
 * Amazon renders the counter as a bare number ("12,345") with the word
 * "ratings" living in a sibling, so the dedicated counter element is trusted
 * to carry a naked count. Everything else (generic aria-labels, card text)
 * must spell the count out next to a ratings/reviews word.
 */
function parseCounterText(text) {
    const bare = String(text || "").match(/^\s*([0-9][0-9.,]*\s*(?:[KkMm])?)\s*$/);
    if (bare) {
        const value = parseCount(bare[1]);
        if (value != null && value > 0) return value;
    }
    return parseReviewCountStrict(text);
}

function pickReviewCount(counters, others) {
    for (const c of counters || []) {
        const value = parseCounterText(c);
        if (value != null) return value;
    }
    for (const c of others || []) {
        const value = parseReviewCountStrict(c);
        if (value != null) return value;
    }
    return null;
}

function parseAsin(input) {
    if (!input) return null;
    const s = String(input).trim();
    const m = s.match(/(?:\/dp\/|\/gp\/product\/|\/product-reviews\/)([A-Z0-9]{10})/i);
    if (m) return m[1].toUpperCase();
    if (/^[A-Z0-9]{10}$/i.test(s)) return s.toUpperCase();
    return null;
}

function parseDomain(input) {
    try {
        const host = new URL(input).hostname.replace(/^www\./, "");
        if (host.startsWith("amazon.")) return host;
    } catch {}
    return null;
}

function parseReviewDate(raw) {
    const text = cleanText(raw);
    // "Reviewed in the United States 🇺🇸 on March 3, 2025"
    const m = text.match(/Reviewed in (?:the )?(.+?)\s+on\s+(.+)$/i);
    if (!m) return { country: null, date: text || null };
    return {
        country: m[1].replace(/[^\p{L}\s]/gu, "").trim() || null,
        date: m[2].trim()
    };
}

// =========================================================
// SEARCH
// =========================================================

async function readSearchCards(page) {
    return page.evaluate((selector) => {
        const cards = Array.from(document.querySelectorAll(selector));

        return cards.map((card) => {
            const txt = (sel) => {
                const el = card.querySelector(sel);
                return el ? (el.textContent || "").replace(/\s+/g, " ").trim() : "";
            };

            const cardText = (card.innerText || "").replace(/\s+/g, " ");

            const sponsored =
                !!card.querySelector(".puis-sponsored-label-text, [aria-label*='Sponsored' i]") ||
                /^\s*Sponsored/i.test(cardText) ||
                /Sponsored Ad/i.test(cardText.slice(0, 60));

            // The review count lives in its own link. Picking "the first
            // aria-label mentioning rating" is unreliable, because a comma
            // separated selector returns whichever match comes first in the
            // document, and the star widget ("4.5 out of 5 stars") often wins.
            // So collect the candidates and keep the first one that actually
            // carries a count.
            // Tier 1: the dedicated counter element (may hold a bare number).
            // Tier 2: every other aria-label and the card text, where the count
            // has to be spelled out next to a ratings/reviews word.
            const counterTexts = [];
            const counterSelectors = [
                "[data-csa-c-content-id='alf-customer-ratings-count-link']",
                "a[href*='customerReviews'] .a-size-base",
                "span.a-size-base.s-underline-text",
                "span.a-size-base.a-link-normal"
            ];
            for (const sel of counterSelectors) {
                card.querySelectorAll(sel).forEach((el) => {
                    const v = (el.getAttribute("aria-label") || el.textContent || "").trim();
                    if (v) counterTexts.push(v);
                });
            }

            const otherLabels = [];
            card.querySelectorAll("[aria-label]").forEach((el) => {
                const v = (el.getAttribute("aria-label") || "").trim();
                if (v) otherLabels.push(v);
            });
            otherLabels.push(cardText);

            const img = card.querySelector("img.s-image");

            const shipMatch =
                cardText.match(/(FREE (?:delivery|shipping)[^.\n]{0,60})/i) ||
                cardText.match(/(FREE Returns)/i);

            // Price fallbacks. The .a-offscreen node is not always rendered (it is
            // injected late, and some layouts never add it), so try several
            // containers before giving up. Price drives a quarter of the score,
            // so a null here is expensive.
            let priceText = txt(".a-price:not(.a-text-price) .a-offscreen");
            if (!priceText) priceText = txt('[data-cy="price-recipe-price"] .a-offscreen');
            if (!priceText) priceText = txt(".a-price .a-offscreen");
            if (!priceText) {
                // never the struck through list price
                const anyPrice = card.querySelector(".a-price:not(.a-text-price)");
                if (anyPrice) priceText = (anyPrice.textContent || "").replace(/\s+/g, " ").trim();
            }
            if (!priceText) {
                const whole = txt(".a-price-whole").replace(/[.,]$/, "");
                const fraction = txt(".a-price-fraction");
                if (whole) priceText = `${whole}.${fraction || "00"}`;
            }
            if (!priceText) {
                // some cards only carry it in an aria-label
                const labelled = Array.from(card.querySelectorAll("[aria-label]"))
                    .map((el) => el.getAttribute("aria-label") || "")
                    .find((v) => /^\s*[£$€]?\s*[0-9][0-9.,]*\s*$/.test(v));
                if (labelled) priceText = labelled;
            }
            if (!priceText) {
                // last resort: a single unambiguous price in the card text.
                // currency agnostic, because the domain decides the symbol.
                const found = Array.from(
                    cardText.matchAll(/[£$€]\s?([0-9]{1,4}(?:,[0-9]{3})*(?:[.,][0-9]{2})?)/g)
                ).map((m) => m[1]);
                const distinct = Array.from(new Set(found));
                if (distinct.length === 1) priceText = distinct[0];
            }

            return {
                asin: card.getAttribute("data-asin"),
                sponsored,
                title: txt("h2 span") || txt("h2"),
                price: priceText,
                originalPrice: txt(".a-price.a-text-price .a-offscreen"),
                ratingText: txt(".a-icon-alt"),
                counterTexts,
                otherLabels,
                image: img ? img.getAttribute("src") : null,
                shipping: shipMatch ? shipMatch[1] : null
            };
        });
    }, CARD_SELECTOR);
}

async function scrapeAmazon(query, limit = 5, options = {}) {
    const domains = options.domain ? [options.domain] : DEFAULT_DOMAINS;
    let lastError = null;

    for (const domain of domains) {
        console.log(`\nAmazon search on ${domain}: "${query}"`);

        try {
            const products = await withPage(
                { timezoneId: "America/New_York", extraHTTPHeaders: { "Accept-Language": "en-US,en;q=0.9" } },
                async (page) => {
                    const url = `https://www.${domain}/s?k=${encodeURIComponent(query)}`;
                    await page.goto(url, { waitUntil: "domcontentloaded" });
                    await page.waitForSelector(CARD_SELECTOR, { timeout: 15000 }).catch(() => {});

                    // Prices are hydrated after the cards, and the struck through
                    // list price is a separate node. Scroll once to force
                    // rendering, then wait for the offscreen price spans so a
                    // slow first paint does not read as "no price".
                    await page.mouse.wheel(0, 700);
                    await sleep(500);
                    await page.waitForSelector(`${CARD_SELECTOR} .a-price .a-offscreen`, { timeout: 8000 }).catch(() => {});
                    await jitter(800, 1600);

                    await assertNotBlocked(page);
                    await saveDebug(`amazon-search-${domain}.html`, await page.content());

                    const raw = await readSearchCards(page);
                    console.log(`Cards found: ${raw.length}`);

                    const results = [];

                    for (const c of raw) {
                        if (results.length >= limit) break;
                        if (!c.asin || !c.title) continue;
                        if (c.sponsored && !options.includeSponsored) continue;

                        const price = parsePrice(c.price);
                        let originalPrice = parsePrice(c.originalPrice);
                        if (price != null && originalPrice != null && originalPrice <= price) {
                            originalPrice = null;
                        }
                        // Amazon frequently shows a crossed out "list price" that is
                        // pure noise (a $23 earbud listed against $299). Discounting
                        // against it inflates the discount score, so drop the
                        // implausible ones rather than poisoning the ranking.
                        if (price != null && originalPrice != null) {
                            const off = ((originalPrice - price) / originalPrice) * 100;
                            if (off > 80) {
                                console.log(`  ${c.asin}: dropping list price ${originalPrice} vs ${price} (${off.toFixed(0)}% off)`);
                                originalPrice = null;
                            }
                        }

                        const reviews = pickReviewCount(c.counterTexts, c.otherLabels);

                        results.push({
                            name: c.title.replace(/^Sponsored Ad\s*[–-]\s*/i, ""),
                            price,
                            original_price: originalPrice,
                            currency: detectCurrency(c.price) || DOMAIN_CURRENCY[domain] || null,
                            rating: parseRating(c.ratingText),
                            sold: null, // not exposed reliably on Amazon search pages
                            reviews,
                            shipping: c.shipping ? cleanText(c.shipping) : null,
                            image: c.image,
                            url: `https://www.${domain}/dp/${c.asin}`,
                            product_id: c.asin,
                            domain,
                            source: "amazon"
                        });
                    }

                    return results;
                }
            );

            if (products.length > 0) return products;
        } catch (error) {
            lastError = error;
            console.log(`Amazon ${domain} failed: ${error.message}`);
        }
    }

    // Every domain failed because of blocking: tell the caller instead of returning an empty list
    if (lastError instanceof BlockedError) throw lastError;

    return [];
}

// =========================================================
// PRODUCT + REVIEWS
// =========================================================

async function readReviewsOnPage(page) {
    return page.evaluate(() => {
        const t = (root, sel) => {
            const el = root.querySelector(sel);
            return el ? (el.innerText || el.textContent || "").trim() : "";
        };

        return Array.from(document.querySelectorAll('[data-hook="review"]')).map((r) => {
            const titleLines = t(r, '[data-hook="review-title"]')
                .split("\n")
                .map((l) => l.trim())
                .filter(Boolean);

            const ratingEl = r.querySelector(
                '[data-hook="review-star-rating"] .a-icon-alt, [data-hook="cmps-review-star-rating"] .a-icon-alt'
            );

            return {
                id: r.getAttribute("id"),
                author: t(r, ".a-profile-name"),
                ratingText: ratingEl ? ratingEl.textContent : "",
                title: titleLines.length ? titleLines[titleLines.length - 1] : "",
                dateText: t(r, '[data-hook="review-date"]'),
                verified: !!r.querySelector('[data-hook="avp-badge"]'),
                variant: t(r, '[data-hook="format-strip"]'),
                body: t(r, '[data-hook="review-body"]').replace(/\s*Read more\s*$/i, ""),
                helpfulText: t(r, '[data-hook="helpful-vote-statement"]'),
                images: Array.from(r.querySelectorAll('img[data-hook="review-image-tile"]')).map((i) => i.src)
            };
        });
    });
}

function mapReview(r) {
    const { country, date } = parseReviewDate(r.dateText);
    return {
        id: r.id || null,
        author: r.author || null,
        rating: parseRating(r.ratingText),
        title: cleanText(r.title) || null,
        text: cleanText(r.body),
        date,
        country,
        verified_purchase: !!r.verified,
        variant: cleanText(r.variant) || null,
        helpful_votes: r.helpfulText ? (/^one\b/i.test(r.helpfulText) ? 1 : parseCount(r.helpfulText)) : 0,
        images: r.images || []
    };
}

async function scrapeAmazonProduct(input, reviewsLimit = 10, options = {}) {
    const asin = parseAsin(input.product_id || input.url);
    if (!asin) throw new Error("Could not determine the Amazon ASIN (send product_id or a /dp/ URL)");

    const domain = options.domain || parseDomain(input.url) || DEFAULT_DOMAINS[0];
    reviewsLimit = Math.max(0, Math.min(Number(reviewsLimit) || 0, 50));

    return withPage(
        { timezoneId: "America/New_York", extraHTTPHeaders: { "Accept-Language": "en-US,en;q=0.9" } },
        async (page) => {
            const url = `https://www.${domain}/dp/${asin}`;
            console.log(`Amazon product: ${url}`);

            await page.goto(url, { waitUntil: "domcontentloaded" });
            await page.waitForSelector("#productTitle", { timeout: 15000 }).catch(() => {});
            await assertNotBlocked(page);

            // Scroll so the lazy "reviews" section is rendered
            for (let i = 0; i < 6; i++) {
                await page.mouse.wheel(0, 1400);
                await sleep(400);
            }
            await page
                .waitForSelector('[data-hook="review"]', { timeout: 6000 })
                .catch(() => {});

            await saveDebug(`amazon-product-${asin}.html`, await page.content());

            const details = await page.evaluate(() => {
                const t = (sel) => {
                    const el = document.querySelector(sel);
                    return el ? (el.textContent || "").replace(/\s+/g, " ").trim() : "";
                };

                const price =
                    t("#corePrice_feature_div .a-price:not(.a-text-price) .a-offscreen") ||
                    t("#corePriceDisplay_desktop_feature_div .a-price:not(.a-text-price) .a-offscreen") ||
                    t(".a-price:not(.a-text-price) .a-offscreen");

                const original =
                    t("#corePrice_feature_div .a-price.a-text-price .a-offscreen") ||
                    t(".basisPrice .a-offscreen");

                const landing = document.querySelector("#landingImage");
                const images = [];
                if (landing) {
                    const hires = landing.getAttribute("data-old-hires") || landing.src;
                    if (hires) images.push(hires);
                }
                document.querySelectorAll("#altImages img").forEach((i) => {
                    const src = (i.src || "").replace(/\._[A-Z0-9,_]+_\./, ".");
                    if (src && !images.includes(src) && !src.includes("sprite")) images.push(src);
                });

                const distribution = {};
                document
                    .querySelectorAll("#histogramTable [aria-label], #cm_cr_dp_d_rating_histogram [aria-label]")
                    .forEach((el) => {
                        const m = (el.getAttribute("aria-label") || "").match(/(\d+)\s*(?:percent|%)[^\d]*(\d)\s*star/i);
                        if (m) distribution[m[2]] = parseInt(m[1], 10);
                    });

                return {
                    title: t("#productTitle"),
                    price,
                    original,
                    ratingText: t("#acrPopover .a-icon-alt") || t("[data-hook='rating-out-of-text']"),
                    reviewCountText: t("#acrCustomerReviewText"),
                    availability: t("#availability"),
                    brand: t("#bylineInfo"),
                    bullets: Array.from(document.querySelectorAll("#feature-bullets li span.a-list-item"))
                        .map((e) => e.textContent.replace(/\s+/g, " ").trim())
                        .filter(Boolean),
                    customersSay: t("[data-hook='cr-insights-widget-summary']") || t("#product-summary p"),
                    images: images.slice(0, 10),
                    distribution
                };
            });

            // ---- Reviews: first the ones embedded in the product page ----
            let reviews = (await readReviewsOnPage(page)).map(mapReview);
            let reviewsSource = "product_page";

            // ---- Then try the full reviews pages (works only if Amazon doesn't ask for login) ----
            if (reviews.length < reviewsLimit) {
                try {
                    const collected = [...reviews];
                    let reachedLogin = false;

                    for (let p = 1; p <= 5 && collected.length < reviewsLimit; p++) {
                        await jitter();
                        await page.goto(
                            `https://www.${domain}/product-reviews/${asin}?reviewerType=all_reviews&sortBy=recent&pageNumber=${p}`,
                            { waitUntil: "domcontentloaded" }
                        );

                        if (/\/ap\/signin|\/ap\/challenge/i.test(page.url())) {
                            reachedLogin = true;
                            break;
                        }

                        await page.waitForSelector('[data-hook="review"]', { timeout: 8000 }).catch(() => {});
                        const more = (await readReviewsOnPage(page)).map(mapReview);
                        if (more.length === 0) break;
                        collected.push(...more);
                    }

                    if (!reachedLogin && collected.length > reviews.length) {
                        reviews = collected;
                        reviewsSource = "reviews_page";
                    }
                } catch (e) {
                    console.log(`Reviews page failed: ${e.message}`);
                }
            }

            reviews = dedupeReviews(reviews).slice(0, reviewsLimit);

            const price = parsePrice(details.price);
            let originalPrice = parsePrice(details.original);
            if (price != null && originalPrice != null && originalPrice <= price) originalPrice = null;

            return {
                product_id: asin,
                url,
                domain,
                name: details.title || null,
                brand: details.brand || null,
                price,
                original_price: originalPrice,
                currency: detectCurrency(details.price) || DOMAIN_CURRENCY[domain] || null,
                availability: details.availability || null,
                rating: parseRating(details.ratingText),
                reviews_count: parseCount(details.reviewCountText),
                rating_distribution: details.distribution,
                customers_say: details.customersSay || null,
                bullets: details.bullets,
                images: details.images,
                reviews,
                reviews_source: reviewsSource,
                source: "amazon"
            };
        }
    );
}

module.exports = { scrapeAmazon, scrapeAmazonProduct };
