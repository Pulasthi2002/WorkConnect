<?php
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';

SessionManager::requireRole(['admin']);

// Get user statistics
$stats = [];
try {
    $result = $conn->query("SELECT COUNT(*) as total FROM users WHERE role != 'admin'");
    $stats['total_users'] = $result->fetch_assoc()['total'];
    
    $result = $conn->query("SELECT COUNT(*) as customers FROM users WHERE role = 'customer'");
    $stats['customers'] = $result->fetch_assoc()['customers'];
    
    $result = $conn->query("SELECT COUNT(*) as workers FROM users WHERE role = 'worker'");
    $stats['workers'] = $result->fetch_assoc()['workers'];
    
    $result = $conn->query("SELECT COUNT(*) as active FROM users WHERE status = 'active' AND role != 'admin'");
    $stats['active_users'] = $result->fetch_assoc()['active'];
    
} catch (Exception $e) {
    $stats = ['total_users' => 0, 'customers' => 0, 'workers' => 0, 'active_users' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
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
                <h2><i class="fas fa-users text-primary me-2"></i>User Management</h2>
                <p class="text-muted">Manage all platform users and their accounts</p>
            </div>
            <div class="col-md-4 text-end">
                <button class="btn btn-success" onclick="showAddUserModal()">
                    <i class="fas fa-plus me-2"></i>Add New User
                </button>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-primary"><?php echo number_format($stats['total_users']); ?></h3>
                        <p class="text-muted mb-0">Total Users</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-info"><?php echo number_format($stats['customers']); ?></h3>
                        <p class="text-muted mb-0">Customers</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-success"><?php echo number_format($stats['workers']); ?></h3>
                        <p class="text-muted mb-0">Workers</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-warning"><?php echo number_format($stats['active_users']); ?></h3>
                        <p class="text-muted mb-0">Active Users</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search and Filters -->
        <div class="row mb-4">
            <div class="col-md-4">
                <input type="text" class="form-control" id="searchUsers" placeholder="Search users...">
            </div>
            <div class="col-md-2">
                <select class="form-select" id="roleFilter">
                    <option value="">All Roles</option>
                    <option value="customer">Customer</option>
                    <option value="worker">Worker</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" id="statusFilter">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="disabled">Disabled</option>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100" onclick="loadUsers()">
                    <i class="fas fa-search me-1"></i>Search
                </button>
            </div>
            <div class="col-md-2">
                <button class="btn btn-secondary w-100" onclick="exportUsers()">
                    <i class="fas fa-download me-1"></i>Export
                </button>
            </div>
        </div>

        <!-- Users Table -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Joined</th>
                                <th>Jobs/Applications</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="usersTableBody">
                            <tr>
                                <td colspan="7" class="text-center">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading users...</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <nav aria-label="Users pagination">
                    <ul class="pagination justify-content-center" id="usersPagination">
                        <!-- Pagination will be generated by JavaScript -->
                    </ul>
                </nav>
            </div>
        </div>
    </div>

    <!-- Add/Edit User Modal -->
    <div class="modal fade" id="userModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="userModalTitle">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="userForm">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                        <input type="hidden" name="user_id" id="editUserId">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name *</label>
                                <input type="text" class="form-control" name="name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" class="form-control" name="email" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone Number *</label>
                                <input type="tel" class="form-control" name="telephone" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Role *</label>
                                <select class="form-select" name="role" required>
                                    <option value="customer">Customer</option>
                                    <option value="worker">Worker</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Address *</label>
                            <input type="text" class="form-control" name="address" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="active">Active</option>
                                    <option value="disabled">Disabled</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3" id="passwordField">
                                <label class="form-label">Password</label>
                                <input type="password" class="form-control" name="password" placeholder="Leave empty to keep current">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <span id="userSubmitText"><i class="fas fa-save me-2"></i>Save User</span>
                            <span id="userSubmitSpinner" class="d-none"><i class="fas fa-spinner fa-spin me-2"></i>Saving...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- User Details Modal -->
    <div class="modal fade" id="userDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">User Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="userDetailsContent">
                    <!-- User details will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js"></script>
    <script>
    let currentPage = 1;
    const usersPerPage = 10;

    $(document).ready(function() {
        loadUsers();

        // Search handlers
        $('#searchUsers').on('keyup', debounce(loadUsers, 500));
        $('#roleFilter, #statusFilter').on('change', loadUsers);

        // Form submission
        $('#userForm').on('submit', function(e) {
            e.preventDefault();
            saveUser();
        });
    });

    function loadUsers(page = 1) {
        currentPage = page;
        const searchData = {
            search: $('#searchUsers').val(),
            role: $('#roleFilter').val(),
            status: $('#statusFilter').val(),
            page: page,
            per_page: usersPerPage,
            csrf_token: '<?php echo Security::generateCSRFToken(); ?>'
        };

        $('#usersTableBody').html('<tr><td colspan="7" class="text-center"><div class="spinner-border text-primary"></div></td></tr>');

        makeAjaxRequest(
            '../processes/get_admin_users.php',
            searchData,
            function(response) {
                $('#usersTableBody').html(response.users_html);
                generatePagination(response.total_users, response.current_page, response.total_pages);
            },
            function(error) {
                $('#usersTableBody').html('<tr><td colspan="7" class="text-center text-danger">' + error + '</td></tr>');
            }
        );
    }

    function generatePagination(totalUsers, currentPage, totalPages) {
        let paginationHtml = '';
        
        if (totalPages > 1) {
            // Previous button
            paginationHtml += `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="loadUsers(${currentPage - 1})">Previous</a>
            </li>`;
            
            // Page numbers
            for (let i = Math.max(1, currentPage - 2); i <= Math.min(totalPages, currentPage + 2); i++) {
                paginationHtml += `<li class="page-item ${i === currentPage ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="loadUsers(${i})">${i}</a>
                </li>`;
            }
            
            // Next button
            paginationHtml += `<li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="loadUsers(${currentPage + 1})">Next</a>
            </li>`;
        }
        
        $('#usersPagination').html(paginationHtml);
    }

    function showAddUserModal() {
        $('#userModalTitle').text('Add New User');
        $('#editUserId').val('');
        $('#userForm')[0].reset();
        $('#passwordField label').text('Password *');
        $('#passwordField input').attr('required', true).attr('placeholder', 'Enter password');
        $('#userModal').modal('show');
    }

    function editUser(userId) {
        $('#userModalTitle').text('Edit User');
        $('#editUserId').val(userId);
        $('#passwordField label').text('Password');
        $('#passwordField input').removeAttr('required').attr('placeholder', 'Leave empty to keep current');

        // Load user data
        makeAjaxRequest(
            '../processes/get_user_details.php',
            { user_id: userId, csrf_token: '<?php echo Security::generateCSRFToken(); ?>' },
            function(response) {
                const user = response.user;
                $('input[name="name"]').val(user.name);
                $('input[name="email"]').val(user.email);
                $('input[name="telephone"]').val(user.telephone);
                $('input[name="address"]').val(user.address);
                $('select[name="role"]').val(user.role);
                $('select[name="status"]').val(user.status);
                $('#userModal').modal('show');
            },
            function(error) {
                showAlert('danger', 'Failed to load user data: ' + error);
            }
        );
    }

    function saveUser() {
        const $button = $('#userForm button[type="submit"]');
        const isEditing = $('#editUserId').val() !== '';
        
        $('#userSubmitText').addClass('d-none');
        $('#userSubmitSpinner').removeClass('d-none');
        $button.prop('disabled', true);

        const endpoint = isEditing ? '../processes/admin_update_user.php' : '../processes/admin_add_user.php';
        
        makeAjaxRequest(
            endpoint,
            $('#userForm').serialize(),
            function(response) {
                $('#userModal').modal('hide');
                showAlert('success', response.message);
                loadUsers(currentPage);
            },
            function(error) {
                showAlert('danger', error);
            }
        ).always(function() {
            $('#userSubmitText').removeClass('d-none');
            $('#userSubmitSpinner').addClass('d-none');
            $button.prop('disabled', false);
        });
    }

    function viewUser(userId) {
        $('#userDetailsContent').html('<div class="text-center"><div class="spinner-border text-primary"></div></div>');
        $('#userDetailsModal').modal('show');

        makeAjaxRequest(
            '../processes/get_user_details.php',
            { user_id: userId, detailed: true, csrf_token: '<?php echo Security::generateCSRFToken(); ?>' },
            function(response) {
                $('#userDetailsContent').html(response.user_html);
            },
            function(error) {
                $('#userDetailsContent').html('<div class="alert alert-danger">' + error + '</div>');
            }
        );
    }

    function deleteUser(userId, userName) {
        if (confirm(`Are you sure you want to delete user "${userName}"? This action cannot be undone.`)) {
            makeAjaxRequest(
                '../processes/admin_delete_user.php',
                { user_id: userId, csrf_token: '<?php echo Security::generateCSRFToken(); ?>' },
                function(response) {
                    showAlert('success', response.message);
                    loadUsers(currentPage);
                },
                function(error) {
                    showAlert('danger', error);
                }
            );
        }
    }

    function exportUsers() {
        window.open('../processes/export_users.php?csrf_token=<?php echo Security::generateCSRFToken(); ?>', '_blank');
    }

    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    </script>
</body>
</html>
