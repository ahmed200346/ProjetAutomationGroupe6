const crypto = require("crypto");

const { withPage } = require("./browser");
const {
    BlockedError,
    cleanText,
    sleep,
    jitter,
    parseCount,
    parsePrice,
    saveDebug,
    dedupeReviews
} = require("./utils");

const CONTEXT = {
    locale: "en-US",
    timezoneId: "Africa/Tunis",
    extraHTTPHeaders: { "Accept-Language": "en-US,en;q=0.9" }
};

// =========================================================
// AliExpress mtop API
//
// The product page does not render its reviews in a stable DOM,
// it calls the internal "mtop" gateway instead. Every call has to
// be signed with the short lived `_m_h5_tk` cookie, and it has to
// be sent from inside the page so the session cookies travel
// with the request.
// =========================================================

const MTOP_HOST = "https://acs.aliexpress.com";
const MTOP_APP_KEY = "12574478";
const REVIEW_API = "mtop.aliexpress.review.pc.list";
const LEGACY_REVIEW_API = "https://feedback.aliexpress.com/pc/searchEvaluation.do";

const REVIEW_PAGE_SIZE = 20; // AliExpress always answers with 20, whatever we ask for
const MAX_REVIEW_PAGES = 5;

// Language the platform translates the reviews into. The page itself
// follows the IP country, which would hand back Arabic/French reviews
// depending on where the container runs. Override with
// ALEXPRESS_REVIEW_LANG (en_US, ar_AE, fr_FR, es_ES, ...) if needed.
const REVIEW_LANG = (process.env.ALEXPRESS_REVIEW_LANG || "en_US").trim() || "en_US";
const REVIEW_COUNTRY = (process.env.ALEXPRESS_REVIEW_COUNTRY || "US").trim() || "US";

const RETRYABLE_MTOP_ERROR = /FAIL_SYS_TOKEN|FAIL_SYS_SESSION_EXPIRED|FAIL_SYS_ILLEGAL_ACCESS/i;

// =========================================================
// Helpers
// =========================================================

function parseItemId(input) {
    if (!input) return null;
    const s = String(input);
    const m = s.match(/\/item\/(\d+)/) || s.match(/^(\d{8,})$/);
    return m ? m[1] : null;
}

function canonicalUrl(id) {
    return `https://www.aliexpress.com/item/${id}.html`;
}

function absoluteUrl(src) {
    if (!src) return "";
    return src.startsWith("//") ? `https:${src}` : src;
}

/** Accepts plain JSON as well as the `mtopjsonp1({...})` wrapper mtop replies with. */
function parseLooseJson(body) {
    if (!body) return null;
    const trimmed = String(body).trim();
    if (!trimmed || trimmed.startsWith("<")) return null; // captcha / punish page
    if (trimmed.startsWith("{") || trimmed.startsWith("[")) {
        try {
            return JSON.parse(trimmed);
        } catch {}
    }
    const m = trimmed.match(/^[\w$.]+\s*\(([\s\S]*)\)\s*;?$/);
    if (m) {
        try {
            return JSON.parse(m[1]);
        } catch {}
    }
    return null;
}

async function assertNotBlocked(page) {
    const url = page.url();
    const html = await page.content();

    const blocked =
        /punish|captcha|\/sec\/|login\.aliexpress/i.test(url) ||
        /nc_1_n1z|Captcha Interception|slide to verify|_____tmd_____|baxia-dialog/i.test(html);

    if (blocked) {
        await saveDebug(`aliexpress-blocked-${Date.now()}.html`, html);
        throw new BlockedError("AliExpress returned a captcha / verification page", { url });
    }
}

// ---------------------------------------------------------
// mtop transport
// ---------------------------------------------------------

function mtopUrl({ token, api, version, data }) {
    const t = Date.now();
    const payload = JSON.stringify(data);
    const sign = crypto
        .createHash("md5")
        .update(`${token}&${t}&${MTOP_APP_KEY}&${payload}`)
        .digest("hex");

    const params = new URLSearchParams({
        jsv: "2.5.1",
        appKey: MTOP_APP_KEY,
        t: String(t),
        sign,
        api,
        v: version,
        type: "originaljson",
        dataType: "json",
        timeout: "15000",
        data: payload
    });

    return `${MTOP_HOST}/h5/${api}/${version}/?${params.toString()}`;
}

