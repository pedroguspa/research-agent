/**
 * Cloudflare Worker — Anthropic API Proxy
 *
 * Sits between the browser and api.anthropic.com so the real API key
 * never leaves the server. The key is stored as a CF Worker Secret
 * (wrangler secret put ANTHROPIC_API_KEY) and injected at runtime.
 *
 * Allowed origin is restricted to your GitHub Pages domain so random
 * third parties can't piggyback on your key.
 */

// ── Config ────────────────────────────────────────────────────────────────────
const ANTHROPIC_API = "https://api.anthropic.com";
const ANTHROPIC_VERSION = "2023-06-01";

// Add every origin that should be allowed to call this proxy.
// The wildcard entry lets you test locally too.
const ALLOWED_ORIGINS = [
  /^https:\/\/[a-z0-9-]+\.github\.io$/,   // any GitHub Pages domain
  /^http:\/\/localhost(:\d+)?$/,           // local Vite dev server
  /^http:\/\/127\.0\.0\.1(:\d+)?$/,
];

function isAllowedOrigin(origin) {
  if (!origin) return false;
  return ALLOWED_ORIGINS.some((pattern) => pattern.test(origin));
}

function corsHeaders(origin) {
  return {
    "Access-Control-Allow-Origin": origin,
    "Access-Control-Allow-Methods": "POST, OPTIONS",
    "Access-Control-Allow-Headers": "Content-Type",
    "Access-Control-Max-Age": "86400",
  };
}

function jsonResponse(status, payload, origin) {
  const headers = { "Content-Type": "application/json" };
  if (isAllowedOrigin(origin)) Object.assign(headers, corsHeaders(origin));
  return new Response(JSON.stringify(payload), { status, headers });
}

// ── Main handler ──────────────────────────────────────────────────────────────
export default {
  async fetch(request, env) {
    const origin = request.headers.get("Origin") || "";
    const anthropicApiKey = env?.ANTHROPIC_API_KEY;

    // ── Health check (no auth, no upstream call) ──
    const url = new URL(request.url);
    if (request.method === "GET" && url.pathname === "/health") {
      return jsonResponse(
        200,
        {
          ok: true,
          origin,
          keyPresent: Boolean(anthropicApiKey && anthropicApiKey.trim()),
        },
        origin
      );
    }

    // ── CORS pre-flight ──
    if (request.method === "OPTIONS") {
      if (!isAllowedOrigin(origin)) {
        return new Response("Forbidden", { status: 403 });
      }
      return new Response(null, { status: 204, headers: corsHeaders(origin) });
    }

    // ── Only POST /v1/messages is exposed ──
    if (request.method !== "POST" || url.pathname !== "/v1/messages") {
      return jsonResponse(404, { error: "Not Found" }, origin);
    }

    // ── Origin check ──
    if (!isAllowedOrigin(origin)) {
      // Intentionally do not include CORS headers for disallowed origins.
      return new Response(JSON.stringify({ error: "Origin not allowed" }), {
        status: 403,
        headers: { "Content-Type": "application/json" },
      });
    }

    // ── Validate body is JSON ──
    let body;
    try {
      body = await request.json();
    } catch {
      return jsonResponse(400, { error: "Invalid JSON body" }, origin);
    }

    // ── Guardrails: cap max_tokens to prevent runaway usage ──
    if (body.max_tokens > 500) body.max_tokens = 500;

    if (!anthropicApiKey) {
      return jsonResponse(
        500,
        { error: "Server misconfiguration: missing ANTHROPIC_API_KEY" },
        origin
      );
    }

    // ── Forward to Anthropic ──
    const upstream = await fetch(`${ANTHROPIC_API}/v1/messages`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "x-api-key": anthropicApiKey,
        "anthropic-version": ANTHROPIC_VERSION,
      },
      body: JSON.stringify(body),
    });

    // ── Stream the response back with CORS headers ──
    const response = new Response(upstream.body, {
      status: upstream.status,
      headers: {
        "Content-Type": upstream.headers.get("Content-Type") || "application/json",
        ...corsHeaders(origin),
      },
    });

    return response;
  },
};
