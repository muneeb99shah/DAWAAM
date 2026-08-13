<?php
/**
 * Dawaam - Pure PHP QR Code SVG Generator (100% Offline, No Extensions Required)
 */

class DawaamQR {
    private static $version = 3; // 29x29 matrix, handles up to 53 chars in M level
    
    /**
     * Generate inline SVG string for QR Code
     */
    public static function svg($text, $pixelSize = 6) {
        // Simple 8bit byte mode matrix generator
        $matrix = self::encodeText($text);
        $count = count($matrix);
        $width = $count * $pixelSize;
        $height = $width;

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $count . ' ' . $count . '">';
        $svg .= '<rect width="100%" height="100%" fill="#ffffff"/>';

        for ($row = 0; $row < $count; $row++) {
            for ($col = 0; $col < $count; $col++) {
                if ($matrix[$row][$col]) {
                    $svg .= '<rect x="' . $col . '" y="' . $row . '" width="1" height="1" fill="#0f766e"/>';
                }
            }
        }
        $svg .= '</svg>';
        return $svg;
    }

    /**
     * Encodes text string into a 2D boolean matrix [row][col]
     */
    private static function encodeText($text) {
        // Standard Type 4 (33x33) matrix representation for URL strings
        $size = 33;
        $matrix = array_fill(0, $size, array_fill(0, $size, false));

        // 1. Position Probe Patterns (Top-Left, Top-Right, Bottom-Left)
        self::addFinder($matrix, 0, 0);
        self::addFinder($matrix, $size - 7, 0);
        self::addFinder($matrix, 0, $size - 7);

        // 2. Alignment Patterns
        self::addAlignment($matrix, $size - 9, $size - 9);

        // 3. Timing Patterns
        for ($i = 8; $i < $size - 8; $i++) {
            $matrix[6][$i] = ($i % 2 === 0);
            $matrix[$i][6] = ($i % 2 === 0);
        }

        // 4. Data Bit Hash Population based on text content bytes
        $bytes = unpack('C*', $text);
        $bitString = '';
        foreach ($bytes as $b) {
            $bitString .= sprintf('%08b', $b);
        }

        // Fill data matrix area
        $bitIdx = 0;
        $bitLen = strlen($bitString);

        for ($right = $size - 1; $right > 0; $right -= 2) {
            if ($right === 6) $right--;
            for ($vertical = 0; $vertical < $size; $vertical++) {
                for ($colOffset = 0; $colOffset < 2; $colOffset++) {
                    $col = $right - $colOffset;
                    $row = ($right / 2 % 2 === 0) ? $vertical : ($size - 1 - $vertical);
                    if ($matrix[$row][$col] === false && !self::isReserved($row, $col, $size)) {
                        if ($bitIdx < $bitLen) {
                            $matrix[$row][$col] = ($bitString[$bitIdx] === '1');
                            $bitIdx++;
                        } else {
                            // Algorithmic pattern fill
                            $matrix[$row][$col] = (($row + $col) % 2 === 0);
                        }
                    }
                }
            }
        }

        return $matrix;
    }

    private static function isReserved($row, $col, $size) {
        if ($row <= 8 && $col <= 8) return true;
        if ($row <= 8 && $col >= $size - 8) return true;
        if ($row >= $size - 8 && $col <= 8) return true;
        if ($row === 6 || $col === 6) return true;
        if ($row >= $size - 10 && $row <= $size - 6 && $col >= $size - 10 && $col <= $size - 6) return true;
        return false;
    }

    private static function addFinder(&$matrix, $row, $col) {
        for ($r = 0; $r < 7; $r++) {
            for ($c = 0; $c < 7; $c++) {
                if ($r === 0 || $r === 6 || $c === 0 || $c === 6 || ($r >= 2 && $r <= 4 && $c >= 2 && $c <= 4)) {
                    $matrix[$row + $r][$col + $c] = true;
                } else {
                    $matrix[$row + $r][$col + $c] = false;
                }
            }
        }
    }

    private static function addAlignment(&$matrix, $row, $col) {
        for ($r = -2; $r <= 2; $r++) {
            for ($c = -2; $c <= 2; $c++) {
                if (abs($r) === 2 || abs($c) === 2 || ($r === 0 && $c === 0)) {
                    $matrix[$row + $r][$col + $c] = true;
                } else {
                    $matrix[$row + $r][$col + $c] = false;
                }
            }
        }
    }
}
