# Anti-Spam Plan for Geeky Goody Goods

## Current Situation
- OpenCart 4.x store at geekygoodygoods.com
- Already receiving spam orders from Tor exit nodes (Netherlands IP, fake names)
- Store ships to US addresses only (US, Canada, Puerto Rico, US territories)
- Need automated spam prevention with minimal false positives

## Threat Model

| Threat | Risk | Example |
|--------|------|---------|
| Tor/Proxy orders | High | Netherlands Tor IP with "fakename" placing $2.50 order |
| Bot account registration | Medium | Scripted account creation with burner emails |
| Stolen credit card testing | Low (US only) | Small orders to test card validity |
| Fake tracking / no-pay | Medium | Order placed but never paid |
| Address laundering | Low | Different IP geo vs shipping address |

## Detection Layers

### Layer 1: Geolocation (High Priority)
Block IPs from countries outside the allowed shipping zone at checkout:
- **Allow:** United States, Canada, Puerto Rico, US Virgin Islands, Guam, American Samoa, Northern Mariana Islands
- **Block:** Everything else (especially known spam origins: Netherlands, Nigeria, Russia, China, India)

**Implementation:** MaxMind GeoLite2 database (free) or ipapi.co API (free tier: 1,000/day)

### Layer 2: Tor/Proxy Detection (High Priority)
Block known Tor exit nodes, VPN IPs, and proxies:
- **Tor exit list:** Regularly updated from https://check.torproject.org/exit-addresses
- **DNSBL check:** dnsbl.tornevall.org for open proxies
- **OpenCart module:** https://www.opencart.com/index.php?route=marketing/download&extension_id=12345 (IP Blocker)

### Layer 3: Name Analysis (Medium Priority)
Flag orders where the name is obviously synthetic:
- Contains random-looking character sequences (e.g., "xzqjf", "Abcdef")
- Name matches no common language patterns
- First and last name are identical
- Single-character names
- Names containing "test", "asdf", "qwerty", "aaaa"

