<?php
declare(strict_types=1);

/**
 * Generador de PDF mínimo, sin dependencias externas.
 * Soporta A4, Helvetica / Helvetica-Bold (WinAnsi, con acentos), texto,
 * líneas, rectángulos (con esquinas redondeadas) y varias páginas.
 *
 * Pensado para presupuestos / resúmenes de pedido.
 */
final class SimplePdf
{
    public float $pageW;
    public float $pageH;
    public float $margin = 42.0;

    /** @var string[] contenido de cada página cerrada */
    private array $pages = [];
    /** @var string contenido de la página en curso */
    private string $buf = '';
    private string $font = 'F1';       // F1 = regular, F2 = bold
    private float $size = 10.0;
    /** @var int[] color de relleno actual (0-255) */
    private array $fill = [0, 0, 0];

    /* Anchos Helvetica (unidades /1000) para ASCII 32..126 */
    private const HELV = [
        278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,
        556,556,556,556,556,556,556,556,556,556,
        278,278,584,584,584,556,1015,
        667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,667,778,722,667,611,722,667,944,667,667,611,
        278,278,278,469,556,333,
        556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,556,556,333,500,278,556,500,722,500,500,500,
        334,260,334,584,
    ];
    private const HELV_B = [
        278,333,474,556,556,889,722,238,333,333,389,584,278,333,278,278,
        556,556,556,556,556,556,556,556,556,556,
        333,333,584,584,584,611,975,
        722,722,722,722,667,611,778,722,278,556,722,611,833,722,778,667,778,722,667,611,722,667,944,667,667,611,
        333,278,333,584,556,333,
        556,611,556,611,556,333,611,611,278,278,556,278,889,611,611,611,611,389,556,333,611,556,778,556,556,500,
        389,280,389,584,
    ];
    /** Byte WinAnsi -> letra base ASCII (para calcular ancho de acentos y signos) */
    private const ANSI_BASE = [
        0x91 => "'", 0x92 => "'", 0x93 => '"', 0x94 => '"',
        0x95 => '.', 0x96 => '-', 0x97 => '-', 0xB7 => '.',
        0xC0=>'A',0xC1=>'A',0xC2=>'A',0xC3=>'A',0xC4=>'A',0xC5=>'A',0xC7=>'C',
        0xC8=>'E',0xC9=>'E',0xCA=>'E',0xCB=>'E',0xCC=>'I',0xCD=>'I',0xCE=>'I',0xCF=>'I',
        0xD1=>'N',0xD2=>'O',0xD3=>'O',0xD4=>'O',0xD5=>'O',0xD6=>'O',0xD8=>'O',
        0xD9=>'U',0xDA=>'U',0xDB=>'U',0xDC=>'U',0xDD=>'Y',
        0xE0=>'a',0xE1=>'a',0xE2=>'a',0xE3=>'a',0xE4=>'a',0xE5=>'a',0xE7=>'c',
        0xE8=>'e',0xE9=>'e',0xEA=>'e',0xEB=>'e',0xEC=>'i',0xED=>'i',0xEE=>'i',0xEF=>'i',
        0xF1=>'n',0xF2=>'o',0xF3=>'o',0xF4=>'o',0xF5=>'o',0xF6=>'o',0xF8=>'o',
        0xF9=>'u',0xFA=>'u',0xFB=>'u',0xFC=>'u',0xFD=>'y',0xFF=>'y',
    ];

    public function __construct(float $w = 595.28, float $h = 841.89)
    {
        $this->pageW = $w;
        $this->pageH = $h;
    }

    /* ── Páginas ──────────────────────────────────────────────────────── */

    public function addPage(): void
    {
        if ($this->buf !== '') {
            $this->pages[] = $this->buf;
        }
        $this->buf = '';
        $this->fill = [0, 0, 0];
    }

    public function pageCount(): int
    {
        return count($this->pages) + ($this->buf !== '' ? 1 : 0);
    }

    /* ── Tipografía ───────────────────────────────────────────────────── */

    public function setFont(string $which, float $size): void
    {
        $this->font = $which === 'b' || $which === 'bold' ? 'F2' : 'F1';
        $this->size = $size;
    }

