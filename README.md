# 🔬 Research Agent

An autonomous AI pipeline: **search → summarize → validate → edit → publish**.

The Anthropic API key never touches the browser. All API calls are proxied through a **Cloudflare Worker** that holds the key as an encrypted secret.

```
Browser  ──POST /v1/messages──▶  CF Worker (key lives here)  ──▶  Anthropic API
```

---

## Architecture

```
research-agent/
├── worker/
│   └── index.js              ← Cloudflare Worker proxy (key stored as CF secret)
├── src/
│   ├── main.jsx
│   └── ResearchAgent.jsx     ← Calls VITE_PROXY_URL, never Anthropic directly
├── .github/workflows/
│   ├── deploy.yml            ← GitHub Pages (frontend)
│   └── deploy-worker.yml     ← Cloudflare Worker (proxy)
├── wrangler.toml             ← CF Worker config
└── vite.config.js
```

---

## 🚀 Setup (≈ 10 min, fully free)

### Step 1 — Cloudflare Worker (proxy)

1. Create a free account at [cloudflare.com](https://cloudflare.com)
2. Find your **Account ID** in the dashboard right sidebar
3. Edit `wrangler.toml` and replace `<YOUR_CF_ACCOUNT_ID>`
4. Create a CF API token:
   `dash.cloudflare.com → My Profile → API Tokens → Create Token → "Edit Cloudflare Workers"`

### Step 2 — GitHub Secrets

Go to your repo → **Settings → Secrets → Actions** and add:

| Secret | Value |
|---|---|
| `CF_API_TOKEN` | Cloudflare API token from step 1 |
| `CF_ACCOUNT_ID` | Your Cloudflare Account ID |
| `ANTHROPIC_API_KEY` | Your `sk-ant-...` key |
| `VITE_PROXY_URL` | `https://research-agent-proxy.<subdomain>.workers.dev` |

> **Note:** Deploy the worker first (push to main) to get its URL, then add `VITE_PROXY_URL` and push again to rebuild the frontend.

### Step 3 — GitHub Pages

`Settings → Pages → Source → GitHub Actions`

### Step 4 — Push

```bash
git init && git add .
git commit -m "chore: init"
git remote add origin https://github.com/<you>/<repo>.git
git push -u origin main
```

Two workflows fire:
- `deploy-worker.yml` → deploys the CF Worker proxy
- `deploy.yml` → builds the React app and deploys to Pages

---

## Local development

```bash
# Terminal 1 — run the worker locally (holds the real key)
cp .env.example .env.local
npx wrangler dev                    # → http://localhost:8787

# In a separate tab, add your key to the worker:
echo "sk-ant-..." | npx wrangler secret put ANTHROPIC_API_KEY

# Terminal 2 — run the frontend
npm install
npm run dev                         # → http://localhost:5173
```

---

## Security model

| Layer | What it does |
|---|---|
| **CF Worker** | Holds API key as encrypted secret; enforces origin allowlist; caps max_tokens |
| **GitHub Secret** | Key is injected into CF Worker via `wrangler secret put` — never in source or bundle |
| **CORS allowlist** | Only `*.github.io` and `localhost` can call the proxy |
| **No key in frontend** | `VITE_PROXY_URL` is just a URL — zero credentials in the browser |