async function mtopToken(context) {
    const cookies = await context.cookies().catch(() => []);
    const hit = cookies.find((c) => c.name === "_m_h5_tk");
    return hit ? String(hit.value).split("_")[0] : "";
}

/**
 * Calls an mtop API. Returns the parsed payload or null.
 * The very first call of a session always answers
 * FAIL_SYS_TOKEN_EMPTY, so the token is re-read and the call retried.
 */
async function mtopCall(page, context, { api, version = "1.0", data }, tries = 4) {
    let reason = "unknown error";

    for (let attempt = 1; attempt <= tries; attempt++) {
        const url = mtopUrl({ token: await mtopToken(context), api, version, data });

        let payload = null;
        try {
            const raw = await page.evaluate(async (u) => {
                const res = await fetch(u, { credentials: "include" });
                return await res.text();
            }, url);
            payload = parseLooseJson(raw);
        } catch (error) {
            reason = error.message;
        }

        const ret = payload && Array.isArray(payload.ret) ? payload.ret.join(" | ") : "";

        if (payload && (/SUCCESS/i.test(ret) || (!ret && payload.data))) return payload;

        reason = ret || reason || "empty response";
        if (payload && !RETRYABLE_MTOP_ERROR.test(ret)) break; // captcha / ban: hammering won't help
        await sleep(400);
    }

    console.log(`  mtop ${api} failed: ${reason}`);
    return null;
}

// ---------------------------------------------------------
// Small parsers
// ---------------------------------------------------------
// Arabic harakat + tatweel only (never the letters themselves), so a word
// written with a damma still matches its plain form.
const ARABIC_MARKS = /[\u0610-\u061A\u0640\u064B-\u065F\u0670\u06D6-\u06ED]/g;

function stripArabicMarks(value) {
    return String(value || "").replace(ARABIC_MARKS, "");
}

/** pdp_npi=...dis!TND!ORIGINAL!CURRENT!... */
function parseUrlPrices(url) {
    try {
        const decoded = decodeURIComponent(url);
        const m = decoded.match(/pdp_npi=[^&]*dis!([A-Z]{3})!([0-9]+(?:\.[0-9]+)?)!([0-9]+(?:\.[0-9]+)?)/i);
        if (m) {
            return {
                currency: m[1].toUpperCase(),
                original_price: parseFloat(m[2]),
                price: parseFloat(m[3])
            };
        }
    } catch {}
    return null;
}

function parseTextPrices(text) {
    const normalized = cleanText(text).replace(/(\d),(\d{3})/g, "$1$2");
    const re = /(TND|US\s?\$|USD|€|EUR)\s?([0-9]+(?:\.[0-9]{1,3})?)/gi;
    const found = [];
    let m;
    while ((m = re.exec(normalized)) !== null) {
        const cur = /TND/i.test(m[1]) ? "TND" : /€|EUR/i.test(m[1]) ? "EUR" : "USD";
        found.push({ currency: cur, value: parseFloat(m[2]) });
    }
    if (found.length === 0) return null;
    return {
        currency: found[0].currency,
        price: found[0].value,
        original_price: found[1] && found[1].value > found[0].value ? found[1].value : null
    };
}

// "1,234" / "3" / "1.2K" / "3 آلاف" - shared by the Arabic and English patterns
const AR_COUNT = "[0-9]+(?:[.,][0-9]+)?\\s*(?:ألف|آلاف|الاف|ألاف|مليون|ملايين|[KkMm])?";

function parseSold(text) {
    const s = stripArabicMarks(cleanText(text));
    const number = `(${AR_COUNT})`;
    const m =
        s.match(new RegExp(`${number}\\s*\\+?\\s*\\+?\\s*sold`, "i")) ||
        s.match(new RegExp(`(?:فوق\\s*)?\\+?\\s*${number}\\s*\\+?\\s*(?:مباعة|مباع|مبيع|تم بيع)`, "i"));
    return m ? parseCount(m[1]) : null;
}

/** "X reviews" / "X تقييمات" when a layout happens to show it. */
function parseReviewCountText(text) {
    const s = stripArabicMarks(cleanText(text));
    const m =
        s.match(new RegExp(`(${AR_COUNT})\\s*(?:reviews?|ratings?)`, "i")) ||
        s.match(new RegExp(`(${AR_COUNT})\\s*(?:ال)?(?:تقييم|مراجعة)`, "i"));
    return m ? parseCount(m[1]) : null;
}

