<?php
/*   __________________________________________________
    |  Obfuscated by YAK Pro - Php Obfuscator  3.0.2   |
    |              on 2026-11-23 10:46:10              |
    |    GitHub: https://github.com/pk-fr/yakpro-po    |
    |__________________________________________________|
*/
 declare (strict_types=1); namespace App\Domain\Lab\Services; use Illuminate\Support\Facades\Cache; class LabFeatureManager { public function isEnabledForBranch(int $branchId = 1): bool { $cacheKey = "\x6c\x69\x73\x5f\146\145\x61\x74\165\162\x65\x5f\146\x6c\141\147\137\142\162\x61\156\x63\x68\x5f{$branchId}"; return (bool) Cache::remember($cacheKey, 3600, function () use ($branchId) { $configured = config("\154\x61\x62\56\x62\x72\x61\x6e\143\150\x65\x73\56{$branchId}\56\x65\x6e\x61\142\154\x65\144"); if ($configured !== null) { return (bool) $configured; } return config("\x6c\141\x62\56\145\156\x61\x62\154\145\144\x5f\147\154\157\x62\x61\154\154\171", true); }); } public function setBranchStatus(int $branchId, bool $enabled): void { Cache::put("\x6c\x69\x73\137\x66\x65\x61\x74\x75\x72\145\137\146\x6c\141\147\x5f\142\x72\141\156\x63\x68\x5f{$branchId}", $enabled, 86400 * 30); } }