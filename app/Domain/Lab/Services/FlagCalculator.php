<?php
/*   __________________________________________________
    |  Obfuscated by YAK Pro - Php Obfuscator  3.0.2   |
    |              on 2026-11-23 10:46:10              |
    |    GitHub: https://github.com/pk-fr/yakpro-po    |
    |__________________________________________________|
*/
 declare (strict_types=1); namespace App\Domain\Lab\Services; class FlagCalculator { public function calculate(?float $numericValue, ?float $low, ?float $high, ?float $criticalLow = null, ?float $criticalHigh = null, ?string $textValue = null): string { if ($numericValue === null) { if ($textValue !== null && in_array(strtoupper(trim($textValue)), ["\120\x4f\123\111\x54\x49\126\x45", "\122\x45\x41\103\x54\111\126\105", "\x50\x52\105\x53\x45\x4e\124", "\x41\x42\116\x4f\x52\115\x41\114"], true)) { return "\x41"; } return "\x4e"; } if ($criticalLow !== null && $numericValue < $criticalLow) { return "\114\114"; } if ($criticalHigh !== null && $numericValue > $criticalHigh) { return "\x48\x48"; } if ($low !== null && $numericValue < $low) { return "\x4c"; } if ($high !== null && $numericValue > $high) { return "\110"; } return "\116"; } }