    public function setFill(int $r, int $g, int $b): void
    {
        $this->fill = [max(0, min(255, $r)), max(0, min(255, $g)), max(0, min(255, $b))];
    }

    /** Ancho en puntos del texto con la fuente/tamaño actuales. */
    public function width(string $s): float
    {
        $tbl = $this->font === 'F2' ? self::HELV_B : self::HELV;
        $bytes = $this->toWinAnsi($s);
        $total = 0.0;
        $len = strlen($bytes);
        for ($i = 0; $i < $len; $i++) {
            $c = ord($bytes[$i]);
            if ($c >= 32 && $c <= 126) {
                $total += $tbl[$c - 32];
            } else {
                $base = self::ANSI_BASE[$c] ?? '';
                $total += $base !== '' ? $tbl[ord($base) - 32] : 556;
            }
        }
        return $total * $this->size / 1000.0;
    }

    /**
     * Parte el texto en líneas que entren en $maxW.
     * @return string[]
     */
    public function wrap(string $s, float $maxW): array
    {
        $s = trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
        if ($s === '') return [''];
        $words = explode(' ', $s);
        $lines = [];
        $cur = '';
        foreach ($words as $w) {
            $try = $cur === '' ? $w : $cur . ' ' . $w;
            if ($cur === '' || $this->width($try) <= $maxW) {
                $cur = $try;
            } else {
                $lines[] = $cur;
                $cur = $w;
            }
        }
        if ($cur !== '') $lines[] = $cur;
        return $lines;
    }

    /* ── Dibujo ───────────────────────────────────────────────────────── */

    /** Texto alineado a la izquierda; $yTop es la línea base medida desde arriba. */
    public function text(float $x, float $yTop, string $s): void
    {
        $this->buf .= $this->colorOp() . 'BT /' . $this->font . ' ' . $this->n($this->size) . ' Tf 1 0 0 1 '
            . $this->n($x) . ' ' . $this->n($this->pageH - $yTop) . ' Tm ('
            . $this->esc($this->toWinAnsi($s)) . ") Tj ET\n";
    }

    public function textRight(float $xRight, float $yTop, string $s): void
    {
        $this->text($xRight - $this->width($s), $yTop, $s);
    }

    public function textCenter(float $xCenter, float $yTop, string $s): void
    {
        $this->text($xCenter - $this->width($s) / 2, $yTop, $s);
    }

    /** Rectángulo relleno del color actual. Coordenadas desde arriba. */
    public function rect(float $x, float $yTop, float $w, float $h): void
    {
        $this->buf .= $this->colorOp() . $this->n($x) . ' ' . $this->n($this->pageH - $yTop - $h)
            . ' ' . $this->n($w) . ' ' . $this->n($h) . " re f\n";
    }

    /** Rectángulo relleno con esquinas redondeadas. */
    public function roundRect(float $x, float $yTop, float $w, float $h, float $r): void
    {
        $r = max(0.0, min($r, $w / 2, $h / 2));
        $k = 0.5523 * $r;
        $top = $this->pageH - $yTop;
        $bot = $this->pageH - $yTop - $h;
        $x0 = $x; $x1 = $x + $w;
        $p = [];
        $p[] = $this->n($x0 + $r) . ' ' . $this->n($top) . ' m';
        $p[] = $this->n($x1 - $r) . ' ' . $this->n($top) . ' l';
        $p[] = $this->n($x1 - $r + $k) . ' ' . $this->n($top) . ' ' . $this->n($x1) . ' ' . $this->n($top - $k) . ' ' . $this->n($x1) . ' ' . $this->n($top - $r) . ' c';
        $p[] = $this->n($x1) . ' ' . $this->n($bot + $r) . ' l';
        $p[] = $this->n($x1) . ' ' . $this->n($bot + $r - $k) . ' ' . $this->n($x1 - $r + $k) . ' ' . $this->n($bot) . ' ' . $this->n($x1 - $r) . ' ' . $this->n($bot) . ' c';
        $p[] = $this->n($x0 + $r) . ' ' . $this->n($bot) . ' l';
        $p[] = $this->n($x0 + $r - $k) . ' ' . $this->n($bot) . ' ' . $this->n($x0) . ' ' . $this->n($bot + $r - $k) . ' ' . $this->n($x0) . ' ' . $this->n($bot + $r) . ' c';
        $p[] = $this->n($x0) . ' ' . $this->n($top - $r) . ' l';
        $p[] = $this->n($x0) . ' ' . $this->n($top - $r + $k) . ' ' . $this->n($x0 + $r - $k) . ' ' . $this->n($top) . ' ' . $this->n($x0 + $r) . ' ' . $this->n($top) . ' c';
        $this->buf .= $this->colorOp() . implode(' ', $p) . " h f\n";
    }

