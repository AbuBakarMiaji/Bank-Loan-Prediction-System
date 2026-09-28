<?php
/**
 * Loan eligibility prediction engine.
 *
 * This is a transparent, weighted scoring model that mirrors the same
 * signals a trained classifier (e.g. Logistic Regression / Random Forest)
 * would learn from historical loan data: credit history, income-to-loan
 * ratio, dependents, education and property area. It is intentionally
 * rule-based and dependency-free so the demo runs on plain PHP without
 * a Python ML runtime — swap predict_loan() for a call to a hosted
 * model/API to plug in a real trained model later.
 *
 * Returns:
 *   [
 *     'result'      => 'Approved' | 'Rejected',
 *     'confidence'  => float 0-100,
 *     'factors'     => [ label => contribution string, ... ]
 *   ]
 */

function predict_loan(array $app): array {
    $income        = (float)$app['applicant_income'] + (float)$app['coapplicant_income'];
    $loanAmount    = max((float)$app['loan_amount'], 1);
    $term          = max((int)$app['loan_term_months'], 1);
    $creditHistory = (int)$app['credit_history'];
    $dependents    = (int)$app['dependents'];
    $education     = $app['education'];
    $selfEmployed  = $app['self_employed'];
    $propertyArea  = $app['property_area'];

    // Monthly installment vs monthly income (debt-to-income proxy)
    $monthlyIncome      = $income / 1;
    $monthlyInstallment = $loanAmount / $term;
    $dti = $monthlyIncome > 0 ? $monthlyInstallment / $monthlyIncome : 1;

    $score = 0.0;
    $factors = [];

    // Credit history is the single strongest signal in the historical data
    if ($creditHistory === 1) {
        $score += 3.2;
        $factors['Credit history'] = '+ strong (clean repayment record)';
    } else {
        $score -= 3.6;
        $factors['Credit history'] = '- weak (no clean repayment record)';
    }

    // Debt-to-income ratio
    if ($dti <= 0.15) {
        $score += 1.6; $factors['Debt-to-income'] = '+ comfortable (' . round($dti * 100, 1) . '%)';
    } elseif ($dti <= 0.30) {
        $score += 0.6; $factors['Debt-to-income'] = '+ manageable (' . round($dti * 100, 1) . '%)';
    } elseif ($dti <= 0.45) {
        $score -= 0.6; $factors['Debt-to-income'] = '- elevated (' . round($dti * 100, 1) . '%)';
    } else {
        $score -= 1.8; $factors['Debt-to-income'] = '- high (' . round($dti * 100, 1) . '%)';
    }

    // Education
    if ($education === 'Graduate') {
        $score += 0.35; $factors['Education'] = '+ graduate';
    } else {
        $score -= 0.15; $factors['Education'] = '- not graduate';
    }

    // Self-employment adds income variability risk
    if ($selfEmployed === 'Yes') {
        $score -= 0.3; $factors['Employment'] = '- self-employed (variable income)';
    } else {
        $score += 0.15; $factors['Employment'] = '+ salaried (stable income)';
    }

    // Dependents
    if ($dependents === 0) {
        $score += 0.3; $factors['Dependents'] = '+ none';
    } elseif ($dependents <= 2) {
        $score += 0.0; $factors['Dependents'] = '~ ' . $dependents . ' dependents';
    } else {
        $score -= 0.4; $factors['Dependents'] = '- ' . $dependents . ' dependents';
    }

    // Property area (semiurban historically has the best repayment rate)
    switch ($propertyArea) {
        case 'Semiurban':
            $score += 0.4; $factors['Property area'] = '+ semiurban';
            break;
        case 'Urban':
            $score += 0.1; $factors['Property area'] = '~ urban';
            break;
        default:
            $score -= 0.2; $factors['Property area'] = '- rural';
    }

    // Loan size relative to income
    if ($income > 0 && $loanAmount / $income > 6) {
        $score -= 0.8; $factors['Loan-to-income'] = '- loan is large vs. income';
    }

    // Convert score to a probability via logistic function
    $probability = 1 / (1 + exp(-$score));
    $confidence  = round($probability * 100, 1);

    $result = $probability >= 0.5 ? 'Approved' : 'Rejected';
    // Confidence should reflect certainty of whichever class was chosen
    $displayConfidence = $result === 'Approved' ? $confidence : round(100 - $confidence, 1);

    return [
        'result'     => $result,
        'confidence' => $displayConfidence,
        'factors'    => $factors,
    ];
}
