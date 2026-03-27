import { useState, useRef, useCallback } from "react";

// ── Styles ──────────────────────────────────────────────────────────────────
const FONTS = `@import url('https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=JetBrains+Mono:wght@300;400;500&display=swap');`;

const css = `
  ${FONTS}
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --bg:       #0b0c0e;
    --surface:  #13141a;
    --border:   #1f2130;
    --dim:      #2a2d40;
    --muted:    #50556e;
    --text:     #d4d6e8;
    --bright:   #eceef8;
    --accent:   #7c6fff;
    --accent2:  #ff6b6b;
    --green:    #4ade80;
    --yellow:   #fbbf24;
    --mono:     'JetBrains Mono', monospace;
    --sans:     'Syne', sans-serif;
  }

  body { background: var(--bg); color: var(--text); font-family: var(--sans); min-height: 100vh; }

  .app { max-width: 900px; margin: 0 auto; padding: 40px 24px 80px; }

  /* Header */
  .header { margin-bottom: 48px; }
  .header-eyebrow { font-family: var(--mono); font-size: 11px; letter-spacing: .18em; text-transform: uppercase; color: var(--accent); margin-bottom: 12px; }
  .header-title { font-size: clamp(28px, 5vw, 46px); font-weight: 800; color: var(--bright); line-height: 1.1; }
  .header-title span { color: var(--accent); }
  .header-sub { font-family: var(--mono); font-size: 13px; color: var(--muted); margin-top: 10px; }

  /* Pipeline bar */
  .pipeline { display: flex; align-items: center; gap: 0; margin-bottom: 40px; overflow-x: auto; padding: 4px 0 8px; }
  .pipe-step { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
  .pipe-dot { width: 28px; height: 28px; border-radius: 50%; border: 1.5px solid var(--border); display: flex; align-items: center; justify-content: center; font-family: var(--mono); font-size: 11px; color: var(--muted); transition: all .3s; flex-shrink: 0; }
  .pipe-dot.active { border-color: var(--accent); color: var(--accent); box-shadow: 0 0 12px #7c6fff40; }
  .pipe-dot.done { border-color: var(--green); background: #4ade8015; color: var(--green); }
  .pipe-dot.error { border-color: var(--accent2); color: var(--accent2); }
  .pipe-label { font-family: var(--mono); font-size: 10px; letter-spacing: .1em; text-transform: uppercase; color: var(--muted); white-space: nowrap; }
  .pipe-label.active { color: var(--accent); }
  .pipe-label.done { color: var(--green); }
  .pipe-connector { width: 32px; height: 1px; background: var(--border); flex-shrink: 0; margin: 0 4px; }
  .pipe-connector.done { background: var(--green); }

  /* Config panel */
  .panel { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 28px; margin-bottom: 20px; }
  .panel-title { font-size: 13px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: var(--bright); margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
  .panel-title::before { content: ''; width: 3px; height: 14px; background: var(--accent); border-radius: 2px; display: block; }

  /* Inputs */
  .field { margin-bottom: 16px; }
  .field label { display: block; font-family: var(--mono); font-size: 11px; letter-spacing: .12em; text-transform: uppercase; color: var(--muted); margin-bottom: 8px; }
  .input { width: 100%; background: var(--bg); border: 1px solid var(--dim); border-radius: 8px; padding: 10px 14px; color: var(--text); font-family: var(--mono); font-size: 13px; outline: none; transition: border-color .2s; }
  .input:focus { border-color: var(--accent); }
  .input::placeholder { color: var(--muted); }
  textarea.input { resize: vertical; min-height: 80px; line-height: 1.6; }

  /* Tags */
  .tags-row { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
  .tag { display: flex; align-items: center; gap: 6px; background: #7c6fff18; border: 1px solid #7c6fff40; border-radius: 20px; padding: 5px 12px; font-family: var(--mono); font-size: 12px; color: var(--accent); }
  .tag button { background: none; border: none; color: var(--muted); cursor: pointer; font-size: 14px; line-height: 1; padding: 0; transition: color .15s; }
  .tag button:hover { color: var(--accent2); }
  .tag-input-row { display: flex; gap: 8px; }
  .tag-input-row .input { flex: 1; }

  /* Buttons */
  .btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; border-radius: 8px; font-family: var(--sans); font-weight: 600; font-size: 13px; cursor: pointer; border: none; transition: all .2s; letter-spacing: .03em; }
  .btn-primary { background: var(--accent); color: #fff; }
  .btn-primary:hover:not(:disabled) { background: #9585ff; box-shadow: 0 0 20px #7c6fff50; }
  .btn-primary:disabled { opacity: .4; cursor: not-allowed; }
  .btn-ghost { background: transparent; color: var(--muted); border: 1px solid var(--border); }
  .btn-ghost:hover { border-color: var(--dim); color: var(--text); }
  .btn-green { background: #4ade8018; color: var(--green); border: 1px solid #4ade8040; }
  .btn-green:hover { background: #4ade8028; }
  .btn-sm { padding: 7px 14px; font-size: 12px; }

  /* Log terminal */
  .terminal { background: #090a0d; border: 1px solid var(--border); border-radius: 10px; padding: 16px 20px; font-family: var(--mono); font-size: 12px; line-height: 1.8; max-height: 280px; overflow-y: auto; }
  .terminal::-webkit-scrollbar { width: 4px; }
  .terminal::-webkit-scrollbar-thumb { background: var(--dim); border-radius: 2px; }
  .log-line { display: flex; gap: 12px; }
  .log-time { color: var(--muted); flex-shrink: 0; }
  .log-info { color: var(--text); }
  .log-success { color: var(--green); }
  .log-warn { color: var(--yellow); }
  .log-error { color: var(--accent2); }
  .log-accent { color: var(--accent); }
  .cursor { display: inline-block; width: 8px; height: 14px; background: var(--accent); animation: blink 1s step-end infinite; vertical-align: middle; margin-left: 2px; }
  @keyframes blink { 50% { opacity: 0; } }

  /* Stage result cards */
  .result-card { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; padding: 20px; margin-bottom: 14px; transition: border-color .2s; }
  .result-card:hover { border-color: var(--dim); }
  .result-card-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 14px; flex-wrap: wrap; gap: 8px; }
  .result-card-title { font-weight: 700; font-size: 15px; color: var(--bright); }
  .result-card-meta { font-family: var(--mono); font-size: 11px; color: var(--muted); }
  .result-card-body { font-size: 14px; color: var(--text); line-height: 1.7; white-space: pre-wrap; }
  .badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 9px; border-radius: 12px; font-family: var(--mono); font-size: 10px; font-weight: 500; letter-spacing: .06em; text-transform: uppercase; }
  .badge-ok { background: #4ade8015; color: var(--green); border: 1px solid #4ade8030; }
  .badge-warn { background: #fbbf2415; color: var(--yellow); border: 1px solid #fbbf2430; }
  .badge-err { background: #ff6b6b15; color: var(--accent2); border: 1px solid #ff6b6b30; }

  /* MD Preview */
  .md-preview { background: #090a0d; border: 1px solid var(--border); border-radius: 10px; padding: 24px 28px; font-family: var(--mono); font-size: 13px; line-height: 1.8; color: var(--text); white-space: pre-wrap; word-break: break-word; max-height: 480px; overflow-y: auto; }
  .md-preview::-webkit-scrollbar { width: 4px; }
  .md-preview::-webkit-scrollbar-thumb { background: var(--dim); border-radius: 2px; }

  /* Progress shimmer */
  .shimmer { height: 3px; background: linear-gradient(90deg, var(--accent) 0%, #ff6b6b 50%, var(--accent) 100%); background-size: 200%; animation: shimmer 1.4s linear infinite; border-radius: 2px; margin-bottom: 20px; }
  @keyframes shimmer { 0% { background-position: 0% } 100% { background-position: 200% } }

  /* Grid */
  .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
  @media (max-width: 580px) { .grid-2 { grid-template-columns: 1fr; } }

  .actions-row { display: flex; gap: 10px; flex-wrap: wrap; }
  .section-gap { margin-top: 28px; }
  .text-muted { color: var(--muted); font-family: var(--mono); font-size: 12px; }
  .divider { border: none; border-top: 1px solid var(--border); margin: 24px 0; }
  .empty-state { text-align: center; padding: 40px; color: var(--muted); font-family: var(--mono); font-size: 13px; }
`;

