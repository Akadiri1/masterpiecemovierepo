<?php
ob_start();
$level_check = ['MASTER'];
include 'includes/header.php';

// Get statistics
$totalUsers = count(selectContent($conn, 'users', []));

// Today's registrations
$todayWhere = [];
$allUsers = selectContent($conn, 'users', []);
$today = date('Y-m-d');
$todayCount = 0;
$weekCount = 0;
$monthCount = 0;
$weekAgo = date('Y-m-d', strtotime('-7 days'));
$monthAgo = date('Y-m-d', strtotime('-30 days'));

foreach ($allUsers as $user) {
    $regDate = date('Y-m-d', strtotime($user['date_created']));
    if ($regDate == $today) {
        $todayCount++;
    }
    if ($regDate >= $weekAgo) {
        $weekCount++;
    }
    if ($regDate >= $monthAgo) {
        $monthCount++;
    }
}

// Recent registrations (last 20)
$recentUsers = array_slice(array_reverse($allUsers), 0, 20);

// Prepare chart data - last 7 days
$chartLabels = [];
$chartData = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chartLabels[] = date('M d', strtotime($date));
    $count = 0;
    foreach ($allUsers as $user) {
        if (date('Y-m-d', strtotime($user['date_created'])) == $date) {
            $count++;
        }
    }
    $chartData[] = $count;
}
?>

<!-- [ Header ] end -->
<div class="container">
  <div class="wrapper">
    <div class="content">
      <div class="main-body">
        <div class="page-wrapper">
          
          <!-- Page Header -->
          <div class="page-header">
            <div class="page-block">
              <div class="row align-items-center">
                <div class="col-md-12">
                  <div class="page-header-title">
                    <h5>Registration Dashboard</h5>
                  </div>
                  <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/admin"><i class="feather icon-home"></i></a></li>
                    <li class="breadcrumb-item"><a href="#!">Registration Dashboard</a></li>
                  </ul>
                </div>
              </div>
            </div>
          </div>

          <!-- Stats Cards Row -->
          <div class="row">
            <!-- Total Users -->
            <div class="col-md-6 col-xl-3">
              <div class="card bg-c-blue order-card">
                <div class="card-body">
                  <h6 class="text-white">Total Users</h6>
                  <h2 class="text-white"><?php echo $totalUsers; ?></h2>
                  <p class="m-b-0 text-white">All registered accounts</p>
                  <i class="card-icon feather icon-users"></i>
                </div>
              </div>
            </div>
            
            <!-- Today's Registrations -->
            <div class="col-md-6 col-xl-3">
              <div class="card bg-c-green order-card">
                <div class="card-body">
                  <h6 class="text-white">Today</h6>
                  <h2 class="text-white"><?php echo $todayCount; ?></h2>
                  <p class="m-b-0 text-white">New sign ups today</p>
                  <i class="card-icon feather icon-user-plus"></i>
                </div>
              </div>
            </div>
            
            <!-- This Week -->
            <div class="col-md-6 col-xl-3">
              <div class="card bg-c-yellow order-card">
                <div class="card-body">
                  <h6 class="text-white">This Week</h6>
                  <h2 class="text-white"><?php echo $weekCount; ?></h2>
                  <p class="m-b-0 text-white">Last 7 days</p>
                  <i class="card-icon feather icon-calendar"></i>
                </div>
              </div>
            </div>
            
            <!-- This Month -->
            <div class="col-md-6 col-xl-3">
              <div class="card bg-c-red order-card">
                <div class="card-body">
                  <h6 class="text-white">This Month</h6>
                  <h2 class="text-white"><?php echo $monthCount; ?></h2>
                  <p class="m-b-0 text-white">Last 30 days</p>
                  <i class="card-icon feather icon-trending-up"></i>
                </div>
              </div>
            </div>
          </div>

          <!-- Chart Row -->
          <div class="row">
            <div class="col-xl-12">
              <div class="card">
                <div class="card-header">
                  <h5>Registration Trend (Last 7 Days)</h5>
                </div>
                <div class="card-body">
                  <canvas id="registrationChart" height="100"></canvas>
                </div>
              </div>
            </div>
          </div>

          <!-- Recent Registrations Table -->
          <div class="row">
            <div class="col-sm-12">
              <div class="card">
                <div class="card-header">
                  <h5>Recent Registrations</h5>
                  <span class="text-muted">Last 20 users who signed up</span>
                </div>
                <div class="card-body">
                  <div class="dt-responsive table-responsive">
                    <table id="registrationTable" class="table table-striped table-bordered nowrap">
                      <thead>
                        <tr>
                          <th>#</th>
                          <th>Name</th>
                          <th>Email</th>
                          <th>Phone</th>
                          <th>Status</th>
                          <th>Registered</th>
                          <th>Actions</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php 
                        $counter = 1;
                        foreach ($recentUsers as $user): 
                          $status = '';
                          $statusClass = '';
                          if ($user['user_status'] == 1) {
                            $status = 'Active';
                            $statusClass = 'badge-success';
                          } elseif ($user['user_status'] == NULL || $user['verification'] != 1) {
                            $status = 'Unverified';
                            $statusClass = 'badge-warning';
                          } else {
                            $status = 'Suspended';
                            $statusClass = 'badge-danger';
                          }
                        ?>
                        <tr>
                          <td><?php echo $counter++; ?></td>
                          <td>
                            <strong><?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?></strong>
                          </td>
                          <td>
                            <a href="mailto:<?php echo htmlspecialchars($user['email']); ?>">
                              <?php echo htmlspecialchars($user['email']); ?>
                            </a>
                          </td>
                          <td><?php echo htmlspecialchars($user['phone_number'] ?? 'N/A'); ?></td>
                          <td><span class="badge <?php echo $statusClass; ?>"><?php echo $status; ?></span></td>
                          <td>
                            <?php echo date('M d, Y H:i', strtotime($user['date_created'])); ?>
                          </td>
                          <td>
                            <button type="button" class="btn btn-info btn-sm view-user-btn" 
                                    data-toggle="modal" 
                                    data-target="#userModal"
                                    data-id="<?php echo $user['id']; ?>"
                                    data-firstname="<?php echo htmlspecialchars($user['firstname']); ?>"
                                    data-lastname="<?php echo htmlspecialchars($user['lastname']); ?>"
                                    data-email="<?php echo htmlspecialchars($user['email']); ?>"
                                    data-phone="<?php echo htmlspecialchars($user['phone_number'] ?? 'N/A'); ?>"
                                    data-hashid="<?php echo htmlspecialchars($user['hash_id']); ?>"
                                    data-status="<?php echo $status; ?>"
                                    data-created="<?php echo $user['date_created']; ?>"
                                    data-kids="<?php echo isset($user['is_kids_mode']) && $user['is_kids_mode'] == 1 ? 'Yes' : 'No'; ?>">
                              <i class="feather icon-eye"></i> View
                            </button>
                            <?php if ($user['user_status'] != 1): ?>
                            <a href="/updateContent.php?id=<?php echo $user['id']; ?>&user_status=1&verification=1&data=users" class="btn btn-success btn-sm">
                              <i class="feather icon-check"></i> Verify
                            </a>
                            <?php endif; ?>
                          </td>
                        </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>
