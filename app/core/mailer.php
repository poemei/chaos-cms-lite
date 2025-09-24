<?php
// /app/core/mailer.php  — chaoscms-lite mailer (JSON settings, AUTH LOGIN, SPF only)
// Version: 1.1.0 (no DKIM; JSON settings; verbose logging; PHP mail() fallback)

namespace app\core;

class mailer {
    // Settings
    private string $root;            // project root
    private string $send_from = '';
    private string $smtp_host = '';
    private int    $smtp_port = 587;
    private string $smtp_user = '';
    private string $smtp_pass = '';
    private string $smtp_secure = 'tls';   // 'tls' or 'ssl' or ''
    private bool   $smtp_enabled = true;   // can be toggled off in settings
    private string $auth_method = 'login'; // force AUTH LOGIN

    // Runtime
    private $sock = null;
    private string $logfile;

    public function __construct(string $projectRoot = null) {
        $this->root = $projectRoot ?? dirname(__DIR__, 1); // /app -> project root
        $this->logfile = $this->root . '/logs/mailer.log';
        $this->ensureLogDir();
        $this->log('stage: init');
        $this->initFromJson($this->root . '/data/mailer.json');
    }

    /* ---------------- Public API ---------------- */

    // Read settings from /data/settings.json (lite build)
    public function initFromJson(string $jsonPath): void {
        $this->log('stage: load-settings ' . $jsonPath);
        $cfg = $this->jread($jsonPath, []);
        // Map expected keys (all optional, we validate later)
        $this->send_from   = (string)($cfg['send_from']   ?? $this->send_from);
        $this->smtp_host   = (string)($cfg['smtp_host']   ?? $this->smtp_host);
        $this->smtp_port   = (int)   ($cfg['smtp_port']   ?? $this->smtp_port);
        $this->smtp_user   = (string)($cfg['smtp_user']   ?? $this->smtp_user);
        $this->smtp_pass   = (string)($cfg['smtp_pass']   ?? $this->smtp_pass);
        $this->smtp_secure = (string)($cfg['smtp_secure'] ?? $this->smtp_secure); // 'tls' | 'ssl' | ''
        $this->smtp_enabled= (bool)  ($cfg['smtp_enabled']?? $this->smtp_enabled);
        $this->auth_method = 'login'; // hard-force LOGIN as requested
    }

    // Optional: allow overriding settings at runtime
    public function set(array $opts): void {
        foreach ($opts as $k=>$v) {
            if (!property_exists($this,$k)) continue;
            $this->$k = $v;
        }
    }

    /**
     * Send an email.
     * @param string $to
     * @param string $subject
     * @param string $bodyHtml
     * @param string $bodyText
     * @param array  $extraHeaders  e.g. ['Reply-To'=>'support@...','CC'=>'...']
     */
    public function send(string $to, string $subject, string $bodyHtml, string $bodyText = '', array $extraHeaders = []): bool {
        $this->log("Attempt send: to={$to} subject={$subject}");
        $this->log('stage: build-message');

        if ($this->send_from === '') {
            $this->log('FAIL: missing send_from');
            return false;
        }

        // Build MIME (simple alt)
        $boundary = '=_mime_' . bin2hex(random_bytes(8));
        $date     = date('r');
        $from     = $this->send_from;
        $cleanSub = $this->foldHeader($subject);

        $headers  = [
            "Date: {$date}",
            "From: {$from}",
            "To: {$to}",
            "Subject: {$cleanSub}",
            "MIME-Version: 1.0",
            "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
        ];
        foreach ($extraHeaders as $hk=>$hv) {
            $hk = trim($hk); if ($hk==='') continue;
            $headers[] = $hk . ': ' . $this->foldHeader((string)$hv);
        }

        // Bodies
        $text = $bodyText !== '' ? $bodyText : strip_tags($bodyHtml);
        $mime  = '';
        $mime .= "--{$boundary}\r\n";
        $mime .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $mime .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $mime .= $text . "\r\n\r\n";
        $mime .= "--{$boundary}\r\n";
        $mime .= "Content-Type: text/html; charset=UTF-8\r\n";
        $mime .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $mime .= $bodyHtml . "\r\n\r\n";
        $mime .= "--{$boundary}--\r\n";

        // SMTP or fallback
        if ($this->smtp_enabled && $this->smtp_host !== '' && $this->smtp_user !== '' && $this->smtp_pass !== '') {
            $ok = $this->smtpSend($from, $to, $headers, $mime);
            if ($ok) { $this->log('OK'); return true; }
            $this->log('fallback: trying PHP mail()');
        } else {
            $this->log('hint: SMTP disabled or missing creds ? using PHP mail()');
        }

        $ok2 = $this->phpMailSend($to, $from, $subject, $headers, $mime);
        $this->log($ok2 ? 'fallback: OK' : 'fallback: FAIL');
        return $ok2;
    }

    /* ---------------- SMTP (LOGIN only) ---------------- */

