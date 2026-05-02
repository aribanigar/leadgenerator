# Lead Research Assistant

Identifies high-quality leads for ViaKashmir by analyzing incoming leads, enriching contact and company data, searching for new prospects, and providing actionable outreach strategies tailored to Kashmir travel packages.

## When to Use This Skill

Invoke this skill when the user asks to:
- Research or enrich a lead from the CRM
- Find new potential leads / prospects for ViaKashmir
- Score or qualify a lead against the ideal customer profile
- Generate an outreach message or follow-up strategy for a lead
- Build a prospect list for a specific destination, season, or customer segment

## Ideal Customer Profile (ICP)

**B2C (primary)**
- High-net-worth individuals or families planning leisure travel to Kashmir / North India
- Age 28–60, disposable income, prior experience with packaged tours
- Pain points: itinerary complexity, safety concerns, finding authentic local experiences
- Signals: inquiring about budget > ₹50,000, groups of 3+, flexible dates, mention of honeymoon/anniversary/family reunion

**B2B (secondary)**
- Corporate travel managers booking team offsites, incentive trips, or MICE events in Kashmir
- Travel agencies / OTAs looking for DMC (Destination Management Company) partnerships
- Signals: company email domain, mention of "group", "corporate", "partnership", "B2B rates"

## Lead Research Workflow

### Step 1 — Understand the Lead
Extract from the CRM record or user input:
- Name, email, phone
- Destination interest, travel dates, group size, budget
- Source platform (Meta / Google) and campaign/form name
- Any notes or prior contact

### Step 2 — Enrich the Lead
Use available tools in this order:

1. **Apollo people match** (`apollo_people_match`) — match by name + email to get LinkedIn, job title, company, location, seniority
2. **Apollo organization enrich** (`apollo_organizations_enrich`) — if a company domain is known, enrich company size, industry, tech stack, revenue range
3. **Web search** — search `"<name>" "<city>" Kashmir travel` or `"<company>" travel budget` for public signals

### Step 3 — Score the Lead (1–10)

| Factor | Weight | Signal |
|---|---|---|
| Budget fit | 30% | Budget ≥ ₹60k = high; ₹30–60k = medium; <₹30k = low |
| Group size | 20% | 4+ pax = high; 2–3 = medium; 1 = low |
| Timeline | 20% | Travel within 90 days = high; 90–180 = medium; >180 = low |
| Engagement quality | 15% | Filled multiple form fields, specific destination = high |
| Contact completeness | 15% | Has email + phone + name = high; partial = lower |

Calculate a weighted score and round to nearest integer. Label it:
- **8–10**: Hot — contact within 24 hours
- **5–7**: Warm — contact within 48–72 hours
- **1–4**: Cold — add to nurture sequence

### Step 4 — Identify Decision-Maker / Influencer
For B2C: the lead themselves. Note if they mentioned a spouse or family — address both.
For B2B: use Apollo to find Travel Manager, Office Manager, HR Head, or EA at the company.

### Step 5 — Craft Outreach Strategy

Provide:
1. **Opening line** — personalised to their destination/dates/campaign source
2. **Value proposition** — 1–2 sentences matching their stated need
3. **Call to action** — specific (e.g. "Can I send you a 7-day Gulmarg itinerary by tomorrow?")
4. **WhatsApp message draft** (≤160 chars) — since most ViaKashmir leads prefer WhatsApp
5. **Email subject line** — if email is available

## Output Format

For each lead researched, output a structured card:

```
## Lead: <Full Name>
**Score**: <N>/10 — <Hot/Warm/Cold>
**Source**: <platform> › <campaign> › <form>
**Contact**: <phone> | <email>
**Travel**: <destination> | <dates or season> | <adults> adults, <children> children
**Budget**: <budget>

### Enriched Profile
- **Title / Company**: <from Apollo or "Unknown">
- **Location**: <city, state>
- **LinkedIn**: <url or "Not found">

### Fit Rationale
<2–3 sentences explaining why this lead scores as it does>

### Outreach Strategy
**Recommended channel**: <WhatsApp / Email / Phone>
**Best time to contact**: <e.g. weekday evening 6–8pm IST>

**WhatsApp draft**:
> <message ≤160 chars>

**Email subject**: <subject line>

**Opening script**:
> <2–4 sentence call/email opener>

### Next Steps
1. <specific action>
2. <specific action>
```

## Prospect Search (Finding New Leads)

When asked to find new prospects rather than enrich existing ones:

1. Use `apollo_mixed_people_api_search` or `apollo_mixed_companies_search` with filters:
   - Location: India (tier-1 + tier-2 cities)
   - Seniority: Director, VP, C-Suite, Owner (for B2B)
   - Industry: Travel, Hospitality, Events, IT Services (corporate travel buyers)
   - Keywords: "travel", "Kashmir", "leisure", "incentive travel"

2. For each prospect returned, apply the ICP scoring above.

3. Output a ranked table:

```
| # | Name | Company | Title | Location | Score | Reason |
|---|------|---------|-------|----------|-------|--------|
| 1 | ...  | ...     | ...   | ...      | 8/10  | ...    |
```

4. Ask the user: "Would you like me to add the top prospects to Apollo as contacts and enroll them in a sequence?"

## Apollo Sequence Integration

When the user approves adding leads to Apollo:
1. Create or update the contact via `apollo_contacts_create` / `apollo_contacts_update`
2. Search for the right sequence via `apollo_emailer_campaigns_search` (look for "Kashmir" or "ViaKashmir" campaigns)
3. Enroll via `apollo_emailer_campaigns_add_contact_ids`
4. Confirm enrollment and share the sequence name

## Follow-up Actions Available

After the initial research, offer:
- **Draft follow-up email** — personalised multi-paragraph email for warm leads
- **Export to CSV** — format: Name, Email, Phone, Destination, Budget, Score, Status
- **Add to CRM note** — paste the lead card as a note in the ViaKashmir dashboard
- **Competitive analysis** — compare ViaKashmir packages against competitors for this lead's destination
- **Batch research** — process multiple leads from a CSV or table at once

## Example Invocations

```
# Enrich a single lead
Research lead ID 42 from the CRM and give me an outreach strategy

# Find new prospects
Find 10 B2B prospects in Delhi and Mumbai who might book corporate offsites in Kashmir

# Score and prioritise
Score all leads from last week's Meta campaign and rank by priority

# Build outreach
Draft WhatsApp messages for the top 5 hot leads this week
```
