<?php
// app/core/mailer.php (Lite) — JSON settings, FORCE AUTH LOGIN, SMTP + mail() fallback
// No DKIM. No database. Minimal deps (expects h() from utility.php already loaded).

namespace app\core;

class mailer {
    private string $from = 'no-reply@localhost';
    private string $host = '';
    private int    $port = 587;
    private string $user = '';
    private string $pass = '';
    private string $secure = 'tls'; // 'tls' | 'ssl' | ''
    private bool   $enable_mail_fallback = true;

    private array  $headers = [];
    private string $logFile;

    public function __construct() {
        $this->initFromJson();
    }

    /** Public: add/override headers (Reply-To, CC, BCC, List-Unsubscribe, etc.) */
    public function set_headers(array $hdrs): void {
        foreach ($hdrs as $k => $v) $this->headers[(string)$k] = (string)$v;
    }

    /** Public: send email (HTML + optional text). Returns bool. */
    public function send(string $to, string $subject, string $html, string $text=''): bool {
        $this->log("Attempt send: to={$to} subject={$subject}");
        $this->log("stage: build-message");

        if ($text === '') {
            $text = trim(preg_replace('/\s+/', ' ', strip_tags($html)));
        }

        [$headersStr, $bodyStr] = $this->buildMime($to, $subject, $html, $text);

        // Try SMTP if configured
        if ($this->host !== '' && $this->port > 0) {
            try {
                $this->log("stage: connect");
                [$fp, $crypto] = $this->smtp_connect();

                $this->log("stage: banner");
                $banner = $this->smtp_read($fp);
                $this->log("banner: ".$banner);

                $ehloHost = 'localhost';
                $this->log("stage: ehlo");
                $this->smtp_cmd($fp, "EHLO {$ehloHost}");
                $ehlo1 = $this->smtp_read($fp);
                $this->log("ehlo: ".$ehlo1);

                // STARTTLS upgrade if needed
                if ($this->secure === 'tls' && stripos($ehlo1, 'STARTTLS') !== false && !$crypto) {
                    $this->log("stage: starttls");
                    $this->smtp_cmd($fp, "STARTTLS");
                    $resp = $this->smtp_read($fp);
                    $this->log("starttls: ".$resp);
                    if (strpos($resp, '220') !== 0) throw new \RuntimeException("starttls-failed");
                    if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                        throw new \RuntimeException("crypto-upgrade-failed");
                    }
                    $crypto = true;

                    $this->log("stage: ehlo-2");
                    $this->smtp_cmd($fp, "EHLO {$ehloHost}");
                    $ehlo2 = $this->smtp_read($fp);
                    $this->log("ehlo-2: ".$ehlo2);
                }

                // ---------- FORCE AUTH LOGIN ONLY ----------
                if ($this->user !== '' || $this->pass !== '') {
                    $this->log("stage: auth");
                    $this->force_auth_login($fp, $this->user, $this->pass);
                }

                $this->log("stage: mail-from");
                $this->smtp_cmd($fp, "MAIL FROM:<{$this->from}>");
                $this->smtp_expect($fp, 250);

                $this->log("stage: rcpt-to");
                $this->smtp_cmd($fp, "RCPT TO:<{$to}>");
                $this->smtp_expect($fp, 250, [251]);

                $this->log("stage: data");
                $this->smtp_cmd($fp, "DATA");
                $this->smtp_expect($fp, 354);

                $this->log("stage: data-body");
                fwrite($fp, $headersStr."\r\n\r\n".$bodyStr."\r\n.\r\n");
                $this->smtp_expect($fp, 250);

                $this->log("stage: quit");
                $this->smtp_cmd($fp, "QUIT");
                @fclose($fp);

                $this->log("OK");
                return true;
            } catch (\Throwable $e) {
                $msg = $e->getMessage();
                $this->log("FAIL smtp: ".$msg);
                if (stripos($msg,'socket')   !== false) $this->log('hint: check smtp_host/port/DNS/firewall');
                if (stripos($msg,'starttls') !== false) $this->log('hint: try 587+tls or 465+ssl per host');
                if (stripos($msg,'crypto')   !== false) $this->log('hint: TLS negotiation failed – try other secure mode');
                if (stripos($msg,'auth')     !== false || stripos($msg,'535') !== false) $this->log('hint: AUTH failed – verify smtp_user/smtp_pass (some hosts require local-part username)');
                // fall through to PHP mail()
            }
        } else {
            $this->log("FAIL: smtp_host/port not configured (host='{$this->host}', port='{$this->port}')");
        }

