<?php
namespace Opencart\Catalog\Controller\Event;

class Antispam extends \Opencart\System\Engine\Controller {
    
    public function beforeCheckout(&$route, &$args): void {
        $ip = $this->request->server['REMOTE_ADDR'] ?? '';
        
        // Log check
        $logMsg = '';
        
        // 1. Tor exit node check via DNSBL
        if ($this->isTorExitNode($ip)) {
            $this->logBlock($ip, 'tor_exit_node');
            $this->session->data['antispam_error'] = 'Orders from this network are not accepted.';
            $this->response->redirect($this->url->link('checkout/cart'));
            return;
        }
        
        // 2. Country check via ipapi.co
        $country = $this->getIpCountry($ip);
        $allowed = ['US', 'CA', 'PR', 'VI', 'GU', 'AS', 'MP'];
        if (!in_array($country, $allowed)) {
            $this->logBlock($ip, 'country_' . $country);
            $this->session->data['antispam_error'] = 'Orders from your country are not accepted.';
            $this->response->redirect($this->url->link('checkout/cart'));
            return;
        }
        
        // 3. Name pattern check (from POST data)
        if (!empty($this->request->post)) {
            $firstName = trim($this->request->post['firstname'] ?? '');
            $lastName = trim($this->request->post['lastname'] ?? '');
            $email = trim($this->request->post['email'] ?? '');
            
            if ($this->isSyntheticName($firstName) || $this->isSyntheticName($lastName)) {
                $this->logBlock($ip, 'synthetic_name');
                $this->session->data['antispam_error'] = 'Invalid name detected.';
                $this->response->redirect($this->url->link('checkout/cart'));
                return;
            }
            
            if ($this->isDisposableEmail($email)) {
                $this->logBlock($ip, 'disposable_email');
                $this->session->data['antispam_error'] = 'Disposable email not accepted.';
                $this->response->redirect($this->url->link('checkout/cart'));
                return;
            }
        }
        
        // 4. IP rate limiting
        $this->checkRateLimit($ip);
    }
    
    private function isTorExitNode(string $ip): bool {
        $parts = explode('.', $ip);
        if (count($parts) !== 4) return false;
        $host = implode('.', array_reverse($parts)) . '.tor.dnsbl.tornevall.org';
        $result = @gethostbyname($host);
        return $result !== $host;
    }
    
    private function getIpCountry(string $ip): string {
        if ($ip === '127.0.0.1' || $ip === '::1') return 'US';
        $data = @file_get_contents("https://ipapi.co/{$ip}/country/");
        return $data ? trim($data) : 'US';
    }
    
    private function isSyntheticName(string $name): bool {
        if (strlen($name) <= 1) return true;
        if (preg_match('/^(test|asdf|qwerty|aaaa|xxxx|abc|xyz)$/i', $name)) return true;
        if (preg_match('/[xzqj]{3,}/i', $name)) return true;
        return false;
    }
    
    private function isDisposableEmail(string $email): bool {
        if (empty($email)) return false;
        $domain = strtolower(substr(strrchr($email, '@'), 1));
        if (empty($domain)) return false;
        // Check common disposable domains (local cache)
        $disposable = ['mailinator.com','10minutemail.com','guerrillamail.com','temp-mail.org',
            'throwaway.email','yopmail.com','trashmail.com','sharklasers.com','maildrop.cc',
            'getnada.com','tempmail.com','emailondeck.com','mailnesia.com','spamgourmet.com',
            'burnermail.io','inboxbear.com','mohmal.com','fakeinbox.com','tempinbox.com'];
        return in_array($domain, $disposable);
    }
    
    private function checkRateLimit(string $ip): void {
        $key = 'antispam_rate_' . md5($ip);
        $count = $this->cache->get($key);
        if ($count === null) {
            $this->cache->set($key, 1, 3600);
        } elseif ((int)$count >= 10) {
            $this->logBlock($ip, 'rate_limit');
            $this->session->data['antispam_error'] = 'Too many attempts. Try again later.';
            $this->response->redirect($this->url->link('checkout/cart'));
        } else {
            $this->cache->set($key, (int)$count + 1, 3600);
        }
    }
    
    private function logBlock(string $ip, string $reason): void {
        $log = date('Y-m-d H:i:s') . " BLOCKED $ip reason=$reason\n";
        @file_put_contents(DIR_LOGS . 'antispam.log', $log, FILE_APPEND);
    }
}