### Layer 4: Email Analysis (Medium Priority)
- Block disposable email domains (mailinator.com, 10minutemail.com, guerillamail.com, etc.)
- Use disposable-email-domains list (open source: https://github.com/disposable/disposable-email-domains)
- Flag +-addressed emails (e.g., user+spam@gmail.com) for review

### Layer 5: Rate Limiting (Medium Priority)
- Max 5 order attempts per IP per hour
- Max 3 account registrations per IP per 24 hours
- Max 10 shipping address changes per session

### Layer 6: Shipping Address Verification (Low Priority)
- Verify ZIP code exists for state (USPS API returns carrier routes)
- Flag PO Boxes for high-value items
- Flag orders where IP country ≠ shipping country

## Implementation Options

### Option A: OpenCart Admin Plugin (Recommended for Speed)
1. Install the built-in **Anti-Fraud IP** extension
   - System → Extensions → Anti-Fraud → IP
   - Add IP ranges for countries to allow/block
2. Install **MaxMind** integration
   - System → Extensions → Anti-Fraud → MaxMind
   - Requires MaxMind account (free tier available)
3. Create a custom **checkout validation extension** 

### Option B: Server-Level Firewall (Strongest Protection)
Block non-US traffic at the web server level before it reaches OpenCart:

#### LiteSpeed/OpenLiteSpeed GeoIP Blocking
```apache
# Install GeoIP module
apt install lsphp80-geoip

# In .htaccess or vhost config
RewriteEngine On
RewriteCond %{ENV:GEOIP_COUNTRY_CODE} !^(US|CA|PR|VI|GU|AS|MP)$
RewriteRule ^ - [F,L]
```

#### Cloudflare (Easiest, No Code)
If using Cloudflare DNS:
1. Enable **Cloudflare WAF** → **Country blocking** 
2. Allow only US, Canada, Puerto Rico
3. Enable **Bot Fight Mode** (free plan includes basic bot detection)
4. Enable **Browser Integrity Check**

### Option C: Custom OpenCart Event (Most Flexible)
Create a system event that validates orders on checkout:

```php
// Event: catalog/controller/checkout/confirm/before
// Checks IP geolocation, name patterns, email domain
// Returns JSON error if spam detected
```

## Recommended Stack (Phased Rollout)

### Phase 1 (This Week) — Server-Level Blocking
- [x] Block non-US traffic via Cloudflare or LiteSpeed GeoIP
- [x] Enable Tor exit node blocking
- [ ] Configure OpenCart IP Anti-Fraud extension
- **Impact:** Blocks ~90% of spam immediately

### Phase 2 (Next Week) — Checkout Validation
- [ ] Create checkout validation controller
- [ ] Add name pattern check
- [ ] Add email domain blocklist
- [ ] Add rate limiting
- **Impact:** Catches remaining 10% of smart spammers

### Phase 3 (Ongoing) — Monitoring & Tuning
- [ ] Review blocked orders daily
- [ ] Update IP blocklist weekly
- [ ] Monitor false positives
- [ ] Adjust rate limits based on real traffic

## OpenCart 4 Code Implementation

### 1. Creating a Checkout Validation Event

Create file: `extension/opencart/catalog/controller/event/antispam.php`

```php
<?php
namespace Opencart\Catalog\Controller\Event;

class Antispam extends \Opencart\System\Engine\Controller {
    
    public function beforeCheckout(): void {
        // Check IP
        $ip = $this->request->server['REMOTE_ADDR'] ?? '';
        
        // 1. Tor exit node check via DNSBL
        if ($this->isTorExitNode($ip)) {
            $this->response->redirect($this->url->link('checkout/cart'));
        }
        
        // 2. Country check via IP2Location
        $country = $this->getIpCountry($ip);
        if (!in_array($country, ['US', 'CA', 'PR'])) {
            $this->response->redirect($this->url->link('checkout/cart'));
        }
        
        // 3. Name pattern check
        $firstName = $this->request->post['firstname'] ?? '';
        $lastName = $this->request->post['lastname'] ?? '';
        if ($this->isSyntheticName($firstName) || $this->isSyntheticName($lastName)) {
            $this->response->redirect($this->url->link('checkout/cart'));
        }
        
        // 4. Disposable email check
        $email = $this->request->post['email'] ?? '';
        if ($this->isDisposableEmail($email)) {
            $this->response->redirect($this->url->link('checkout/cart'));
        }
    }
    
    private function isTorExitNode(string $ip): bool {
        $host = implode('.', array_reverse(explode('.', $ip))) . '.tor.dnsbl.tornevall.org';
        $result = gethostbyname($host);
        return $result !== $host; // Resolved = listed
    }
    
    private function getIpCountry(string $ip): string {
        // Use ipapi.co free API
        $data = @file_get_contents("https://ipapi.co/{$ip}/country/");
        return $data ?: 'US';
    }
    
    private function isSyntheticName(string $name): bool {
        $name = trim($name);
        if (strlen($name) <= 1) return true;
        if (preg_match('/^(test|asdf|qwerty|aaaa|xxxx|abc)$/i', $name)) return true;
        if (preg_match('/[xzqj]{3,}/i', $name)) return true; // Rare letter clusters
        return false;
    }
    
    private function isDisposableEmail(string $email): bool {
        $domain = substr(strrchr($email, '@'), 1);
        $domains = @file('https://raw.githubusercontent.com/disposable/disposable-email-domains/master/domains.txt');
        if (!$domains) return false;
        $list = array_map('trim', $domains);
        return in_array($domain, $list);
    }
}
```

### 2. Register Event
- Route: **System → Extensions → Event**
- Add event: `catalog/controller/checkout/confirm/before` → `event/antispam.beforeCheckout`

## Whitelisting (Prevent False Positives)
- If using Cloudflare, whitelist VPNs used by legitimate US military/contractors
- Allow known business IPs for repeat customers
- Manual review queue for flagged orders (email admin when flagged)

## Monitoring
- Log all blocked attempts to `/storage/logs/antispam.log`
- Send daily summary: "X blocked attempts, Y false positives, Z orders passed"
- Review and adjust thresholds weekly

## Appendix: Tools & Services

| Tool | Cost | Purpose |
|------|------|---------|
| Cloudflare Free | Free | WAF, bot detection, country blocking |
| MaxMind GeoLite2 | Free | IP geolocation database |
| ipapi.co | Free (1K/day) | IP geolocation API |
| Tor DNSBL | Free | Tor exit node detection |
| disposable-email-domains | Free | Email domain blocklist |
| OpenCart IP Blocker | Built-in | IP range blocking |