/**
 * Review counter as printed in the product page header, e.g.
 * "541 ratings", "541 التقييمات", "1.2K ratings", "التقييمات (541)".
 * This is the Amazon-style source: plain DOM, no signed API call, so it
 * keeps working even when the review endpoint is degraded.
 */
function parseReviewCountLabel(label) {
    const s = stripArabicMarks(cleanText(label));
    if (!s) return null;

    const direct = parseReviewCountText(s);
    if (direct != null) return direct;

    // "(541)" / "التقييمات 541"
    const paren = s.match(/[([]\s*([0-9][0-9.,]*\s*(?:[KkMm])?)\s*[)\]]/);
    if (paren) return parseCount(paren[1]);

    const bare = s.match(/([0-9][0-9.,]*\s*(?:[KkMm])?)/);
    return bare ? parseCount(bare[1]) : null;
}

/** Average rating printed in the same header block. */
function parseRatingLabel(label) {
    const m = String(label || "").match(/([0-5](?:[.,]\d)?)/);
    if (!m) return null;
    const v = parseFloat(m[1].replace(",", "."));
    return Number.isFinite(v) ? v : null;
}

function guessName(lines) {
    const candidates = lines.filter(
        (l) =>
            l.length > 15 &&
            !/^(TND|US\s?\$|USD|€|EUR)/i.test(l) &&
            !/\bsold\b|free shipping|شحن|مباعة|مباع|مبيع|%\s*off/i.test(l)
    );
    return candidates.sort((a, b) => b.length - a.length)[0] || "";
}

/**
 * Titles come from a page that follows the IP country, so they arrive with the
 * marketplace suffix glued on ("... - AliExpress 44"). Removing it keeps the
 * title usable for the downstream cleaning step.
 */
function cleanTitle(value) {
    return cleanText(value)
        .replace(/\s*[-|–—]\s*AliExpress(?:\s*\.com)?\s*\d*\s*$/i, "")
        .replace(/\s*[-|–—]\s*aliexpress\.com\s*$/i, "")
        .replace(/^AliExpress\s*[-|–—]\s*/i, "")
        .trim();
}

// =========================================================
// REVIEWS mapping
// =========================================================

/** "Color:Pink " -> "Color: Pink" */
function cleanSku(value) {
    return cleanText(String(value || "").replace(/\s*:\s*/g, ": ")) || null;
}

const ARABIC_RE = /[\u0600-\u06FF]/;
const CJK_RE = /[\u3040-\u30FF\u4E00-\u9FFF]/;
const CYRILLIC_RE = /[\u0400-\u04FF]/;

/** Coarse script detection: enough to tell "English" from "translated". */
function detectLanguage(text) {
    const s = String(text || "");
    if (!s) return null;
    if (ARABIC_RE.test(s)) return "ar";
    if (CJK_RE.test(s)) return "ja";
    if (CYRILLIC_RE.test(s)) return "ru";
    return "en";
}

function reviewImages(r) {
    const out = [];
    const push = (src) => {
        if (!src) return;
        // "photo.jpg_220x220.jpg_.avif" -> "photo.jpg"
        const url = absoluteUrl(String(src))
            .split("?")[0]
            .replace(/_\d+x\d+(\.[a-z0-9]+)?_?(\.avif)?$/i, "");
        if (url && !out.includes(url)) out.push(url);
    };
    (r.images || []).forEach(push);
    (r.buyerAddFbImages || []).forEach(push);
    return out.slice(0, 12);
}

function reviewLabels(r) {
    const labels = [];
    for (let i = 1; i <= 3; i++) {
        const name = cleanText(r[`reviewLabel${i}`]);
        const value = cleanText(r[`reviewLabelValue${i}`]);
        if (name) labels.push({ name, value: value || null });
    }
    return labels;
}

