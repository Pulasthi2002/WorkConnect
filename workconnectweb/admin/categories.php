<?php
require_once '../config.php';
require_once '../includes/session.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';

SessionManager::requireRole(['admin']);

// Get category statistics
$stats = [];
try {
    $result = $conn->query("SELECT COUNT(*) as total FROM service_categories");
    $stats['total_categories'] = $result->fetch_assoc()['total'];
    
    $result = $conn->query("SELECT COUNT(*) as active FROM service_categories WHERE is_active = 1");
    $stats['active_categories'] = $result->fetch_assoc()['active'];
    
    $result = $conn->query("SELECT COUNT(*) as total FROM services");
    $stats['total_services'] = $result->fetch_assoc()['total'];
    
    $result = $conn->query("SELECT COUNT(*) as active FROM services WHERE is_active = 1");
    $stats['active_services'] = $result->fetch_assoc()['active'];
    
} catch (Exception $e) {
    $stats = ['total_categories' => 0, 'active_categories' => 0, 'total_services' => 0, 'active_services' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories & Services - <?php echo APP_NAME; ?></title>
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
            <div class="col-12">
                <h2><i class="fas fa-tags text-warning me-2"></i>Categories & Services Management</h2>
                <p class="text-muted">Manage service categories and individual services</p>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-primary"><?php echo number_format($stats['total_categories']); ?></h3>
                        <p class="text-muted mb-0">Total Categories</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-success"><?php echo number_format($stats['active_categories']); ?></h3>
                        <p class="text-muted mb-0">Active Categories</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-info"><?php echo number_format($stats['total_services']); ?></h3>
                        <p class="text-muted mb-0">Total Services</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-warning"><?php echo number_format($stats['active_services']); ?></h3>
                        <p class="text-muted mb-0">Active Services</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <ul class="nav nav-tabs" id="managementTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="categories-tab" data-bs-toggle="tab" data-bs-target="#categories" type="button" role="tab">
                    <i class="fas fa-folder me-2"></i>Categories
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="services-tab" data-bs-toggle="tab" data-bs-target="#services" type="button" role="tab">
                    <i class="fas fa-cog me-2"></i>Services
                </button>
            </li>
        </ul>

        <div class="tab-content" id="managementTabsContent">
            <!-- Categories Tab -->
            <div class="tab-pane fade show active" id="categories" role="tabpanel">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Service Categories</h5>
                        <button class="btn btn-success" onclick="showAddCategoryModal()">
                            <i class="fas fa-plus me-2"></i>Add Category
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Icon</th>
                                        <th>Name</th>
                                        <th>Description</th>
                                        <th>Services</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="categoriesTableBody">
                                    <tr>
                                        <td colspan="7" class="text-center">
                                            <div class="spinner-border text-primary" role="status"></div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Services Tab -->
            <div class="tab-pane fade" id="services" role="tabpanel">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">Services</h5>
                            <div class="mt-2">
                                <select class="form-select" id="serviceCategoryFilter" style="width: 200px;">
                                    <option value="">All Categories</option>
                                    <!-- Options will be loaded dynamically -->
                                </select>
                            </div>
                        </div>
                        <button class="btn btn-success" onclick="showAddServiceModal()">
                            <i class="fas fa-plus me-2"></i>Add Service
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Service Name</th>
                                        <th>Category</th>
                                        <th>Description</th>
                                        <th>Jobs Posted</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="servicesTableBody">
                                    <tr>
                                        <td colspan="7" class="text-center">
                                            <div class="spinner-border text-primary" role="status"></div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Category Modal -->
    <div class="modal fade" id="categoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="categoryModalTitle">Add Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="categoryForm">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                        <input type="hidden" name="category_id" id="editCategoryId">
                        
                        <div class="mb-3">
                            <label class="form-label">Category Name *</label>
                            <input type="text" class="form-control" name="name" required maxlength="100">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3" maxlength="500"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Icon Class</label>
                            <input type="text" class="form-control" name="icon" placeholder="e.g., fas fa-tools" value="fas fa-tools">
                            <div class="form-text">FontAwesome icon class (e.g., fas fa-tools)</div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" id="categoryActive" checked>
                                <label class="form-check-label" for="categoryActive">
                                    Active
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <span id="categorySubmitText"><i class="fas fa-save me-2"></i>Save Category</span>
                            <span id="categorySubmitSpinner" class="d-none"><i class="fas fa-spinner fa-spin me-2"></i>Saving...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Service Modal -->
    <div class="modal fade" id="serviceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="serviceModalTitle">Add Service</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="serviceForm">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                        <input type="hidden" name="service_id" id="editServiceId">
                        
                        <div class="mb-3">
                            <label class="form-label">Service Name *</label>
                            <input type="text" class="form-control" name="service_name" required maxlength="150">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Category *</label>
                            <select class="form-select" name="category_id" id="serviceCategory" required>
                                <option value="">Select Category</option>
                                <!-- Options will be loaded dynamically -->
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3" maxlength="500"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" id="serviceActive" checked>
                                <label class="form-check-label" for="serviceActive">
                                    Active
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <span id="serviceSubmitText"><i class="fas fa-save me-2"></i>Save Service</span>
                            <span id="serviceSubmitSpinner" class="d-none"><i class="fas fa-spinner fa-spin me-2"></i>Saving...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js"></script>
    <script>
    $(document).ready(function() {
        loadCategories();
        loadServices();
        loadCategoryOptions();

        // Tab change handlers
        $('#categories-tab').on('shown.bs.tab', function() {
            loadCategories();
        });

        $('#services-tab').on('shown.bs.tab', function() {
            loadServices();
        });

        // Service category filter
        $('#serviceCategoryFilter').on('change', function() {
            loadServices();
        });

        // Form submissions
        $('#categoryForm').on('submit', function(e) {
            e.preventDefault();
            saveCategory();
        });

        $('#serviceForm').on('submit', function(e) {
            e.preventDefault();
            saveService();
        });
    });

    function loadCategories() {
        $('#categoriesTableBody').html('<tr><td colspan="7" class="text-center"><div class="spinner-border text-primary"></div></td></tr>');

        makeAjaxRequest(
            '../processes/get_admin_categories.php',
            { csrf_token: '<?php echo Security::generateCSRFToken(); ?>' },
            function(response) {
                $('#categoriesTableBody').html(response.categories_html);
            },
            function(error) {
                $('#categoriesTableBody').html('<tr><td colspan="7" class="text-center text-danger">' + error + '</td></tr>');
            }
        );
    }

    function loadServices() {
        $('#servicesTableBody').html('<tr><td colspan="7" class="text-center"><div class="spinner-border text-primary"></div></td></tr>');

        const searchData = {
            category_id: $('#serviceCategoryFilter').val(),
            csrf_token: '<?php echo Security::generateCSRFToken(); ?>'
        };

        makeAjaxRequest(
            '../processes/get_admin_services.php',
            searchData,
            function(response) {
                $('#servicesTableBody').html(response.services_html);
            },
            function(error) {
                $('#servicesTableBody').html('<tr><td colspan="7" class="text-center text-danger">' + error + '</td></tr>');
            }
        );
    }

    function loadCategoryOptions() {
        makeAjaxRequest(
            '../processes/get_admin_categories.php',
            { options_only: true, csrf_token: '<?php echo Security::generateCSRFToken(); ?>' },
            function(response) {
                $('#serviceCategory').html('<option value="">Select Category</option>' + response.category_options);
                $('#serviceCategoryFilter').html('<option value="">All Categories</option>' + response.category_options);
            }
        );
    }

    function showAddCategoryModal() {
        $('#categoryModalTitle').text('Add Category');
        $('#editCategoryId').val('');
        $('#categoryForm')[0].reset();
        $('#categoryActive').prop('checked', true);
        $('#categoryModal').modal('show');
    }

    function editCategory(categoryId) {
        $('#categoryModalTitle').text('Edit Category');
        $('#editCategoryId').val(categoryId);

        // Load category data
        makeAjaxRequest(
            '../processes/get_category_details.php',
            { category_id: categoryId, csrf_token: '<?php echo Security::generateCSRFToken(); ?>' },
            function(response) {
                const category = response.category;
                $('input[name="name"]').val(category.name);
                $('textarea[name="description"]').val(category.description);
                $('input[name="icon"]').val(category.icon);
                $('#categoryActive').prop('checked', category.is_active == 1);
                $('#categoryModal').modal('show');
            },
            function(error) {
                showAlert('danger', 'Failed to load category data: ' + error);
            }
        );
    }

    function saveCategory() {
        const $button = $('#categoryForm button[type="submit"]');
        const isEditing = $('#editCategoryId').val() !== '';
        
        $('#categorySubmitText').addClass('d-none');
        $('#categorySubmitSpinner').removeClass('d-none');
        $button.prop('disabled', true);

        const endpoint = isEditing ? '../processes/admin_update_category.php' : '../processes/admin_add_category.php';
        
        makeAjaxRequest(
            endpoint,
            $('#categoryForm').serialize(),
            function(response) {
                $('#categoryModal').modal('hide');
                showAlert('success', response.message);
                loadCategories();
                loadCategoryOptions();
            },
            function(error) {
                showAlert('danger', error);
            }
        ).always(function() {
            $('#categorySubmitText').removeClass('d-none');
            $('#categorySubmitSpinner').addClass('d-none');
            $button.prop('disabled', false);
        });
    }

    function deleteCategory(categoryId, categoryName) {
        if (confirm(`Are you sure you want to delete category "${categoryName}"? This will also affect all services in this category.`)) {
            makeAjaxRequest(
                '../processes/admin_delete_category.php',
                { category_id: categoryId, csrf_token: '<?php echo Security::generateCSRFToken(); ?>' },
                function(response) {
                    showAlert('success', response.message);
                    loadCategories();
                    loadServices();
                    loadCategoryOptions();
                },
                function(error) {
                    showAlert('danger', error);
                }
            );
        }
    }

    function showAddServiceModal() {
        $('#serviceModalTitle').text('Add Service');
        $('#editServiceId').val('');
        $('#serviceForm')[0].reset();
        $('#serviceActive').prop('checked', true);
        $('#serviceModal').modal('show');
    }

    function editService(serviceId) {
        $('#serviceModalTitle').text('Edit Service');
        $('#editServiceId').val(serviceId);

        // Load service data
        makeAjaxRequest(
            '../processes/get_service_details.php',
            { service_id: serviceId, csrf_token: '<?php echo Security::generateCSRFToken(); ?>' },
            function(response) {
                const service = response.service;
                $('input[name="service_name"]').val(service.service_name);
                $('select[name="category_id"]').val(service.category_id);
                $('textarea[name="description"]').val(service.description);
                $('#serviceActive').prop('checked', service.is_active == 1);
                $('#serviceModal').modal('show');
            },
            function(error) {
                showAlert('danger', 'Failed to load service data: ' + error);
            }
        );
    }

    function saveService() {
        const $button = $('#serviceForm button[type="submit"]');
        const isEditing = $('#editServiceId').val() !== '';
        
        $('#serviceSubmitText').addClass('d-none');
        $('#serviceSubmitSpinner').removeClass('d-none');
        $button.prop('disabled', true);

        const endpoint = isEditing ? '../processes/admin_update_service.php' : '../processes/admin_add_service.php';
        
        makeAjaxRequest(
            endpoint,
            $('#serviceForm').serialize(),
            function(response) {
                $('#serviceModal').modal('hide');
                showAlert('success', response.message);
                loadServices();
            },
            function(error) {
                showAlert('danger', error);
            }
        ).always(function() {
            $('#serviceSubmitText').removeClass('d-none');
            $('#serviceSubmitSpinner').addClass('d-none');
            $button.prop('disabled', false);
        });
    }

    function deleteService(serviceId, serviceName) {
        if (confirm(`Are you sure you want to delete service "${serviceName}"?`)) {
            makeAjaxRequest(
                '../processes/admin_delete_service.php',
                { service_id: serviceId, csrf_token: '<?php echo Security::generateCSRFToken(); ?>' },
                function(response) {
                    showAlert('success', response.message);
                    loadServices();
                },
                function(error) {
                    showAlert('danger', error);
                }
            );
        }
    }
    </script>
</body>
</html>
