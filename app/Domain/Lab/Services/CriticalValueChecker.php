<?php
/*   __________________________________________________
    |  Obfuscated by YAK Pro - Php Obfuscator  3.0.2   |
    |              on 2026-11-23 10:46:10              |
    |    GitHub: https://github.com/pk-fr/yakpro-po    |
    |__________________________________________________|
*/
 declare (strict_types=1); namespace App\Domain\Lab\Services; use App\Domain\Lab\Models\LabResultValue; class CriticalValueChecker { public function isCritical(string $flag): bool { return in_array($flag, ["\114\114", "\110\110"], true); } public function checkAcknowledgements(iterable $resultValues): array { $unackCount = 0; foreach ($resultValues as $val) { if ($this->isCritical($val->flag)) { $ack = $val->criticalAcknowledgement()->exists(); if (!$ack) { $unackCount++; } } } return ["\x68\141\163\137\165\x6e\141\x63\153\x6e\157\x77\x6c\x65\x64\x67\x65\x64\x5f\143\x72\151\x74\x69\x63\x61\x6c" => $unackCount > 0, "\x75\156\141\x63\153\x6e\157\167\x6c\x65\x64\147\145\144\137\x63\x6f\165\x6e\x74" => $unackCount]; } }