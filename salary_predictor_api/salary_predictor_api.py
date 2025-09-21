# salary_predictor_api.py
from flask import Flask, request, jsonify
import pandas as pd
import numpy as np
import pickle
import joblib
from datetime import datetime
import os

app = Flask(__name__)

# Load the trained model
MODEL_PATH = 'models/salary_predictor_model.pkl'  # Update this path if needed
PREPROCESSOR_PATH = 'models/salary_predictor_preprocessing.pkl'  # Update if needed

# Industry mapping (same as in training)
industry_mapping = {
    'A': 'Agriculture', 'B': 'Mining', 'C': 'Manufacturing', 'D': 'Utilities',
    'E': 'Water_Waste', 'F': 'Construction', 'G': 'Trade', 'H': 'Transportation',
    'I': 'Accommodation', 'J': 'Information', 'K': 'Finance', 'L': 'Real_Estate',
    'M': 'Professional', 'N': 'Administrative', 'O': 'Public_Admin', 'P': 'Education',
    'Q': 'Health', 'R': 'Arts', 'S': 'Other_Services', 'T': 'Household', 'U': 'Extraterritorial'
}

# Load model and preprocessor
def load_artifacts():
    try:
        # Try joblib first, then pickle
        try:
            model_info = joblib.load(MODEL_PATH)
        except:
            with open(MODEL_PATH, 'rb') as f:
                model_info = pickle.load(f)
        
        # Load preprocessor
        try:
            with open(PREPROCESSOR_PATH, 'rb') as f:
                preprocessor_info = pickle.load(f)
        except:
            preprocessor_info = None
            
        return model_info, preprocessor_info
    except Exception as e:
        raise Exception(f"Error loading model artifacts: {str(e)}")

model_info, preprocessor_info = load_artifacts()
model = model_info['model']
print(f"Model loaded successfully: {model_info['model_name']}")

@app.route('/')
def home():
    return jsonify({
        "message": "Salary Prediction API",
        "model": model_info['model_name'],
        "performance": model_info['performance_metrics'],
        "status": "API is running"
    })

@app.route('/predict', methods=['POST'])
def predict():
    try:
        # Get JSON data from request
        data = request.get_json()
        
        if not data:
            return jsonify({"error": "No input data provided"}), 400
        
        # Create DataFrame from input
        input_df = pd.DataFrame([data])
        
        # Apply feature engineering (same as during training)
        input_df['industry_name'] = input_df['industry'].map(industry_mapping)
        input_df['leadership_score'] = (input_df['influencing'] + input_df['negotiating'] + 
                                      input_df['no_subordinates']) / 3
        input_df['problem_solving_score'] = (input_df['problem_solving_quick'] + 
                                           input_df['problem_solving_long']) / 2
        input_df['autonomy_score'] = (input_df['choose_hours'] + input_df['choose_method']) / 2
        input_df['teaching_score'] = (input_df['advising'] + input_df['instructing']) / 2
        input_df['education_premium'] = input_df['highest_qual'] / input_df['job_quals']
        input_df['education_efficiency'] = input_df['yrs_qual'] / input_df['highest_qual']
        
        # Make prediction
        prediction = model.predict(input_df)[0]
        prediction = max(0, prediction)  # Ensure non-negative salary
        
        return jsonify({
            "predicted_salary": round(float(prediction), 2),
            "currency": "USD",
            "period": "monthly"
        })
        
    except Exception as e:
        return jsonify({"error": str(e)}), 500

@app.route('/batch_predict', methods=['POST'])
def batch_predict():
    try:
        # Get JSON data from request
        data = request.get_json()
        
        if not data or 'records' not in data:
            return jsonify({"error": "No records provided"}), 400
        
        records = data['records']
        results = []
        
        for record in records:
            try:
                # Create DataFrame from input
                input_df = pd.DataFrame([record])
                
                # Apply feature engineering
                input_df['industry_name'] = input_df['industry'].map(industry_mapping)
                input_df['leadership_score'] = (input_df['influencing'] + input_df['negotiating'] + 
                                              input_df['no_subordinates']) / 3
                input_df['problem_solving_score'] = (input_df['problem_solving_quick'] + 
                                                   input_df['problem_solving_long']) / 2
                input_df['autonomy_score'] = (input_df['choose_hours'] + input_df['choose_method']) / 2
                input_df['teaching_score'] = (input_df['advising'] + input_df['instructing']) / 2
                input_df['education_premium'] = input_df['highest_qual'] / input_df['job_quals']
                input_df['education_efficiency'] = input_df['yrs_qual'] / input_df['highest_qual']
                
                # Make prediction
                prediction = model.predict(input_df)[0]
                prediction = max(0, prediction)
                
                results.append({
                    "record": record,
                    "predicted_salary": round(float(prediction), 2),
                    "currency": "USD",
                    "period": "monthly",
                    "status": "success"
                })
            except Exception as e:
                results.append({
                    "record": record,
                    "error": str(e),
                    "status": "error"
                })
        
        return jsonify({"results": results})
        
    except Exception as e:
        return jsonify({"error": str(e)}), 500

@app.route('/model_info', methods=['GET'])
def get_model_info():
    return jsonify({
        "model_name": model_info['model_name'],
        "training_date": model_info['training_date'],
        "performance_metrics": model_info['performance_metrics'],
        "feature_names": model_info['feature_names'],
        "categorical_features": model_info['categorical_features'],
        "numerical_features": model_info['numerical_features']
    })

if __name__ == '__main__':
    # Create sample request for testing
    sample_request = {
        'industry': 'J',
        'occupation': 2,
        'yrs_qual': 16,
        'sex': 1,
        'highest_qual': 12,
        'area_of_study': 5,
        'influencing': 3,
        'negotiating': 3,
        'sector': 1,
        'workforce_change': 1,
        'no_subordinates': 1,
        'choose_hours': 3,
        'choose_method': 4,
        'job_quals': 12,
        'qual_needed': 1,
        'experience_needed': 4,
        'keeping_current': 4,
        'satisfaction': 2,
        'advising': 3,
        'instructing': 2,
        'problem_solving_quick': 4,
        'problem_solving_long': 4,
        'labour': 1,
        'manual_skill': 2,
        'computer': 1,
        'group_meetings': 1,
        'computer_level': 2
    }
    
    print("Sample request JSON:")
    print(sample_request)
    
    # Run the Flask app
    app.run(debug=True, host='0.0.0.0', port=5000)