<?php
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';

SessionManager::requireRole(['worker']);

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Get worker profile for pre-filling
$worker_profile = null;
try {
    $stmt = $conn->prepare("
        SELECT wp.*, u.address 
        FROM worker_profiles wp
        INNER JOIN users u ON wp.user_id = u.id
        WHERE wp.user_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $worker_profile = $stmt->get_result()->fetch_assoc();
} catch (Exception $e) {
    error_log("Worker profile error: " . $e->getMessage());
}

// Industry mappings for dropdown
$industries = [
    'A' => 'Agriculture, Forestry and Fishing',
    'B' => 'Mining and Quarrying', 
    'C' => 'Manufacturing',
    'D' => 'Electricity, Gas, Steam and Air Conditioning',
    'E' => 'Water Supply; Sewerage, Waste Management',
    'F' => 'Construction',
    'G' => 'Wholesale and Retail Trade',
    'H' => 'Transportation and Storage',
    'I' => 'Accommodation and Food Service',
    'J' => 'Information and Communication',
    'K' => 'Financial and Insurance Activities',
    'L' => 'Real Estate Activities',
    'M' => 'Professional, Scientific and Technical',
    'N' => 'Administrative and Support Service',
    'O' => 'Public Administration and Defence',
    'P' => 'Education',
    'Q' => 'Human Health and Social Work',
    'R' => 'Arts, Entertainment and Recreation',
    'S' => 'Other Service Activities',
    'T' => 'Household Activities',
    'U' => 'Extraterritorial Organizations'
];

$occupations = [
    1 => 'Managers',
    2 => 'Professionals', 
    3 => 'Technicians and Associate Professionals',
    4 => 'Clerical Support Workers',
    5 => 'Service and Sales Workers',
    6 => 'Skilled Agricultural, Forestry and Fishery Workers',
    7 => 'Craft and Related Trades Workers',
    8 => 'Plant and Machine Operators',
    9 => 'Elementary Occupations'
];

$study_areas = [
    1 => 'General Programmes',
    2 => 'Education',
    3 => 'Arts and Humanities',
    4 => 'Social Sciences, Journalism and Information',
    5 => 'Business, Administration and Law',
    6 => 'Natural Sciences, Mathematics and Statistics',
    7 => 'Information and Communication Technologies',
    8 => 'Engineering, Manufacturing and Construction',
    9 => 'Agriculture, Forestry, Fisheries and Veterinary',
    10 => 'Health and Welfare',
    11 => 'Services'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salary Predictor - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .prediction-result {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin: 20px 0;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .salary-amount {
            font-size: 3rem;
            font-weight: bold;
            margin: 20px 0;
        }
        
        .form-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .skill-slider {
            margin: 10px 0;
        }
        
        .slider-label {
            display: flex;
            justify-content: space-between;
            font-size: 0.8rem;
            color: #666;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-tools me-2"></i><?php echo APP_NAME; ?> Worker
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h2><i class="fas fa-chart-line me-2"></i>AI Salary Predictor</h2>
                        <p class="mb-0">Get an estimated salary range based on your skills, experience, and industry using machine learning</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Form Column -->
            <div class="col-lg-8">
                <form id="salaryPredictorForm">
                    <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                    
                    <!-- Basic Information -->
                    <div class="form-section">
                        <h5><i class="fas fa-user me-2"></i>Basic Information</h5>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Industry *</label>
                                <select class="form-select" name="industry" required>
                                    <option value="">Select Industry</option>
                                    <?php foreach ($industries as $code => $name): ?>
                                        <option value="<?php echo $code; ?>"><?php echo $name; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Occupation Level *</label>
                                <select class="form-select" name="occupation" required>
                                    <option value="">Select Occupation</option>
                                    <?php foreach ($occupations as $code => $name): ?>
                                        <option value="<?php echo $code; ?>"><?php echo $name; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Gender</label>
                                <select class="form-select" name="sex">
                                    <option value="1">Male</option>
                                    <option value="0">Female</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Work Sector</label>
                                <select class="form-select" name="sector">
                                    <option value="1">Private Sector</option>
                                    <option value="0">Public Sector</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Education & Experience -->
                    <div class="form-section">
                        <h5><i class="fas fa-graduation-cap me-2"></i>Education & Experience</h5>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Years of Education *</label>
                                <input type="number" class="form-control" name="yrs_qual" 
                                       min="0" max="25" value="12" required>
                                <small class="text-muted">Total years of formal education</small>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Highest Qualification Level *</label>
                                <select class="form-select" name="highest_qual" required>
                                    <option value="1">No Qualification</option>
                                    <option value="8">Primary Education</option>
                                    <option value="10">Lower Secondary</option>
                                    <option value="12" selected>Upper Secondary</option>
                                    <option value="14">Post-Secondary Non-Tertiary</option>
                                    <option value="16">Bachelor's Degree</option>
                                    <option value="18">Master's Degree</option>
                                    <option value="20">Doctoral Degree</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Job Qualification Required *</label>
                                <select class="form-select" name="job_quals" required>
                                    <option value="1">No Qualification</option>
                                    <option value="8">Primary Education</option>
                                    <option value="10">Lower Secondary</option>
                                    <option value="12" selected>Upper Secondary</option>
                                    <option value="14">Post-Secondary Non-Tertiary</option>
                                    <option value="16">Bachelor's Degree</option>
                                    <option value="18">Master's Degree</option>
                                    <option value="20">Doctoral Degree</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Area of Study</label>
                                <select class="form-select" name="area_of_study">
                                    <?php foreach ($study_areas as $code => $name): ?>
                                        <option value="<?php echo $code; ?>" <?php echo $code == 5 ? 'selected' : ''; ?>>
                                            <?php echo $name; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Experience Needed for Job</label>
                                <select class="form-select" name="experience_needed">
                                    <option value="1">No Experience</option>
                                    <option value="2">Less than 1 year</option>
                                    <option value="3">1-2 years</option>
                                    <option value="4" selected>3-5 years</option>
                                    <option value="5">5+ years</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Skills Assessment -->
                    <div class="form-section">
                        <h5><i class="fas fa-cogs me-2"></i>Skills Assessment</h5>
                        <p class="text-muted">Rate your skills on a scale of 1-5 (1 = Beginner, 5 = Expert)</p>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <!-- Leadership Skills -->
                                <div class="skill-slider mb-3">
                                    <label class="form-label">Influencing Others</label>
                                    <input type="range" class="form-range" name="influencing" 
                                           min="1" max="5" value="3" id="influencingRange">
                                    <div class="slider-label">
                                        <span>Beginner</span>
                                        <span id="influencingValue">3</span>
                                        <span>Expert</span>
                                    </div>
                                </div>
                                
                                <div class="skill-slider mb-3">
                                    <label class="form-label">Negotiating</label>
                                    <input type="range" class="form-range" name="negotiating" 
                                           min="1" max="5" value="3" id="negotiatingRange">
                                    <div class="slider-label">
                                        <span>Beginner</span>
                                        <span id="negotiatingValue">3</span>
                                        <span>Expert</span>
                                    </div>
                                </div>
                                
                                <div class="skill-slider mb-3">
                                    <label class="form-label">Advising/Counseling</label>
                                    <input type="range" class="form-range" name="advising" 
                                           min="1" max="5" value="3" id="advisingRange">
                                    <div class="slider-label">
                                        <span>Beginner</span>
                                        <span id="advisingValue">3</span>
                                        <span>Expert</span>
                                    </div>
                                </div>
                                
                                <div class="skill-slider mb-3">
                                    <label class="form-label">Instructing/Teaching</label>
                                    <input type="range" class="form-range" name="instructing" 
                                           min="1" max="5" value="2" id="instructingRange">
                                    <div class="slider-label">
                                        <span>Beginner</span>
                                        <span id="instructingValue">2</span>
                                        <span>Expert</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <!-- Technical Skills -->
                                <div class="skill-slider mb-3">
                                    <label class="form-label">Problem Solving (Quick)</label>
                                    <input type="range" class="form-range" name="problem_solving_quick" 
                                           min="1" max="5" value="4" id="problemQuickRange">
                                    <div class="slider-label">
                                        <span>Beginner</span>
                                        <span id="problemQuickValue">4</span>
                                        <span>Expert</span>
                                    </div>
                                </div>
                                
                                <div class="skill-slider mb-3">
                                    <label class="form-label">Problem Solving (Complex)</label>
                                    <input type="range" class="form-range" name="problem_solving_long" 
                                           min="1" max="5" value="4" id="problemLongRange">
                                    <div class="slider-label">
                                        <span>Beginner</span>
                                        <span id="problemLongValue">4</span>
                                        <span>Expert</span>
                                    </div>
                                </div>
                                
                                <div class="skill-slider mb-3">
                                    <label class="form-label">Computer Skills</label>
                                    <select class="form-select" name="computer_level">
                                        <option value="1">Basic</option>
                                        <option value="2" selected>Intermediate</option>
                                        <option value="3">Advanced</option>
                                        <option value="4">Expert</option>
                                    </select>
                                </div>
                                
                                <div class="skill-slider mb-3">
                                    <label class="form-label">Manual/Technical Skills</label>
                                    <input type="range" class="form-range" name="manual_skill" 
                                           min="1" max="5" value="2" id="manualRange">
                                    <div class="slider-label">
                                        <span>Beginner</span>
                                        <span id="manualValue">2</span>
                                        <span>Expert</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Work Environment -->
                    <div class="form-section">
                        <h5><i class="fas fa-building me-2"></i>Work Environment</h5>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Number of Subordinates</label>
                                <select class="form-select" name="no_subordinates">
                                    <option value="1" selected>None</option>
                                    <option value="2">1-5 people</option>
                                    <option value="3">6-10 people</option>
                                    <option value="4">11-25 people</option>
                                    <option value="5">25+ people</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Choose Your Hours</label>
                                <select class="form-select" name="choose_hours">
                                    <option value="1">No Control</option>
                                    <option value="2">Limited Control</option>
                                    <option value="3" selected>Some Control</option>
                                    <option value="4">Good Control</option>
                                    <option value="5">Complete Control</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Choose Work Methods</label>
                                <select class="form-select" name="choose_method">
                                    <option value="1">No Control</option>
                                    <option value="2">Limited Control</option>
                                    <option value="3">Some Control</option>
                                    <option value="4" selected>Good Control</option>
                                    <option value="5">Complete Control</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Keeping Skills Current</label>
                                <select class="form-select" name="keeping_current">
                                    <option value="1">Not Important</option>
                                    <option value="2">Slightly Important</option>
                                    <option value="3">Moderately Important</option>
                                    <option value="4" selected>Very Important</option>
                                    <option value="5">Extremely Important</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Factors -->
                    <div class="form-section">
                        <h5><i class="fas fa-plus-circle me-2"></i>Additional Factors</h5>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Job Satisfaction</label>
                                <select class="form-select" name="satisfaction">
                                    <option value="1">Very Dissatisfied</option>
                                    <option value="2" selected>Somewhat Satisfied</option>
                                    <option value="3">Satisfied</option>
                                    <option value="4">Very Satisfied</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Workforce Change</label>
                                <select class="form-select" name="workforce_change">
                                    <option value="0">Decreased</option>
                                    <option value="1" selected>Stable</option>
                                    <option value="2">Increased</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Qualification Needed</label>
                                <select class="form-select" name="qual_needed">
                                    <option value="0">Not Essential</option>
                                    <option value="1" selected>Essential</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Physical Labour</label>
                                <select class="form-select" name="labour">
                                    <option value="0">High Physical Labour</option>
                                    <option value="1" selected>Low Physical Labour</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Computer Usage</label>
                                <select class="form-select" name="computer">
                                    <option value="0">Rarely Use</option>
                                    <option value="1" selected>Regularly Use</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Group Meetings</label>
                                <select class="form-select" name="group_meetings">
                                    <option value="0">Rarely</option>
                                    <option value="1" selected>Regularly</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="text-center">
                        <button type="submit" class="btn btn-success btn-lg px-5">
                            <span id="predictText">
                                <i class="fas fa-chart-line me-2"></i>Predict My Salary
                            </span>
                            <span id="predictSpinner" class="d-none">
                                <i class="fas fa-spinner fa-spin me-2"></i>Analyzing...
                            </span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Results Column -->
            <div class="col-lg-4">
                <div class="sticky-top" style="top: 20px;">
                    <!-- Prediction Result -->
                    <div id="predictionResult" class="d-none">
                        <div class="prediction-result">
                            <h5><i class="fas fa-trophy me-2"></i>Predicted Salary</h5>
                            <div class="salary-amount" id="salaryAmount">$0</div>
                            <p class="mb-0">Estimated monthly salary</p>
                            <small class="opacity-75">Based on AI analysis of your profile</small>
                        </div>
                    </div>

                    <!-- Tips Card -->
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-lightbulb me-2"></i>Tips for Better Prediction</h6>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled mb-0">
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Be honest about your skill levels
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Select the industry that best matches your work
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Include all relevant qualifications
                                </li>
                                <li class="mb-0">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Consider your actual work environment
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize range sliders
            initializeRangeSliders();
            
            // Form submission
            $('#salaryPredictorForm').on('submit', function(e) {
                e.preventDefault();
                predictSalary();
            });
        });

        function initializeRangeSliders() {
            const sliders = [
                'influencing', 'negotiating', 'advising', 'instructing',
                'problemQuick', 'problemLong', 'manual'
            ];
            
            sliders.forEach(function(slider) {
                const element = document.getElementById(slider + 'Range');
                const valueDisplay = document.getElementById(slider + 'Value');
                
                if (element && valueDisplay) {
                    element.addEventListener('input', function() {
                        valueDisplay.textContent = this.value;
                    });
                }
            });
        }

        function predictSalary() {
            // Show loading state
            $('#predictText').addClass('d-none');
            $('#predictSpinner').removeClass('d-none');
            $('button[type="submit"]').prop('disabled', true);
            
            // Collect form data
            const formData = $('#salaryPredictorForm').serialize();
            
            makeAjaxRequest(
                '../processes/predict_salary.php',
                formData,
                function(response) {
                    displayPrediction(response.predicted_salary);
                    showAlert('success', 'Salary prediction completed successfully!');
                },
                function(error) {
                    showAlert('danger', 'Failed to predict salary: ' + error);
                }
            ).always(function() {
                // Reset button state
                $('#predictText').removeClass('d-none');
                $('#predictSpinner').addClass('d-none');
                $('button[type="submit"]').prop('disabled', false);
            });
        }

        function displayPrediction(salary) {
            // Format salary with currency
            const formattedSalary = new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: 'USD',
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(salary);
            
            $('#salaryAmount').text(formattedSalary);
            $('#predictionResult').removeClass('d-none').hide().fadeIn();
            
            // Smooth scroll to result
            $('html, body').animate({
                scrollTop: $('#predictionResult').offset().top - 100
            }, 1000);
        }
    </script>
</body>
</html>