// ── Constants ────────────────────────────────────────────────────────────────
const STAGES = ["Config", "Research", "Summarize", "Validate", "Edit", "Publish"];

const STAGE_PROMPTS = {
  research: (cats, focus) =>
    `You are a research agent. Search the web for recent, high-quality information about the following categories: ${cats.join(", ")}.
Focus area: ${focus || "general"}.
For each category, find 3-5 key findings or developments. Be specific and cite sources when possible.
Return a JSON object: { "results": [ { "category": "...", "findings": ["finding1", "finding2", ...], "sources": ["url or title", ...] } ] }
Return ONLY valid JSON, no markdown fences.`,

  summarize: (researchData) =>
    `You are a summarization agent. Given these research findings, write a concise but rich summary for each category.
Research data: ${JSON.stringify(researchData)}
Return a JSON object: { "summaries": [ { "category": "...", "summary": "3-5 sentence summary...", "keyPoints": ["point1", "point2", "point3"] } ] }
Return ONLY valid JSON, no markdown fences.`,

  validate: (summaries) =>
    `You are a validation agent. Review these summaries for factual consistency, completeness, and quality.
Summaries: ${JSON.stringify(summaries)}
For each summary, assess: Does it accurately reflect research? Are claims grounded? Is it comprehensive?
Return a JSON object: { "validations": [ { "category": "...", "status": "ok"|"warning"|"error", "notes": "brief validation note", "confidence": 0-100 } ] }
Return ONLY valid JSON, no markdown fences.`,

  edit: (summaries, validations, repoName) =>
    `You are an editorial agent. Polish and finalize these summaries into publication-ready Markdown.
Summaries: ${JSON.stringify(summaries)}
Validation notes: ${JSON.stringify(validations)}
Repository: ${repoName || "research-output"}

Write a complete Markdown document with (keep it concise to fit within the token limit):
- A top-level title and date
- Table of contents with at most 3 entries
- One section per category with heading, 1 polished paragraph summary, and at most 3 bullet key points
- A brief concluding remarks section (2-4 sentences)
- Footer with generation timestamp

Output must be valid JSON only: exactly one object with a single top-level key "markdown".
Do not include any extra commentary. Do not include code fences.

Return a JSON object: { "markdown": "...full markdown string..." }
Return ONLY valid JSON, no markdown fences.`,
};