        // Fallback: PHP mail()
        if ($this->enable_mail_fallback) {
            $this->log("fallback: trying PHP mail()");
            $ok = $this->send_via_php_mail($to, $this->encodeHeader($subject), $headersStr, $bodyStr);
            $this->log($ok ? "fallback: OK" : "fallback: FAIL");
            return $ok;
        }

        return false;
    }

    /* ---------- Settings (JSON) ---------- */

    private function initFromJson(): void {
        $root = dirname(__DIR__, 2); // project root
        $this->logFile = $root . '/logs/mailer.log';
        @is_dir($root.'/logs') || @mkdir($root.'/logs', 0775, true);

        $file = $root . '/data/settings.json';
        $cfg = [];
        if (is_file($file)) {
            $j = json_decode(@file_get_contents($file), true);
            if (is_array($j)) $cfg = $j;
        }

        $get = function (array $keys, $def='') use ($cfg) {
            foreach ($keys as $k) {
                if (array_key_exists($k, $cfg) && trim((string)$cfg[$k]) !== '') {
                    return is_string($def) ? trim((string)$cfg[$k]) : $cfg[$k];
                }
            }
            return $def;
        };

        $this->from   = $get(['send_from','from','email_from'], 'no-reply@localhost');
        $this->host   = $get(['smtp_host','mail_host','host'], '');
        $this->port   = (int)($get(['smtp_port','mail_port','port'], 587) ?: 587);
        $this->user   = $get(['smtp_user','smtp_username','mail_user','user'], '');
        $this->pass   = $get(['smtp_pass','smtp_password','mail_pass','pass'], '');
        $this->secure = strtolower($get(['smtp_secure','secure','encryption'], 'tls'));
        $fallback     = strtolower($get(['use_mail_fallback','mail_fallback'], '1'));
        $this->enable_mail_fallback = in_array($fallback, ['1','true','yes','on'], true);

        $mask = fn(string $s) => ($s === '') ? '(empty)'
            : ((strpos($s,'@')!==false) ? preg_replace('/^(.).+(@.+)$/','$1***$2',$s) : preg_replace('/^(.).+$/','$1***',$s));
        $passTag = ($this->pass === '') ? '(empty)' : 'len='.strlen($this->pass);

        $this->log("settings(json): from={$this->from} host={$this->host} port={$this->port} user=".$mask($this->user)
            ." pass={$passTag} secure={$this->secure} mail_fallback=".($this->enable_mail_fallback?'on':'off'));
    }

    /* ---------- AUTH LOGIN only ---------- */

    private function force_auth_login($fp, string $user, string $pass): void {
        $maskUser = (strpos($user, '@') !== false)
            ? preg_replace('/^(.).+(@.+)$/', '$1***$2', $user)
            : preg_replace('/^(.).+$/',      '$1***',    $user);

        // Try full username
        if ($this->auth_login($fp, $user, $pass, "LOGIN as {$maskUser}")) return;

        // Some Exim/cPanel want local-part only
        if (strpos($user, '@') !== false) {
            $alt = substr($user, 0, strpos($user, '@'));
            if ($alt !== '' && $this->auth_login($fp, $alt, $pass, "LOGIN(alt) as {$alt}***")) return;
        }

        throw new \RuntimeException("auth-failed");
    }

    private function auth_login($fp, string $user, string $pass, string $label): bool {
        $this->log("auth: forcing {$label}");
        $this->smtp_cmd($fp, "AUTH LOGIN");
        $res = $this->smtp_read($fp);
        $this->log("auth-login: ".$res);
        if (strpos($res, '334') !== 0 && strpos($res, '235') !== 0) {
            return false;
        }
        if (strpos($res, '235') === 0) { // already authenticated
            $this->log("auth OK (short-circuit)");
            return true;
        }

        // Username
        $this->smtp_cmd($fp, base64_encode($user));
        $res = $this->smtp_read($fp);
        $this->log("auth-login-user: ".$res);
        if (strpos($res, '334') !== 0) return false;

        // Password
        $this->smtp_cmd($fp, base64_encode($pass));
        $res = $this->smtp_read($fp);
        $this->log("auth-login-pass: ".$res);
        if (strpos($res, '235') === 0) {
            $this->log("auth OK (LOGIN)");
            return true;
        }
        return false;
    }

    /* ---------- MIME / PHP mail() ---------- */

    private function buildMime(string $to, string $subject, string $html, string $text): array {
        $boundary = '=_chaos_'.bin2hex(random_bytes(8));
        $date     = gmdate('D, d M Y H:i:s').' +0000';

        $headers  = [];
        $headers[] = "Date: {$date}";
        $headers[] = "From: {$this->from}";
        $headers[] = "To: {$to}";
        $headers[] = "Subject: ".$this->encodeHeader($subject);
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";

        foreach ($this->headers as $k => $v) $headers[] = "{$k}: {$v}";

        $body  = '';
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=\"utf-8\"\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= rtrim(chunk_split(base64_encode($text), 76, "\r\n")) . "\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=\"utf-8\"\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= rtrim(chunk_split(base64_encode($html), 76, "\r\n")) . "\r\n";
        $body .= "--{$boundary}--\r\n";

        return [implode("\r\n", $headers), $body];
    }

    private function send_via_php_mail(string $to, string $subjectEnc, string $headersStr, string $bodyStr): bool {
        $params = '';
        $fromEmail = $this->extractEmail($this->from);
        if ($fromEmail !== '') $params = '-f'.$fromEmail; // envelope sender for SPF alignment
        return @mail($to, $subjectEnc, $bodyStr, $headersStr, $params);
    }

    private function extractEmail(string $addr): string {
        if (preg_match('/<([^>]+)>/', $addr, $m)) return trim($m[1]);
        return trim($addr);
    }

    /* ---------- SMTP internals ---------- */

    private function smtp_connect(): array {
        $remote = ($this->secure === 'ssl')
            ? 'ssl://'.$this->host.':'.$this->port  // implicit SSL (465)
            : $this->host.':'.$this->port;          // plain TCP (587 + STARTTLS)
        $this->log("dial: {$remote}");

        $ctx = stream_context_create(['ssl'=>[
            'verify_peer'       => true,
            'verify_peer_name'  => true,
            'allow_self_signed' => false,
        ]]);

        $errno = 0; $errstr = '';
        $fp = @stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) {
            $last = error_get_last();
            if ($last && isset($last['message'])) $this->log("socket_last_error: ".$last['message']);
            throw new \RuntimeException("socket: {$errstr} ({$errno})");
        }
        stream_set_timeout($fp, 20);
        return [$fp, ($this->secure === 'ssl')];
    }

    private function smtp_cmd($fp, string $line): void { fwrite($fp, $line."\r\n"); }

    private function smtp_read($fp): string {
        $out = '';
        while (!feof($fp)) {
            $line = fgets($fp, 4096);
            if ($line === false) break;
            $out .= $line;
            if (preg_match('/^\d{3} /', $line)) break; // last line of multi-line reply
        }
        return trim($out);
    }

    private function smtp_expect($fp, int $code, array $alsoOk=[]): void {
        $resp = $this->smtp_read($fp);
        $ok   = (int)substr($resp,0,3);
        if ($ok !== $code && !in_array($ok, $alsoOk, true)) {
            throw new \RuntimeException("expect {$code} got {$ok}: {$resp}");
        }
    }

    private function encodeHeader(string $s): string {
        return preg_match('/[^\x20-\x7E]/', $s)
            ? '=?UTF-8?B?'.base64_encode($s).'?='
            : $s;
    }

    private function log(string $line): void {
        $ts  = date('Y-m-d H:i:s');
        @file_put_contents($this->logFile, "[{$ts}] {$line}\n", FILE_APPEND);
    }
}
