"""
Flask ML Prediction API for Bank Loan System.
Uses the trained Random Forest Classifier from Bank_Loan(1).ipynb.

Architecture:
  PHP Frontend -> predict.php -> HTTP POST /predict -> ml_api.py -> Trained Model -> JSON Response
"""

import os
import json
import pickle
import joblib
import pandas as pd
import numpy as np
from flask import Flask, request, jsonify

app = Flask(__name__)

# Paths to trained model artifacts
BASE_DIR = os.path.dirname(os.path.abspath(__file__))
MODEL_DIR = os.path.join(BASE_DIR, 'model')
MODEL_PATH = os.path.join(MODEL_DIR, 'loan_prediction_rf_model.pkl')
ENCODERS_PATH = os.path.join(MODEL_DIR, 'loan_prediction_label_encoders_dict.pkl')
METADATA_PATH = os.path.join(MODEL_DIR, 'model_metadata.json')

# Global holders for model, encoders, and metadata
rf_model = None
label_encoders = None
metadata = None

import sys

def load_artifacts():
    """Load model, label encoders, and metadata from disk."""
    global rf_model, label_encoders, metadata

    if not os.path.exists(MODEL_PATH):
        raise FileNotFoundError(f"Model file not found at: {MODEL_PATH}. Run export_model.py first.")
    if not os.path.exists(ENCODERS_PATH):
        raise FileNotFoundError(f"Label encoders file not found at: {ENCODERS_PATH}. Run export_model.py first.")
    if not os.path.exists(METADATA_PATH):
        raise FileNotFoundError(f"Metadata file not found at: {METADATA_PATH}. Run export_model.py first.")

    with open(MODEL_PATH, 'rb') as f:
        rf_model = pickle.load(f)

    label_encoders = joblib.load(ENCODERS_PATH)

    with open(METADATA_PATH, 'r') as f:
        metadata = json.load(f)

    print(f"[ML API] Successfully loaded model: {metadata.get('model_name')}", file=sys.stderr)
    print(f"[ML API] Features required: {metadata.get('features')}", file=sys.stderr)

# Load artifacts on startup
load_artifacts()

def extract_and_map_features(data):
    """
    Map input data from PHP application to the exact 13 features
    required by the trained Random Forest model from Bank_Loan(1).ipynb.
    """
    # 1. Applicant income (support both raw model names and PHP form names)
    applicant_income = float(data.get('applicant_income', data.get('Person Income', 50000)))
    coapplicant_income = float(data.get('coapplicant_income', 0))
    # In PHP UI, income is monthly; in dataset Person Income is annual
    # If income is clearly monthly (< 200,000 BDT), convert to annual equivalent
    monthly_total = applicant_income + coapplicant_income
    person_income = float(data.get('Person Income', monthly_total * 12 if monthly_total < 200000 else monthly_total))
    if person_income <= 0:
        person_income = 1.0  # Prevent division by zero

    # 2. Loan Amount
    loan_amount = float(data.get('loan_amount', data.get('Loan Amount', 10000)))

    # 3. Loan interest Rate
    interest_rate = float(data.get('interest_rate', data.get('Loan interest Rate', 11.0)))

    # 4. Loan percentage (Loan Amount / Person Income)
    computed_loan_pct = round(loan_amount / person_income, 2)
    loan_percentage = float(data.get('loan_percentage', data.get('Loan percentage', computed_loan_pct)))

    # 5. Credit History & Credit Score mapping
    # PHP form provides credit_history as 1 (Clean record) or 0 (Poor/No record)
    raw_credit_history = data.get('credit_history', data.get('Credit History', 1))
    try:
        credit_history_num = int(raw_credit_history)
    except (ValueError, TypeError):
        credit_history_num = 1

    # In the dataset, Credit History is length in years (e.g., 2-15) and Credit Score is 390-850
    # Clean record (1): Good credit score (~720), 5 years credit history, no previous default
    # Poor record (0): Low credit score (~510), 2 years credit history, previous default = yes
    if 'Credit Score' in data:
        credit_score = int(data['Credit Score'])
    elif 'credit_score' in data:
        credit_score = int(data['credit_score'])
    else:
        credit_score = 720 if credit_history_num == 1 else 510

    if 'Credit History' in data and int(data['Credit History']) > 1:
        credit_history_years = int(data['Credit History'])
    else:
        credit_history_years = 5 if credit_history_num == 1 else 2

    # 6. Previous Loan / default on file
    if 'Previous Loan' in data:
        previous_loan = str(data['Previous Loan']).lower()
    elif 'previous_loan' in data:
        previous_loan = str(data['previous_loan']).lower()
    else:
        # In the training dataset, Previous Loan = 'Yes' indicates an existing borrower with clean repayment.
        # Credit history 1 (Clean) maps to 'yes', Credit history 0 (Poor/no record) maps to 'no'.
        previous_loan = 'yes' if credit_history_num == 1 else 'no'

    # 7. Education
    # PHP sends 'Graduate' or 'Not Graduate'.
    # Model encoder classes: ['associate', 'bachelor', 'doctorate', 'high school', 'master']
    raw_edu = str(data.get('education', data.get('Education', 'bachelor'))).strip().lower()
    if raw_edu in ['graduate', 'grad']:
        education = 'bachelor'
    elif raw_edu in ['not graduate', 'not_graduate', 'undergraduate']:
        education = 'high school'
    elif raw_edu in ['associate', 'bachelor', 'doctorate', 'high school', 'master']:
        education = raw_edu
    else:
        education = 'bachelor'

    # 8. Home Ownership
    # PHP sends property_area ('Urban', 'Semiurban', 'Rural')
    # Model encoder classes: ['mortgage', 'other', 'own', 'rent']
    if 'Home Onwership' in data:
        home_ownership = str(data['Home Onwership']).lower()
    elif 'home_ownership' in data:
        home_ownership = str(data['home_ownership']).lower()
    else:
        prop_area = str(data.get('property_area', 'Urban')).strip().lower()
        if prop_area == 'rural':
            home_ownership = 'own'
        elif prop_area == 'semiurban':
            home_ownership = 'mortgage'
        else:
            home_ownership = 'rent'

    # 9. Age
    age = int(data.get('age', data.get('Age', 30)))

    # 10. Gender
    gender = str(data.get('gender', data.get('Gender', 'male'))).strip().lower()
    if gender not in ['female', 'male']:
        gender = 'male'

    # 11. Employee Experience
    # If self_employed is 'Yes', typically higher or variable; default to 5
    emp_exp = int(data.get('employee_experience', data.get('Employee Experience', 5)))

    # 12. Loan Intent
    # Classes: ['debtconsolidation', 'education', 'homeimprovement', 'medical', 'personal', 'venture']
    loan_intent = str(data.get('loan_intent', data.get('Loan Intent', 'personal'))).strip().lower().replace(' ', '')
    if loan_intent not in ['debtconsolidation', 'education', 'homeimprovement', 'medical', 'personal', 'venture']:
        loan_intent = 'personal'

    # Assembled feature dictionary matching notebook exact column names
    mapped_record = {
        'Age': age,
        'Gender': gender,
        'Education': education,
        'Person Income': person_income,
        'Employee Experience': emp_exp,
        'Home Onwership': home_ownership,
        'Loan Amount': loan_amount,
        'Loan Intent': loan_intent,
        'Loan interest Rate': interest_rate,
        'Loan percentage': loan_percentage,
        'Credit History': credit_history_years,
        'Credit Score': credit_score,
        'Previous Loan': previous_loan
    }

    return mapped_record

