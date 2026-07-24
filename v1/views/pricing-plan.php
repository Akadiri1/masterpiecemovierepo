<?php
include APP_PATH . '/views/includes/header.php';

// 1. Fetch Plans from Database
if (isset($conn)) {
    $stmt = $conn->query("SELECT * FROM plans ORDER BY price ASC");
    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $plans = [
        ['id'=>1, 'name'=>'Free', 'price'=>0, 'video_quality'=>'720p', 'ads'=>1],
        ['id'=>2, 'name'=>'Pro', 'price'=>9.99, 'video_quality'=>'1080p', 'ads'=>0],
        ['id'=>3, 'name'=>'Premium', 'price'=>19.99, 'video_quality'=>'4K+HDR', 'ads'=>0]
    ];
}

$userPlanId = $_SESSION['plan_id'] ?? 1;

// Plan features mapping
$planFeatures = [
    'Free' => [
        ['icon' => 'ph-check-circle', 'color' => 'text-success', 'text' => 'SD & HD (720p) streaming'],
        ['icon' => 'ph-x-circle', 'color' => 'text-danger', 'text' => 'Contains advertisements'],
        ['icon' => 'ph-check-circle', 'color' => 'text-success', 'text' => 'Watch on 1 device'],
        ['icon' => 'ph-check-circle', 'color' => 'text-success', 'text' => 'Access to limited library'],
        ['icon' => 'ph-x-circle', 'color' => 'text-danger', 'text' => 'No downloads'],
        ['icon' => 'ph-x-circle', 'color' => 'text-danger', 'text' => 'No Kids Mode'],
    ],
    'Pro' => [
        ['icon' => 'ph-check-circle', 'color' => 'text-success', 'text' => 'Full HD (1080p) streaming'],
        ['icon' => 'ph-check-circle', 'color' => 'text-success', 'text' => 'Zero advertisements'],
        ['icon' => 'ph-check-circle', 'color' => 'text-success', 'text' => 'Watch on 3 devices'],
        ['icon' => 'ph-check-circle', 'color' => 'text-success', 'text' => 'Full movie & TV library'],
        ['icon' => 'ph-check-circle', 'color' => 'text-success', 'text' => 'Download up to 10 titles'],
        ['icon' => 'ph-check-circle', 'color' => 'text-success', 'text' => 'Kids Mode included'],
    ],
    'Premium' => [
        ['icon' => 'ph-check-circle', 'color' => 'text-success', 'text' => '4K Ultra HD + HDR streaming'],
        ['icon' => 'ph-check-circle', 'color' => 'text-success', 'text' => 'Zero advertisements'],
        ['icon' => 'ph-check-circle', 'color' => 'text-success', 'text' => 'Watch on 5 devices'],
        ['icon' => 'ph-check-circle', 'color' => 'text-success', 'text' => 'Full movie & TV library'],
        ['icon' => 'ph-check-circle', 'color' => 'text-success', 'text' => 'Unlimited downloads'],
        ['icon' => 'ph-check-circle', 'color' => 'text-success', 'text' => 'Kids Mode + Parental Controls'],
        ['icon' => 'ph-check-circle', 'color' => 'text-success', 'text' => 'Early access to new releases'],
        ['icon' => 'ph-check-circle', 'color' => 'text-success', 'text' => 'ZEN-AI unlimited chat'],
    ],
];
?>

