# US Launch Readiness Checklist — geekygoodygoods.com

**Store:** geekygoodygoods.com (OpenCart 4.x)
**Location:** Houston, Texas
**Sells on:** eBay (marketplace) + OpenCart (direct)
**Payment:** Stripe
**Current state:** Demo/default config — no tax/shipping rules set for US

> **Audit snapshot (July 2026):** Country set to United Kingdom (should be US), zone empty, store name "Your Store", tax classes are UK VAT defaults, only Flat Rate shipping is installed, no Stripe payment extension installed. Everything below addresses this.

---

## 1. SALES TAX — Most Critical

### 1.1 Physical Nexus — Texas (Your Home State)

Because you are physically located in Houston, Texas, you have **physical nexus** in Texas. This means:

- **You MUST collect Texas sales tax on ALL sales shipped to Texas addresses** — no threshold applies.
- Register for a Texas Sales and Use Tax Permit with the Texas Comptroller if you haven't already: https://comptroller.texas.gov/taxes/sales/
- Texas state rate: **6.25%**. Houston (Harris County) local rate adds ~2%, for a **combined rate of ~8.25%**. Use the Texas Comptroller's rate locator to find exact rates by ZIP: https://mycpa.cpa.state.tx.us/staxrates/
- File returns: monthly, quarterly, or annually depending on volume (Comptroller assigns frequency).

### 1.2 Economic Nexus (Post-Wayfair) — The 200/$100k Rule

Under **South Dakota v. Wayfair (2018)**, states can require out-of-state sellers to collect tax once they cross certain thresholds. The **standard** thresholds are:

- **$100,000 in gross sales** into the state, OR
- **200 separate transactions** into the state

...in the current or previous calendar year.

**Important nuance:** The "200 transactions" rule is being phased out by many states. As of mid-2026, the trend is toward **dollar-only thresholds**. You should track where your sales go via eBay reports + OpenCart order data.

### 1.3 States With Dollar-Only Thresholds (No Transaction Count)

These states have eliminated or never had the 200-transaction test — only the $100k (or higher) dollar threshold applies:

| State | Threshold | Notes |
|-------|-----------|-------|
| **Texas** | $500,000 | Economic nexus threshold is $500k — but physical nexus (Houston) means you collect anyway |
| **California** | $500,000 | No transaction threshold |
| **New York** | $500,000 + 100 trans | Actually has both, but $500k is high |
| **Florida** | $100,000 | Dollar only |
| **Maine** | $100,000 | Dropped transaction threshold |
| **Illinois** | $100,000 | Removed 200-transaction threshold in 2025 |
| **Utah** | $100,000 | Removed 200-transaction threshold July 2025 |
| **Alaska** | $100,000 | No state sales tax but some local jurisdictions; removed transaction threshold 2025 |

### 1.4 States That Still Use the 200-Transaction Threshold

These states use **$100,000 OR 200 transactions** (both tests):

Alabama, Arizona, Arkansas, Colorado, Connecticut, Georgia, Hawaii, Idaho, Indiana, Iowa, Kansas, Kentucky, Louisiana, Maryland, Massachusetts, Michigan, Minnesota, Mississippi, Missouri, Nebraska, Nevada, New Jersey, New Mexico, North Carolina, North Dakota, Ohio, Oklahoma, Pennsylvania, Rhode Island, South Carolina, South Dakota, Tennessee, Vermont, Virginia, Washington, West Virginia, Wisconsin, Wyoming.

> **Note:** This list changes frequently. Always verify at https://www.salestaxinstitute.com/resources/economic-nexus-state-guide

### 1.5 Marketplace Facilitator Laws — eBay vs. OpenCart

**This is critical for your setup:**

- **eBay sales:** Under marketplace facilitator laws (adopted by all sales-tax states as of 2023), **eBay is required to collect and remit sales tax on your behalf** for sales made through their platform. You do NOT need to handle tax on eBay sales.
- **OpenCart direct sales:** These are YOUR responsibility. When a customer buys through geekygoodygoods.com, you must collect and remit the appropriate sales tax.

**Practical implication:** Your eBay sales count toward economic nexus thresholds in each state, but eBay handles the tax collection. Your OpenCart sales may push you over thresholds in states where you haven't yet registered.

### 1.6 What Counts Toward Thresholds

- **Gross sales** of taxable tangible personal property (cassette tapes, electronics = taxable goods in all states)
- Includes both eBay and OpenCart sales
- Sales tax itself is NOT included in the threshold calculation
- Some states include marketplace sales, some don't — check each state

### 1.7 Registration Process

Once you cross a threshold in any state, you must:

1. **Register** with that state's Department of Revenue (or equivalent)
2. Get a **sales tax permit / registration number**
3. Set up the tax rate in OpenCart (see section 5)
4. Begin **collecting tax on the next transaction**
5. File returns at the state's required frequency

Typical forms: Form ST-1 (varies by state), usually online.
Typical cost: $0-$50 per state.
Typical timeline: 2-6 weeks.

### 1.8 Penalties for Non-Compliance

- **Interest** on unpaid tax (typically 6-12% per year)
- **Late filing penalties** (5-25% of tax due)
- **Willful non-compliance** can result in:
  - Revocation of business licenses
  - Personal liability for business owners
  - Criminal charges in extreme cases (tax evasion)
- **Audit risk** increases with multi-state activity
- **Voluntary Disclosure Agreements (VDAs)** may reduce penalties if you proactively register after discovering you should have

### 1.9 Recommended Approach

**Phase 1 (Immediate):** Set up Texas tax in OpenCart. You must collect this from day one.

**Phase 2 (Monitor):** Track sales by state. Use OpenCart's built-in sales reports and export by shipping address.

**Phase 3 (At threshold):** Register in each state as you cross thresholds. Use a service like **TaxJar** ($19/mo), **Avalara AvaTax**, or **TaxCloud** (free for basic use) to automate calculation and filing.