</div>

<!-- User Details Modal -->
<div class="modal fade" id="userModal" tabindex="-1" role="dialog" aria-labelledby="userModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header bg-primary">
        <h5 class="modal-title text-white" id="userModalLabel">User Details</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label><strong>First Name</strong></label>
              <p id="modal-firstname" class="form-control-static"></p>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label><strong>Last Name</strong></label>
              <p id="modal-lastname" class="form-control-static"></p>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label><strong>Email</strong></label>
              <p id="modal-email" class="form-control-static"></p>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label><strong>Phone Number</strong></label>
              <p id="modal-phone" class="form-control-static"></p>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label><strong>Account Status</strong></label>
              <p id="modal-status" class="form-control-static"></p>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label><strong>Kids Mode</strong></label>
              <p id="modal-kids" class="form-control-static"></p>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label><strong>User Hash ID</strong></label>
              <p id="modal-hashid" class="form-control-static text-muted" style="font-size: 12px; word-break: break-all;"></p>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label><strong>Registration Date</strong></label>
              <p id="modal-created" class="form-control-static"></p>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        <a href="#" id="modal-verify-btn" class="btn btn-success">Verify User</a>
        <a href="#" id="modal-suspend-btn" class="btn btn-danger">Suspend User</a>
      </div>
    </div>
  </div>
</div>

<!-- Required Js -->
<script src="/da/assets/js/vendor-all.min.js"></script>
<script src="/da/assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="/da/assets/js/pcoded.min.js"></script>
<script src="/da/assets/plugins/prism/js/prism.min.js"></script>
<script src="/da/assets/js/horizontal-menu.js"></script>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- DataTables -->
<script src="/da/assets/plugins/data-tables/js/datatables.min.js"></script>

<script>
// Chart.js configuration
const ctx = document.getElementById('registrationChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($chartLabels); ?>,
        datasets: [{
            label: 'New Registrations',
            data: <?php echo json_encode($chartData); ?>,
            backgroundColor: 'rgba(66, 133, 244, 0.6)',
            borderColor: 'rgba(66, 133, 244, 1)',
            borderWidth: 2,
            borderRadius: 5
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});

// DataTable initialization
$(document).ready(function() {
    $('#registrationTable').DataTable({
        "order": [[5, "desc"]],
        "pageLength": 10
    });
});

// Modal population
$('.view-user-btn').on('click', function() {
    var btn = $(this);
    $('#modal-firstname').text(btn.data('firstname'));
    $('#modal-lastname').text(btn.data('lastname'));
    $('#modal-email').html('<a href="mailto:' + btn.data('email') + '">' + btn.data('email') + '</a>');
    $('#modal-phone').text(btn.data('phone'));
    $('#modal-status').text(btn.data('status'));
    $('#modal-kids').text(btn.data('kids'));
    $('#modal-hashid').text(btn.data('hashid'));
    $('#modal-created').text(btn.data('created'));
    
    var userId = btn.data('id');
    $('#modal-verify-btn').attr('href', '/updateContent.php?id=' + userId + '&user_status=1&verification=1&data=users');
    $('#modal-suspend-btn').attr('href', '/updateContent.php?id=' + userId + '&user_status=2&data=users');
});
</script>

<style>
.order-card {
    color: #fff;
    position: relative;
    overflow: hidden;
}
.order-card .card-icon {
    position: absolute;
    right: 20px;
    top: 20px;
    font-size: 48px;
    opacity: 0.3;
}
.bg-c-blue {
    background: linear-gradient(45deg, #4099ff, #73b4ff);
}
.bg-c-green {
    background: linear-gradient(45deg, #2ed8b6, #59e0c5);
}
.bg-c-yellow {
    background: linear-gradient(45deg, #FFB64D, #ffcb80);
}
.bg-c-red {
    background: linear-gradient(45deg, #FF5370, #ff869a);
}
.badge-success {
    background-color: #2ed8b6;
}
.badge-warning {
    background-color: #FFB64D;
    color: #fff;
}
.badge-danger {
    background-color: #FF5370;
}
</style>

</body>
</html>