function mapReview(r) {
    // AliExpress scores a review out of 100 (20, 40, 60, 80, 100)
    const raw = Number(r.buyerEval ?? r.star ?? r.rating);
    let rating = null;
    if (Number.isFinite(raw) && raw > 0) {
        rating = raw <= 5 ? raw : Math.round((raw / 20) * 10) / 10;
    }

    const original = cleanText(r.buyerFeedback);
    const translated = cleanText(r.buyerTranslationFeedback);

    return {
        // evaluationId is a 17 digit number: JSON.parse rounds it, the string twin does not
        id: r.evaluationIdStr || (r.evaluationId != null ? String(r.evaluationId) : null),
        author: cleanText(r.buyerName) || null,
        rating,
        title: null,
        text: translated || original,
        text_original: translated && translated !== original ? original : null,
        extra_text: cleanText(r.buyerAddFbTranslation || r.buyerAddFbContent) || null,
        date: cleanText(r.evalDate) || null,
        country: r.buyerCountry || null,
        verified_purchase: true,
        variant: cleanSku(r.skuInfo),
        helpful_votes: Number(r.upVoteCount) || 0,
        unhelpful_votes: Number(r.downVoteCount) || 0,
        source_language: (r.buyerFbType && r.buyerFbType.sourceLang) || null,
        logistics: cleanText(r.logistics) || null,
        labels: reviewLabels(r),
        images: reviewImages(r)
    };
}

/** Recursively finds arrays of review-like objects in any JSON payload. */
function collectReviews(node, out = []) {
    if (Array.isArray(node)) {
        const isReviewArray = node.some(
            (n) => n && typeof n === "object" && ("buyerFeedback" in n || "buyerTranslationFeedback" in n)
        );
        if (isReviewArray) {
            for (const r of node) out.push(mapReview(r));
        } else {
            for (const n of node) collectReviews(n, out);
        }
    } else if (node && typeof node === "object") {
        for (const v of Object.values(node)) collectReviews(v, out);
    }
    return out;
}

function dedupeById(reviews) {
    const seen = new Set();
    const out = [];
    for (const r of reviews) {
        const key = r.id || `${r.author || ""}|${r.date || ""}|${(r.text || "").slice(0, 80)}`;
        if (seen.has(key)) continue;
        seen.add(key);
        out.push(r);
    }
    return out;
}

function hasContent(r) {
    return !!(r.text || r.extra_text);
}

function mapStats(stats) {
    if (!stats) return { rating: null, reviews_count: null, distribution: null, sentiment: null, sold: null };

    const dist = {
        5: Number(stats.fiveStarNum) || 0,
        4: Number(stats.fourStarNum) || 0,
        3: Number(stats.threeStarNum) || 0,
        2: Number(stats.twoStarNum) || 0,
        1: Number(stats.oneStarNum) || 0
    };

    // A product with no ratings reports evarageStar 0. That is "unknown", not
    // "terrible", and the downstream scorer turns a 0 into a score of 0.
    const rawRating = stats.evarageStar != null ? Number(stats.evarageStar) : null;

    return {
        rating: rawRating != null && rawRating > 0 ? rawRating : null,
        reviews_count: stats.totalNum != null ? parseCount(stats.totalNum) : null,
        sold: parseSold(String(stats.tradeCount || stats.soldCount || "")),
        distribution: dist,
        sentiment: {
            positive: Number(stats.positiveNum) || 0,
            neutral: Number(stats.neutralNum) || 0,
            negative: Number(stats.negativeNum) || 0,
            positive_rate: stats.positiveRate != null ? Number(stats.positiveRate) : null
        }
    };
}

// ---------------------------------------------------------
// Review sources, in order of reliability
// ---------------------------------------------------------

async function fetchReviewPage(page, context, productId, pageNumber) {
    const payload = await mtopCall(page, context, {
        api: REVIEW_API,
        version: "1.0",
        data: {
            productId: String(productId),
            page: pageNumber,
            pageSize: REVIEW_PAGE_SIZE,
            _lang: REVIEW_LANG,
            filter: "all",
            sort: "complex_default",
            country: REVIEW_COUNTRY,
            clientType: "web"
        }
    });
    return payload && payload.data ? payload.data : null;
}

async function fetchLegacyReviewPage(page, productId, pageNumber) {
    const raw = await page.evaluate(
        async ({ url }) => {
            const res = await fetch(url, { credentials: "include" });
            return await res.text();
        },
        {
            url:
                `${LEGACY_REVIEW_API}?productId=${productId}` +
                `&lang=${encodeURIComponent(REVIEW_LANG)}&country=${REVIEW_COUNTRY}` +
                `&page=${pageNumber}&pageSize=${REVIEW_PAGE_SIZE}&filter=all&sort=complex_default`
        }
    );
    const payload = parseLooseJson(raw);
    return payload && payload.data ? payload.data : null;
}