    private function smtpSend(string $from, string $to, array $headers, string $data): bool {
        $this->log('stage: connect');

        $host = $this->smtp_host;
        $port = $this->smtp_port ?: 587;
        $secure = strtolower($this->smtp_secure);

        $remote = ($secure === 'ssl')
            ? "ssl://{$host}:{$port}"
            : "tcp://{$host}:{$port}";

        $this->sock = @stream_socket_client(
            $remote,
            $errno, $errstr,
            15,
            STREAM_CLIENT_CONNECT,
            stream_context_create(['ssl'=>['verify_peer'=>false,'verify_peer_name'=>false]])
        );
        if (!$this->sock) {
            $this->log("FAIL: socket: {$errstr} ({$errno})");
            return false;
        }
        stream_set_timeout($this->sock, 15);

        // Banner
        $banner = $this->readLine();
        $this->log("stage: banner");
        $this->log("banner: " . trim($banner));
        if (substr($banner,0,3) !== '220') {
            $this->quit();
            $this->log('FAIL: Bad banner');
            return false;
        }

        // EHLO
        $this->log('stage: ehlo');
        if (!$this->writeExpect("EHLO ".$this->ehloName()."\r\n", '250')) return $this->failQuit('EHLO failed');

        // STARTTLS if needed
        if ($secure === 'tls') {
            $this->log('stage: starttls');
            if (!$this->writeExpect("STARTTLS\r\n", '220')) return $this->failQuit('STARTTLS refused');
            if (!stream_socket_enable_crypto($this->sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                return $this->failQuit('TLS negotiation failed');
            }
            // EHLO (again)
            $this->log('stage: ehlo-2');
            if (!$this->writeExpect("EHLO ".$this->ehloName()."\r\n", '250')) return $this->failQuit('EHLO(2) failed');
        }

        // AUTH LOGIN (forced)
        $this->log('stage: auth');
        $user = $this->smtp_user;
        $pass = $this->smtp_pass;

        // LOGIN sequence
        if (!$this->writeExpect("AUTH LOGIN\r\n", '334')) return $this->failQuit('AUTH LOGIN not accepted');
        if (!$this->writeExpect(base64_encode($user)."\r\n", '334')) return $this->failQuit('Username rejected');
        if (!$this->writeExpect(base64_encode($pass)."\r\n", '235')) return $this->failQuit('Password rejected');

        // MAIL FROM / RCPT TO
        $this->log('stage: mail-from');
        if (!$this->writeExpect("MAIL FROM:<{$from}>\r\n", '250')) return $this->failQuit('MAIL FROM fail');

        $this->log('stage: rcpt-to');
        if (!$this->writeExpect("RCPT TO:<{$to}>\r\n", '250')) return $this->failQuit('RCPT TO fail');

        // DATA
        $this->log('stage: data');
        if (!$this->writeExpect("DATA\r\n", '354')) return $this->failQuit('DATA not accepted');

        $this->log('stage: data-body');
        $out = implode("\r\n", $headers) . "\r\n\r\n" . $data . "\r\n.\r\n";
        fwrite($this->sock, $out);
        $resp = $this->readLine();
        if (substr($resp,0,3) !== '250') { $this->quit(); $this->log('FAIL: DATA rejected: ' . trim($resp)); return false; }

        // QUIT
        $this->log('stage: quit');
        $this->write("QUIT\r\n");
        @fclose($this->sock);
        $this->sock = null;
        return true;
    }

    /* ---------------- Helpers ---------------- */

    private function phpMailSend(string $to, string $from, string $subject, array $headers, string $mime): bool {
        // mail() expects headers WITHOUT To/Subject lines (we already included them in SMTP path)
        $clean = array_values(array_filter($headers, function($h) {
            return (stripos($h, 'To:') !== 0) && (stripos($h, 'Subject:') !== 0);
        }));
        $hdrStr = implode("\r\n", $clean);
        return @mail($to, $subject, $mime, $hdrStr, "-f{$from}");
    }

    private function ehloName(): string {
        $hn = @gethostname();
        if (!$hn) $hn = 'localhost';
        return $hn;
    }

    private function write(string $s): void {
        fwrite($this->sock, $s);
    }

    private function readLine(): string {
        $line = '';
        while (!feof($this->sock)) {
            $l = fgets($this->sock, 4096);
            if ($l === false) break;
            $line .= $l;
            if (strlen($l) >= 4 && preg_match('/^\d{3}\s/s', $l)) break; // final line "250 ..."
        }
        return $line;
    }

    private function writeExpect(string $cmd, string $expect): bool {
        $this->write($cmd);
        $resp = $this->readLine();
        // log first line only
        $this->log(strtok(trim($resp), "\r\n"));
        return (substr($resp,0,strlen($expect)) === $expect);
    }

    private function failQuit(string $msg): bool {
        $this->log("FAIL smtp: {$msg}");
        $this->quit();
        return false;
    }

    private function quit(): void {
        if ($this->sock) {
            @fwrite($this->sock, "QUIT\r\n");
            @fclose($this->sock);
            $this->sock = null;
        }
    }

    private function foldHeader(string $v): string {
        // very light header folding
        $v = trim(preg_replace("/\r|\n/", ' ', $v));
        return $v;
    }

    private function jread(string $file, $default=null) {
        if (!is_file($file)) return $default;
        $s = @file_get_contents($file);
        if ($s === false) return $default;
        $j = json_decode($s, true);
        return (json_last_error() === JSON_ERROR_NONE) ? $j : $default;
    }

    private function ensureLogDir(): void {
        $dir = dirname($this->logfile);
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
    }

    private function log(string $line): void {
        $ts = date('[Y-m-d H:i:s] ');
        @file_put_contents($this->logfile, $ts . $line . PHP_EOL, FILE_APPEND);
    }
}
