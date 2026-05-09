# wp-x402-paywall

**WordPress plugin that charges bots and AI agents micropayments via HTTP 402 Payment Required.** Instead of blocking automated traffic with CAPTCHAs or robots.txt, request a tiny cryptocurrency payment. Or use it as an **anti-DDoS panic switch** that auto-activates under load.

Built on the [x402 protocol](https://x402.org) — an open standard for HTTP 402 micropayments with stablecoin settlement on Base, Solana, Polygon, and Avalanche.

---

## Table of Contents

- [Why x402?](#why-x402)
- [How It Works](#how-it-works)
- [Modes](#modes)
- [DDoS / Auto Panic Mode](#ddos--auto-panic-mode)
- [Installation](#installation)
- [Settings](#settings)
- [x402 Protocol Details](#x402-protocol-details)
- [Bot Detection](#bot-detection)
- [Admin Bar Panic Button](#admin-bar-panic-button)
- [Requirements](#requirements)
- [License](#license)

---

## Why x402?

Every day, AI agents, scrapers, and crawlers hit your site. You have two choices:

1. **Block them** → Lose reach, lose AI discoverability (Google, ChatGPT, Claude never see your content)
2. **Let them through** → Get nothing in return for the server load

x402 offers a **third way**: charge them. A tiny $0.001 micropayment per request, settled in stablecoin on-chain via the [x402 facilitator](https://x402.org). Bots that pay get through. Humans browse free. You keep AI discoverability for the bots that matter, monetize the ones that don't.

Production deployment: **[CryptoSlate](https://cryptoslate.com/updates/cryptoslate-integrates-the-x402-standard-with-proofivy/)** uses this exact protocol for AI pay-per-article.

---

## How It Works

```
Visitor → WordPress
  ↓
Paywall checks:
  ├─ Enabled? (manual ON / auto-activated / OFF)
  ├─ Bot whitelisted? (googlebot, bingbot → pass)
  ├─ Session cookie valid? (already paid → pass)
  ├─ Excluded path? (wp-admin, llms.txt → pass)
  └─ Is bot? (UA pattern match → 402 / pass)
       ↓
  402 Payment Required + PAYMENT-REQUIRED header
       ↓
  Agent pays → retries with PAYMENT-SIGNATURE
       ↓
  Validated via facilitator → session cookie → content served
```

The facilitator handles blockchain verification so your WordPress server never touches crypto. Default: [https://x402.org/facilitator](https://x402.org) — or run your own.

---

## Modes

| Mode | Behavior | Best For |
|------|----------|----------|
| **Bots only** *(default)* | Paywall applies to known crawlers, scrapers, and AI agents. Real browsers (Chrome, Safari, Firefox) pass free. | Most sites — monetize bots without hurting human traffic |
| **All traffic** | Every visitor gets a 402 until they pay (except excluded paths). | Private content, premium APIs |
| **Specific paths** | Paywall only URL prefixes (e.g., `/wp-json/`, `/api/`). | API monetization |
| **Auto / Panic** | Paywall stays OFF normally. Activates automatically when traffic spikes above a threshold. Deactivates when traffic normalizes. | **Anti-DDoS** — protection that doesn't sacrifice discoverability |

---

## DDoS / Auto Panic Mode

This is the plugin's most practical feature for most WordPress sites.

### The Problem
You run a site that needs AI discoverability (blog, author site, business). Normally you want Googlebot, ChatGPT, Claude, Perplexity indexing your content. But when you get slammed — whether by a DDoS, a scraper botnet, or a viral post — your server buckles.

### The Solution
Set mode to **Auto**. The plugin silently monitors request rate in a sliding window. When traffic exceeds your trigger threshold, the paywall **automatically activates** — returning a lightweight 402 response (no DB queries, no page rendering) instead of serving full pages to attackers.

### What Happens Under Attack

| Visitor Type | During Normal Traffic | During Spike |
|-------------|----------------------|--------------|
| **Googlebot / Bingbot** | Pass through free | **Pass through free** (whitelisted) |
| **Real browser users** | Pass through free | Get 402 — but can pay or wait for deactivation |
| **DDoS bot / scraper** | Pass through free | **402 — bounces** (no crypto wallet, no access) |
| **AI agent with wallet** | Pass through free | **Pays $0.001 → gets through** (rate limiting via pricing) |

### Configuration

- **Trigger Threshold** — Requests per window that activate the paywall (default: 1000)
- **Deactivation Threshold** — When rate drops below this, paywall turns back off (default: 500)
- **Rate Window** — Sliding window in seconds (default: 60, min: 10)
- **Bot Whitelist** — User-Agent patterns always exempted (default: `googlebot`, `bingbot`, `slurp`, `duckduckbot`, `baiduspider`, `yandexbot`, `applebot`)

> **Tip:** Set trigger threshold slightly above your peak legitimate traffic. Monitor your access logs for a week to find your normal peak.

---

## Installation

1. Upload the `wp-x402-paywall` folder to `/wp-content/plugins/`
2. Activate through WordPress **Plugins** screen
3. Go to **Settings → x402 Paywall**
4. Choose your mode and configure

### Quick Start (Bots Only)
1. Set **Mode** → `Bots only`
2. Set **Wallet Address** → your Base or Solana wallet (or leave empty to test — paywall runs but doesn't charge)
3. Set **Enable Paywall** → `Enabled`
4. Save

### Quick Start (Auto Panic)
1. Set **Mode** → `Auto`
2. Enable paywall (it stays off until triggered)
3. Set **Trigger Threshold** → `1000`
4. Set **Bot Whitelist** → defaults are fine
5. Save

---

## Settings

### General
| Setting | Default | Description |
|---------|---------|-------------|
| Enable Paywall | Disabled | Master switch |
| Paywall Mode | Bots only | `Bots` / `All` / `Paths` / `Auto` |
| Fail Open | Yes | If facilitator unreachable, let requests through (recommended) |
| Support Email | — | Contact shown on 402 page |

### Pricing & Blockchain
| Setting | Default | Description |
|---------|---------|-------------|
| Price | $0.001 | Per-request charge |
| Network | Base (eip155:8453) | Blockchain for settlement |
| Wallet Address | — | Where payments go |
| Facilitator URL | https://x402.org/facilitator | x402 verification endpoint |

### Session & Rules
| Setting | Default | Description |
|---------|---------|-------------|
| Session TTL | 24 hours | Paid visitors bypass paywall for this long |
| Bot Patterns | ~30 patterns | User-Agent keywords flagged as bots |
| Excluded Paths | llms.txt, wp-admin, sitemaps, feeds | Never paywalled |
| Paywalled Paths | /wp-json/ | For "paths" mode |
| IP Whitelist | — | IPs that bypass entirely |

### Auto Mode
| Setting | Default | Description |
|---------|---------|-------------|
| Trigger Threshold | 1000 | Requests per window to activate |
| Deactivation Threshold | 500 | Rate to deactivate |
| Rate Window | 60 seconds | Sliding window size |
| Bot Whitelist Patterns | googlebot, bingbot, etc. | Always pass through |

---

## x402 Protocol Details

x402 is an open protocol for HTTP 402 Payment Required. Three headers:

| Header | Direction | Purpose |
|--------|-----------|---------|
| `PAYMENT-REQUIRED` | Server → Client | Base64 JSON: price, network, wallet address |
| `PAYMENT-SIGNATURE` | Client → Server | Signed proof of payment |
| `PAYMENT-RESPONSE` | Server → Client | Settlement confirmation |

The **facilitator** handles blockchain interaction — your server POSTs payment payloads to `/verify` and `/settle`. No crypto node needed.

- **Spec:** [x402.org](https://x402.org)
- **Default facilitator:** `https://x402.org/facilitator`
- **Supported networks:** Base, Solana, Polygon, Avalanche (mainnet + testnets)
- **Settlement:** USDC stablecoin (no volatility risk)

---

## Bot Detection

The plugin uses User-Agent pattern matching:

1. **Browser check** — `Mozilla/5.0` + `Chrome|Safari|Firefox|Edge|Opera` → human, unless AI-specific pattern detected
2. **AI overrides** — `Anthropic|Claude|GPT|ChatGPT|OpenAI|Perplexity|Cohere` → flagged even with browser-like UA
3. **Pattern file** — Configurable list of 30+ patterns including crawlers (`Googlebot`, `Bingbot`), tools (`curl`, `wget`, `python-requests`), and AI agents (`Claude`, `GPT`, `Perplexity`)
4. **Empty UA** — No user-agent → treated as bot

The **bot whitelist** (separate from detection patterns) lets known good bots pass through even in auto panic mode. Googlebot and Bingbot are whitelisted by default so your SEO is never affected.

---

## Admin Bar Panic Button

When logged in as admin, the WordPress admin bar shows your paywall status on every page:

- 🟢 **Paywall OFF** — click to enable instantly
- 🔴 **Paywall ON** — click to disable instantly
- ⚠️ **Paywall (AUTO)** — auto-activated under load, click to override

Clicking toggles the paywall immediately — no page load delay, no navigating to settings.

---

## Requirements

- WordPress 5.0+
- PHP 7.4+
- An x402-compatible facilitator (default: `https://x402.org/facilitator`)
- A wallet address on Base, Solana, Polygon, or Avalanche *(optional — paywall works without for testing)*

---

## License

GPL v2 or later. Free as in freedom.
