@echo off
title BU Bank ML Prediction API Service
echo ==============================================================================
echo BU Bank Loan Prediction System — Machine Learning Service
echo ==============================================================================
echo Model:    Random Forest Classifier (Accuracy: 92%%)
echo Source:   Bank_Loan(1).ipynb
echo Service:  http://127.0.0.1:5000
echo ==============================================================================
echo.
"C:\Users\abuba\AppData\Local\Programs\Python\Python311\python.exe" ml_api.py
if errorlevel 1 (
    echo.
    echo Python failed to start. Falling back to default system python...
    python ml_api.py
)
pause
