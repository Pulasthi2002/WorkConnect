<?php
class SmartMatchingEngine {
    private $conn;
    
    public function __construct($database_connection) {
        $this->conn = $database_connection;
        if (!$this->conn) {
            throw new Exception("Database connection is null");
        }
    }
    
    /**
     * Calculate matching scores for all workers for a specific job
     */
    public function calculateJobMatches($job_id) {
        try {
            error_log("SmartMatchingEngine: Starting calculation for job $job_id");
            
            // Get job details
            $job = $this->getJobDetails($job_id);
            if (!$job) {
                error_log("SmartMatchingEngine: Job not found - $job_id");
                throw new Exception("Job not found");
            }
            
            error_log("SmartMatchingEngine: Job found - " . $job['title']);
            
            // Get customer preferences or use defaults
            $preferences = $this->getCustomerPreferences($job['client_id']);
            error_log("SmartMatchingEngine: Got preferences for client " . $job['client_id']);
            
            // Get all available workers
            $workers = $this->getAvailableWorkers();
            error_log("SmartMatchingEngine: Found " . count($workers) . " available workers");
            
            if (empty($workers)) {
                error_log("SmartMatchingEngine: No workers available");
                return true; // Not an error, just no workers
            }
            
            // Clear existing scores for this job
            $this->clearJobScores($job_id);
            error_log("SmartMatchingEngine: Cleared existing scores");
            
            $successful_calculations = 0;
            foreach ($workers as $worker) {
                try {
                    $scores = $this->calculateWorkerScore($job, $worker, $preferences);
                    if ($this->saveMatchingScore($job_id, $worker['worker_id'], $scores)) {
                        $successful_calculations++;
                    }
                } catch (Exception $e) {
                    error_log("SmartMatchingEngine: Error calculating score for worker " . $worker['worker_id'] . ": " . $e->getMessage());
                    // Continue with other workers
                }
            }
            
            error_log("SmartMatchingEngine: Successfully calculated scores for $successful_calculations workers");
            return true;
            
        } catch (Exception $e) {
            error_log("SmartMatchingEngine: calculateJobMatches error - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get job details with service information
     */
    private function getJobDetails($job_id) {
        try {
            $stmt = $this->conn->prepare("
                SELECT jp.*, s.service_name, sc.name as category_name, u.address as client_address
                FROM job_postings jp
                INNER JOIN services s ON jp.service_id = s.id
                INNER JOIN service_categories sc ON s.category_id = sc.id
                INNER JOIN users u ON jp.client_id = u.id
                WHERE jp.id = ? AND jp.status = 'open'
            ");
            
            if (!$stmt) {
                throw new Exception("Prepare failed for getJobDetails: " . $this->conn->error);
            }
            
            $stmt->bind_param("i", $job_id);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->fetch_assoc();
        } catch (Exception $e) {
            error_log("SmartMatchingEngine: getJobDetails error - " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get customer matching preferences or return defaults
     */
    private function getCustomerPreferences($customer_id) {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM customer_matching_preferences WHERE customer_id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $customer_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    return $result->fetch_assoc();
                }
            }
        } catch (Exception $e) {
            error_log("SmartMatchingEngine: getCustomerPreferences error - " . $e->getMessage());
        }
        
        // Return default preferences
        return [
            'skill_weight' => 0.30,
            'location_weight' => 0.20,
            'budget_weight' => 0.20,
            'experience_weight' => 0.15,
            'rating_weight' => 0.10,
            'availability_weight' => 0.05,
            'max_distance_km' => 50,
            'min_rating' => 3.00
        ];
    }
    
    /**
     * Get all available workers with their details
     */
    private function getAvailableWorkers() {
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    wp.id as worker_id,
                    wp.*,
                    u.name, u.address,
                    COUNT(ws.id) as skill_count,
                    COALESCE(AVG(CASE WHEN ja.status = 'accepted' THEN 1 ELSE 0 END), 0) as acceptance_rate
                FROM worker_profiles wp
                INNER JOIN users u ON wp.user_id = u.id
                LEFT JOIN worker_skills ws ON wp.id = ws.worker_id
                LEFT JOIN job_applications ja ON wp.id = ja.worker_id
                WHERE u.status = 'active' AND wp.is_available = 1
                GROUP BY wp.id
                HAVING wp.average_rating >= 0
            ");
            
            if (!$stmt) {
                throw new Exception("Prepare failed for getAvailableWorkers: " . $this->conn->error);
            }
            
            $stmt->execute();
            return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            error_log("SmartMatchingEngine: getAvailableWorkers error - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Calculate comprehensive matching score for a worker
     */
    private function calculateWorkerScore($job, $worker, $preferences) {
        $scores = [];
        
        // 1. Skill Match Score (0-100)
        $scores['skill_score'] = $this->calculateSkillScore($job, $worker);
        
        // 2. Location Score (0-100) 
        $scores['location_score'] = $this->calculateLocationScore($job, $worker, $preferences);
        
        // 3. Budget Compatibility Score (0-100)
        $scores['budget_score'] = $this->calculateBudgetScore($job, $worker);
        
        // 4. Experience Score (0-100)
        $scores['experience_score'] = $this->calculateExperienceScore($job, $worker);
        
        // 5. Rating/Reputation Score (0-100)
        $scores['rating_score'] = $this->calculateRatingScore($worker);
        
        // 6. Availability Score (0-100)
        $scores['availability_score'] = $this->calculateAvailabilityScore($worker);
        
        // Calculate weighted total score
        $scores['total_score'] = 
            ($scores['skill_score'] * $preferences['skill_weight']) +
            ($scores['location_score'] * $preferences['location_weight']) +
            ($scores['budget_score'] * $preferences['budget_weight']) +
            ($scores['experience_score'] * $preferences['experience_weight']) +
            ($scores['rating_score'] * $preferences['rating_weight']) +
            ($scores['availability_score'] * $preferences['availability_weight']);
        
        return $scores;
    }
    
    /**
     * Calculate skill matching score
     */
    private function calculateSkillScore($job, $worker) {
        try {
            // Check if worker has the exact skill
            $stmt = $this->conn->prepare("
                SELECT ws.skill_level 
                FROM worker_skills ws 
                WHERE ws.worker_id = ? AND ws.service_id = ?
            ");
            
            if (!$stmt) {
                return 10; // Default low score if query fails
            }
            
            $stmt->bind_param("ii", $worker['worker_id'], $job['service_id']);
            $stmt->execute();
            $skill = $stmt->get_result()->fetch_assoc();
            
            if ($skill) {
                // Perfect match - score based on skill level
                $level_scores = [
                    'beginner' => 70,
                    'intermediate' => 85,
                    'advanced' => 95,
                    'expert' => 100
                ];
                return $level_scores[$skill['skill_level']] ?? 70;
            }
            
            // Check if worker has skills in the same category
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) as related_skills
                FROM worker_skills ws
                INNER JOIN services s ON ws.service_id = s.id
                INNER JOIN services js ON s.category_id = js.category_id
                WHERE ws.worker_id = ? AND js.id = ?
            ");
            
            if ($stmt) {
                $stmt->bind_param("ii", $worker['worker_id'], $job['service_id']);
                $stmt->execute();
                $related = $stmt->get_result()->fetch_assoc();
                
                if ($related['related_skills'] > 0) {
                    return 40; // Related skills
                }
            }
            
            return 10; // No related skills
            
        } catch (Exception $e) {
            error_log("SmartMatchingEngine: calculateSkillScore error - " . $e->getMessage());
            return 10;
        }
    }
    
    /**
     * Calculate location proximity score
     */
    private function calculateLocationScore($job, $worker, $preferences) {
        // Simple location matching based on city/area
        $job_location = strtolower(trim($job['client_address'] ?? ''));
        $worker_location = strtolower(trim($worker['address'] ?? ''));
        
        if ($job_location === $worker_location) {
            return 100; // Same location
        }
        
        // Check if locations contain similar words
        $job_words = explode(' ', $job_location);
        $worker_words = explode(' ', $worker_location);
        
        $matches = count(array_intersect($job_words, $worker_words));
        
        if ($matches > 0) {
            return min(80, $matches * 20); // Partial location match
        }
        
        return 30; // Different locations but within country
    }
    
    /**
     * Calculate budget compatibility score
     */
    private function calculateBudgetScore($job, $worker) {
        if ($job['budget_type'] === 'negotiable') {
            return 80; // Assume good compatibility for negotiable
        }
        
        $worker_min = $worker['hourly_rate_min'] ?? 0;
        $worker_max = $worker['hourly_rate_max'] ?? $worker_min;
        
        if ($worker_min == 0 && $worker_max == 0) {
            return 70; // Worker hasn't set rates
        }
        
        $job_budget = $job['budget_max'] ?: $job['budget_min'];
        if (!$job_budget) {
            return 50;
        }
        
        // Assume hourly work for calculation
        $estimated_hours = 8; // Default estimation
        $hourly_budget = $job_budget / $estimated_hours;
        
        if ($worker_min <= $hourly_budget) {
            if ($worker_max <= $hourly_budget) {
                return 100; // Perfect fit
            } else {
                return 75; // Partially fits
            }
        }
        
        // Calculate how much over budget
        $over_budget = ($worker_min - $hourly_budget) / $hourly_budget;
        
        if ($over_budget < 0.2) { // 20% over
            return 60;
        } elseif ($over_budget < 0.5) { // 50% over
            return 30;
        } else {
            return 10;
        }
    }
    
    /**
     * Calculate experience score
     */
    private function calculateExperienceScore($job, $worker) {
        $experience = $worker['experience_years'] ?? 0;
        $total_jobs = $worker['total_jobs'] ?? 0;
        
        // Base score from years of experience
        $exp_score = min(100, $experience * 10);
        
        // Bonus from completed jobs
        $job_bonus = min(30, $total_jobs * 2);
        
        // Urgency factor
        if ($job['urgency'] === 'urgent' && $experience >= 3) {
            $exp_score += 10; // Bonus for urgent jobs
        }
        
        return min(100, $exp_score + $job_bonus);
    }
    
    /**
     * Calculate rating/reputation score
     */
    private function calculateRatingScore($worker) {
        $rating = $worker['average_rating'] ?? 0;
        $total_jobs = $worker['total_jobs'] ?? 0;
        
        if ($rating == 0) {
            return 50; // New worker
        }
        
        $score = ($rating / 5) * 100;
        
        // Bonus for workers with more reviews
        if ($total_jobs >= 10) {
            $score += 10;
        } elseif ($total_jobs >= 5) {
            $score += 5;
        }
        
        return min(100, $score);
    }
    
    /**
     * Calculate availability score
     */
    private function calculateAvailabilityScore($worker) {
        if ($worker['is_available']) {
            return 100;
        }
        return 0;
    }
    
    /**
     * Save matching scores to database
     */
    private function saveMatchingScore($job_id, $worker_id, $scores) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO worker_matching_scores 
                (job_id, worker_id, total_score, skill_score, location_score, budget_score, 
                 experience_score, rating_score, availability_score) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                total_score = VALUES(total_score),
                skill_score = VALUES(skill_score),
                location_score = VALUES(location_score),
                budget_score = VALUES(budget_score),
                experience_score = VALUES(experience_score),
                rating_score = VALUES(rating_score),
                availability_score = VALUES(availability_score)
            ");
            
            if (!$stmt) {
                throw new Exception("Prepare failed for saveMatchingScore: " . $this->conn->error);
            }
            
            $stmt->bind_param("iiidddddd", 
            $job_id, 
            $worker_id,
            $scores['total_score'],
            $scores['skill_score'],
            $scores['location_score'],
            $scores['budget_score'],
            $scores['experience_score'],
            $scores['rating_score'],
            $scores['availability_score']
        );

            
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("SmartMatchingEngine: saveMatchingScore error - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Clear existing scores for a job
     */
    private function clearJobScores($job_id) {
        try {
            $stmt = $this->conn->prepare("DELETE FROM worker_matching_scores WHERE job_id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $job_id);
                return $stmt->execute();
            }
        } catch (Exception $e) {
            error_log("SmartMatchingEngine: clearJobScores error - " . $e->getMessage());
        }
        return false;
    }
    
    /**
     * Get top matched workers for a job
     */
    public function getTopMatches($job_id, $limit = 10) {
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    wms.*,
                    u.name as worker_name,
                    u.email as worker_email,
                    u.telephone as worker_phone,
                    u.address as worker_address,
                    wp.bio, wp.experience_years, wp.average_rating, wp.total_jobs,
                    wp.hourly_rate_min, wp.hourly_rate_max, wp.is_available
                FROM worker_matching_scores wms
                INNER JOIN worker_profiles wp ON wms.worker_id = wp.id
                INNER JOIN users u ON wp.user_id = u.id
                WHERE wms.job_id = ? AND u.status = 'active'
                ORDER BY wms.total_score DESC
                LIMIT ?
            ");
            
            if (!$stmt) {
                throw new Exception("Prepare failed for getTopMatches: " . $this->conn->error);
            }
            
            $stmt->bind_param("ii", $job_id, $limit);
            $stmt->execute();
            return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            error_log("SmartMatchingEngine: getTopMatches error - " . $e->getMessage());
            return [];
        }
    }
}
?>