/** Last resort: the 3 reviews the page renders itself. */
async function readReviewsOnPage(page) {
    return page.evaluate(() => {
        const text = (root, fragment) => {
            const el = root.querySelector(`[class*="${fragment}"]`);
            return el ? (el.innerText || el.textContent || "").replace(/\s+/g, " ").trim() : "";
        };

        return Array.from(document.querySelectorAll('[class*="itemReview"]'))
            .map((node) => {
                const item = node.closest('[class*="itemBox"], [class*="itemContent"]') || node.parentElement;
                const stars = item ? item.querySelectorAll('[class*="starreviewfilled"]').length : 0;

                const info = text(item, "itemInfo"); // "author | date"
                const [author, date] = info.split("|").map((s) => (s || "").trim());

                return {
                    author: author || null,
                    date: date || null,
                    text: (node.innerText || "").replace(/\s+/g, " ").trim(),
                    variant: text(item, "itemSku") || null,
                    rating: stars > 0 ? Math.min(5, stars) : null,
                    helpfulText: text(item, "itemHelpText")
                };
            })
            .filter((r) => r.text);
    });
}

function mapDomReview(r) {
    const votes = String(r.helpfulText || "").match(/[0-9]+/);
    return {
        id: null,
        author: r.author || null,
        rating: r.rating,
        title: null,
        text: cleanText(r.text),
        text_original: null,
        extra_text: null,
        date: r.date || null,
        country: null,
        verified_purchase: true,
        variant: cleanSku(r.variant),
        helpful_votes: votes ? Number(votes[0]) : 0,
        unhelpful_votes: 0,
        source_language: null,
        logistics: null,
        labels: [],
        images: []
    };
}

/**
 * Pulls reviews for a product.
 * 1. the signed mtop endpoint used by the product page itself
 * 2. a reload + retry of the same endpoint (throttling often clears)
 * 3. the legacy feedback endpoint
 * 4. whatever review payloads the page loaded on its own
 * 5. the reviews rendered in the DOM
 *
 * Sources 4 and 5 are in whatever language the page was served in, which
 * follows the IP country. Falling back to them silently would mix languages,
 * so the language actually returned is reported back to the caller.
 */
async function fetchReviews(page, context, productId, limit, captured) {
    const result = {
        reviews: [],
        stats: null,
        impressions: [],
        source: "none",
        language: null,
        total_pages: null
    };

    const want = limit > 0 ? Math.min(limit, 50) : 0;
    // The endpoint sometimes answers a page with far fewer than 20 reviews, so we
    // keep going until we have enough rather than trusting the page size.
    const maxPages = Math.max(1, Math.min(MAX_REVIEW_PAGES, want || 1));

    const runMtop = async () => {
        let emptyPages = 0;

        for (let p = 1; p <= maxPages; p++) {
            const data = await fetchReviewPage(page, context, productId, p);
            if (!data) break;

            if (!result.stats) {
                result.stats = data.productEvaluationStatistic || null;
                result.impressions = (data.impressionDTOList || []).map((i) => ({
                    text: cleanText(i.content) || null,
                    count: Number(i.num) || 0,
                    sentiment: Number(i.emotion) || 0
                }));
            }
            result.total_pages = Number(data.totalPage) || null;

            const batch = (Array.isArray(data.evaViewList) ? data.evaViewList : []).map(mapReview).filter(hasContent);
            console.log(`  reviews page ${p}: ${batch.length}`);

            if (batch.length === 0) {
                if (++emptyPages >= 2) break;
            } else {
                emptyPages = 0;
                result.reviews.push(...batch);
                result.source = "mtop";
            }

            if (want > 0 && result.reviews.length >= want) break;
            if (p >= (Number(data.totalPage) || 1)) break;
            await jitter(300, 700);
        }
    };

    await runMtop();

    // ---- 2) stats say there are reviews but the list came back empty:
    // that is throttling, so reload for a fresh token and try once more.
    const declared = result.stats && parseCount(result.stats.totalNum);
    if (result.reviews.length === 0 && declared > 0) {
        console.log(`  review list empty although ${declared} are reported, reloading...`);
        try {
            await page.reload({ waitUntil: "domcontentloaded" });
            await page.waitForSelector("h1", { timeout: 10000 }).catch(() => {});
            await sleep(1500);
            result.reviews = [];
            await runMtop();
        } catch (e) {
            console.log(`  reload retry failed: ${e.message}`);
        }
    }

    // ---- 3) legacy endpoint ----
    if (result.reviews.length === 0) {
        const data = await fetchLegacyReviewPage(page, productId, 1).catch((e) => {
            console.log(`  legacy feedback endpoint failed: ${e.message}`);
            return null;
        });

        if (data) {
            if (!result.stats) result.stats = data.productEvaluationStatistic || null;
            const batch = (Array.isArray(data.evaViewList) ? data.evaViewList : []).map(mapReview).filter(hasContent);
            if (batch.length) {
                result.reviews.push(...batch);
                result.source = "legacy_endpoint";
            }
        }
    }

    // ---- 4) review payloads the page already loaded ----
    if (result.reviews.length === 0 && captured.length) {
        for (const payload of captured) {
            const found = collectReviews(payload, []).filter(hasContent);
            if (found.length) {
                result.reviews.push(...found);
                result.source = "network_capture";
            }
        }
    }

    // ---- 5) rendered DOM ----
    if (result.reviews.length === 0) {
        const domReviews = await readReviewsOnPage(page).catch(() => []);
        if (domReviews.length) {
            result.reviews.push(...domReviews.map(mapDomReview));
            result.source = "page_dom";
        }
    }

    result.reviews = dedupeById(result.reviews).slice(0, want);

    // Report the language we actually got. Only the first two sources honour
    // REVIEW_LANG; the last two follow the IP country, so the caller has to be
    // able to tell the difference instead of trusting the field blindly.
    result.language =
        result.reviews.length === 0
            ? null
            : detectLanguage(result.reviews.map((r) => r.text).join(" ").slice(0, 4000));

    const expected = REVIEW_LANG.split("_")[0];
    if (result.language && result.language !== expected) {
        console.log(
            `  WARNING: reviews came back in "${result.language}" not "${expected}" ` +
                `(source=${result.source}) - the review API was unavailable and the page language was used`
        );
    }

    return result;
}

