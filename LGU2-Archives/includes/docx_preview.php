<?php
/**
 * DOCX text-only preview support.
 *
 * A .docx file is a ZIP archive whose readable body lives in
 * word/document.xml. This module extracts that text without relying on
 * ZipArchive (the php_zip extension is often disabled in shared/XAMPP
 * setups) by reading the ZIP central directory directly and inflating the
 * single entry with the built-in gzinflate().
 */

if (!function_exists('docx_zip_get')) {

    /**
     * Read a single uncompressed entry from a ZIP archive using pure PHP.
     *
     * @param string $file  Path to the archive.
     * @param string $entry Entry name, e.g. "word/document.xml".
     * @return string|null  Raw entry contents, or null when not found/unreadable.
     */
    function docx_zip_get(string $file, string $entry): ?string {
        $size = @filesize($file);
        if ($size === false || $size <= 0 || $size > 200 * 1024 * 1024) {
            return null;
        }

        $fh = @fopen($file, 'rb');
        if (!$fh) {
            return null;
        }

        // Locate the End Of Central Directory record (searched in the last 64KB + 22B).
        $tailLen = (int)min($size, 65557);
        fseek($fh, $size - $tailLen);
        $tail = fread($fh, $tailLen);
        $eocdPos = strrpos($tail, "PK\x05\x06");
        if ($eocdPos === false) {
            fclose($fh);
            return null;
        }

        $totalEntries = unpack('v', substr($tail, $eocdPos + 10, 2))[1];
        $cdSize       = unpack('V', substr($tail, $eocdPos + 12, 4))[1];
        $cdOffset     = unpack('V', substr($tail, $eocdPos + 16, 4))[1];
        if ($totalEntries <= 0 || $cdSize <= 0 || $cdSize > 50 * 1024 * 1024) {
            fclose($fh);
            return null;
        }

        // Read the whole central directory.
        fseek($fh, $cdOffset);
        $cd = fread($fh, $cdSize);
        fclose($fh);

        $pos = 0;
        $len = strlen($cd);
        while ($pos + 46 <= $len) {
            if (substr($cd, $pos, 4) !== "PK\x01\x02") {
                break;
            }
            $compMethod   = unpack('v', substr($cd, $pos + 10, 2))[1];
            $compSize     = unpack('V', substr($cd, $pos + 20, 4))[1];
            $localOffset  = unpack('V', substr($cd, $pos + 42, 4))[1];
            $nameLen      = unpack('v', substr($cd, $pos + 28, 2))[1];
            $extraLen     = unpack('v', substr($cd, $pos + 30, 2))[1];
            $commentLen   = unpack('v', substr($cd, $pos + 32, 2))[1];
            $name         = substr($cd, $pos + 46, $nameLen);

            if ($name === $entry) {
                $data = docx_read_local_entry($file, $localOffset, $compSize, $compMethod);
                return $data;
            }

            $pos += 46 + $nameLen + $extraLen + $commentLen;
        }

        return null;
    }

    /**
     * Read the local file header + compressed data for a single ZIP entry.
     */
    function docx_read_local_entry(string $file, int $localOffset, int $compSize, int $compMethod): ?string {
        $fh = @fopen($file, 'rb');
        if (!$fh) {
            return null;
        }
        fseek($fh, $localOffset);
        $lh = fread($fh, 30);
        if (strlen($lh) < 30 || substr($lh, 0, 4) !== "PK\x03\x04") {
            fclose($fh);
            return null;
        }
        $lhNameLen  = unpack('v', substr($lh, 26, 2))[1];
        $lhExtraLen = unpack('v', substr($lh, 28, 2))[1];
        fseek($fh, $localOffset + 30 + $lhNameLen + $lhExtraLen);
        $data = fread($fh, $compSize);
        fclose($fh);

        if ($compMethod === 0) {
            return $data !== false ? $data : null;
        }
        if ($compMethod === 8) {
            $out = @gzinflate($data);
            return ($out === false) ? null : $out;
        }
        return null;
    }
}

if (!function_exists('docx_extract_text')) {

    /**
     * Extract readable body text from a .docx file.
     *
     * @param string $file Absolute path to the .docx.
     * @return string|null Extracted text, or null on any failure.
     */
    function docx_extract_text(string $file): ?string {
        $xml = docx_zip_get($file, 'word/document.xml');
        if ($xml === null || $xml === '') {
            return null;
        }
        return docx_parse_text_xml($xml);
    }

    /**
     * Convert raw word/document.xml into plain text.
     */
    function docx_parse_text_xml(string $xml): string {
        // Preserve paragraph, tab and line breaks as plain-text equivalents.
        $xml = str_replace('</w:p>', "\n", $xml);
        $xml = str_replace(['<w:tab/>', '<w:tab />'], "\t", $xml);
        $xml = str_replace(['<w:br/>', '<w:br />', '<w:cr/>', '<w:cr />'], "\n", $xml);

        // Drop every remaining tag.
        $text = preg_replace('/<[^>]*>/', '', $xml);
        if ($text === null) {
            return '';
        }

        // Decode XML entities (e.g. &amp;, &#8217;).
        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');

        // Normalize whitespace-only lines and collapse repeated blank lines.
        $lines = preg_split('/\R/u', $text);
        if ($lines === false) {
            return trim($text);
        }
        $out = [];
        foreach ($lines as $ln) {
            $ln = trim($ln);
            if ($ln === '' && (count($out) === 0 || end($out) === '')) {
                continue;
            }
            $out[] = $ln;
        }
        // Remove trailing blank lines.
        while (count($out) > 0 && end($out) === '') {
            array_pop($out);
        }
        return implode("\n", $out);
    }
}

if (!function_exists('docx_resolve_file')) {

    /**
     * Resolve a DB/web-relative path (e.g. "uploads/legislative/.../x.docx")
     * to an absolute .docx path that lives inside the project, safely.
     *
     * @param string $rel Raw path as stored/requested.
     * @return string|null Absolute path, or null when invalid.
     */
    function docx_resolve_file(string $rel): ?string {
        if ($rel === '') {
            return null;
        }
        // Strip any query string, decode percent-encoding.
        if (strpos($rel, '?') !== false) {
            $rel = substr($rel, 0, strpos($rel, '?'));
        }
        $rel = rawurldecode(trim($rel));

        // Reject traversal, absolute web paths and null bytes outright.
        if ($rel === '' || strpos($rel, "\0") !== false || strpos($rel, '..') !== false) {
            return null;
        }
        // Normalize backslashes so Windows paths work too.
        $rel = str_replace('\\', '/', $rel);
        if ($rel[0] === '/') {
            return null;
        }

        $base = __DIR__ . DIRECTORY_SEPARATOR . '..';
        $abs  = realpath($base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel));
        if ($abs === false || !is_file($abs)) {
            return null;
        }
        if (stripos($abs . DIRECTORY_SEPARATOR, realpath($base) . DIRECTORY_SEPARATOR) !== 0) {
            return null;
        }
        if (strtolower(pathinfo($abs, PATHINFO_EXTENSION)) !== 'docx') {
            return null;
        }
        return $abs;
    }
}
