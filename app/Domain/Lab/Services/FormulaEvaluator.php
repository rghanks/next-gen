<?php

declare(strict_types=1);

namespace App\Domain\Lab\Services;

use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Throwable;

class FormulaEvaluator
{
    protected ExpressionLanguage $expressionLanguage;

    public function __construct()
    {
        $this->expressionLanguage = new ExpressionLanguage;
    }

    /**
     * Evaluate a formula safely using Symfony ExpressionLanguage.
     *
     * @param  string  $formula  E.g. "[UREA] * 0.467" or "[TP] - [ALB]" or "param_1 * 3"
     * @param  array<string, int|float|null>  $variables  Keyed by param identifier e.g. ['UREA' => 40]
     * @param  int  $decimals  Decimal places to round to
     */
    public function evaluate(string $formula, array $variables, int $decimals = 2): ?float
    {
        $cleanedFormula = trim($formula);
        if ($cleanedFormula === '') {
            return null;
        }

        // 1. Normalize variables to lowercase
        $normVars = [];
        foreach ($variables as $key => $val) {
            if ($val !== null && $val !== '' && is_numeric($val)) {
                $cleanKey = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', (string) $key));
                $normVars[$cleanKey] = (float) $val;
            }
        }

        // 2. Replace bracketed identifiers [XYZ] -> xyz
        $parsedFormula = preg_replace_callback('/\[([a-zA-Z0-9_]+)\]/', function ($m) {
            return strtolower($m[1]);
        }, $cleanedFormula);

        // 3. Ensure any unbracketed identifiers are also lowercase
        $parsedFormula = preg_replace_callback('/(?<![a-zA-Z0-9_])[a-zA-Z_][a-zA-Z0-9_]*(?![a-zA-Z0-9_])/', function ($m) {
            return strtolower($m[0]);
        }, $parsedFormula);

        // 4. Check if all required variables exist in $normVars
        preg_match_all('/[a-zA-Z_][a-zA-Z0-9_]*/', $parsedFormula, $matches);
        $knownFunctions = ['round', 'abs', 'ceil', 'floor', 'min', 'max', 'pow', 'sqrt', 'exp', 'log'];
        foreach ($matches[0] as $token) {
            $lowerToken = strtolower($token);
            if (in_array($lowerToken, $knownFunctions, true)) {
                continue;
            }
            if (! isset($normVars[$lowerToken])) {
                // Required variable not provided or empty
                return null;
            }
        }

        // 5. Evaluate ExpressionLanguage
        try {
            $result = $this->expressionLanguage->evaluate($parsedFormula, $normVars);

            if (is_numeric($result)) {
                if (is_infinite((float) $result) || is_nan((float) $result)) {
                    return null;
                }

                return round((float) $result, $decimals);
            }

            return null;
        } catch (Throwable) {
            return null;
        }
    }
}
