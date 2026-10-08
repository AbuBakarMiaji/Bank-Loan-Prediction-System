<?php
require_once 'includes/predict.php';

echo "========================================================\n";
echo "TEST 1: Prime Candidate (Approved)\n";
echo "========================================================\n";
$applicant1 = [
    'applicant_income'   => 80000,
    'coapplicant_income' => 20000,
    'loan_amount'        => 15000,
    'loan_term_years'    => 5,
    'credit_history'     => 1,
    'dependents'         => 0,
    'education'          => 'Graduate',
    'self_employed'      => 'No',
    'property_area'      => 'Urban',
    'interest_rate'      => 8.5
];
$res1 = predict_loan($applicant1);
print_r($res1);

echo "\n========================================================\n";
echo "TEST 2: Severe High Risk Candidate (Rejected)\n";
echo "========================================================\n";
$applicant2 = [
    'applicant_income'   => 8000,
    'coapplicant_income' => 0,
    'loan_amount'        => 60000,
    'loan_term_years'    => 3,
    'credit_history'     => 0,
    'dependents'         => 3,
    'education'          => 'Not Graduate',
    'self_employed'      => 'Yes',
    'property_area'      => 'Rural',
    'interest_rate'      => 14.0
];
$res2 = predict_loan($applicant2);
print_r($res2);