// =========================================================
// SEARCH
// =========================================================

async function readSearchCards(page) {
    return page.evaluate(() => {
        const idOf = (href) => {
            const m = (href || "").match(/\/item\/(\d+)/);
            return m ? m[1] : null;
        };

        const byId = new Map();

        for (const a of document.querySelectorAll('a[href*="/item/"]')) {
            const id = idOf(a.href);
            if (!id || byId.has(id)) continue;

            // Climb up until the container would include another product
            let node = a;
            while (node.parentElement && node.parentElement !== document.body) {
                const parent = node.parentElement;
                const ids = new Set(
                    Array.from(parent.querySelectorAll('a[href*="/item/"]'))
                        .map((x) => idOf(x.href))
                        .filter(Boolean)
                );
                if (ids.size > 1) break;
                node = parent;
            }

            const img = node.querySelector("img");
            const h = node.querySelector("h1, h2, h3");

            byId.set(id, {
                id,
                href: a.href,
                title: (h && h.innerText) || a.getAttribute("title") || (img && img.alt) || "",
                lines: (node.innerText || "")
                    .split("\n")
                    .map((l) => l.replace(/\s+/g, " ").trim())
                    .filter(Boolean),
                image: img ? img.getAttribute("src") || img.getAttribute("data-src") || "" : ""
            });

            if (byId.size >= 60) break;
        }

        return Array.from(byId.values());
    });
}

/** Search cards have no review counter, so ask the review API for it (3 at a time). */
async function fillSearchReviewCounts(page, context, products) {
    const queue = products.filter((p) => p.product_id);
    const workers = Math.min(3, queue.length);

    await Promise.all(
        Array.from({ length: workers }, async () => {
            while (queue.length) {
                const product = queue.shift();
                const data = await fetchReviewPage(page, context, product.product_id, 1);
                if (!data) continue;

                const stats = mapStats(data.productEvaluationStatistic);
                if (stats.reviews_count != null) product.reviews = stats.reviews_count;
                // overwrite a missing or impossible rating, but never contradict a
                // real one that came off the card
                if (stats.rating != null && (product.rating == null || product.rating <= 0)) {
                    product.rating = stats.rating;
                }
                if (product.sold == null) {
                    const sold = stats.sold;
                    if (sold != null) product.sold = sold;
                }
            }
        })
    );
}

