<?php
declare(strict_types=1);
/**
 * Lecture et envoi de mails sans extension PHP (ni imap, ni mailparse) :
 * l'hébergeur peut ne pas les proposer. Tout passe par des connexions chiffrées.
 *
 *  - ImapLite  : lecture d'une boîte (IMAP sur SSL, port 993), rangement dans des
 *                dossiers, dépôt de brouillons, suppression d'un brouillon.
 *  - SmtpLite  : envoi (SMTP sur SSL port 465, ou STARTTLS port 587).
 *  - mime_parse() : découpe un message brut (en-têtes, texte, pièces jointes).
 *  - mime_build() : fabrique un message texte simple en UTF-8.
 *
 * Aucune de ces fonctions n'écrit de mot de passe dans un journal ou une exception.
 */

final class MailboxError extends RuntimeException {}

/* ══════════════════════════════ IMAP ══════════════════════════════ */

final class ImapLite
{
    /** @var resource|null */
    private $s = null;
    private int $tag = 0;
    public array $caps = [];
    public string $prefix = '';      // espace personnel (« INBOX. » chez cPanel, vide chez Gmail)
    public string $delim = '/';
    private ?array $listCache = null;

    public function __construct(private string $host, private int $port = 993, private bool $verify = true, private int $timeout = 20) {}

    public function connect(string $user, string $pass): void
    {
        $ctx = stream_context_create(['ssl' => [
            'verify_peer' => $this->verify, 'verify_peer_name' => $this->verify, 'SNI_enabled' => true, 'peer_name' => $this->host,
        ]]);
        $errno = 0; $errstr = '';
        $s = @stream_socket_client('ssl://'.$this->host.':'.$this->port, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT, $ctx);
        if (!$s) throw new MailboxError('Connexion à '.$this->host.':'.$this->port.' impossible ('.($errstr ?: 'délai dépassé').').');
        stream_set_timeout($s, $this->timeout);
        $this->s = $s;
        $greet = $this->line();
        if (!str_starts_with($greet, '* OK') && !str_starts_with($greet, '* PREAUTH')) throw new MailboxError('Réponse inattendue du serveur de mail.');
        $r = $this->cmd('LOGIN '.self::q($user).' '.self::q($pass));
        if (!$r['ok']) throw new MailboxError('Identifiant ou mot de passe refusé par '.$this->host.'.');
        $this->caps = [];
        foreach ($this->cmd('CAPABILITY')['lines'] as $l) {
            if (preg_match('/^\* CAPABILITY (.+)$/i', $l, $m)) $this->caps = array_map('strtoupper', preg_split('/\s+/', trim($m[1])));
        }
        if ($this->has('NAMESPACE')) {
            foreach ($this->cmd('NAMESPACE')['lines'] as $l) {
                if (preg_match('/^\* NAMESPACE \(\("([^"]*)" (?:"([^"]*)"|NIL)\)/i', $l, $m)) { $this->prefix = $m[1]; $this->delim = $m[2] ?? '/'; }
            }
        } else {
            foreach ($this->cmd('LIST "" ""')['lines'] as $l) {
                if (preg_match('/^\* LIST \([^)]*\) "([^"]*)"/i', $l, $m)) $this->delim = $m[1];
            }
        }
    }

    public function has(string $cap): bool { return in_array(strtoupper($cap), $this->caps, true); }

    public function logout(): void
    {
        if ($this->s) { try { $this->cmd('LOGOUT'); } catch (Throwable $e) {} @fclose($this->s); }
        $this->s = null;
    }

    public function __destruct() { if ($this->s) @fclose($this->s); }

    /** Sélectionne un dossier. Retour : ['uidvalidity' => int, 'uidnext' => int, 'exists' => int]. */
    public function select(string $folder): array
    {
        $r = $this->cmd('SELECT '.self::q(self::utf7($folder)));
        if (!$r['ok']) throw new MailboxError('Dossier « '.$folder.' » introuvable.');
        $out = ['uidvalidity' => 0, 'uidnext' => 0, 'exists' => 0];
        foreach ($r['lines'] as $l) {
            if (preg_match('/UIDVALIDITY (\d+)/i', $l, $m)) $out['uidvalidity'] = (int)$m[1];
            if (preg_match('/UIDNEXT (\d+)/i', $l, $m)) $out['uidnext'] = (int)$m[1];
            if (preg_match('/^\* (\d+) EXISTS/i', $l, $m)) $out['exists'] = (int)$m[1];
        }
        return $out;
    }

