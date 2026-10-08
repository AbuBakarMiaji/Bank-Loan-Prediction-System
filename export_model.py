"""
Script to reproduce the exact model training and export from Bank_Loan(1).ipynb.
This uses the exact dataset, preprocessing, random_state=42, and parameters
specified in the notebook.
"""

import os
import pickle
import joblib
import pandas as pd
from sklearn.model_selection import train_test_split
from sklearn.linear_model import LogisticRegression
from sklearn.ensemble import RandomForestClassifier
from sklearn.preprocessing import LabelEncoder, StandardScaler
from sklearn.metrics import accuracy_score, classification_report, confusion_matrix

def train_and_export():
    dataset_path = 'loan_data_new.csv'
    if not os.path.exists(dataset_path):
        raise FileNotFoundError(f"Dataset not found at {dataset_path}")

    print("Step 1: Loading dataset...")
    df = pd.read_csv(dataset_path)
    print(f"Initial shape: {df.shape}")

    # Step 2: Missing/Null filter & Duplicates removal (Cell 2 of notebook)
    df = df.dropna()
    df = df.drop_duplicates()
    print(f"Shape after dropna and drop_duplicates: {df.shape}")

    # Step 4: Outlier removal (Cell 5 of notebook)
    limit = df['Loan Amount'].quantile(0.99)
    df = df[df['Loan Amount'] < limit]
    print(f"Shape after outlier removal (Loan Amount < {limit}): {df.shape}")

    # Step 5: Categorical Encoding (Cell 6 of notebook)
    categorical_cols_to_encode = ['Gender', 'Education', 'Home Onwership', 'Loan Intent', 'Previous Loan']
    label_encoders = {}
    for col in categorical_cols_to_encode:
        le = LabelEncoder()
        df[col] = df[col].astype(str).str.lower()
        df[col] = le.fit_transform(df[col])
        label_encoders[col] = le
        print(f"Encoded '{col}': classes = {le.classes_.tolist()}")

    # Step 6: Separate Features and Target (Cell 7 of notebook)
    X = df.drop('Loan Status', axis=1)
    y = df['Loan Status']
    feature_names = X.columns.tolist()
    print(f"Training features ({len(feature_names)}): {feature_names}")

    # Step 7: Train-test split (80% train, 20% test, random_state=42) (Cell 8 of notebook)
    X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.2, random_state=42)

    # Logistic Regression
    scaler = StandardScaler()
    X_train_scaled = scaler.fit_transform(X_train)
    X_test_scaled = scaler.transform(X_test)

    lr_model = LogisticRegression(max_iter=1000)
    lr_model.fit(X_train_scaled, y_train)
    lr_pred = lr_model.predict(X_test_scaled)
    lr_acc = accuracy_score(y_test, lr_pred)
    print(f"\n--- Performance Analysis: Logistic Regression ---")
    print(f"Accuracy: {lr_acc:.2f}")
    print(classification_report(y_test, lr_pred))

    # Random Forest Classifier (Best model selected in notebook)
    rf_model = RandomForestClassifier(n_estimators=100, random_state=42)
    rf_model.fit(X_train, y_train)
    rf_pred = rf_model.predict(X_test)
    rf_acc = accuracy_score(y_test, rf_pred)
    print(f"\n--- Performance Analysis: Random Forest ---")
    print(f"Accuracy: {rf_acc:.2f}")
    print(classification_report(y_test, rf_pred))

    # Step 8: Save Model and Artifacts
    model_dir = os.path.join(os.path.dirname(__file__), 'model')
    os.makedirs(model_dir, exist_ok=True)

    rf_model_path = os.path.join(model_dir, 'loan_prediction_rf_model.pkl')
    with open(rf_model_path, 'wb') as f:
        pickle.dump(rf_model, f)
    print(f"Saved Random Forest model to: {rf_model_path}")

    encoders_path = os.path.join(model_dir, 'loan_prediction_label_encoders_dict.pkl')
    joblib.dump(label_encoders, encoders_path)
    print(f"Saved Label Encoders to: {encoders_path}")

    # Also save metadata for the API service: feature order, types, class labels
    metadata = {
        'model_name': 'RandomForestClassifier',
        'n_estimators': 100,
        'random_state': 42,
        'features': feature_names,
        'categorical_columns': categorical_cols_to_encode,
        'classes': [0, 1],
        'class_meanings': {0: 'Approved', 1: 'Rejected'},
        'evaluation': {
            'random_forest_accuracy': float(rf_acc),
            'logistic_regression_accuracy': float(lr_acc)
        }
    }
    metadata_path = os.path.join(model_dir, 'model_metadata.json')
    import json
    with open(metadata_path, 'w') as f:
        json.dump(metadata, f, indent=2)
    print(f"Saved Model Metadata to: {metadata_path}")

    # Test sample prediction matching notebook Cell 10 & 12
    print("\n--- Sanity Check on Sample Data ---")
    sample_df = pd.DataFrame([{
        'Age': 35,
        'Gender': 'male',
        'Education': 'master',
        'Person Income': 70000,
        'Employee Experience': 10,
        'Home Onwership': 'rent',
        'Loan Amount': 15000,
        'Loan Intent': 'medical',
        'Loan interest Rate': 10.0,
        'Loan percentage': 0.15,
        'Credit History': 5,
        'Credit Score': 720,
        'Previous Loan': 'no'
    }])

    for col in categorical_cols_to_encode:
        le = label_encoders[col]
        sample_df[col] = le.transform(sample_df[col])

    sample_pred = rf_model.predict(sample_df)[0]
    sample_proba = rf_model.predict_proba(sample_df)[0]
    print(f"Sample prediction: {sample_pred} (Class meaning: {metadata['class_meanings'][sample_pred]})")
    print(f"Probabilities: Class 0 (Repaid/Approved) = {sample_proba[0]:.4f}, Class 1 (Default/Rejected) = {sample_proba[1]:.4f}")

if __name__ == '__main__':
    train_and_export()
