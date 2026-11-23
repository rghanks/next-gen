<?php
/*   __________________________________________________
    |  Obfuscated by YAK Pro - Php Obfuscator  3.0.2   |
    |              on 2026-11-23 10:46:07              |
    |    GitHub: https://github.com/pk-fr/yakpro-po    |
    |__________________________________________________|
*/
 declare (strict_types=1); namespace App\Http\Controllers\Lab; use App\Domain\Lab\Models\LabOrderEvent; use App\Http\Controllers\Controller; use Illuminate\Http\Request; use Illuminate\View\View; class AuditController extends Controller { public function index(Request $request): View { $orderId = $request->input("\x6f\162\144\x65\162\x5f\x69\144"); $query = LabOrderEvent::with(["\x6f\162\x64\145\162\x2e\x70\141\164\x69\x65\156\164", "\x75\x73\x65\x72"])->latest("\x69\144"); if ($orderId) { $query->where("\157\162\144\145\162\137\x69\x64", (int) $orderId); } $events = $query->paginate(30); return view("\154\141\142\56\x61\165\x64\x69\164\x2e\151\x6e\x64\x65\x78", ["\x65\x76\x65\156\x74\163" => $events, "\x6f\x72\144\x65\x72\111\x64" => $orderId]); } }