    /** UID des messages, selon un critère IMAP (« UID 120:* », « SINCE 09-Nov-2026 »…). */
    public function search(string $criteria): array
    {
        $r = $this->cmd('UID SEARCH '.$criteria);
        $uids = [];
        foreach ($r['lines'] as $l) {
            if (preg_match('/^\* SEARCH ?(.*)$/i', $l, $m)) foreach (preg_split('/\s+/', trim($m[1])) as $u) if ($u !== '' && ctype_digit($u)) $uids[] = (int)$u;
        }
        sort($uids);
        return $uids;
    }

    /** Message brut (sans le marquer comme lu), tronqué à $max octets. */
    public function fetchRaw(int $uid, int $max = 1_500_000): string
    {
        $r = $this->cmd('UID FETCH '.$uid.' (BODY.PEEK[]<0.'.$max.'>)');
        return $r['literals'][0] ?? '';
    }

    /** Liste des dossiers : [nom => attributs]. */
    public function folders(): array
    {
        if ($this->listCache !== null) return $this->listCache;
        $out = [];
        $r = $this->cmd('LIST "" "*"');
        foreach ($r['lines'] as $l) {
            if (!preg_match('/^\* LIST \(([^)]*)\) (?:"(?:[^"\\\\]|\\\\.)*"|NIL) (.*)$/i', $l, $m)) continue;
            $name = trim($m[2]);
            if (preg_match('/^\{(\d+)\}$/', $name, $mm)) $name = $r['literals'][(int)$mm[1]] ?? '';
            elseif (str_starts_with($name, '"')) $name = stripcslashes(substr($name, 1, -1));
            $out[self::utf8($name)] = strtolower($m[1]);
        }
        return $this->listCache = $out;
    }

    /** Dossier spécial (\Drafts, \Sent, \Trash, \Junk) ou, à défaut, un nom courant. */
    public function special(string $use, array $fallbacks = []): ?string
    {
        foreach ($this->folders() as $name => $attrs) if (str_contains($attrs, '\\'.strtolower($use))) return $name;
        $names = array_keys($this->folders());
        foreach ($fallbacks as $f) foreach ($names as $n) if (strcasecmp($n, $f) === 0 || strcasecmp($n, $this->prefix.$f) === 0) return $n;
        return null;
    }

    /** Nom complet d'un sous-dossier (« EMAE/Devis », « INBOX.EMAE.Devis »…), créé s'il manque. */
    public function ensureFolder(string $parent, string $child): string
    {
        $full = $this->prefix.($parent !== '' ? $parent.$this->delim : '').$child;
        if (!array_key_exists($full, $this->folders())) {
            if ($parent !== '' && !array_key_exists($this->prefix.$parent, $this->folders())) {
                $this->cmd('CREATE '.self::q(self::utf7($this->prefix.$parent)));
                $this->cmd('SUBSCRIBE '.self::q(self::utf7($this->prefix.$parent)));
            }
            $r = $this->cmd('CREATE '.self::q(self::utf7($full)));
            if (!$r['ok'] && !preg_match('/ALREADYEXISTS|exist/i', $r['status'])) throw new MailboxError('Impossible de créer le dossier « '.$full.' ».');
            $this->cmd('SUBSCRIBE '.self::q(self::utf7($full)));
            $this->listCache = null;
        }
        return $full;
    }

    /** Range un message : déplacé (il quitte la boîte de réception) ou copié (il y reste). */
    public function file(int $uid, string $folder, bool $move): bool
    {
        $f = self::q(self::utf7($folder));
        if ($move && $this->has('MOVE')) return $this->cmd('UID MOVE '.$uid.' '.$f)['ok'];
        $ok = $this->cmd('UID COPY '.$uid.' '.$f)['ok'];
        if ($ok && $move) {
            $this->cmd('UID STORE '.$uid.' +FLAGS.SILENT (\\Deleted)');
            $this->cmd($this->has('UIDPLUS') ? 'UID EXPUNGE '.$uid : 'EXPUNGE');
        }
        return $ok;
    }

