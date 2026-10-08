<?php
/**
 * ==============================================================================
 * Bank Loan Prediction Engine — Real Machine Learning Integration
 * ==============================================================================
 *
 * Model Source: Bank_Loan(1).ipynb
 * Selected Model: Random Forest Classifier (Accuracy: 92%, Class 0 F1: 0.95, Class 1 F1: 0.81)
 * Artifacts:
 *   - Model:          model/loan_prediction_rf_model.pkl
 *   - Encoders:       model/loan_prediction_label_encoders_dict.pkl
 *   - Metadata:       model/model_metadata.json
 *
 * Prediction Architecture:
 *   1. PHP Frontend (loan_apply.php) passes user input array to predict_loan().
 *   2. predict_loan() attempts HTTP POST to local Python Flask API (http://127.0.0.1:5000/predict).
 *   3. If the Flask daemon is offline, it automatically executes predict_cli.py via PHP CLI runner
 *      as a zero-downtime fallback, ensuring the real trained ML model is ALWAYS used.
 *   4. The trained Random Forest model predicts Class 0 (Approved) or Class 1 (Rejected)
 *      along with probability and confidence.
 *   5. predict_loan() returns a normalized associative array to the caller.
 *
 * ==============================================================================
 */

// Configuration constants for the Python ML Service
define('ML_API_URL', 'http://127.0.0.1:5000/predict');
define('ML_API_TIMEOUT_SEC', 4);
define('PYTHON_EXECUTABLE_PATH', 'C:\\Users\\abuba\\AppData\\Local\\Programs\\Python\\Python311\\python.exe');

/**
 * Predict loan eligibility using the trained Machine Learning model.
 *
 * @param array $app Form data submitted by the loan applicant
 * @return array Prediction result with structure:
 *               [
 *                 'result'      => 'Approved' | 'Rejected',
 *                 'confidence'  => float,
 *                 'probability' => float,
 *                 'factors'     => array,
 *                 'model_used'  => string,
 *                 'source'      => string,
 *                 'error'       => string|null
 *               ]
 */
function predict_loan(array $app): array {
    // --------------------------------------------------------------------------
    // 1. Prepare and normalize input data for the ML model
    // --------------------------------------------------------------------------
    $income          = (float)($app['applicant_income'] ?? 50000);
    $coIncome        = (float)($app['coapplicant_income'] ?? 0);
    $loanAmount      = max((float)($app['loan_amount'] ?? 10000), 1000);
    $creditHistory   = (int)($app['credit_history'] ?? 1);
    $dependents      = (int)($app['dependents'] ?? 0);
    $education       = trim((string)($app['education'] ?? 'Graduate'));
    $selfEmployed    = trim((string)($app['self_employed'] ?? 'No'));
    $propertyArea    = trim((string)($app['property_area'] ?? 'Urban'));
    $interestRate    = (float)($app['interest_rate'] ?? 8.50);

    // Build payload matching the features expected by ml_api.py / predict_cli.py
    $payload = [
        'applicant_income'    => $income,
        'coapplicant_income'  => $coIncome,
        'loan_amount'         => $loanAmount,
        'credit_history'      => $creditHistory,
        'dependents'          => $dependents,
        'education'           => $education,
        'self_employed'       => $selfEmployed,
        'property_area'       => $propertyArea,
        'interest_rate'       => $interestRate,
        'age'                 => isset($app['age']) ? (int)$app['age'] : 30,
        'gender'              => $app['gender'] ?? 'male',
        'employee_experience' => $selfEmployed === 'Yes' ? 6 : 4,
        'loan_intent'         => $app['loan_intent'] ?? 'personal',
    ];

    $payloadJson = json_encode($payload);

    // --------------------------------------------------------------------------
    // 2. Primary Method: Call Python Flask ML API via HTTP cURL
    // --------------------------------------------------------------------------
    $apiResult = call_python_api_http($payloadJson);
    if ($apiResult !== null && isset($apiResult['prediction'])) {
        return format_ml_response($apiResult, 'Python ML REST API', $payload);
    }

    // --------------------------------------------------------------------------
    // 3. Fallback Method: Direct Python CLI invocation if HTTP daemon is down
    // --------------------------------------------------------------------------
    $cliResult = call_python_cli_fallback($payloadJson);
    if ($cliResult !== null && isset($cliResult['prediction'])) {
        return format_ml_response($cliResult, 'Python ML Engine (CLI Fallback)', $payload);
    }

    // --------------------------------------------------------------------------
    // 4. Graceful Error Handling if Python is completely unavailable
    // --------------------------------------------------------------------------
    return [
        'result'      => 'Pending',
        'confidence'  => 0.0,
        'probability' => 0.0,
        'factors'     => [
            'System Notice' => 'ML Prediction Service currently initializing. Application saved for admin review.'
        ],
        'model_used'  => 'Random Forest (Unavailable)',
        'source'      => 'Offline Notice',
        'error'       => 'The Python ML prediction service could not be reached. Please ensure ml_api.py is running.'
    ];
}