<style>
    .pricing-hero {
        background: linear-gradient(180deg, rgba(0,0,0,0.7) 0%, #141414 100%), url('assets/images/common/01.webp') center/cover no-repeat;
        padding: 80px 0 60px;
        text-align: center;
    }
    .pricing-hero h1 { font-size: 2.8rem; font-weight: 800; color: #fff; margin-bottom: 12px; }
    .pricing-hero p { color: #aaa; font-size: 1.15rem; max-width: 600px; margin: 0 auto; }

    .pricing-grid { padding: 0 0 80px; margin-top: -30px; }

    .price-card {
        background: #1a1a1a;
        border: 1px solid rgba(255,255,255,0.08);
        border-radius: 20px;
        padding: 36px 28px;
        transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
        position: relative;
        height: 100%;
        display: flex;
        flex-direction: column;
    }
    .price-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 20px 50px rgba(0,0,0,0.6);
    }
    .price-card.featured {
        background: linear-gradient(180deg, #2a1015 0%, #1a1a1a 40%);
        border-color: var(--primary);
        box-shadow: 0 0 40px rgba(229,9,20,0.15);
    }
    .price-card.featured:hover {
        box-shadow: 0 20px 60px rgba(229,9,20,0.25);
    }

    .popular-badge {
        position: absolute;
        top: -14px;
        left: 50%;
        transform: translateX(-50%);
        background: linear-gradient(135deg, var(--primary), #ff4757);
        color: #fff;
        padding: 6px 24px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        white-space: nowrap;
    }

    .plan-name {
        font-size: 1rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 2px;
        color: #888;
        margin-bottom: 16px;
    }
    .price-card.featured .plan-name { color: var(--primary); }

    .price-amount {
        font-size: 3.2rem;
        font-weight: 800;
        color: #fff;
        line-height: 1;
        margin-bottom: 6px;
    }
    .price-period { color: #666; font-size: 0.9rem; margin-bottom: 28px; }

    .feature-list { list-style: none; padding: 0; margin: 0 0 30px; flex: 1; }
    .feature-list li {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 0;
        font-size: 0.92rem;
        color: #ccc;
        border-bottom: 1px solid rgba(255,255,255,0.04);
    }
    .feature-list li:last-child { border-bottom: none; }
    .feature-list li i { font-size: 1.1rem; flex-shrink: 0; }

    .price-cta {
        width: 100%;
        padding: 14px;
        border-radius: 12px;
        font-weight: 700;
        font-size: 1rem;
        transition: all 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .price-cta.btn-primary:hover { transform: scale(1.02); box-shadow: 0 8px 25px rgba(229,9,20,0.4); }
    .price-cta.btn-outline-light:hover { background: #fff; color: #000; }

    /* Guarantee strip */
    .guarantee-strip {
        text-align: center;
        padding: 40px 20px;
        border-top: 1px solid rgba(255,255,255,0.05);
    }
    .guarantee-strip i { font-size: 2rem; color: #4cd137; margin-bottom: 10px; }
    .guarantee-strip h5 { color: #fff; margin-bottom: 5px; }
    .guarantee-strip p { color: #888; font-size: 0.9rem; max-width: 500px; margin: 0 auto; }

    /* Responsive */
    @media (max-width: 767px) {
        .pricing-hero { padding: 50px 20px 40px; }
        .pricing-hero h1 { font-size: 1.8rem; }
        .pricing-hero p { font-size: 1rem; }
        .price-amount { font-size: 2.5rem; }
        .price-card { padding: 28px 20px; }
    }
</style>

<!-- Hero Section -->
<div class="pricing-hero">
    <h1>Choose Your Plan</h1>
    <p>Stream unlimited movies and TV shows. Upgrade or downgrade anytime.</p>
</div>

<!-- Pricing Cards -->
<div class="container pricing-grid">
    <div class="row g-4 justify-content-center">
        <?php foreach ($plans as $plan):
            $isCurrent = ($userPlanId == $plan['id']);
            $isFeatured = ($plan['name'] === 'Premium');
            $features = $planFeatures[$plan['name']] ?? [];
        ?>
        <div class="col-lg-4 col-md-6">
            <div class="price-card <?php echo $isFeatured ? 'featured' : ''; ?>">
                <?php if ($isFeatured): ?>
                    <span class="popular-badge">Most Popular</span>
                <?php endif; ?>

                <div class="plan-name"><?php echo htmlspecialchars($plan['name']); ?></div>

                <div class="price-amount">
                    <?php echo $plan['price'] == 0 ? 'Free' : '$' . number_format($plan['price'], 2); ?>
                </div>
                <div class="price-period">
                    <?php echo $plan['price'] > 0 ? 'per month, cancel anytime' : 'forever, no credit card needed'; ?>
                </div>

                <ul class="feature-list">
                    <?php foreach ($features as $f): ?>
                    <li>
                        <i class="ph-fill <?php echo $f['icon']; ?> <?php echo $f['color']; ?>"></i>
                        <span><?php echo $f['text']; ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>

                <?php if ($isCurrent): ?>
                    <button class="price-cta btn btn-secondary" disabled>Current Plan</button>
                <?php else: ?>
                    <form action="/checkout" method="GET">
                        <input type="hidden" name="plan_id" value="<?php echo $plan['id']; ?>">
                        <button type="submit" class="price-cta btn <?php echo $isFeatured ? 'btn-primary' : 'btn-outline-light'; ?>">
                            <?php echo $plan['price'] == 0 ? 'Get Started Free' : 'Choose ' . $plan['name']; ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Trust Strip -->
<div class="guarantee-strip">
    <i class="ph-fill ph-shield-check"></i>
    <h5>30-Day Money-Back Guarantee</h5>
    <p>Not satisfied? Get a full refund within 30 days. No questions asked.</p>
</div>

<?php include APP_PATH . '/views/includes/footer.php'; ?>