async function scrapeAliExpress(query, limit = 5, options = {}) {
    limit = Math.max(1, Math.min(Number(limit) || 5, 20));
    const includeReviews = options.includeReviews !== false;

    console.log(`\nAliExpress search: "${query}" (limit ${limit})`);

    return withPage(CONTEXT, async (page, context) => {
        const searchUrl = "https://www.aliexpress.com/wholesale?SearchText=" + encodeURIComponent(query);

        // AliExpress sometimes answers with a 404 stub before redirecting to the
        // real /w/wholesale-... page, and a captcha can be transient rate
        // limiting. Both clear up on a fresh attempt, so retry before failing.
        let lastError = null;

        for (let attempt = 1; attempt <= 3; attempt++) {
            try {
                await page.goto(searchUrl, { waitUntil: "domcontentloaded" });
                const ok = await page
                    .waitForSelector('a[href*="/item/"]', { timeout: 12000 })
                    .then(() => true)
                    .catch(() => false);

                if (!ok) {
                    console.log(`  search attempt ${attempt} landed on ${page.url()}, retrying`);
                    await jitter(1500, 3000);
                    continue;
                }

                await assertNotBlocked(page);
                lastError = null;
                break;
            } catch (error) {
                lastError = error;
                console.log(`  search attempt ${attempt} failed: ${error.message}`);
                await jitter(4000, 8000);
            }
        }

        if (lastError) throw lastError;

        // Scroll to trigger lazy loading
        for (let i = 0; i < 8; i++) {
            await page.mouse.wheel(0, 900);
            await sleep(400);
        }
        await jitter(800, 1500);

        await saveDebug("aliexpress-search.html", await page.content());

        const raw = await readSearchCards(page);
        console.log(`Cards found: ${raw.length}`);

        if (raw.length === 0) {
            throw new BlockedError("No product cards found (probably a verification page or layout change)", {
                url: page.url()
            });
        }

        const products = [];

        for (const c of raw) {
            if (products.length >= limit) break;

            const text = c.lines.join(" ");
            const prices = parseUrlPrices(c.href) || parseTextPrices(text) || {};
            const name = cleanTitle(c.title) || cleanTitle(guessName(c.lines));
            if (!name) continue;

            // "0" is not a rating, it is an unrated listing. A card can also print
            // a bare "0" for an unrelated counter. Only 1..5 counts, everything else
            // stays null so the scorer can fall back to the review API.
            const ratingLine = c.lines.find((l) => /^[1-5](\.\d)?$/.test(l));
            // Only free shipping is reported. The downstream scorer awards 100 for
            // free, 50 for paid and nothing when absent, so returning null for paid
            // shipping actually scores better than returning a paid label.
            const freeShipping = /free\s*shipping|شحن\s*مجاني|الشحن\s+مجاني|free\s+delivery/i.test(text);

            products.push({
                name,
                price: prices.price ?? null,
                original_price:
                    prices.original_price != null && prices.original_price > (prices.price ?? 0)
                        ? prices.original_price
                        : null,
                currency: prices.currency ?? null,
                rating: ratingLine ? parseFloat(ratingLine) : null,
                sold: parseSold(text),
                reviews: parseReviewCountText(text),
                shipping: freeShipping ? "free shipping" : null,
                image: absoluteUrl(c.image),
                url: canonicalUrl(c.id),
                product_id: c.id,
                source: "aliexpress"
            });
        }

        if (includeReviews && products.length) {
            console.log(`Fetching review counters for ${products.length} products...`);
            await fillSearchReviewCounts(page, context, products);
        }

        return products;
    });
}

// =========================================================
// PRODUCT + REVIEWS
// =========================================================