    /** Dépose un message (brouillon, copie d'envoi) dans un dossier. */
    public function append(string $folder, string $raw, string $flags = '\\Seen'): bool
    {
        $raw = preg_replace("/\r?\n/", "\r\n", $raw) ?? $raw;
        $n = strlen($raw);
        $plus = $this->has('LITERAL+');
        $this->send($this->nextTag().' APPEND '.self::q(self::utf7($folder)).' ('.$flags.') {'.$n.($plus ? '+' : '').'}');
        if (!$plus) {
            $l = $this->line();
            if (!str_starts_with($l, '+')) { $this->readUntilTag(); return false; }
        }
        fwrite($this->s, $raw."\r\n");
        return $this->readUntilTag()['ok'];
    }

    /** Supprime les messages d'un dossier qui portent un en-tête donné (brouillon envoyé). */
    public function deleteByHeader(string $folder, string $header, string $value): int
    {
        $this->select($folder);
        $uids = $this->search('HEADER '.$header.' '.self::q($value));
        foreach ($uids as $u) $this->cmd('UID STORE '.$u.' +FLAGS.SILENT (\\Deleted)');
        if ($uids) $this->cmd($this->has('UIDPLUS') ? 'UID EXPUNGE '.implode(',', $uids) : 'EXPUNGE');
        return count($uids);
    }

    /* ── protocole ── */
    private function nextTag(): string { return 'E'.(++$this->tag); }

    private function send(string $line): void
    {
        if (!$this->s) throw new MailboxError('Connexion au serveur de mail fermée.');
        fwrite($this->s, $line."\r\n");
    }

    private function line(): string
    {
        $l = fgets($this->s);
        if ($l === false) throw new MailboxError('Le serveur de mail ne répond plus.');
        return rtrim($l, "\r\n");
    }

    /** Envoie une commande et lit la réponse complète (lignes + blocs littéraux). */
    public function cmd(string $command): array
    {
        $this->send($this->nextTag().' '.$command);
        return $this->readUntilTag();
    }

    private function readUntilTag(): array
    {
        $tag = 'E'.$this->tag;
        $lines = []; $lits = [];
        while (true) {
            $l = $this->line();
            // Bloc littéral {n} : n octets exacts suivent, puis la fin de la ligne.
            while (preg_match('/\{(\d+)\}$/', $l, $m)) {
                $n = (int)$m[1]; $buf = '';
                while (strlen($buf) < $n) {
                    $chunk = fread($this->s, min(65536, $n - strlen($buf)));
                    if ($chunk === false || $chunk === '') throw new MailboxError('Lecture du message interrompue.');
                    $buf .= $chunk;
                }
                $lits[] = $buf;
                $l = substr($l, 0, -strlen($m[0])).'{'.(count($lits) - 1).'}'.$this->line();
            }
            if (str_starts_with($l, $tag.' ')) {
                $status = substr($l, strlen($tag) + 1);
                return ['ok' => str_starts_with(strtoupper($status), 'OK'), 'status' => $status, 'lines' => $lines, 'literals' => $lits];
            }
            $lines[] = $l;
        }
    }

    public static function q(string $s): string { return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $s).'"'; }
    public static function utf7(string $s): string { return preg_match('/[^\x20-\x7e]/', $s) ? mb_convert_encoding($s, 'UTF7-IMAP', 'UTF-8') : $s; }
    public static function utf8(string $s): string { return str_contains($s, '&') ? (string)@mb_convert_encoding($s, 'UTF-8', 'UTF7-IMAP') : $s; }
}

/* ══════════════════════════════ SMTP ══════════════════════════════ */

final class SmtpLite
{
    /** @var resource|null */
    private $s = null;

    public function __construct(private string $host, private int $port = 465, private bool $verify = true, private int $timeout = 20) {}