// ── API helper ───────────────────────────────────────────────────────────────
// All requests are routed through the Cloudflare Worker proxy.
// The Anthropic API key lives there as an encrypted secret — it never
// reaches the browser bundle or the network tab.
//
// VITE_PROXY_URL is set at build time (see .env.example / deploy.yml):
//   Local dev  → http://localhost:8787   (wrangler dev)
//   Production → https://research-agent-proxy.<subdomain>.workers.dev
const PROXY_URL = import.meta.env.VITE_PROXY_URL || "http://localhost:8787";

async function callClaude(prompt, useWebSearch = false) {
  const tools = useWebSearch
    ? [{ type: "web_search_20250305", name: "web_search" }]
    : undefined;

  const res = await fetch(`${PROXY_URL}/v1/messages`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      model: "claude-sonnet-4-20250514",
      max_tokens: 4000,
      ...(tools ? { tools } : {}),
      messages: [{ role: "user", content: prompt }],
    }),
  });

  if (!res.ok) {
    const msg = await res.text().catch(() => res.statusText);
    throw new Error(`Proxy error ${res.status}: ${msg}`);
  }
  const data = await res.json();

  // Collect all text blocks
  const text = data.content
    .filter((b) => b.type === "text")
    .map((b) => b.text)
    .join("");

  return text;
}