async function scrapeAliExpressProduct(input, reviewsLimit = 10) {
    const id = parseItemId(input.product_id || input.url);
    if (!id) throw new Error("Could not determine the AliExpress item id (send product_id or an /item/ URL)");

    reviewsLimit = Math.max(0, Math.min(Number(reviewsLimit) || 0, 50));

    return withPage(CONTEXT, async (page, context) => {
        const captured = [];
        const pending = [];

        // Keep whatever review payload the product page asks for on its own
        page.on("response", (response) => {
            const u = response.url();
            if (!/review\.pc\.list|searchEvaluation\.do|feedback/i.test(u)) return;
            pending.push(
                response
                    .text()
                    .then((body) => {
                        const payload = parseLooseJson(body);
                        if (payload) captured.push(payload);
                    })
                    .catch(() => {})
            );
        });

        const url = canonicalUrl(id);
        console.log(`AliExpress product: ${url}`);

        // A captcha is often transient (rate limiting), so give it a couple of
        // fresh attempts before failing the whole request.
        let blocked = null;
        for (let attempt = 1; attempt <= 3; attempt++) {
            await page.goto(url, { waitUntil: "domcontentloaded" });
            await page.waitForSelector("h1", { timeout: 15000 }).catch(() => {});

            try {
                await assertNotBlocked(page);
                blocked = null;
                break;
            } catch (error) {
                blocked = error;
                console.log(`  attempt ${attempt}/3 blocked, waiting before retry`);
                await jitter(4000, 8000);
            }
        }
        if (blocked) throw blocked;

        // Scroll gradually so the reviews block loads
        for (let i = 0; i < 8; i++) {
            await page.mouse.wheel(0, 900);
            await sleep(500);
        }

        // Some layouts hide reviews behind a tab
        await page
            .getByText(/^(Customer )?Reviews/i)
            .first()
            .click({ timeout: 2500 })
            .catch(() => {});
        await sleep(2000);

        await saveDebug(`aliexpress-product-${id}.html`, await page.content());

        // ---- Product details (JSON-LD first, DOM as fallback) ----
        const details = await page.evaluate(() => {
            let ld = null;
            document.querySelectorAll('script[type="application/ld+json"]').forEach((s) => {
                try {
                    const j = JSON.parse(s.textContent);
                    const list = Array.isArray(j) ? j : [j];
                    const p = list.find((x) => x && x["@type"] === "Product");
                    if (p) ld = p;
                } catch {}
            });

            const meta = (p) => {
                const el = document.querySelector(`meta[property="${p}"]`);
                return el ? el.getAttribute("content") : "";
            };

            const h1 = document.querySelector("h1");
            const offers = ld && (Array.isArray(ld.offers) ? ld.offers[0] : ld.offers);

            // Amazon-style: the header block prints rating / count / sold as plain
            // text. Independent of the review API, so it stays correct even when
            // that endpoint is throttled. The class names are hashed, but the
            // prefix before the hash is stable.
            const box = document.querySelector('[data-pl="product-reviewer"]');
            const pick = (fragment) => {
                if (!box) return "";
                const el = box.querySelector('[class*="' + fragment + '"]');
                return el ? (el.innerText || el.textContent || "").replace(/\s+/g, " ").trim() : "";
            };

            return {
                title: (ld && ld.name) || (h1 && h1.innerText) || meta("og:title") || "",
                images: ld && ld.image ? [].concat(ld.image).slice(0, 10) : [meta("og:image")].filter(Boolean),
                price: offers ? offers.price || offers.lowPrice : null,
                currency: offers ? offers.priceCurrency : null,
                rating: ld && ld.aggregateRating ? ld.aggregateRating.ratingValue : null,
                reviewCount: ld && ld.aggregateRating ? ld.aggregateRating.reviewCount : null,
                domRatingText: pick("reviewer--rating"),
                domReviewCountText: pick("reviewer--reviews"),
                domSoldText: pick("reviewer--sold"),
                bodyText: (document.body.innerText || "").slice(0, 20000)
            };
        });

        // ---- Reviews ----
        await Promise.allSettled(pending);
        const fetched = await fetchReviews(page, context, id, reviewsLimit, captured);

        const stats = mapStats(fetched.stats);
        const reviews = dedupeReviews(fetched.reviews).slice(0, reviewsLimit);

        // The API wins, but the header block covers it when the API is degraded
        const domRating = parseRatingLabel(details.domRatingText);
        const domReviews = parseReviewCountLabel(details.domReviewCountText);
        const domSold = parseSold(details.domSoldText);

        return {
            product_id: id,
            url,
            name: cleanText(details.title) || null,
            price: parsePrice(details.price),
            currency: details.currency || null,
            rating: stats.rating ?? domRating ?? (details.rating != null ? Number(details.rating) : null),
            reviews_count:
                stats.reviews_count ??
                domReviews ??
                (details.reviewCount != null ? parseCount(details.reviewCount) : null),
            rating_distribution: stats.distribution,
            review_sentiment: stats.sentiment,
            review_impressions: fetched.impressions,
            reviews_pages: fetched.total_pages,
            sold: domSold ?? parseSold(details.bodyText),
            images: details.images,
            reviews,
            reviews_source: fetched.source,
            reviews_language: fetched.language,
            source: "aliexpress"
        };
    });
}

module.exports = { scrapeAliExpress, scrapeAliExpressProduct };