    /** Envoie un message brut. $to : liste d'adresses. */
    public function send(string $user, string $pass, string $from, array $to, string $raw): void
    {
        $ctx = stream_context_create(['ssl' => ['verify_peer' => $this->verify, 'verify_peer_name' => $this->verify, 'peer_name' => $this->host]]);
        // 587 (et 25) : connexion en clair puis STARTTLS ; tout autre port (465…) : chiffré d'emblée.
        $implicit = !in_array($this->port, [25, 587, 2525], true);
        $errno = 0; $errstr = '';
        $s = @stream_socket_client(($implicit ? 'ssl://' : 'tcp://').$this->host.':'.$this->port, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT, $ctx);
        if (!$s) throw new MailboxError('Connexion à '.$this->host.':'.$this->port.' impossible ('.($errstr ?: 'délai dépassé').').');
        stream_set_timeout($s, $this->timeout);
        $this->s = $s;
        try {
            $this->expect(220);
            $ehlo = 'EHLO '.(gethostname() ?: 'emaee.fr');
            $this->c($ehlo, 250);
            if (!$implicit) {
                $this->c('STARTTLS', 220);
                if (!@stream_socket_enable_crypto($s, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new MailboxError('Chiffrement refusé par '.$this->host.'.');
                $this->c($ehlo, 250);
            }
            $this->c('AUTH LOGIN', 334);
            $this->c(base64_encode($user), 334);
            $r = $this->c(base64_encode($pass), null);
            if ((int)substr($r, 0, 3) !== 235) throw new MailboxError('Identifiant ou mot de passe refusé par '.$this->host.' pour l\'envoi.');
            $this->c('MAIL FROM:<'.$from.'>', 250);
            foreach ($to as $addr) $this->c('RCPT TO:<'.$addr.'>', [250, 251]);
            $this->c('DATA', 354);
            $body = preg_replace("/\r?\n/", "\r\n", $raw) ?? $raw;
            $body = preg_replace('/^\./m', '..', $body) ?? $body;   // un point en début de ligne est doublé
            fwrite($s, $body."\r\n.\r\n");
            $this->expect(250);
            $this->c('QUIT', null);
        } finally {
            @fclose($s);
            $this->s = null;
        }
    }

    private function c(string $line, int|array|null $want): string
    {
        fwrite($this->s, $line."\r\n");
        return $this->expect($want);
    }

    private function expect(int|array|null $want): string
    {
        $resp = '';
        while (true) {
            $l = fgets($this->s);
            if ($l === false) throw new MailboxError('Le serveur d\'envoi ne répond plus.');
            $resp .= $l;
            if (strlen($l) < 4 || $l[3] !== '-') break;
        }
        if ($want !== null && !in_array((int)substr($resp, 0, 3), (array)$want, true)) {
            throw new MailboxError('Envoi refusé par le serveur : '.mb_substr(trim($resp), 0, 160));
        }
        return $resp;
    }
}

/* ══════════════════════════════ MIME ══════════════════════════════ */

/** Décode un en-tête (« =?UTF-8?B?…?= »…) en UTF-8. */
function mime_header_decode(string $v): string
{
    $v = trim($v);
    if ($v === '') return '';
    if (str_contains($v, '=?')) {
        $d = @iconv_mime_decode($v, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
        if ($d === false || $d === '') $d = mb_decode_mimeheader($v);
        $v = $d;
    } elseif (!mb_check_encoding($v, 'UTF-8')) {
        $v = mb_convert_encoding($v, 'UTF-8', 'ISO-8859-1');
    }
    return mb_scrub($v, 'UTF-8');
}

/** En-têtes bruts → [nom en minuscules => valeur décodée] (le premier gagne, sauf received). */
function mime_headers(string $block): array
{
    $block = preg_replace("/\r?\n[ \t]+/", ' ', $block) ?? $block;   // lignes repliées
    $out = [];
    foreach (preg_split("/\r?\n/", $block) as $line) {
        $p = strpos($line, ':');
        if ($p === false) continue;
        $k = strtolower(trim(substr($line, 0, $p)));
        if ($k === '' || isset($out[$k])) continue;
        $out[$k] = trim(substr($line, $p + 1));
    }
    return $out;
}

/** « text/plain; charset="utf-8"; name*=UTF-8''fa%C3%A7ade.pdf » → ['value' => …, 'params' => […]]. */
function mime_header_params(string $v): array
{
    $parts = preg_split('/;(?=(?:[^"]*"[^"]*")*[^"]*$)/', $v);
    $out = ['value' => strtolower(trim((string)array_shift($parts))), 'params' => []];
    foreach ($parts as $p) {
        $eq = strpos($p, '=');
        if ($eq === false) continue;
        $k = strtolower(trim(substr($p, 0, $eq)));
        $val = trim(substr($p, $eq + 1));
        if (str_starts_with($val, '"')) $val = stripcslashes(substr($val, 1, -1));
        if (preg_match('/^(.+)\*(\d+)?\*?$/', $k, $m)) {      // RFC 2231
            $k = $m[1];
            if (preg_match("/^([^']*)'[^']*'(.*)$/", $val, $mm)) $val = @mb_convert_encoding(rawurldecode($mm[2]), 'UTF-8', $mm[1] ?: 'UTF-8') ?: rawurldecode($mm[2]);
            else $val = rawurldecode($val);
            $out['params'][$k] = ($out['params'][$k] ?? '').$val;
            continue;
        }
        $out['params'][$k] = mime_header_decode($val);
    }
    return $out;
}

function mime_to_utf8(string $s, string $charset): string
{
    $charset = strtolower(trim($charset));
    if ($charset === '' || $charset === 'utf-8' || $charset === 'us-ascii') return mb_scrub($s, 'UTF-8');
    if (in_array($charset, ['windows-1252', 'cp1252', 'iso-8859-1', 'latin1'], true)) $charset = 'Windows-1252';
    try { $r = @mb_convert_encoding($s, 'UTF-8', $charset); if (is_string($r)) return $r; } catch (Throwable $e) {}
    $r = @iconv($charset, 'UTF-8//IGNORE', $s);
    return is_string($r) ? $r : mb_scrub($s, 'UTF-8');
}

/** HTML → texte lisible (liens et retours à la ligne conservés). */
function mime_html_to_text(string $html): string
{
    $html = preg_replace('#<(script|style|head)\b.*?</\1>#is', '', $html) ?? $html;
    $html = preg_replace('#<br\s*/?>|</(p|div|tr|li|h[1-6]|table)>#i', "\n", $html) ?? $html;
    $t = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = preg_replace("/[ \t\x{00A0}]+/u", ' ', $t) ?? $t;
    return trim(preg_replace("/\n\s*\n\s*\n+/", "\n\n", $t) ?? $t);
}

/**
 * Message brut → ['headers' => […], 'from_email', 'from_name', 'subject', 'date', 'message_id',
 *   'in_reply_to', 'references', 'text', 'attachments' => [noms]].
 * Tolère un message tronqué (pièces jointes coupées).
 */
function mime_parse(string $raw): array
{
    $msg = ['text' => '', 'html' => '', 'attachments' => []];
    mime_walk($raw, $msg, 0);
    $sep = preg_split("/\r?\n\r?\n/", $raw, 2);
    $h = mime_headers($sep[0] ?? '');
    [$fromName, $fromEmail] = mime_address(mime_header_decode($h['from'] ?? ''));
    [, $replyTo] = mime_address(mime_header_decode($h['reply-to'] ?? ''));
    $text = trim($msg['text']) !== '' ? $msg['text'] : mime_html_to_text($msg['html']);
    return [
        'headers'     => $h,
        'from_name'   => $fromName,
        'from_email'  => $fromEmail,
        'reply_to'    => $replyTo,
        'to'          => mime_header_decode($h['to'] ?? ''),
        'subject'     => mime_header_decode($h['subject'] ?? ''),
        'date'        => (($t = strtotime((string)($h['date'] ?? ''))) !== false) ? date('Y-m-d H:i:s', $t) : date('Y-m-d H:i:s'),
        'message_id'  => trim((string)($h['message-id'] ?? ''), " \t<>"),
        'in_reply_to' => trim((string)($h['in-reply-to'] ?? '')),
        'references'  => trim((string)($h['references'] ?? '')),
        'text'        => trim(str_replace("\r", '', $text)),
        'attachments' => $msg['attachments'],
    ];
}

function mime_walk(string $part, array &$msg, int $depth): void
{
    if ($depth > 8) return;
    $sep = preg_split("/\r?\n\r?\n/", $part, 2);
    $h = mime_headers($sep[0] ?? '');
    $body = $sep[1] ?? '';
    $ct = mime_header_params($h['content-type'] ?? 'text/plain');
    $disp = mime_header_params($h['content-disposition'] ?? '');
    $name = $disp['params']['filename'] ?? ($ct['params']['name'] ?? '');

    if (str_starts_with($ct['value'], 'multipart/')) {
        $b = $ct['params']['boundary'] ?? '';
        if ($b === '') return;
        $chunks = explode('--'.$b, $body);
        array_shift($chunks);                                   // avant la première frontière
        foreach ($chunks as $c) {
            if (str_starts_with($c, '--')) break;               // frontière de fin
            mime_walk(ltrim($c, "\r\n"), $msg, $depth + 1);
        }
        return;
    }
    if ($ct['value'] === 'message/rfc822') { $msg['attachments'][] = $name !== '' ? $name : 'message joint'; return; }

    if ($name !== '' || $disp['value'] === 'attachment' || !str_starts_with($ct['value'], 'text/')) {
        $msg['attachments'][] = $name !== '' ? $name : $ct['value'];
        return;
    }
    $enc = strtolower(trim((string)($h['content-transfer-encoding'] ?? '')));
    if ($enc === 'base64') $body = (string)base64_decode(preg_replace('/[^A-Za-z0-9+\/=]/', '', $body) ?? '', false);
    elseif ($enc === 'quoted-printable') $body = quoted_printable_decode($body);
    $body = mime_to_utf8($body, (string)($ct['params']['charset'] ?? 'utf-8'));
    if ($ct['value'] === 'text/html') { if ($msg['html'] === '') $msg['html'] = $body; }
    elseif ($msg['text'] === '') $msg['text'] = $body;
}

/** « "Jean Dupont" <jean@x.fr> » → ['Jean Dupont', 'jean@x.fr']. */
function mime_address(string $v): array
{
    if (preg_match('/^\s*"?([^"<]*?)"?\s*<([^>]+)>/', $v, $m)) return [trim($m[1]), strtolower(trim($m[2]))];
    if (preg_match('/[A-Z0-9._%+\'-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $v, $m)) return ['', strtolower($m[0])];
    return ['', ''];
}

/** Retire l'historique cité d'une réponse (« Le … a écrit : », lignes « > »…). */
function mime_strip_quoted(string $text): string
{
    $lines = explode("\n", str_replace("\r", '', $text));
    $out = [];
    foreach ($lines as $l) {
        if (preg_match('/^\s*(Le .{4,120} a écrit\s*:|On .{4,120} wrote\s*:|-{2,}\s*(Original Message|Message d\'origine|Message transféré)|De\s*:\s.+@|From\s*:\s.+@|Envoyé de mon (iPhone|Android))/iu', $l)) break;
        if (str_starts_with(ltrim($l), '>')) continue;
        $out[] = $l;
    }
    return trim(implode("\n", $out));
}

/** Message texte simple en UTF-8. $h : from, from_name, to, subject, in_reply_to, references, extra (en-têtes). */
function mime_build(array $h, string $body): string
{
    $enc = static fn(string $s): string => preg_match('/[^\x20-\x7e]/', $s) ? mb_encode_mimeheader($s, 'UTF-8', 'B', "\r\n") : $s;
    $domain = substr((string)strrchr((string)$h['from'], '@'), 1) ?: 'emaee.fr';
    $lines = [
        'Date: '.date('r'),
        'From: '.(($h['from_name'] ?? '') !== '' ? $enc((string)$h['from_name']).' <'.$h['from'].'>' : $h['from']),
        'To: '.$h['to'],
        'Subject: '.$enc((string)$h['subject']),
        'Message-ID: <'.bin2hex(random_bytes(12)).'@'.$domain.'>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: quoted-printable',
    ];
    if (!empty($h['in_reply_to'])) $lines[] = 'In-Reply-To: '.$h['in_reply_to'];
    if (!empty($h['references'])) $lines[] = 'References: '.$h['references'];
    foreach ((array)($h['extra'] ?? []) as $k => $v) $lines[] = $k.': '.$v;
    return implode("\r\n", $lines)."\r\n\r\n".quoted_printable_encode(str_replace("\r", '', $body));
}