function parseJSON(raw) {
  const clean = raw.replace(/```json\n?/g, "").replace(/```\n?/g, "").trim();
  const start = clean.indexOf("{");
  const end = clean.lastIndexOf("}");
  const candidate = start !== -1 && end !== -1 && end > start ? clean.slice(start, end + 1) : clean;
  try {
    return JSON.parse(candidate);
  } catch (e) {
    const snippet = candidate.slice(0, 420) + (candidate.length > 420 ? "..." : "");
    throw new Error(`Model returned invalid JSON (${e.message}). Snippet: ${snippet}`);
  }
}

// ── Sub-components ───────────────────────────────────────────────────────────
function PipelineBar({ stage }) {
  const current = STAGES.indexOf(stage);
  return (
    <div className="pipeline">
      {STAGES.map((s, i) => {
        const isDone = i < current;
        const isActive = i === current;
        return (
          <div key={s} className="pipe-step">
            {i > 0 && <div className={`pipe-connector ${isDone ? "done" : ""}`} />}
            <div className={`pipe-dot ${isActive ? "active" : isDone ? "done" : ""}`}>
              {isDone ? "✓" : i + 1}
            </div>
            <span className={`pipe-label ${isActive ? "active" : isDone ? "done" : ""}`}>{s}</span>
          </div>
        );
      })}
    </div>
  );
}

function Terminal({ logs, running }) {
  const ref = useRef(null);
  // Auto-scroll
  useState(() => {
    if (ref.current) ref.current.scrollTop = ref.current.scrollHeight;
  });
  return (
    <div className="terminal" ref={ref}>
      {logs.map((l, i) => (
        <div key={i} className="log-line">
          <span className="log-time">{l.time}</span>
          <span className={`log-${l.type}`}>{l.msg}</span>
        </div>
      ))}
      {running && (
        <div className="log-line">
          <span className="log-time">{ts()}</span>
          <span className="log-accent">processing<span className="cursor" /></span>
        </div>
      )}
    </div>
  );
}

function ts() {
  return new Date().toLocaleTimeString("en-US", { hour12: false });
}

