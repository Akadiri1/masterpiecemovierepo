<?php
include APP_PATH . '/views/includes/header.php';

// 1. Fetch Plans from Database
if (isset($conn)) {
    $stmt = $conn->query("SELECT * FROM plans ORDER BY price ASC");
    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Fallback if DB fails
    $plans = [
        ['id'=>1, 'name'=>'Free', 'price'=>0, 'video_quality'=>'720p', 'ads'=>1],
        ['id'=>2, 'name'=>'Pro', 'price'=>9.99, 'video_quality'=>'1080p', 'ads'=>0],
        ['id'=>3, 'name'=>'Premium', 'price'=>19.99, 'video_quality'=>'4K+HDR', 'ads'=>0]
    ];
}

$userPlanId = $_SESSION['plan_id'] ?? 1; // Default to Free if not logged in
?>

<!-- Breadcrumb -->
<div class="iq-breadcrumb-one iq-bg-over iq-over-dark-50" style="background-image: url('assets/images/common/01.webp');">
   <div class="container-fluid">
      <div class="row align-items-center">
         <div class="col-sm-12">
            <nav aria-label="breadcrumb" class="text-center iq-breadcrumb-two">
               <h2 class="title">Pricing Plans</h2>
               <ol class="breadcrumb main-bg">
                  <li class="breadcrumb-item"><a href="/">Home</a></li>
                  <li class="breadcrumb-item active">Pricing</li>
               </ol>
            </nav>
         </div>
      </div>
   </div>
</div>

<!-- Pricing Section -->
<div class="section-padding">
    <div class="container">
        <div class="row align-items-center justify-content-center">
            
            <?php foreach ($plans as $plan): 
                $isCurrent = ($userPlanId == $plan['id']);
                $cardClass = ($plan['name'] === 'Premium') ? 'border-primary bg-dark' : 'border-secondary';
                $btnClass = ($plan['name'] === 'Premium') ? 'btn-primary' : 'btn-secondary';
            ?>
            <div class="col-lg-4 col-md-6 mb-4 mb-lg-0">
                <div class="pricing-card p-4 rounded-3 text-center position-relative border <?php echo $cardClass; ?>" style="border-width: 2px;">
                    
                    <?php if($plan['name'] === 'Premium'): ?>
                        <span class="badge bg-primary position-absolute top-0 start-50 translate-middle px-3 py-2">Recommended</span>
                    <?php endif; ?>

                    <h4 class="mb-3 mt-3 text-uppercase"><?php echo htmlspecialchars($plan['name']); ?></h4>
                    
                    <div class="price-box mb-4">
                        <h1 class="display-4 fw-bold text-white">
                            <?php echo $plan['price'] == 0 ? 'Free' : '$' . $plan['price']; ?>
                        </h1>
                        <?php if($plan['price'] > 0): ?>
                            <span class="text-muted">/ month</span>
                        <?php endif; ?>
                    </div>

                    <ul class="list-unstyled text-start mb-4 d-inline-block">
                        <li class="mb-3 d-flex align-items-center gap-2">
                            <i class="ph-fill ph-check-circle text-success"></i> 
                            <span>Video Quality: <strong><?php echo $plan['video_quality']; ?></strong></span>
                        </li>
                        <li class="mb-3 d-flex align-items-center gap-2">
                            <i class="ph-fill <?php echo $plan['ads'] ? 'ph-x-circle text-danger' : 'ph-check-circle text-success'; ?>"></i>
                            <span><?php echo $plan['ads'] ? 'Ad-Supported' : 'No Ads'; ?></span>
                        </li>
                        <li class="mb-3 d-flex align-items-center gap-2">
                            <i class="ph-fill ph-check-circle text-success"></i>
                            <span>Unlimited Movies & TV</span>
                        </li>
                        <li class="mb-3 d-flex align-items-center gap-2">
                            <i class="ph-fill <?php echo $plan['name'] === 'Free' ? 'ph-x-circle text-danger' : 'ph-check-circle text-success'; ?>"></i>
                            <span>Cancel Anytime</span>
                        </li>
                    </ul>

                    <div class="pricing-footer">
                        <?php if($isCurrent): ?>
                            <button class="btn btn-secondary w-100 disabled" disabled>Current Plan</button>
                        <?php else: ?>
                            <form action="/checkout" method="GET">
                                <input type="hidden" name="plan_id" value="<?php echo $plan['id']; ?>">
                                <button type="submit" class="btn <?php echo $btnClass; ?> w-100">Choose <?php echo $plan['name']; ?></button>
                            </form>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
            <?php endforeach; ?>

        </div>
    </div>
</div>

<?php include APP_PATH . '/views/includes/footer.php'; ?>