**Phase 4 (Scale):** Consider a full tax automation plugin for OpenCart. Available options:
- TaxJar OpenCart plugin (third-party)
- Avalara AvaTax for OpenCart
- OpenCart marketplace has "Sales Tax" extensions

---

## 2. SHIPPING

### 2.1 Carrier Recommendations

For your product types (cassette tapes, small electronics, collectibles):

| Carrier | Best For | Notes |
|---------|----------|-------|
| **USPS Ground Advantage** | Small items under 1 lb | ~$5-8 for a cassette tape; cheapest option |
| **USPS Priority Mail Flat Rate** | Multiple tapes / small boxes | Small Flat Rate Box: ~$10.20 (up to 70 lbs) |
| **USPS Priority Mail** | Faster delivery, tracking included | ~$8-15 for typical orders |
| **UPS Ground** | Larger/heavier electronics | Better for items over 5 lbs |
| **FedEx Ground** | Similar to UPS | Competitive pricing for volume shippers |

**Recommendation:** Start with USPS for 90% of orders. Cassette tapes are small, light, and ideal for USPS Ground Advantage or Flat Rate envelopes.

### 2.2 Shipping Insurance for Collectibles

- **USPS** includes $50-100 insurance with Priority Mail
- **UPS** includes $100 insurance with Ground
- For valuable collectibles (rare tapes, vintage electronics), purchase additional insurance:
  - USPS: $1.25 per $100 of coverage beyond included amount
  - Third-party: Shipsurance, U-PIC (often cheaper than carrier insurance)
- **Recommendation:** Insure any item valued over $100

### 2.3 OpenCart Shipping Configuration

**Current state:** Only Flat Rate shipping installed. No real-time rates.

**Recommended setup:**

**Route:** Extensions > Extensions > Shipping

**Option A: Flat Rate (Start Here)**
- Install and enable `shipping/flat`
- Set a flat rate (e.g., $5.99 for Ground Advantage, $12.99 for Priority)
- Create Geo Zones for Continental US, Alaska/Hawaii, US Territories
- Set different rates per zone

**Option B: Real-Time Rates (Better, requires extension)**
OpenCart doesn't have built-in USPS/UPS/FedEx rate integration. You'll need a third-party extension:
- **USPS Shipping** extension from OpenCart marketplace (~$20-50 one-time)
- **UPS Shipping** extension
- **ShipStation** or **Shippo** integration (auto-pulls rates from all carriers)
- **Multi Flat Rate** extension (10 shipping options) — lets you set different flat rates by weight

**To install a shipping extension:**
1. Extensions > Installer — upload the .ocmod.zip file
2. Extensions > Extensions > Shipping — find it and click Install
3. Click Edit to configure API keys, rates, etc.

### 2.4 Free Shipping Threshold

Consider offering **Free Shipping on orders over $50**:
- Encourages larger cart sizes
- Average cassette tape order: likely 3-5 tapes = $30-60
- Absorb the $5-8 shipping cost on larger orders
- Set up via: Extensions > Extensions > Shipping > Free Shipping
- Configure minimum total and Geo Zone

### 2.5 Shipping Zones to Create

Go to: **System > Localization > Geo Zones**

Create these geo zones:

| Geo Zone Name | Included States |
|---------------|-----------------|
| Continental US | 48 contiguous states |
| AK/HI | Alaska, Hawaii |
| US Territories | PR, GU, VI, etc. |
| APO/FPO | Military addresses |

Then assign shipping methods to each zone with appropriate rates.

---

## 3. LEGAL / COMPLIANCE

### 3.1 Terms of Service & Privacy Policy

**Current state:** Information pages exist for Privacy Policy, Terms & Conditions, About Us, Delivery Information, Returns.

**Action items:**

1. **Update Privacy Policy** (Admin: Catalog > Information > Edit "Privacy Policy")
   - Must disclose what data you collect (name, address, email, payment info)
   - How data is used (order fulfillment, marketing if opted in)
   - Data sharing (shipping carriers, Stripe, eBay integration)
   - Cookie policy (GDPR-compliant — already linked in settings)
   - CCPA-specific disclosure for California customers
   - Data retention period
   - Contact for data requests

2. **Update Terms & Conditions** (Catalog > Information > Edit "Terms & Conditions")
   - Payment terms
   - Shipping policy
   - Return/refund policy
   - Limitation of liability
   - Governing law (Texas)
   - Dispute resolution

3. **Set Account Terms** (System > Settings > Edit > Option tab)
   - Already set to "Privacy Policy" — but you should also have "Terms & Conditions" as a requirement

### 3.2 Return Policy & RMA (OpenCart Built-in)

Route: **Sales > Returns**

OpenCart has a built-in RMA (Return Merchandise Authorization) system.

**Configure these first:**
- **Return Statuses:** Pending, Awaiting Inspection, Complete, Declined
- **Return Actions:** Refund, Store Credit, Replace
- **Return Reasons:** Wrong item, Damaged, Doesn't work, No longer wanted

