"""
CLI Prediction script for Bank Loan System.
Can be invoked directly from PHP via shell_exec / proc_open as a fallback
if the Flask ML HTTP API is temporarily unreachable.
Uses the exact same trained model and preprocessing.
"""

import sys
import json
import argparse
from ml_api import predict_single, load_artifacts

def main():
    parser = argparse.ArgumentParser(description="Predict loan approval from JSON input")
    parser.add_argument('--json', type=str, help="JSON string containing applicant details")
    args = parser.parse_args()

    if not args.json:
        # Read from stdin if no --json flag
        raw_input = sys.stdin.read()
    else:
        raw_input = args.json

    try:
        data = json.loads(raw_input)
        result = predict_single(data)
        result["status"] = "success"
        print(json.dumps(result))
    except Exception as e:
        error_res = {
            "status": "error",
            "message": str(e)
        }
        print(json.dumps(error_res))
        sys.exit(1)

if __name__ == '__main__':
    main()