def predict_single(data_dict):
    """
    Run prediction on mapped data using the exact preprocessing and trained model.
    """
    mapped = extract_and_map_features(data_dict)
    feature_names = metadata['features']
    categorical_cols = metadata['categorical_columns']

    # Create single-row DataFrame
    df = pd.DataFrame([mapped])
    # Ensure correct column ordering
    df = df[feature_names]

    # Preprocess categorical columns with fitted LabelEncoders (from notebook Cell 6 & 10)
    for col in categorical_cols:
        le = label_encoders[col]
        val = str(df.at[0, col]).lower()
        if val in le.classes_:
            encoded_val = int(le.transform([val])[0])
        else:
            # Fallback for unseen classes as demonstrated in notebook Cell 10
            default_val = le.classes_[0]
            encoded_val = int(le.transform([default_val])[0])
        df[col] = encoded_val

    # Predict using the trained Random Forest model
    raw_pred = int(rf_model.predict(df)[0])
    probabilities = rf_model.predict_proba(df)[0]

    # In Bank_Loan(1).ipynb:
    # Class 0 = LIKELY TO BE REPAID (Approved)
    # Class 1 = LIKELY TO DEFAULT (Rejected)
    proba_repaid = float(probabilities[0])    # Probability of Class 0 (Approved)
    proba_default = float(probabilities[1])   # Probability of Class 1 (Rejected)

    if raw_pred == 0:
        prediction_label = "Approved"
        probability = proba_repaid
    else:
        prediction_label = "Rejected"
        probability = proba_default

    confidence = round(probability * 100, 2)

    return {
        "prediction": prediction_label,
        "probability": round(probability, 4),
        "confidence": confidence,
        "raw_class": raw_pred,
        "model_used": metadata.get("model_name", "RandomForestClassifier"),
        "class_probabilities": {
            "Approved": round(proba_repaid, 4),
            "Rejected": round(proba_default, 4)
        },
        "features_used": mapped
    }

@app.route('/health', methods=['GET'])
def health():
    """Health check endpoint."""
    return jsonify({
        "status": "online",
        "model_loaded": rf_model is not None,
        "model_name": metadata.get("model_name") if metadata else None,
        "model_accuracy": metadata.get("evaluation", {}).get("random_forest_accuracy") if metadata else None
    })

@app.route('/predict', methods=['POST'])
def predict():
    """
    Main prediction endpoint.
    Receives JSON with loan applicant details and returns prediction and confidence.
    """
    try:
        data = request.get_json(force=True, silent=True)
        if not data:
            return jsonify({
                "status": "error",
                "message": "Invalid JSON or empty request body"
            }), 400

        result = predict_single(data)
        result["status"] = "success"
        return jsonify(result), 200

    except Exception as e:
        return jsonify({
            "status": "error",
            "message": f"Prediction failed: {str(e)}"
        }), 500

if __name__ == '__main__':
    port = int(os.environ.get('PORT', 5000))
    print(f"Starting Loan Prediction ML API on port {port}...")
    app.run(host='127.0.0.1', port=port, debug=False)
