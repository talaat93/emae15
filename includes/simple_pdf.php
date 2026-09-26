<?php
declare(strict_types=1);
/**
 * Générateur PDF minimal, sans dépendance (le dossier vendor/ n'est pas déployé).
 * Pages A4, polices standard Helvetica / Helvetica-Bold (encodage WinAnsi : accents et €),
 * texte, traits et rectangles. Coordonnées en points, origine en haut à gauche.
 * Suffisant pour un devis à signer électroniquement.
 */
final class SimplePdf
{
    public const W = 595.28;
    public const H = 841.89;
    private array $pages = [];
    private string $cur = '';

    /** Largeurs Helvetica (1/1000 em) des caractères courants ; 556 par défaut. */
    private const WIDTHS = [' ' => 278, ',' => 278, '.' => 278, ':' => 278, '-' => 333, '/' => 278, '(' => 333, ')' => 333, '%' => 889,
        'i' => 222, 'l' => 222, 'j' => 222, 't' => 278, 'f' => 278, 'r' => 333, 'I' => 278, 'm' => 833, 'w' => 722, 'M' => 833, 'W' => 944,
        "\xA0" => 278, "\x80" => 556];

    public function addPage(): void
    {
        if ($this->cur !== '') $this->pages[] = $this->cur;
        $this->cur = "0.2 w\n";
    }

    private static function enc(string $s): string
    {
        $s = str_replace(["\u{202F}", "\u{2019}", "\u{2013}", "\u{2014}"], ["\u{00A0}", "'", '-', '-'], $s);
        $w = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $s);
        return $w === false ? preg_replace('/[^\x20-\x7E]/', '?', $s) : $w;
    }

    public static function width(string $utf8, float $size, bool $bold = false): float
    {
        $w = 0;
        foreach (str_split(self::enc($utf8)) as $ch) $w += self::WIDTHS[$ch] ?? (ctype_upper($ch) ? 667 : 556);
        return $w * $size / 1000 * ($bold ? 1.05 : 1);
    }

    public function text(float $x, float $y, string $s, float $size = 10, bool $bold = false, array $rgb = [0, 0, 0], string $align = 'L'): void
    {
        if ($align === 'R') $x -= self::width($s, $size, $bold);
        $t = str_replace(['\\', '(', ')', "\r"], ['\\\\', '\\(', '\\)', ''], self::enc($s));
        $this->cur .= sprintf("%.3f %.3f %.3f rg BT /%s %.2f Tf %.2f %.2f Td (%s) Tj ET\n",
            $rgb[0], $rgb[1], $rgb[2], $bold ? 'F2' : 'F1', $size, $x, self::H - $y, $t);
    }

    /** Texte sur plusieurs lignes dans une largeur donnée ; renvoie la position y suivante. */
    public function paragraph(float $x, float $y, float $width, string $s, float $size = 10, float $lead = 1.35, array $rgb = [0, 0, 0]): float
    {
        foreach (preg_split("/\r?\n/", $s) as $para) {
            $line = '';
            foreach (preg_split('/\s+/', trim($para)) as $word) {
                $try = $line === '' ? $word : $line.' '.$word;
                if ($line !== '' && self::width($try, $size) > $width) {
                    $this->text($x, $y, $line, $size, false, $rgb);
                    $y += $size * $lead;
                    $line = $word;
                } else {
                    $line = $try;
                }
            }
            $this->text($x, $y, $line, $size, false, $rgb);
            $y += $size * $lead;
        }
        return $y;
    }

    public function line(float $x1, float $y1, float $x2, float $y2, array $rgb = [0.8, 0.82, 0.86]): void
    {
        $this->cur .= sprintf("%.3f %.3f %.3f RG %.2f %.2f m %.2f %.2f l S\n", $rgb[0], $rgb[1], $rgb[2], $x1, self::H - $y1, $x2, self::H - $y2);
    }

    public function rect(float $x, float $y, float $w, float $h, ?array $fill = null, ?array $stroke = null): void
    {
        $op = $fill && $stroke ? 'B' : ($fill ? 'f' : 'S');
        $c = '';
        if ($fill) $c .= sprintf('%.3f %.3f %.3f rg ', $fill[0], $fill[1], $fill[2]);
        if ($stroke) $c .= sprintf('%.3f %.3f %.3f RG ', $stroke[0], $stroke[1], $stroke[2]);
        $this->cur .= $c.sprintf("%.2f %.2f %.2f %.2f re %s\n", $x, self::H - $y - $h, $w, $h, $op);
    }

    public function output(): string
    {
        if ($this->cur !== '') { $this->pages[] = $this->cur; $this->cur = ''; }
        $objs = [];
        $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objs[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objs[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $kids = [];
        $n = 5;
        foreach ($this->pages as $content) {
            $pageId = $n++; $contId = $n++;
            $kids[] = $pageId.' 0 R';
            $objs[$pageId] = sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2f %.2f] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>', self::W, self::H, $contId);
            $objs[$contId] = '<< /Length '.strlen($content)." >>\nstream\n".$content."endstream";
        }
        $objs[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($kids).' >>';
        ksort($objs);
        $out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objs as $id => $body) { $offsets[$id] = strlen($out); $out .= $id." 0 obj\n".$body."\nendobj\n"; }
        $xref = strlen($out);
        $max = max(array_keys($objs));
        $out .= "xref\n0 ".($max + 1)."\n0000000000 65535 f \n";
        for ($i = 1; $i <= $max; $i++) $out .= isset($offsets[$i]) ? sprintf("%010d 00000 n \n", $offsets[$i]) : "0000000000 65535 f \n";
        $out .= "trailer\n<< /Size ".($max + 1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
        return $out;
    }
}
