const fs = require("fs");

const DEBUG = /^(1|true|yes)$/i.test(process.env.DEBUG || "");

class BlockedError extends Error {
    constructor(message, details = {}) {
        super(message);
        this.name = "BlockedError";
        this.code = "blocked";
        this.details = details;
    }
}

function cleanText(value) {
    if (value === null || value === undefined) return "";
    return String(value)
        .replace(/\u00a0/g, " ")
        .replace(/\s+/g, " ")
        .trim();
}

function sleep(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
}

function jitter(min = 400, max = 1200) {
    return sleep(min + Math.random() * (max - min));
}

/**
 * Parses counts such as: "1.2K", "1,234", "12.345", "3 ألف", "2M", "845"
 */
function parseCount(text) {
    if (text === null || text === undefined) return null;

    const s = cleanText(text);
    const m = s.match(
        /(\d[\d.,]*)\s*(ألف|آلاف|الاف|ألاف|مليون|ملايين|[KkMm])?/
    );
    if (!m) return null;

    let num = m[1];
    const suffix = (m[2] || "").toLowerCase();
    let value;

    if (suffix) {
        value = parseFloat(num.replace(",", "."));
        if (["ألف", "آلاف", "الاف", "ألاف", "k"].includes(suffix)) {
            value *= 1000;
        } else {
            value *= 1000000;
        }
    } else if (/^\d{1,3}([.,]\d{3})+$/.test(num)) {
        value = parseFloat(num.replace(/[.,]/g, ""));
    } else {
        value = parseFloat(num.replace(",", "."));
    }

    return Number.isFinite(value) ? Math.round(value) : null;
}

/** Parses a 0-5 rating from strings like "4.5 out of 5 stars" or "4,6 von 5 Sternen" */
function parseRating(text) {
    if (!text) return null;
    const m = String(text).match(/([0-5](?:[.,]\d)?)/);
    if (!m) return null;
    const v = parseFloat(m[1].replace(",", "."));
    return v >= 0 && v <= 5 ? v : null;
}

/** Parses "1,234.56", "1.234,56", "123,45", "123.45" */
function parsePrice(text) {
    if (!text) return null;
    const s = String(text).replace(/\u00a0/g, " ").replace(/[^\d.,]/g, "").trim();
    if (!s) return null;

    if (/^\d{1,3}(,\d{3})+(\.\d+)?$/.test(s)) return parseFloat(s.replace(/,/g, ""));
    if (/^\d{1,3}(\.\d{3})+(,\d+)?$/.test(s)) {
        return parseFloat(s.replace(/\./g, "").replace(",", "."));
    }
    if (/^\d+,\d{1,2}$/.test(s)) return parseFloat(s.replace(",", "."));

    const v = parseFloat(s.replace(/,/g, ""));
    return Number.isFinite(v) ? v : null;
}

function detectCurrency(text) {
    if (!text) return null;
    const s = String(text);
    if (/TND|د\.ت/i.test(s)) return "TND";
    if (s.includes("$") || /\bUSD\b/i.test(s)) return "USD";
    if (s.includes("€") || /\bEUR\b/i.test(s)) return "EUR";
    if (s.includes("£") || /\bGBP\b/i.test(s)) return "GBP";
    return null;
}

async function saveDebug(name, content) {
    if (!DEBUG) return;
    try {
        fs.writeFileSync(`/tmp/${name}`, content);
        console.log(`[DEBUG] saved /tmp/${name}`);
    } catch (e) {
        console.log(`[DEBUG] could not save ${name}: ${e.message}`);
    }
}

function dedupeReviews(reviews) {
    const seen = new Set();
    const out = [];
    for (const r of reviews) {
        const key = `${r.author || ""}|${r.date || ""}|${(r.text || "").slice(0, 80)}`;
        if (seen.has(key)) continue;
        seen.add(key);
        out.push(r);
    }
    return out;
}

module.exports = {
    DEBUG,
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
};