**For used/collectible items, set policies:**
- Inspection period: Customer must report issues within 7 days of delivery
- Condition: Item must be returned in the same condition as shipped
- Refund timeline: Within 5 business days of receiving returned item
- Return shipping: Who pays? (Recommend: seller pays if item defective, buyer pays if buyer's remorse)
- Restocking fee: Optional for collectibles (10-20% is common)

**Display return policy clearly** on product pages and in checkout.

### 3.3 Refund Requirements for Used/Collectible Items

- **Used items:** Must match the described condition (cosmetic wear is expected but must be disclosed)
- **"As-is" sales:** Should be clearly marked; however, FTC rules still require items to be as described
- **Electronics:** Must function as advertised. Test items before listing.
- **FTC Cooling-Off Rule:** Does NOT generally apply to e-commerce (applies to in-home sales over $25)
- **Credit card chargebacks:** If customer disputes with Stripe, you lose unless you have clear return/refund documentation

### 3.4 CCPA Compliance (California Customers)

If you sell to California residents (likely), the **California Consumer Privacy Act** applies:

Requirements:
- Privacy Policy must disclose **categories** of personal information collected
- California residents have the **right to know** what data you've collected
- **Right to delete** personal data
- **Right to opt out** of sale of personal information (you don't sell data, but must state this)
- Must respond to requests within 45 days

OpenCart has a **GDPR** module (Admin: Customers > GDPR) that can handle some of this, but you also need CCPA-specific language in the Privacy Policy.

### 3.5 SSL / HTTPS

**Current state:** Already active — site runs on HTTPS via SSL certificate. Verified: HTTP/2 with `Secure` cookie flag.

No action needed here except to verify in OpenCart settings:
- System > Settings > Edit > Server tab
- **Use SSL:** Should be enabled (confirm)
- Both store URLs should use `https://`

### 3.6 eBay OpenCart Sync Considerations

Since you sell on both eBay and OpenCart:
- Terms/policies on both platforms should be **consistent**
- eBay's Money Back Guarantee requires sellers to accept returns within 30 days
- You may want the same 30-day return policy on OpenCart for consistency
- Product descriptions should match between platforms
- Inventory sync needed if selling same items on both (use an inventory management tool if scaling)

---

## 4. PAYMENT

### 4.1 Stripe Setup

**Current state:** No Stripe payment extension installed. Only COD (Cash on Delivery) and Free Checkout are available.

**To install Stripe:**

1. **Install the Stripe extension** from OpenCart Marketplace:
   - Extensions > Marketplace > Search "Stripe"
   - Download and install the official Stripe Payments extension

2. **Configure:**
   - Extensions > Extensions > Payments > Stripe
   - Enter your Stripe **Publishable Key** and **Secret Key** (from Stripe Dashboard)
   - Set **Transaction Mode:** Live (use Test mode first)
   - Set **Order Status:** Complete (or Processing)
   - Enable **3D Secure** for fraud protection
   - Set **Capture Type:** Authorize & Capture (immediate) or Authorize Only (manual capture)

3. **Stripe Account Setup:**
   - Create Stripe account at https://stripe.com (if not already)
   - Complete business verification
   - Set up bank account for payouts
   - Enable webhooks for real-time payment status updates

### 4.2 Fraud Detection

**Stripe Radar** (built into Stripe):
- Uses machine learning to detect fraudulent transactions
- Includes **Radar for Fraud Teams** (advanced, $0.02/transaction)
- Set rules for high-risk countries, unusual amounts, etc.

**OpenCart fraud prevention:**
- Enable **Max Login Attempts** (currently set to 5 — good)
- Consider **Address Verification Service (AVS)** through Stripe (enabled by default)
- Enable **CVV verification** in Stripe settings

### 4.3 Address Verification (AVS)

Stripe automatically runs AVS checks. You can:
- Set Stripe to **decline** transactions where AVS fails
- Or set to **review** and manually approve
- For collectibles, recommend declining AVS failures to reduce fraud risk

### 4.4 PCI Compliance

Since you're using Stripe (a PCI Level 1 provider), your PCI compliance burden is minimal:
- **Stripe handles** card data — you never see full card numbers
- You need **SAQ A** (Self-Assessment Questionnaire A) — simplest form
- Complete annually via https://www.pcisecuritystandards.org/
- Requirements: SSL (✓ done), secure admin password, no stored card data
- Stripe provides a PCI compliance guide in your dashboard

### 4.5 Additional Payment Methods

Consider adding **PayPal** alongside Stripe:
- Many eBay customers already have PayPal accounts
- Increases conversion rate
- Easy to install via OpenCart marketplace

---

## 5. OPENCART-SPECIFIC CONFIGURATION — Step by Step

### 5.1 Store Location Setup

**Route:** System > Settings > Edit > Store tab

Current: "Your Store", "Your Name", "Address 1"
**Change to:**
- Store Name: `Geeky Goody Goods`
- Store Owner: Your real name or business name
- Address: Your Houston, Texas physical address
- E-Mail: Your business email

**Route:** System > Settings > Edit > Local tab

Current: Country = United Kingdom (value 222)
**Change to:**
- Country: **United States** (value 223)
- Region/State: **Texas**
- Language: **English**
- Currency: **US Dollar** (already correct)
- Length Class: Inch or Centimeter
- Weight Class: **Pound** or Ounce
- Order Status: **Pending** (already correct for new orders)

### 5.2 Creating Geo Zones (For Tax & Shipping)

**Route:** System > Localization > Geo Zones > Add New

Create these geo zones:

1. **Texas** — Country: United States, Region: Texas
2. **US All States** — Country: United States, All regions
3. **Continental US** — Country: United States, All regions except AK, HI
4. **US + AK/HI** — Country: United States, All regions including AK, HI

> **Important:** Delete the existing "UK Shipping" and "UK VAT Zone" geo zones — they are for the UK, not US.

### 5.3 Creating Tax Classes & Rates

**Step 1: Create Tax Rates**
**Route:** System > Localization > Tax Rates > Add New

Create these tax rates:

| Rate Name | Rate | Type | Geo Zone |
|-----------|------|------|----------|
| TX State | 6.250 | Percentage | Texas |
| TX Local (Houston) | 2.000 | Percentage | Texas |
| [State] State | 4.000 | Percentage | Continental US |
| ... (add as needed per state) | | | |

**For Texas (immediate):**
- Create geo zone "Texas" with Country=US, Region=Texas
- Create tax rate "TX State": 6.250%, Percentage, Texas geo zone
- Create tax rate "TX Local": varies by local jurisdiction (check Harris County rate, typically 2.0%)

**For other states (as you register):**
- Create a new geo zone for that state
- Create a tax rate with the appropriate rate

**Step 2: Create Tax Classes**
**Route:** System > Localization > Tax Classes > Add New

| Tax Class | Tax Rates | Applies To |
|-----------|-----------|------------|
| Taxable Goods | TX State, TX Local | All physical products |
| Electronics | TX State, TX Local | Electronics (same but separate if different treatment) |
| Downloads | (none or 0%) | Digital downloads |

**Step 3: Assign Tax Class to Products**
**Route:** Catalog > Products > Edit > General tab
- Set **Tax Class** to "Taxable Goods" for all physical items

### 5.4 Configuring Tax Settings

**Route:** System > Settings > Edit > Option tab > Taxes accordion

Current settings (audited):
- Display Prices With Tax: **ON** (checked) ← Good for B2C
- Use Store Tax Address: **Shipping Address** ← Good
- Use Customer Tax Address: **Shipping Address** ← Good

**Recommendation:** Keep these settings. Tax is calculated based on the shipping address, which is correct for US sales tax (origin-based vs destination-based — most states are destination-based, meaning tax at the ship-to address).

### 5.5 Shipping Methods Setup

**Route:** Extensions > Extensions > Shipping

**Immediate setup:**
1. Click **Install** on "Flat Rate"
2. Click **Edit** to configure:
   - Cost: `5.99` (or whatever you decide)
   - Geo Zone: US All States
   - Status: Enabled
   - Sort Order: 1

3. Click **Install** on "Free Shipping"
4. Configure for orders over $50 (or your threshold)
5. Create additional flat rates if offering multiple shipping options

**For real-time rates (enhancement):**
- Install a USPS/UPS extension from marketplace
- Configure API keys from USPS Web Tools or UPS Developer Kit

### 5.6 Currency & Regional Settings

**Route:** System > Localization > Currencies

- USD should be the default (already set)
- Autoupdate currency rates is enabled (✓)
- Remove any unused currencies (GBP, EUR, etc.) if not needed

**Route:** System > Localization > Languages
- English (en-gb) is correct for US store
- Verify the locale/regional settings

### 5.7 SEO & URL Configuration

**Route:** System > Settings > Edit > Server tab

Current: SEO URLs are **OFF**
- **Enable SEO URLs** — turn this on for clean URLs
- This makes URLs readable: `/product/cassette-tape-name` instead of `?route=product/product&product_id=123`

### 5.8 Information Pages Checklist

**Route:** Catalog > Information

| Page | Status | Action |
|------|--------|--------|
| About Us | Exists | Review/update |
| Delivery Information | Exists | Update with US-specific shipping info |
| Privacy Policy | Exists | Update with CCPA language |
| Terms & Conditions | Exists | Update with US-specific terms |
| Returns | Exists | Configure RMA settings |

### 5.9 Abandoned Cart & Customer Communications

- **Route:** System > Settings > Edit > Mail tab
- Set up SMTP mail (use your hosting provider's mail settings, SendGrid, or Mailgun)
- **Route:** Marketing > Mail — configure order confirmations, shipping notifications
- Transactional emails are critical for customer trust

---

## 6. LAUNCH CHECKLIST — Quick Reference

### Pre-Launch (Must Do)

- [ ] Change store country from UK to **United States / Texas** (System > Settings > Edit > Local)
- [ ] Set store name, owner, address to real values
- [ ] Create **Texas geo zone** for tax
- [ ] Create **TX State + TX Local tax rates**
- [ ] Create **Taxable Goods tax class**, assign to all products
- [ ] Delete UK tax rates/classes/geo zones
- [ ] Install and configure **Flat Rate shipping** for US
- [ ] Install **Stripe payment extension** from marketplace
- [ ] Configure Stripe with live API keys
- [ ] Update **Privacy Policy** with CCPA language
- [ ] Update **Terms & Conditions** with US-specific terms
- [ ] Configure **Return RMA** settings (statuses, reasons, actions)
- [ ] Set up **return policy** information page
- [ ] Enable **SEO URLs**
- [ ] Verify **SSL/HTTPS** is working (it is)
- [ ] Test a full checkout flow with a test order

### Week 1

- [ ] Register with **Texas Comptroller** for sales tax permit
- [ ] File initial Texas sales tax return (even if $0)
- [ ] Set up **Stripe webhooks** for order status updates
- [ ] Review eBay and OpenCart product descriptions for consistency
- [ ] Set up **inventory tracking** for items sold on both channels
- [ ] Create USPS shipping labels system (Stamps.com, Pirate Ship, or ShipStation)

### Month 1

- [ ] Monitor sales by state using OpenCart reports
- [ ] Evaluate if any economic nexus thresholds have been crossed
- [ ] Register in any threshold-crossed states
- [ ] Consider **TaxJar** or **Avalara** if selling to 5+ states
- [ ] Set up free shipping threshold based on average order value

### Ongoing

- [ ] File sales tax returns per state schedule
- [ ] Review nexus thresholds quarterly
- [ ] Update tax rates when states change them
- [ ] Maintain PCI compliance SAQ A annually
- [ ] Monitor Stripe Radar for fraud patterns
- [ ] Keep Privacy Policy current with changing laws

---

## 7. FEDEX ECONOMY SHIPPING

### 7.1 Overview: FedEx Ground Economy (Formerly SmartPost)

FedEx Ground Economy (rebranded from FedEx SmartPost in 2022) is FedEx's **final-mile consolidation** service. FedEx handles the long-haul transportation, then hands off the package to **USPS for the final delivery** to the customer's mailbox. This is the cheapest FedEx option but the **slowest** (typically 5-10 business days).

**How it works:**
1. FedEx picks up from your Houston location
2. FedEx transports to the destination USPS hub
3. USPS delivers to the customer's mailbox (not doorstep)
4. Tracking is provided but has gaps during the USPS handoff

**Key characteristics:**
- **Package limit:** Up to 70 lbs or 130" combined length+girth
- **Size restrictions:** Priority for lightweight packages under 5 lbs
- **Delivery to mailbox** (not front door) — higher risk of theft for collectibles
- **No signature on delivery** (by default — can be added at extra cost)
- **Tracking:** FedEx provides a tracking number that feeds into USPS tracking after handoff
- **Transit time:** 5-10 business days (vs 2-5 for USPS Ground Advantage)
- **Claim process:** FedEx handles claims; USPS may deny claims for last-mile damage

### 7.2 Obtaining FedEx Ground Economy Rates

FedEx does not publish public rate cards for Ground Economy. Rates are **negotiated** and heavily discounted for volume shippers. Typical approaches:

**Option A: FedEx Account (Direct, No Discount)**
- Open a FedEx account at https://www.fedex.com/en-us/start-shipping.html
- You'll get **list rates**, which for Ground Economy are typically:
  - Under 1 lb: ~$8.00-$12.00 (expensive vs USPS at this volume level)
- **Problem:** Without a negotiated contract, Ground Economy rates are not competitive for small shippers

**Option B: Third-Party Shipping Platforms (Recommended)**

These platforms negotiate FedEx volume discounts and pass them to you. They also handle the API integration:

| Platform | FedEx Ground Economy Rate (<1lb) | Monthly Fee | Notes |
|----------|----------------------------------|-------------|-------|
| **Pirate Ship** | ~$4.50-$6.50 | Free | Best for small shippers; also has USPS Cubic rates |
| **ShipStation** | ~$4.00-$6.00 | $9-$29/mo | More features, integrates with OpenCart |
| **Shippo** | ~$4.50-$6.50 | Free tier | Simple integration, OpenCart plugin available |
| **Stamps.com** | ~$5.00-$7.00 | $17.99/mo | Primarily USPS but FedEx available |
| **EZ Shipping (OpenCart plugin)** | ~$4.50-$6.50 | $30 one-time | Direct OpenCart integration with discount rates |

**Option C: FedEx Volume Discounts (Direct Negotiation)**
- FedEx offers automatic discounts starting at ~10% for new accounts
- Significant discounts (20-40%) require **shipping volume commitments** (typically 5,000+ packages/year)
- Small operations rarely qualify for meaningful direct FedEx discounts

### 7.3 OpenCart Integration for FedEx

**What you need for real-time FedEx rates in OpenCart:**

**Option 1: Pirate Ship + Manual Label Purchase (Simplest)**
- No OpenCart API integration needed for rates
- You purchase labels on Pirate Ship after each order
- Enter tracking number manually in OpenCart order
- Best for low volume (<20 orders/day)
- Pirate Ship does NOT have a direct OpenCart plugin

**Option 2: ShipStation ($9-$29/mo)**
- Integrates with OpenCart via ShipStation's OpenCart plugin (OC4 compatible)
- Pulls orders automatically from OpenCart
- Shows real-time FedEx, USPS, UPS rates
- Prints labels, auto-posts tracking back to OpenCart
- **Best option for growth** — used by many OpenCart merchants

**Option 3: FedEx API Direct via OpenCart Extension**
- Search OpenCart Marketplace for "FedEx Shipping" extension
- Requires a FedEx Web Services Developer Portal account (free)
- You need a FedEx account number and API credentials
- Extensions available for OC4:
  - **FedEx Shipping by Qphoria** (~$30)
  - **FedEx Shipping by unitedsoftwares** (~$20-40)
  - **FedEx Shipping by CartPerk** (~$25)

**Option 4: Shippo OpenCart Plugin**
- Free plugin available on OpenCart Marketplace
- Shows FedEx, USPS, UPS real-time rates at checkout
- Auto-purchases labels and posts tracking

### 7.4 Typical Rates: FedEx Ground Economy vs USPS for Cassette-Sized Items

For a **standard cassette tape** shipped in a poly mailer or small bubble mailer (~4oz, 7"x4"x1"):
| Carrier/Service | Typical Rate | Transit Time | Delivery Method |
|-----------------|-------------|-------------|-----------------|
| **FedEx Ground Economy** | $4.50-$6.50 | 5-10 business days | Mailbox (USPS final mile) |
| **USPS Ground Advantage** | $4.50-$6.50 | 2-5 business days | Mailbox/Front door |
| **USPS Priority Mail Cubic** | $3.80-$6.50 | 1-3 business days | Front door |
| **USPS Priority Mail (Flat Rate Envelope)** | $10.20 | 1-3 business days | Front door |
| **USPS Media Mail (if eligible)** | $3.80-$5.00 | 2-8 business days | Mailbox |

**Cassette tapes are NOT eligible for Media Mail** (that rate is for books, CDs, DVDs, and educational materials only).

### 7.5 FedEx Ground Economy vs USPS Comparison for Cassette Sales

| Factor | FedEx Ground Economy | USPS Ground Advantage | USPS Priority Mail Cubic |
|--------|---------------------|----------------------|--------------------------|
| **Cost (<1lb)** | $4.50-$6.50 | $4.50-$6.50 | $3.80-$6.50 (best value) |
| **Speed** | Slow (5-10 days) | Medium (2-5 days) | Fast (1-3 days) |
| **Tracking** | Partial (gaps at handoff) | Full end-to-end | Full end-to-end |
| **Delivery** | Mailbox only | Mailbox or doorstep | Doorstep |
| **Insurance included** | $100 | $100 | $50-$100 |
| **Collectible safe?** | No (mailbox, theft risk) | Yes | Yes |
| **Label purchase** | 3rd-party platforms | Pirate Ship, many options | Pirate Ship |
| **OpenCart integration** | Via ShipStation/Shippo | Via ShipStation/Shippo | Via ShipStation/Shippo |

**Verdict for cassette tapes:** FedEx Ground Economy offers **no advantage** over USPS Ground Advantage at similar pricing. USPS Ground Advantage is faster, delivers more securely, and has better tracking. **USPS Priority Mail Cubic is the best value** for cassette-sized items when using Pirate Ship (which gets you Cubic rates at ~$3.80-$5.50 for small boxes).

### 7.6 When FedEx Ground Economy Makes Sense

- **High-volume operations** where every penny counts and customers accept slow delivery
- **Items too large for Priority Mail Cubic** (>0.5 cubic foot) but still under 5 lbs
- **Non-collectible merchandise** where mailbox delivery and slower transit are acceptable
- **When USPS is unreliable** in certain regions (FedEx long-haul is more consistent than USPS in some areas)

### 7.7 Recommendations for Geeky Goody Goods

FedEx Ground Economy is **not recommended** for your initial launch. Instead:
1. **Start with USPS Ground Advantage** (~$5-8) for single cassette orders
2. **Use USPS Priority Mail Cubic** (via Pirate Ship, ~$3.80-$5.50) for small boxes of 1-3 tapes — this is actually your cheapest option for small boxes
3. **Use USPS Priority Mail Flat Rate Envelope** ($10.20) for larger orders of 4+ tapes
4. **Revisit FedEx Ground Economy** if you reach 20+ orders/day — at that volume, ShipStation provides enough discount to make FedEx competitive, and the slower delivery becomes tolerable

---

## 8. GOOGLE SHOPPING / MERCHANT CENTER

### 8.1 Overview

Google Shopping displays product listings directly in Google Search results (the "Shopping" tab and image-rich results in web search). There are two paths:

- **Free Listings:** Show your products in Google Shopping results at no cost
- **Paid Shopping Ads (formerly Google Shopping Ads):** Pay-per-click (PPC) listings that appear at the top of Shopping results

Both require a **Google Merchant Center** account and a product feed — the only difference is whether you opt into paid campaigns.

### 8.2 Google Merchant Center Account Setup

**Step 1: Create a Merchant Center Account**
- Go to https://merchants.google.com
- Sign in with your Google account (create a dedicated business Gmail, not your personal one)
- Choose account type:
  - **Individual/sole proprietor** (for your business structure)
  - **Standard account** (not a multi-client account — those are for agencies)

**Step 2: Provide Business Information**
- Business name: Geeky Goody Goods
- Business website: https://geekygoodygoods.com
- Country: United States
- Time zone: America/Chicago (Houston is Central Time)
- Business contact info (email, phone)

**Step 3: Verify & Claim Your Website**
- Google will ask you to verify you own geekygoodygoods.com
- Methods available:
  - **Meta tag verification** (add a `<meta>` tag to your site header) — easy with OpenCart
  - **Google Analytics** (if linked to same Google account)
  - **Google Tag Manager**
  - **DNS record** (TXT record in your domain's DNS settings)
- **Recommended for OpenCart:** Add the meta tag to the `<head>` section of your OpenCart theme. You can do this via:
  - Extensions > Themes > Edit your active theme > Add to `header.twig` before `</head>`
  - Or use the "Additional Scripts" section in System > Settings > Edit > Server tab

**Step 4: Configure Shipping Settings in Merchant Center**
- This is where you tell Google how you ship — Google uses this to show shipping costs in listings
- **Critical:** These must match what's actually configured in OpenCart, or your products may be disapproved
- Set up shipping rates for: Continental US, AK/HI, APO/FPO
- You can enter flat rates, rate tables by price/weight, or a carrier-calculated rate

**Step 5: Configure Tax Settings in Merchant Center**
- Tell Google whether your prices include tax or not
- For US: Most states require tax to be added at checkout, so set "Tax: Does not apply" in Merchant Center and handle it via OpenCart

### 8.3 Free Listings vs Paid Shopping Ads

| Aspect | Free Listings | Paid Shopping Ads (Google Ads) |
|--------|--------------|-------------------------------|
| **Cost** | $0 | Pay-per-click (varies by product category) |
| **Placement** | Below paid ads, in Shopping tab | Top of Shopping results, above free listings |
| **Visibility** | Good, but lower than paid | Highest visibility |
| **Traffic** | 20-40% of what paid generates | 60-80% of Shopping traffic |
| **Impressions** | Fewer impressions | More impressions |
| **Setup** | Merchant Center feed only | Merchant Center + Google Ads campaign |
| **Bid control** | N/A | You set bids per product group |
| **Eligibility** | All merchants (after feed approval) | Requires Google Ads account |
| **ROI** | High (free traffic) | Variable (depends on bid optimization) |

**Estimated costs for Shopping Ads (cassette tapes / collectibles):**
- Average CPC for "cassette tapes" category: $0.15-$0.40 per click
- Average CPC for "used electronics": $0.30-$0.80 per click
- Typical conversion rate for Shopping ads: 1.5-3%
- So to get one sale, expect to spend ~$10-$30 in ad clicks
- **Budget recommendation:** Start with $10/day if trying paid ads

### 8.4 Product Feed Requirements

Google requires a product feed — a structured data file (XML or TSV) that lists all your products with specific attributes.

**Required Feed Attributes:**

| Attribute | Requirement | Notes for Geeky Goody Goods |
|-----------|-------------|---------------------------|
| **id** | Required | Unique product identifier (use OpenCart product ID) |
| **title** | Required | Must match product page title; 150 char max; no promotional text (e.g., "SALE!!") |
| **description** | Required | Must match product page description; HTML stripped; no promotional text |
| **link** | Required | Direct URL to the product page on geekygoodygoods.com |
| **image_link** | Required | High-quality image (100x100px min, 800x800px recommended); no watermarks |
| **availability** | Required | "in stock", "out of stock", or "preorder" |
| **price** | Required | Must match the price on the landing page (MISMATCH = DISAPPROVAL) |
| **condition** | Required | "new", "refurbished", or "used" |
| **gtin** | Strongly recommended | GTIN/UPC/EAN/ISBN/JAN — needed for best visibility |
| **mpn** | Recommended | Manufacturer Part Number (optional for collectibles) |
| **brand** | Recommended | "Unbranded" if no brand, or the actual brand |
| **google_product_category** | Recommended | Google's taxonomy ID (see section 8.7) |
| **product_type** | Recommended | Your own category hierarchy (e.g., "Music > Cassette Tapes") |
| **shipping** | Recommended | Shipping cost (or use Merchant Center settings) |
| **shipping_weight** | Recommended | Needed for carrier-calculated shipping in Merchant Center |

### 8.5 GTIN Requirements (Important for Used Items)

**Key rule:** Google does NOT require GTINs for used/collectible items. If your cassettes are sold as **used** (`condition = "used"`), you can omit the GTIN field.

However, **if a GTIN exists** (e.g., a modern reissue cassette with a UPC barcode), providing it improves visibility:
- Products with GTINs rank better in Shopping results
- Google may show rich product information (reviews, etc.) for GTIN-matched products
- You can find UPC barcodes on original cassette releases at https://www.upcdatabase.com or by scanning with a phone app

**For vintage cassettes without barcodes:** Simply omit the GTIN. Set `condition = "used"`. Google accepts this.

### 8.6 Condition Field for Used/Collectible Items

Google's allowed values: `new`, `refurbished`, `used`

For your inventory:
- **New, sealed cassettes** → `new`
- **Used but playable** → `used`
- **Collectible/graded** → `used` (Google has no separate "collectible" condition)
- **Refurbished electronics** (e.g., repaired Walkman) → `refurbished`

**Important for used items:** Google may restrict visibility of used items in some categories. Cassettes classified under "Music > Recordings" usually get standard visibility.

### 8.7 Google Product Taxonomy for Cassette Tapes

Find the full taxonomy at: https://www.google.com/basepages/producttype/taxonomy.en-US.txt

**Best taxonomy paths for your products:**

| Product | Google Product Category ID | Category Path |
|---------|---------------------------|---------------|
| Blank cassette tapes | **653** | Media > Recording Media > Blank Audio Tapes |
| Pre-recorded cassette tapes (music) | **421** | Media > Music > Recordings |
| Cassette players (Walkman, boombox) | **123** | Electronics > Audio > Audio Players & Recorders |
| Used electronics (general) | **2900** | Electronics > Used Electronics |
| Vintage electronics | **2900** or **123** | Electronics > Audio > Audio Players & Recorders |
| Collectibles (non-electronic) | **3382** | Collectibles > Collectible ... (choose subcategory) |

**Recommendation for cassettes:** Use **ID 421** (Media > Music > Recordings) for pre-recorded music cassettes. This is the most accurate and maps to the highest traffic Shopping queries.

### 8.8 OpenCart Google Shopping Feed Plugins (OC4 Compatible)

OpenCart 4 does NOT have a built-in Google Shopping feed generator. You need a third-party extension or service. Here are the options that work with OC4:

**Option A: OpenCart Marketplace Extensions**

| Extension | Price | OC4 Compatible | Notes |
|-----------|-------|----------------|-------|
| **Google Shopping Feed by OCSOFT** | ~$30 one-time | Yes | XML/TSV feed generation, scheduled generation, supports conditions/GTIN |
| **Google Shopping (Google Merchant Center)** by unitedsoftwares | ~$35 one-time | Yes | Full feed with all required attributes |
| **Feed Manager for Google Shopping** by iSenseLabs | ~$50 one-time | Yes | Supports multiple feed formats, category mapping, scheduled generation |
| **Google Shopping Feed Pro** | ~$40 one-time | Yes | Advanced filtering, price overrides, multi-language |

**Option B: Third-Party Feed Services (No Plugin Needed)**

| Service | Price | How It Works |
|---------|-------|-------------|
| **GoDataFeed** | $19-$99/mo | Pulls data from your site via API or sitemap; generates and submits feeds |
| **DataFeedWatch** | $19-$299/mo | Similar; supports Google Shopping + 2000+ channels |
| **Shopping Feed (by Lengow)** | €25-200/mo | Enterprise-grade, multi-channel |
| **Google Merchant Center API** | Free | Manually build your own feed with a script (requires development work) |

**Option C: Custom Feed via Cron Job (Free, Requires Development)**

If you have developer access, you can build a custom feed:
1. Create a PHP script that queries the OpenCart database (`oc_product`, `oc_product_description`, etc.)
2. Generate an XML feed matching Google's specification
3. Set up a cron job to generate the feed file daily
4. Point Merchant Center to the feed URL (e.g., `https://geekygoodygoods.com/feed/google_shopping.xml`)

Google provides a feed specification: https://support.google.com/merchants/answer/7052112

### 8.9 Feed Generation Frequency

| Scenario | Recommended Frequency | Notes |
|----------|----------------------|-------|
| **Inventory changes daily** | Daily (every 24 hours) | Most common; Google accepts 1-2 fetches per day |
| **Static inventory** | Weekly | If products rarely go out of stock |
| **Real-time updates** | Use Content API for Shopping | Requires development; updates within minutes |
| **Scheduled fetch** | Set in Merchant Center | Google fetches your feed URL on a schedule |

**Recommendation:** Set up **daily feed generation** via cron job (if using a plugin) or configure the plugin to auto-generate and upload. In Merchant Center, set scheduled fetch to match.

### 8.10 Common Pitfalls

**1. Account Suspension / Disapproval**
- **Most common cause:** Price mismatch — the price in your feed must match the price on the product page (including tax, if you display prices with tax)
- **Issue:** If OpenCart displays prices including tax ($10.69) but your feed sends the base price ($9.99), Google flags it
- **Fix:** Ensure feed price matches the displayed price on the product page. In OpenCart, if "Display Prices With Tax" is ON, your feed must send the tax-inclusive price
- **Second most common cause:** Missing contact info or inaccurate business address

**2. Shipping Setting Mismatches**
- If you set shipping in Merchant Center but it doesn't match your OpenCart checkout, products get disapproved
- **Fix:** Keep shipping simple. Use flat rate in both places (e.g., "$5.99") and set a free shipping threshold that matches

**3. Disapproved Products**
- Common reasons:
  - Image too small (<100x100px)
  - Image has watermarks or promotional text
  - Title/description has ALL CAPS or excessive punctuation
  - Link goes to a non-working page
  - Product unavailable when Google checks

**4. Used Items Restricted**
- Google restricts some categories for used items (e.g., used personal care products)
- Cassettes and electronics are generally fine as used
- If disapproved, check the specific Google policy: https://support.google.com/merchants/answer/6149970

**5. Feed Format Errors**
- XML must be well-formed
- UTF-8 encoding required
- Special characters (®, ™, ©) must be encoded properly
- Most feed plugins handle this automatically

**6. Tax Settings Confusion**
- **DO NOT** set tax rates in both Merchant Center AND OpenCart — you'll double-charge
- **Best approach:** Set tax = 0% in Merchant Center, handle all tax via OpenCart
- Google doesn't enforce tax collection — it just needs to know if the displayed price includes tax
- Since you display prices WITH tax in OpenCart, set Merchant Center's tax to "The listed price includes tax"

**7. Data Freshness**
- If your feed is stale and shows items as "in stock" when they're sold out, Google may suspend your account
- Daily feeds are the minimum; set up a cron job

### 8.11 Estimated Costs Summary

| Item | Free Listings Only | Free + Paid Shopping Ads |
|------|-------------------|-------------------------|
| **Merchant Center Account** | Free | Free |
| **Feed Plugin (one-time)** | $30-$50 | $30-$50 |
| **Google Ads Account** | Not needed | Free |
| **Ad Spend (minimum)** | $0 | $10/day ($300/mo) |
| **Average CPC** | N/A | $0.15-$0.80 |
| **Estimated monthly traffic** | 100-500 visits | 500-2,500 visits |
| **Conversion rate** | 1-2% typical | 1.5-3% typical |
| **Professional feed service** | $19/mo (optional) | $19/mo (optional) |

### 8.12 Recommended Launch Sequence

**Phase 1 — Free Listings (Week 1-2):**
1. Install a Google Shopping Feed plugin (recommended: OCSOFT or iSenseLabs, ~$30-50)
2. Create Merchant Center account and verify domain
3. Configure tax/shipping settings in Merchant Center (flat rate, match OpenCart)
4. Generate first feed and upload to Merchant Center
5. Submit feed for review — approval takes 3-5 business days
6. Review diagnostics and fix any disapproved products

**Phase 2 — Optimize (Week 2-4):**
1. Set up daily feed generation via cron job
2. Verify all products are approved
3. Optimize titles and images for Shopping visibility
4. Add GTINs where available for better ranking

**Phase 3 — Paid Ads (Optional, Month 2+):**
1. Create a Google Ads account linked to Merchant Center
2. Start a Standard Shopping Campaign with $10/day budget
3. Target "cassette tape" and related keywords
4. Monitor performance and adjust bids
5. Scale budget to $25-$50/day once profitable

### 8.13 Key Resources

- **Merchant Center Help:** https://support.google.com/merchants
- **Feed Specification:** https://support.google.com/merchants/answer/7052112
- **Product Taxonomy:** https://www.google.com/basepages/producttype/taxonomy.en-US.txt
- **Shopping Ads Policy:** https://support.google.com/merchants/answer/6149970
- **Google Ads for Shopping:** https://ads.google.com
- **Google Merchant Center Promotions:** https://support.google.com/merchants/answer/6147166

---

## Appendix A: Admin Route Reference

| Function | OpenCart Admin Route |
|----------|---------------------|
| Store Settings | System > Settings > Edit |
| Tax Settings | System > Settings > Edit > Option > Taxes |
| Tax Classes | System > Localization > Tax Classes |
| Tax Rates | System > Localization > Tax Rates |
| Geo Zones | System > Localization > Geo Zones |
| Shipping Methods | Extensions > Extensions > Shipping |
| Payment Methods | Extensions > Extensions > Payments |
| Information Pages | Catalog > Information |
| Products | Catalog > Products |
| Returns | Sales > Returns |
| Orders | Sales > Orders |
| Currencies | System > Localization > Currencies |
| Countries/Zones | System > Localization > Countries / Zones |
| GDPR Requests | Customers > GDPR |
| Marketplace | Extensions > Marketplace |

## Appendix B: Key Resources

- **Texas Comptroller:** https://comptroller.texas.gov/taxes/sales/
- **Economic Nexus by State (Sales Tax Institute):** https://www.salestaxinstitute.com/resources/economic-nexus-state-guide
- **Avalara Economic Nexus Guide:** https://www.avalara.com/us/en/learn/guides/state-by-state-guide-economic-nexus-laws.html
- **TaxJar (sales tax automation):** https://www.taxjar.com
- **OpenCart Documentation:** https://docs.opencart.com
- **OpenCart Marketplace:** https://www.opencart.com/index.php?route=marketplace/extension
- **Stripe Dashboard:** https://dashboard.stripe.com
- **USPS Web Tools API:** https://www.usps.com/business/web-tools-apis/
- **Pirate Ship (discounted USPS rates, free):** https://www.pirateship.com
- **FedEx Ground Economy:** https://www.fedex.com/en-us/shipping/ground-economy.html
- **Google Merchant Center:** https://merchants.google.com
- **Google Shopping Feed Spec:** https://support.google.com/merchants/answer/7052112
- **Google Product Taxonomy:** https://www.google.com/basepages/producttype/taxonomy.en-US.txt
- **GoDataFeed (feed service):** https://www.godatafeed.com
- **ShipStation (OpenCart + multi-carrier):** https://www.shipstation.com
- **Shippo (OpenCart shipping plugin):** https://apps.shopify.com/shippo (also available for OpenCart marketplace)