// ── Main App ─────────────────────────────────────────────────────────────────
export default function ResearchAgent() {
  // Config state
  const [categories, setCategories] = useState(["AI Research", "Climate Tech", "Developer Tools"]);
  const [catInput, setCatInput] = useState("");
  const [focusArea, setFocusArea] = useState("latest developments and breakthroughs in 2025");
  const [repoName, setRepoName] = useState("my-research-digest");

  // WordPress publish config
  const [wpEnabled, setWpEnabled] = useState(false);
  const [wpUrl, setWpUrl] = useState("");
  const [wpToken, setWpToken] = useState("");
  const [wpResult, setWpResult] = useState(null);

  // Pipeline state
  const [stage, setStage] = useState("Config");
  const [running, setRunning] = useState(false);
  const [logs, setLogs] = useState([]);

  // Data state
  const [researchData, setResearchData] = useState(null);
  const [summaries, setSummaries] = useState(null);
  const [validations, setValidations] = useState(null);
  const [markdown, setMarkdown] = useState(null);

  const log = useCallback((msg, type = "info") => {
    setLogs((prev) => [...prev, { time: ts(), msg, type }]);
  }, []);

  function addCategory() {
    const v = catInput.trim();
    if (v && !categories.includes(v)) {
      setCategories((p) => [...p, v]);
    }
    setCatInput("");
  }

  function removeCategory(c) {
    setCategories((p) => p.filter((x) => x !== c));
  }

  // ── Run pipeline ────────────────────────────────────────────────────────────
  async function runPipeline() {
    if (categories.length === 0) return;
    setRunning(true);
    setLogs([]);
    setResearchData(null);
    setSummaries(null);
    setValidations(null);
    setMarkdown(null);

    try {
      // ── Stage 1: Research ──
      setStage("Research");
      log("Starting research phase with web search enabled…", "accent");
      log(`Categories: ${categories.join(", ")}`, "info");
      log(`Focus: ${focusArea}`, "info");

      const rawResearch = await callClaude(STAGE_PROMPTS.research(categories, focusArea), true);
      const research = parseJSON(rawResearch);
      setResearchData(research.results);
      log(`Research complete — ${research.results.length} categories processed`, "success");

      // ── Stage 2: Summarize ──
      setStage("Summarize");
      log("Running summarization agent…", "accent");

      const rawSummaries = await callClaude(STAGE_PROMPTS.summarize(research.results));
      const summaryData = parseJSON(rawSummaries);
      setSummaries(summaryData.summaries);
      log(`Summaries generated for ${summaryData.summaries.length} categories`, "success");

      // ── Stage 3: Validate ──
      setStage("Validate");
      log("Validation agent reviewing summaries…", "accent");

      const rawValidations = await callClaude(STAGE_PROMPTS.validate(summaryData.summaries));
      const validationData = parseJSON(rawValidations);
      setValidations(validationData.validations);
      const warns = validationData.validations.filter((v) => v.status !== "ok").length;
      if (warns > 0) {
        log(`Validation complete — ${warns} warning(s) flagged`, "warn");
      } else {
        log("All summaries passed validation ✓", "success");
      }

      // ── Stage 4: Edit ──
      setStage("Edit");
      log("Editorial agent polishing content…", "accent");

      const rawEdit = await callClaude(
        STAGE_PROMPTS.edit(summaryData.summaries, validationData.validations, repoName)
      );
      const editData = parseJSON(rawEdit);
      setMarkdown(editData.markdown);
      log("Editorial pass complete", "success");

      // ── Stage 5: Publish ──
      setStage("Publish");
      log(`Document ready → ${repoName}/digest.md`, "success");

      if (wpEnabled && wpUrl && wpToken) {
        const wpData = await publishToWordPress(editData.markdown);
        if (wpData?.data) {
          setWpResult(wpData.data);
          log(`WordPress: post ${wpData.data.action} — ${wpData.data.url}`, "success");
        }
      }

      log("Pipeline complete ✓", "success");
    } catch (err) {
      log(`Error: ${err.message}`, "error");
      console.error(err);
    }

    setRunning(false);
  }

  function downloadMd() {
    if (!markdown) return;
    const blob = new Blob([markdown], { type: "text/markdown" });
    const a = document.createElement("a");
    a.href = URL.createObjectURL(blob);
    a.download = `${repoName || "digest"}.md`;
    a.click();
  }

  async function publishToWordPress(md) {
    if (!wpUrl || !wpToken) return null;
    log("Publishing to WordPress…", "accent");
    const endpoint = wpUrl.replace(/\/$/, "") + "/wp-json/research-agent/v1/import";
    const res = await fetch(endpoint, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "Authorization": `Bearer ${wpToken}`,
      },
      body: JSON.stringify({
        markdown: md,
        categories: categories,
        status: "publish",
        update_if_exists: true,
      }),
    });
    if (!res.ok) throw new Error(`WordPress API error ${res.status}: ${await res.text()}`);
    const data = await res.json();
    return data;
  }

  function reset() {
    setStage("Config");
    setLogs([]);
    setResearchData(null);
    setSummaries(null);
    setValidations(null);
    setMarkdown(null);
    setWpResult(null);
  }

  const isRunning = running;
  const showResults = researchData || summaries || validations || markdown;

  return (
    <>
      <style>{css}</style>
      <div className="app">
        {/* Header */}
        <div className="header">
          <div className="header-eyebrow">⬡ autonomous pipeline</div>
          <h1 className="header-title">Research <span>Agent</span></h1>
          <p className="header-sub">search → summarize → validate → edit → publish</p>
        </div>

        {/* Pipeline indicator */}
        <PipelineBar stage={stage} />
        {isRunning && <div className="shimmer" />}

        {/* Config panel */}
        <div className="panel">
          <div className="panel-title">Configuration</div>

          <div className="field">
            <label>Research Categories</label>
            <div className="tags-row">
              {categories.map((c) => (
                <div key={c} className="tag">
                  {c}
                  <button onClick={() => removeCategory(c)}>×</button>
                </div>
              ))}
            </div>
            <div className="tag-input-row">
              <input
                className="input"
                placeholder="Add category…"
                value={catInput}
                onChange={(e) => setCatInput(e.target.value)}
                onKeyDown={(e) => e.key === "Enter" && addCategory()}
                disabled={isRunning}
              />
              <button className="btn btn-ghost btn-sm" onClick={addCategory} disabled={isRunning}>
                + Add
              </button>
            </div>
          </div>

          <div className="grid-2">
            <div className="field">
              <label>Focus Area / Scope</label>
              <input
                className="input"
                placeholder="e.g. latest 2025 breakthroughs"
                value={focusArea}
                onChange={(e) => setFocusArea(e.target.value)}
                disabled={isRunning}
              />
            </div>
            <div className="field">
              <label>Repository / Output Name</label>
              <input
                className="input"
                placeholder="e.g. my-research-digest"
                value={repoName}
                onChange={(e) => setRepoName(e.target.value)}
                disabled={isRunning}
              />
            </div>
          </div>

          {/* WordPress publish config */}
          <div style={{ marginTop: 16, borderTop: "1px solid var(--border)", paddingTop: 16 }}>
            <div style={{ display: "flex", alignItems: "center", gap: 10, marginBottom: 12 }}>
              <label style={{ display: "flex", alignItems: "center", gap: 8, cursor: "pointer", fontFamily: "var(--mono)", fontSize: 12, color: "var(--muted)", letterSpacing: ".1em", textTransform: "uppercase" }}>
                <input
                  type="checkbox"
                  checked={wpEnabled}
                  onChange={(e) => setWpEnabled(e.target.checked)}
                  disabled={isRunning}
                  style={{ accentColor: "var(--accent)", width: 14, height: 14 }}
                />
                Publish to WordPress
              </label>
            </div>
            {wpEnabled && (
              <div className="grid-2">
                <div className="field" style={{ marginBottom: 0 }}>
                  <label>WordPress Site URL</label>
                  <input
                    className="input"
                    placeholder="https://yoursite.com"
                    value={wpUrl}
                    onChange={(e) => setWpUrl(e.target.value)}
                    disabled={isRunning}
                  />
                </div>
                <div className="field" style={{ marginBottom: 0 }}>
                  <label>Bearer Token</label>
                  <input
                    className="input"
                    type="password"
                    placeholder="From WP plugin settings"
                    value={wpToken}
                    onChange={(e) => setWpToken(e.target.value)}
                    disabled={isRunning}
                  />
                </div>
              </div>
            )}
          </div>

          <div className="actions-row" style={{ marginTop: 16 }}>
            <button
              className="btn btn-primary"
              onClick={runPipeline}
              disabled={isRunning || categories.length === 0}
            >
              {isRunning ? "⟳ Running pipeline…" : "▶ Run Agent Pipeline"}
            </button>
            {showResults && !isRunning && (
              <button className="btn btn-ghost" onClick={reset}>
                ↺ Reset
              </button>
            )}
          </div>
        </div>

        {/* Terminal log */}
        {(logs.length > 0 || isRunning) && (
          <div className="panel section-gap">
            <div className="panel-title">Agent Log</div>
            <Terminal logs={logs} running={isRunning} />
          </div>
        )}

        {/* Research results */}
        {researchData && (
          <div className="panel section-gap">
            <div className="panel-title">Research Findings</div>
            {researchData.map((r) => (
              <div key={r.category} className="result-card">
                <div className="result-card-header">
                  <div className="result-card-title">📂 {r.category}</div>
                  <div className="result-card-meta">{r.findings?.length || 0} findings</div>
                </div>
                <ul style={{ paddingLeft: 18, fontSize: 13, lineHeight: 1.8, color: "var(--text)" }}>
                  {(r.findings || []).map((f, i) => <li key={i}>{f}</li>)}
                </ul>
                {r.sources?.length > 0 && (
                  <div style={{ marginTop: 10, fontFamily: "var(--mono)", fontSize: 11, color: "var(--muted)" }}>
                    Sources: {r.sources.join(" · ")}
                  </div>
                )}
              </div>
            ))}
          </div>
        )}

        {/* Summaries */}
        {summaries && (
          <div className="panel section-gap">
            <div className="panel-title">Summaries</div>
            {summaries.map((s) => {
              const v = validations?.find((x) => x.category === s.category);
              return (
                <div key={s.category} className="result-card">
                  <div className="result-card-header">
                    <div className="result-card-title">{s.category}</div>
                    {v && (
                      <span className={`badge badge-${v.status === "ok" ? "ok" : v.status === "warning" ? "warn" : "err"}`}>
                        {v.status === "ok" ? "✓ validated" : v.status === "warning" ? "⚠ warning" : "✗ error"} {v.confidence && `${v.confidence}%`}
                      </span>
                    )}
                  </div>
                  <div className="result-card-body">{s.summary}</div>
                  {s.keyPoints?.length > 0 && (
                    <ul style={{ marginTop: 12, paddingLeft: 18, fontSize: 12, lineHeight: 1.8, color: "var(--muted)", fontFamily: "var(--mono)" }}>
                      {s.keyPoints.map((k, i) => <li key={i}>{k}</li>)}
                    </ul>
                  )}
                  {v?.notes && (
                    <div style={{ marginTop: 10, fontFamily: "var(--mono)", fontSize: 11, color: v.status === "ok" ? "var(--green)" : "var(--yellow)", background: v.status === "ok" ? "#4ade8010" : "#fbbf2410", padding: "6px 10px", borderRadius: 6 }}>
                      ↳ {v.notes}
                    </div>
                  )}
                </div>
              );
            })}
          </div>
        )}

        {/* Markdown publish */}
        {markdown && (
          <div className="panel section-gap">
            <div className="panel-title">Published Document — {repoName}/digest.md</div>
            <div className="md-preview">{markdown}</div>
            <div className="actions-row" style={{ marginTop: 16 }}>
              <button className="btn btn-green" onClick={downloadMd}>
                ↓ Download .md
              </button>
              <button className="btn btn-ghost btn-sm" onClick={() => navigator.clipboard?.writeText(markdown)}>
                ⎘ Copy Markdown
              </button>
            </div>
          </div>
        )}

        {/* WordPress publish result */}
        {wpResult && (
          <div className="panel section-gap" style={{ borderColor: "var(--green)" }}>
            <div className="panel-title">WordPress Published</div>
            <div className="result-card" style={{ borderColor: "#4ade8030", background: "#4ade8008" }}>
              <div className="result-card-header">
                <div className="result-card-title">✓ {wpResult.title}</div>
                <span className="badge badge-ok">{wpResult.action}</span>
              </div>
              <div style={{ fontFamily: "var(--mono)", fontSize: 12, color: "var(--muted)", marginTop: 4 }}>
                slug: <span style={{ color: "var(--text)" }}>{wpResult.slug}</span>
              </div>
              <div className="actions-row" style={{ marginTop: 12 }}>
                <a
                  href={wpResult.url}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="btn btn-green btn-sm"
                >
                  ↗ View Post
                </a>
              </div>
            </div>
          </div>
        )}

        {!showResults && !isRunning && (
          <div className="empty-state">Configure your categories above and hit Run to start the pipeline.</div>
        )}
      </div>
    </>
  );
}