/**
 * Send HTTP POST request to Python Flask ML API.
 */
function call_python_api_http(string $jsonPayload): ?array {
    if (!function_exists('curl_init')) {
        return null;
    }

    $ch = curl_init(ML_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $jsonPayload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => ML_API_TIMEOUT_SEC,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json'
        ]
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response !== false && $httpCode === 200) {
        $decoded = json_decode($response, true);
        if (is_array($decoded) && isset($decoded['status']) && $decoded['status'] === 'success') {
            return $decoded;
        }
    }

    return null;
}

/**
 * Execute predict_cli.py directly using Python binary as a reliable zero-downtime fallback.
 */
function call_python_cli_fallback(string $jsonPayload): ?array {
    $projectRoot = dirname(__DIR__);
    $scriptPath  = $projectRoot . DIRECTORY_SEPARATOR . 'predict_cli.py';

    if (!file_exists($scriptPath)) {
        return null;
    }

    // Determine python binary path
    $pythonBin = file_exists(PYTHON_EXECUTABLE_PATH) ? PYTHON_EXECUTABLE_PATH : 'python';

    $descriptors = [
        0 => ['pipe', 'r'], // stdin
        1 => ['pipe', 'w'], // stdout
        2 => ['pipe', 'w']  // stderr
    ];

    $cmd = escapeshellarg($pythonBin) . ' ' . escapeshellarg($scriptPath);
    $process = proc_open($cmd, $descriptors, $pipes, $projectRoot);

    if (is_resource($process)) {
        fwrite($pipes[0], $jsonPayload);
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        proc_close($process);

        if (!empty($stdout)) {
            $decoded = json_decode(trim($stdout), true);
            if (is_array($decoded) && isset($decoded['status']) && $decoded['status'] === 'success') {
                return $decoded;
            }
        }
    }

    return null;
}

/**
 * Format and annotate the ML model response for UI display.
 */
function format_ml_response(array $ml, string $source, array $input): array {
    $result     = $ml['prediction'] === 'Approved' ? 'Approved' : 'Rejected';
    $confidence = (float)($ml['confidence'] ?? 85.0);
    $prob       = (float)($ml['probability'] ?? 0.85);

    // Build explanatory factors from the actual input features that the model evaluated
    $factors = [];
    if (($input['credit_history'] ?? 1) == 1) {
        $factors['Credit Profile'] = '+ Verified clean credit history record';
    } else {
        $factors['Credit Profile'] = '- High credit risk / adverse credit record identified';
    }

    $annualIncome = ($input['applicant_income'] + $input['coapplicant_income']) * 12;
    $dti = $annualIncome > 0 ? ($input['loan_amount'] / $annualIncome) : 1;
    if ($dti <= 0.25) {
        $factors['Loan-to-Income'] = '+ Strong debt capacity (' . round($dti * 100, 1) . '% of annual income)';
    } elseif ($dti <= 0.40) {
        $factors['Loan-to-Income'] = '~ Moderate debt ratio (' . round($dti * 100, 1) . '% of annual income)';
    } else {
        $factors['Loan-to-Income'] = '- Elevated debt exposure (' . round($dti * 100, 1) . '% of annual income)';
    }

    if (!empty($input['education'])) {
        $factors['Education'] = ($input['education'] === 'Graduate' ? '+ Higher Education Completed' : '~ Secondary Education');
    }

    if (!empty($input['property_area'])) {
        $factors['Property Region'] = '~ ' . htmlspecialchars($input['property_area']) . ' location';
    }

    return [
        'result'      => $result,
        'confidence'  => $confidence,
        'probability' => $prob,
        'factors'     => $factors,
        'model_used'  => 'Random Forest Classifier (Bank_Loan(1).ipynb)',
        'source'      => $source,
        'error'       => null
    ];
}
