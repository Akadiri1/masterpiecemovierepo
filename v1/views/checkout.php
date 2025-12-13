<?php
session_start();
include APP_PATH . '/views/includes/header.php';

// 1. Validate User
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    echo "<script>window.location.href = '/login';</script>";
    exit;
}

// 2. Get Plan Details
$planId = $_GET['plan_id'] ?? null;

if (!$planId) {
    echo "<script>window.location.href = '/pricing-plan';</script>";
    exit;
}

// Fetch Plan info
$planName = "Unknown";
$planPrice = 0;

if (isset($conn)) {
    $stmt = $conn->prepare("SELECT * FROM plans WHERE id = ?");
    $stmt->execute([$planId]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($plan) {
        $planName = $plan['name'];
        $planPrice = $plan['price'];
    }
}

// Prepare User Details for Paystack
$customerName = $_SESSION['username'] ?? 'Customer';
$customerEmail = $_SESSION['email'] ?? 'customer@example.com'; 
// Paystack amounts are in kobo (multiply by 100)
$amountKobo = $planPrice * 100; 
?>

<!-- Breadcrumb -->
<div class="iq-breadcrumb-one iq-bg-over iq-over-dark-50" style="background-image: url('assets/images/common/01.webp');">
   <div class="container-fluid">
      <div class="row align-items-center">
         <div class="col-sm-12">
            <nav aria-label="breadcrumb" class="text-center iq-breadcrumb-two">
               <h2 class="title">Secure Checkout</h2>
               <ol class="breadcrumb main-bg">
                  <li class="breadcrumb-item"><a href="/">Home</a></li>
                  <li class="breadcrumb-item active">Checkout</li>
               </ol>
            </nav>
         </div>
      </div>
   </div>
</div>

<div class="section-padding">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="row">
                    
                    <!-- ORDER SUMMARY -->
                    <div class="col-md-5 mb-4">
                        <div class="pricing-card p-4 rounded-3 border border-secondary bg-dark h-100">
                            <h5 class="mb-3 text-uppercase text-primary">Order Summary</h5>
                            <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom border-secondary">
                                <span class="text-white fw-bold"><?php echo htmlspecialchars($planName); ?> Plan</span>
                                <span class="text-warning">$<?php echo $planPrice; ?></span>
                            </div>
                            <ul class="list-unstyled text-muted small mb-4">
                                <li><i class="ph-fill ph-check-circle me-2"></i> Unlimited Streaming</li>
                                <li><i class="ph-fill ph-check-circle me-2"></i> Ad-Free Experience</li>
                                <li><i class="ph-fill ph-check-circle me-2"></i> High Quality Video</li>
                            </ul>
                            <div class="d-flex justify-content-between align-items-center">
                                <h4 class="text-white m-0">Total</h4>
                                <h3 class="text-white m-0">$<?php echo $planPrice; ?></h3>
                            </div>
                        </div>
                    </div>

                    <!-- PAYMENT OPTION -->
                    <div class="col-md-7">
                        <div class="p-4 rounded-3 border border-secondary h-100 d-flex flex-column justify-content-center" style="background: #141414;">
                            <h4 class="mb-2"><i class="ph-fill ph-lock-key me-2"></i> Secure Payment</h4>
                            <p class="text-muted mb-4">Complete your subscription securely using Paystack.</p>
                            
                            <form id="paymentForm">
                                <input type="hidden" id="plan_id" value="<?php echo $planId; ?>">
                                <input type="hidden" id="amount" value="<?php echo $amountKobo; ?>"> <!-- Kobo amount -->
                                <input type="hidden" id="email" value="<?php echo htmlspecialchars($customerEmail); ?>">
                                <input type="hidden" id="name" value="<?php echo htmlspecialchars($customerName); ?>">

                                <button type="button" onclick="payWithPaystack()" id="payButton" class="btn btn-primary w-100 py-3 fw-bold text-uppercase">
                                    Pay with Paystack
                                </button>
                            </form>
                            
                            <div class="mt-3 text-center">
                                <img src="https://upload.wikimedia.org/wikipedia/commons/0/0b/Paystack_Logo.png" alt="Paystack" style="max-width: 150px; opacity: 0.8; filter: invert(1);">
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<?php include APP_PATH . '/views/includes/footer.php'; ?>

<!-- 1. Include Paystack Script -->
<script src="https://js.paystack.co/v1/inline.js"></script>

<!-- 2. Payment Logic -->
<script>
function payWithPaystack() {
    const btn = document.getElementById('payButton');
    const originalText = btn.innerHTML;
    
    // Get Data
    const amount = document.getElementById('amount').value; // In Kobo
    const email = document.getElementById('email').value;
    const plan_id = document.getElementById('plan_id').value;
    const publicKey = "pk_test_0de1d66fdfbb9621ccd971336d399deedfb77ace"; // REPLACE WITH YOUR PUBLIC KEY

    let handler = PaystackPop.setup({
        key: publicKey, 
        email: email,
        amount: amount,
        currency: "NGN", // Change to USD or GHS if needed (Amount must match currency unit)
        ref: 'PSTK_'+Math.floor((Math.random() * 1000000000) + 1), 
        metadata: {
            custom_fields: [
                { display_name: "Plan ID", variable_name: "plan_id", value: plan_id }
            ]
        },
        callback: function(response) {
            // Payment Successful! Now Verify with Backend
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Verifying...';

            // Send Reference to Backend
            const formData = new FormData();
            formData.append('reference', response.reference);
            formData.append('plan_id', plan_id);

            fetch('/process-payment', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    Toastify({ text: "Subscription Active!", style: { background: "#4caf50" } }).showToast();
                    setTimeout(() => { window.location.href = '/'; }, 2000);
                } else {
                    Toastify({ text: data.message, style: { background: "#e50914" } }).showToast();
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
        },
        onClose: function() {
            // alert('Transaction was not completed, window closed.');
        }
    });
    
    handler.openIframe();
}
</script>