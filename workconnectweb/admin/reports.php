<?php
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';

SessionManager::requireRole(['admin']);

// Get comprehensive platform statistics
$stats = [];
try {
    // User stats
    $result = $conn->query("
        SELECT 
            COUNT(*) as total_users,
            SUM(CASE WHEN role = 'customer' THEN 1 ELSE 0 END) as customers,
            SUM(CASE WHEN role = 'worker' THEN 1 ELSE 0 END) as workers,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_users,
            SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_users_month
        FROM users WHERE role != 'admin'
    ");
    $user_stats = $result->fetch_assoc();
    
    // Job stats
    $result = $conn->query("
        SELECT 
            COUNT(*) as total_jobs,
            SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_jobs,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_jobs,
            AVG(CASE WHEN budget_min IS NOT NULL AND budget_max IS NOT NULL 
                     THEN (budget_min + budget_max) / 2 ELSE NULL END) as avg_budget
        FROM job_postings
    ");
    $job_stats = $result->fetch_assoc();
    
    // Application stats
    $result = $conn->query("
        SELECT 
            COUNT(*) as total_applications,
            AVG(proposed_rate) as avg_proposed_rate,
            (COUNT(*) / NULLIF((SELECT COUNT(*) FROM job_postings WHERE status = 'open'), 0)) as apps_per_job
        FROM job_applications
    ");
    $app_stats = $result->fetch_assoc();
    
    // Monthly growth data for charts
    $monthly_data = [];
    $stmt = $conn->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as count,
            'users' as type
        FROM users 
        WHERE role != 'admin' AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        
        UNION ALL
        
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as count,
            'jobs' as type
        FROM job_postings 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        
        ORDER BY month
    ");
    $stmt->execute();
    $monthly_result = $stmt->get_result();
    
    while ($row = $monthly_result->fetch_assoc()) {
        $monthly_data[$row['month']][$row['type']] = $row['count'];
    }
    
    $stats = array_merge($user_stats, $job_stats, $app_stats);
    
} catch (Exception $e) {
    error_log("Reports error: " . $e->getMessage());
    $stats = array_fill_keys([
        'total_users', 'customers', 'workers', 'active_users', 'new_users_month',
        'total_jobs', 'open_jobs', 'completed_jobs', 'avg_budget',
        'total_applications', 'avg_proposed_rate', 'apps_per_job'
    ], 0);
    $monthly_data = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Platform Reports - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-danger">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-tools me-2"></i><?php echo APP_NAME; ?> Admin
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-md-8">
                <h2><i class="fas fa-chart-bar text-primary me-2"></i>Platform Reports</h2>
                <p class="text-muted">Comprehensive analytics and insights</p>
            </div>
            <div class="col-md-4 text-md-end">
                <button class="btn btn-success me-2" onclick="exportReport('csv')">
                    <i class="fas fa-file-csv me-1"></i>Export CSV
                </button>
                <button class="btn btn-primary" onclick="exportReport('pdf')">
                    <i class="fas fa-file-pdf me-1"></i>Export PDF
                </button>
            </div>
        </div>

        <!-- Key Metrics -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h3 class="mb-1"><?php echo number_format($stats['total_users']); ?></h3>
                                <p class="mb-0">Total Users</p>
                                <small class="opacity-75">+<?php echo $stats['new_users_month']; ?> this month</small>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-users fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h3 class="mb-1"><?php echo number_format($stats['total_jobs']); ?></h3>
                                <p class="mb-0">Total Jobs</p>
                                <small class="opacity-75"><?php echo $stats['open_jobs']; ?> currently open</small>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-briefcase fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h3 class="mb-1"><?php echo number_format($stats['total_applications']); ?></h3>
                                <p class="mb-0">Applications</p>
                                <small class="opacity-75"><?php echo number_format($stats['apps_per_job'], 1); ?> per job avg</small>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-file-alt fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h3 class="mb-1"><?php echo Functions::formatMoney($stats['avg_budget']); ?></h3>
                                <p class="mb-0">Avg Job Budget</p>
                                <small class="opacity-75"><?php echo $stats['completed_jobs']; ?> completed jobs</small>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-money-bill-wave fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row mb-4">
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">User Distribution</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="userDistributionChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Job Status Distribution</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="jobStatusChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Growth Chart -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Platform Growth (Last 12 Months)</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="growthChart" width="400" height="150"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Statistics -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">User Statistics</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6">
                                <div class="text-center">
                                    <h4 class="text-primary"><?php echo number_format($stats['customers']); ?></h4>
                                    <p class="text-muted mb-0">Customers</p>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-center">
                                    <h4 class="text-success"><?php echo number_format($stats['workers']); ?></h4>
                                    <p class="text-muted mb-0">Workers</p>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between">
                            <span>Active Users:</span>
                            <strong><?php echo number_format($stats['active_users']); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Activation Rate:</span>
                            <strong><?php echo number_format(($stats['active_users'] / max($stats['total_users'], 1)) * 100, 1); ?>%</strong>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Financial Overview</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Average Job Budget:</span>
                            <strong><?php echo Functions::formatMoney($stats['avg_budget']); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Average Proposal Rate:</span>
                            <strong><?php echo Functions::formatMoney($stats['avg_proposed_rate']); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Total Platform Value:</span>
                            <strong><?php echo Functions::formatMoney($stats['avg_budget'] * $stats['total_jobs']); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Completion Rate:</span>
                            <strong><?php echo number_format(($stats['completed_jobs'] / max($stats['total_jobs'], 1)) * 100, 1); ?>%</strong>
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
        // User Distribution Chart
        const userCtx = document.getElementById('userDistributionChart').getContext('2d');
        new Chart(userCtx, {
            type: 'doughnut',
            data: {
                labels: ['Customers', 'Workers'],
                datasets: [{
                    data: [<?php echo $stats['customers']; ?>, <?php echo $stats['workers']; ?>],
                    backgroundColor: ['#007bff', '#28a745'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Job Status Chart
        const jobCtx = document.getElementById('jobStatusChart').getContext('2d');
        new Chart(jobCtx, {
            type: 'doughnut',
            data: {
                labels: ['Open', 'Completed', 'Other'],
                datasets: [{
                    data: [
                        <?php echo $stats['open_jobs']; ?>, 
                        <?php echo $stats['completed_jobs']; ?>, 
                        <?php echo $stats['total_jobs'] - $stats['open_jobs'] - $stats['completed_jobs']; ?>
                    ],
                    backgroundColor: ['#ffc107', '#28a745', '#6c757d'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Growth Chart
        const growthCtx = document.getElementById('growthChart').getContext('2d');
        const monthlyData = <?php echo json_encode($monthly_data); ?>;
        
        const months = Object.keys(monthlyData).sort();
        const userData = months.map(month => monthlyData[month]?.users || 0);
        const jobData = months.map(month => monthlyData[month]?.jobs || 0);

        new Chart(growthCtx, {
            type: 'line',
            data: {
                labels: months,
                datasets: [{
                    label: 'New Users',
                    data: userData,
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    tension: 0.4
                }, {
                    label: 'New Jobs',
                    data: jobData,
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                },
                plugins: {
                    legend: {
                        position: 'top'
                    }
                }
            }
        });

        function exportReport(format) {
            if (format === 'csv') {
                exportCSV();
            } else if (format === 'pdf') {
                window.print();
            }
        }

        function exportCSV() {
            const data = [
                ['Metric', 'Value'],
                ['Total Users', '<?php echo $stats['total_users']; ?>'],
                ['Customers', '<?php echo $stats['customers']; ?>'],
                ['Workers', '<?php echo $stats['workers']; ?>'],
                ['Active Users', '<?php echo $stats['active_users']; ?>'],
                ['New Users This Month', '<?php echo $stats['new_users_month']; ?>'],
                ['Total Jobs', '<?php echo $stats['total_jobs']; ?>'],
                ['Open Jobs', '<?php echo $stats['open_jobs']; ?>'],
                ['Completed Jobs', '<?php echo $stats['completed_jobs']; ?>'],
                ['Total Applications', '<?php echo $stats['total_applications']; ?>'],
                ['Average Job Budget', '<?php echo number_format($stats['avg_budget'], 2); ?>'],
                ['Average Proposal Rate', '<?php echo number_format($stats['avg_proposed_rate'], 2); ?>']
            ];

            let csvContent = data.map(row => row.join(',')).join('\n');
            
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'platform_report_' + new Date().toISOString().slice(0, 10) + '.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>