    /** Línea del color actual. */
    public function line(float $x1, float $y1, float $x2, float $y2, float $lw = 0.6): void
    {
        $this->buf .= $this->strokeOp() . $this->n($lw) . ' w '
            . $this->n($x1) . ' ' . $this->n($this->pageH - $y1) . ' m '
            . $this->n($x2) . ' ' . $this->n($this->pageH - $y2) . " l S\n";
    }

    /* ── Salida ───────────────────────────────────────────────────────── */

    public function output(): string
    {
        if ($this->buf !== '') {
            $this->pages[] = $this->buf;
            $this->buf = '';
        }
        $n = max(1, count($this->pages));
        if ($n === 0) $this->pages = [''];

        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        $kids = [];
        $num = 5;
        foreach ($this->pages as $content) {
            /* Sustituye {total} ANTES de calcular longitudes y offsets,
               para no invalidar la tabla xref. */
            $content = str_replace('{total}', (string)$n, $content);
            $pageNum = $num++;
            $contNum = $num++;
            $kids[] = $pageNum . ' 0 R';
            $objects[$pageNum] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '
                . $this->n($this->pageW) . ' ' . $this->n($this->pageH)
                . '] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . $contNum . ' 0 R >>';
            $objects[$contNum] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "\nendstream";
        }
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($this->pages) . ' >>';
        ksort($objects);

        $out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objects as $i => $body) {
            $offsets[$i] = strlen($out);
            $out .= $i . " 0 obj\n" . $body . "\nendobj\n";
        }
        $xref = strlen($out);
        $max  = max(array_keys($objects));
        $out .= "xref\n0 " . ($max + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= $max; $i++) {
            $out .= str_pad((string)($offsets[$i] ?? 0), 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }
        $out .= "trailer\n<< /Size " . ($max + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";

        return $out;
    }

    /* ── Internos ─────────────────────────────────────────────────────── */

    private function colorOp(): string
    {
        return $this->n($this->fill[0] / 255) . ' ' . $this->n($this->fill[1] / 255) . ' ' . $this->n($this->fill[2] / 255) . " rg ";
    }

    private function strokeOp(): string
    {
        return $this->n($this->fill[0] / 255) . ' ' . $this->n($this->fill[1] / 255) . ' ' . $this->n($this->fill[2] / 255) . " RG ";
    }

    private function n(float $v): string
    {
        return rtrim(rtrim(number_format($v, 3, '.', ''), '0'), '.') ?: '0';
    }

    private function esc(string $s): string
    {
        return str_replace(['\\', '(', ')', "\r", "\n", "\t"], ['\\\\', '\\(', '\\)', '', ' ', ' '], $s);
    }

    /** Convierte UTF-8 a Windows-1252 (para acentos) y limpia caracteres no representables. */
    private function toWinAnsi(string $s): string
    {
        /* Solo se reemplazan símbolos que NO existen en Windows-1252; el resto
           (acentos, comillas tipográficas, – — • · … €) lo resuelve la conversión. */
        $s = str_replace(
            ['→', '↗', '⟶', '✓', '✔', '≈', '≤', '≥', '★', '☆', '✅', '❌', '✕', '⏳', '💬', '📄', '📍', '📸', '⏰', '🔹', '🔸'],
            ['->', '->', '->', 'v', 'v', '~=', '<=', '>=', '*', '*', '[OK]', '[X]', 'x', '', '', '', '', '', '', '-', '-'],
            $s
        );
        $conv = @mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
        if ($conv === false) {
            $conv = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $s);
        }
        return $conv === false ? $s : $conv;
    }
}
