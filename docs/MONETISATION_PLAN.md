# Monetisation Plan

**Created**: 2026-02-05  
**Context**: Flood Watch is a promotional tool for [automica.io](https://automica.io). The goal is to get to market quickly, keep the service free for users, and optionally generate supporting revenue.

---

## User Model (Current)

| Tier | Access | Limits |
|------|--------|--------|
| **Guest** | Search-first dashboard, map after search | 1 search per 15 min |
| **Registered** | Unlimited searches, auto-refresh (future), Situational Awareness dashboard (future) | None |

---

## Revenue Options

### 1. Donation Link (One-off)

**Mechanism**: Link to Ko-fi, Buy Me a Coffee, or PayPal donation page.

| Pros | Cons |
|------|------|
| Zero friction; no account needed | Low conversion; most users never donate |
| No platform fees (PayPal) or low (Ko-fi ~5%) | No recurring revenue |
| Simple to add (footer link) | Feels optional; easy to ignore |
| Fits "keep it free" ethos | |

**Best for**: Soft ask; users who value the tool and want to chip in.

---

### 2. Patreon

**Mechanism**: Link to Patreon page. Optional: "Supporters" tier with early access or thank-you on site.

| Pros | Cons |
|------|------|
| Recurring revenue (monthly) | Patreon takes 5–12% |
| Community feel; patrons get updates | Requires ongoing content (posts, updates) |
| Familiar for open-source / indie projects | Extra platform to maintain |
| Can offer tiers (e.g. Supporter, Sponsor) | |

**Best for**: Users who want to support ongoing development; builds a small community.

---

### 3. Ko-fi (One-off + Optional Membership)

**Mechanism**: Ko-fi supports one-off "coffee" donations and optional monthly membership.

| Pros | Cons |
|------|------|
| One-off and recurring in one place | Less known than Patreon |
| Lower fees than Patreon for one-off | |
| Simple setup | |
| "Buy me a coffee" resonates with indie projects | |

**Best for**: Hybrid of donations + optional subscription without committing to Patreon.

---

### 4. Paid Tier (Future)

**Mechanism**: Stripe (or similar) for a "Pro" tier: e.g. Situational Awareness dashboard, higher AI limits, priority support.

| Pros | Cons |
|------|------|
| Predictable revenue | Conflicts with "keep it free" |
| Clear value exchange | Requires billing, subscriptions, support |
| Scales with usage | More product work |

**Best for**: Later, if the service grows and costs justify it. Not recommended initially given the promotional goal.

---

### 5. Sponsorship / B2B

**Mechanism**: Offer Flood Watch as a white-label or API for councils, insurers, or flood-response orgs. Charge per seat or per API call.

| Pros | Cons |
|------|------|
| Higher revenue per customer | Different product; sales cycle |
| Aligns with automica.io B2B positioning | Not "free for users" |
| Demonstrates enterprise capability | |

**Best for**: automica.io business development; separate from the free public tool.

---

## Recommendation

Given the goals—**promotional tool**, **keep it free**, **invest in infra to get to market**—the best approach is:

### Phase 1: Soft Donation Ask (Now)

- Add a **single donation link** in the footer (e.g. Ko-fi or Buy Me a Coffee).
- Copy: "Flood Watch is free to use. If it helps you, consider [buying me a coffee](link) to support development."
- No paywall, no pressure. The primary return is **automica.io visibility** and **demonstration of capability**.

### Phase 2: Optional Patreon / Ko-fi (When Traffic Grows)

- If users ask how to support, add a Patreon or Ko-fi link.
- **Ko-fi** is a good fit: one-off donations + optional membership, lower friction than Patreon.
- Use the same footer placement; keep the ask light.

### Phase 3: B2B / Sponsorship (If Opportunity Arises)

- Treat Flood Watch as a **showcase** for automica.io.
- If a council, insurer, or NGO wants a custom deployment or API, that becomes a separate commercial engagement.
- The free public tool stays free.

---

## Implementation: Donation Link

**Minimal change**:

1. Add config: `DONATION_URL` (e.g. `https://ko-fi.com/automica` or `https://buymeacoffee.com/...`).
2. In the footer, add a conditional link when `config('app.donation_url')` is set.
3. Wording: "Flood Watch is free. [Support development](url)".

**Footer snippet** (conceptual):

```
Flood Watch is free to use. If it helps you, consider supporting development.
[Buy me a coffee] · An automica labs project
```

---

## What Not to Do (Initially)

- **Paywall the core experience**: Guests and registered users should get full value. The Situational Awareness dashboard can stay registered-only (cost control) without being paid.
- **Aggressive donation prompts**: No popups or modals. A single footer link is enough.
- **Paid tier for basic features**: Keep search, map, and AI summary free. Paid tiers only if you later add clearly premium features (e.g. API access, white-label).

---

## Summary

| Mechanism | When | Purpose |
|-----------|------|---------|
| Donation link (Ko-fi / Buy Me a Coffee) | Phase 1 | Soft ask; zero friction |
| Patreon / Ko-fi membership | Phase 2 (if interest) | Recurring support from engaged users |
| B2B / sponsorship | If opportunity arises | automica.io business development |
| Paid tier | Not initially | Conflicts with promotional, free-first goal |

**Primary ROI**: Flood Watch as a **promotional asset** for automica.io. Donations are secondary; infrastructure investment is justified by the marketing and capability demonstration value.
