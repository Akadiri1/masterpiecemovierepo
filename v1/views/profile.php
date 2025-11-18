<?php
// Now, check if the session exists. If not, the user is not logged in.
if (!isset($_SESSION['user_id'])) {
    header('Location: /login'); // Redirect to login
    exit;
}
$user = null;
try {
    // We fetch all columns for the logged-in user.
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Failsafe: If user ID in session doesn't exist in DB, destroy session and redirect.
    if (!$user) {
        session_destroy();
        header('Location: /login');
        exit;
    }
} catch (PDOException $e) {
    // Production-safe error handling: Log the error for developers, show a generic message to the user.
    error_log("Database error on profile.php for user_id {$_SESSION['user_id']}: " . $e->getMessage());
    // To prevent a broken page, we can show a full error page.
    include 'includes/header.php';
    echo '<main class="main-content"><div class="container-fluid text-center mt-5"><div class="alert alert-danger">Sorry, we could not load your profile data at this time.</div></div></main>';
    include 'includes/footer.php';
    exit; // Stop further execution
}

// --- PREPARE USER DATA FOR DISPLAY ---
// Set a root-relative path for the avatar, falling back to a default if none is set.
// This ensures the path is correct regardless of the current URL.
$user['avatar_path'] = $user['avatar_url'] ? $user['avatar_url'] : '/assets/images/user/user6.jpg';

// Create a full name for display, falling back to the username if names are not set.
// Using null coalescing operator (??) for safety in case columns are NULL.
$user['fullName'] = trim(($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? ''));
if (empty($user['fullName'])) {
    $user['fullName'] = $user['username'];
}


// --- FETCH WATCHLIST DATA (as you had it) ---
$watchlist = [];
// try {
//     $sql = "SELECT m.id, m.title, m.image_url 
//             FROM watchlist w
//             JOIN movies m ON w.movie_id = m.id
//             WHERE w.user_id = ?";
//     $stmt = $conn->prepare($sql);
//     $stmt->execute([$_SESSION['user_id']]);
//     $watchlist = $stmt->fetchAll(PDO::FETCH_ASSOC);
// } catch (PDOException $e) {
//     error_log("Watchlist fetch error: " . $e->getMessage());
// }

// Now we can include the header, which will use the $user variable we just prepared.
include 'includes/header.php';
?>

<!--bread-crumb-->
<!--bread-crumb-->

<section class="section-padding profile-section-padding">
    <div class="container-fluid">
        <!-- user profile start -->
        <div class="px-sm-5 px-3 py-4 rounded-3 profile-user-info">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <div class="d-flex align-items-center flex-wrap gap-3">
                        <div class="profile-image flex-shrink-0">
                            <!-- DYNAMIC AVATAR -->
                            <img src="<?php echo htmlspecialchars($user['avatar_path']); ?>" alt="<?php echo htmlspecialchars($user['fullName']); ?>"
                                class="user-image user-profile-image" id="profile-header-avatar">
                        </div>
                        <div class="profile-info">
                            <!-- DYNAMIC DATA -->
                            <h5 class="mt-0 info-title" id="profile-header-name"><?php echo htmlspecialchars($user['fullName']); ?></h5>
                            <p class="mb-1 mt-0" id="profile-header-email"><?php echo htmlspecialchars($user['email']); ?></p>
                            <p class="m-0"><?php echo htmlspecialchars($user['username']); ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 mt-sm-0 mt-3">
                    <div class="d-flex align-items-center justify-content-sm-end gap-3">
                        <button type="button" class="btn btn-sm custom-btn-sm btn-primary text-nowrap fw-semibold"
                            data-bs-toggle="modal" data-bs-target="#edit-profile-modal">
                            <i class="icon-edit-icon"></i> Edit Profile </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-5">
            <div class="col-xl-2 col-lg-3">
                <ul class="profile-page-list list-inline p-0 mx-0 nav-tabs" role="tablist">
                    <li class="profile-page-list-item">
                        <a href="#" class="profile-page-list-link active" data-bs-toggle="tab"
                            data-bs-target="#playlist-tab" role="tab" aria-selected="true">
                            Playlist </a>
                    </li>
                    <li class="profile-page-list-item">
                        <a href="#" class="profile-page-list-link" data-bs-toggle="tab" data-bs-target="#watchlist-tab"
                            role="tab" aria-selected="false">
                            Watch List </a>
                    </li>
                    <li class="profile-page-list-item">
                        <a href="#" class="profile-page-list-link" data-bs-toggle="tab"
                            data-bs-target="#notification-tab" role="tab" aria-selected="false">
                            Notification </a>
                    </li>
                    <li class="profile-page-list-item">
                        <a href="#" class="profile-page-list-link" data-bs-toggle="tab" data-bs-target="#membership-tab"
                            role="tab" aria-selected="false">
                            Membership </a>
                    </li>
                </ul>
            </div>

            <div class="col-xl-10 col-lg-9 mt-5 mt-lg-0">
                <div class="tab-content" id="profile-menu-content">
                    <div class="tab-pane fade show active" id="playlist-tab" role="tabpanel">
                        <!-- ... (Your Playlist content as before) ... -->
                        <p class="text-center w-100">Your playlists will show up here.</p>
                    </div>
                    <div class="tab-pane fade" id="watchlist-tab" role="tabpanel">
                        <!-- === WATCHLIST CONTENT START === -->
                        <div class="row gy-4 row-cols-2 row-cols-sm-2 row-cols-md-3 row-cols-lg-3 row-cols-xl-4 row-cols-xxl-5 data-listing">
                            <?php if (empty($watchlist)): ?>
                                <div class="col-12">
                                    <p class="text-center w-100">Your watchlist is empty.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($watchlist as $movie): ?>
                                    <div class="col">
                                        <div class="common_card">
                                            <div class="image-box w-100">
                                                <a href="movie-detail.php?id=<?php echo $movie['id']; ?>" class="d-block">
                                                    <img src="<?php echo htmlspecialchars($movie['image_url'] ?: 'assets/images/placeholder.jpg'); ?>"
                                                         alt="<?php echo htmlspecialchars($movie['title']); ?>" class="img-fluid">
                                                </a>
                                            </div>
                                            <div class="css_prefix-detail-part">
                                                <h6 class="text-capitalize line-count-1 mb-0">
                                                    <a href="movie-detail.php?id=<?php echo $movie['id']; ?>" class="color-inherit">
                                                        <?php echo htmlspecialchars($movie['title']); ?>
                                                    </a>
                                                </h6>
                                                <button class="btn in-watchlist btn-secondary watch-list-btn"
                                                        data-movie-id="<?php echo $movie['id']; ?>"
                                                        data-bs-toggle="tooltip"
                                                        data-bs-title="Remove from watchlist">
                                                    <i class="icon-check-2"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <!-- === WATCHLIST CONTENT END === -->
                    </div>
                    <div class="tab-pane fade" id="notification-tab" role="tabpanel">
                        <!-- ... (Your Notification content as before) ... -->
                         <p class="text-center w-100">Your notifications will show up here.</p>
                    </div>
                    <div class="tab-pane fade" id="membership-tab" role="tabpanel">
                        <!-- ... (Your Membership content as before) ... -->
                    </div>
                </div>
            </div>
        </div>
        <!-- edit profile modal -->
    </div>
</section>


<!-- === EDIT PROFILE MODAL === -->
<div class="modal fade view-more-data-modal edit-profile-modal" id="edit-profile-modal" tabindex="-1" aria-modal="true"
    role="dialog">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header pb-0">
                <h5 class="modal-title" id="exampleModalLabelEdit1">Edit Profile</h5>
                <button type="button" class="btn-close me-0" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">

                <form id="edit-profile-form" novalidate enctype="multipart/form-data">
                    <div class="row">
                        <!-- Avatar Upload Section -->
                        <div class="col-12 text-center mb-5">
                            <div class="position-relative d-inline-block avtar_image">
                                <img src="<?php echo htmlspecialchars($user['avatar_path']); ?>" alt="User Avatar"
                                    class="user-image user-profile-image" id="profile-picture-preview">
                                <div class="avtar_action">
                                    <a class="avtar_action-btn" id="edit-profile-picture-btn">
                                        <i class="icon-edit-icon"></i>
                                    </a>
                                    <a class="avtar_action-btn" id="remove-profile-picture-btn">
                                        <i class="icon-trash-icon"></i>
                                    </a>
                                </div>
                                <input type="file" id="avatar-upload-input" name="avatar" hidden accept="image/png, image/jpeg, image/webp">
                                <input type="hidden" id="is_remove_avatar" name="is_remove_avatar" value="0">
                            </div>
                        </div>

                        <!-- First Name Field -->
                        <div class="col-sm-6">
                            <div class="form-group mb-3">
                                <label for="edit-first-name" class="form-label">First Name</label>
                                <input type="text" class="form-control" name="firstName" id="edit-first-name"
                                    value="<?php echo htmlspecialchars($user['firstName'] ?? ''); ?>">
                                <div class="invalid-feedback">Please enter a valid name.</div>
                            </div>
                        </div>

                        <!-- Last Name Field -->
                        <div class="col-sm-6">
                            <div class="form-group mb-3">
                                <label for="edit-last-name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" name="lastName" id="edit-last-name"
                                    value="<?php echo htmlspecialchars($user['lastName'] ?? ''); ?>">
                                <div class="invalid-feedback">Please enter a valid name.</div>
                            </div>
                        </div>

                        <!-- Email Field -->
                        <div class="col-12">
                            <div class="form-group mb-0">
                                <label for="edit-email" class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" id="edit-email"
                                    value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                                <div class="invalid-feedback">Please enter a valid email.</div>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="iq-button mt-4 text-end">
                        <button type="submit" id="save-profile-button" class="btn btn-primary text-capitalize position-relative rounded-3">
                            <span class="button-text">Save</span>
                            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true" style="display: none;"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
</main>

<?php include 'includes/footer.php'; ?>

<!-- === REQUIRED JAVASCRIPT === -->

<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const editForm = document.querySelector("#edit-profile-form");
    if (!editForm) return;

    // --- 1. Select All Elements ---
    const saveButton = document.querySelector("#save-profile-button");
    const buttonText = saveButton.querySelector(".button-text");
    const loadingSpinner = saveButton.querySelector(".spinner-border");
    
    const firstNameInput = document.querySelector("#edit-first-name");
    const lastNameInput = document.querySelector("#edit-last-name");
    const emailInput = document.querySelector("#edit-email");
    
    const avatarPreview = document.querySelector("#profile-picture-preview");
    const avatarHeader = document.querySelector("#profile-header-avatar");
    const avatarUploadInput = document.querySelector("#avatar-upload-input");
    const editAvatarButton = document.querySelector("#edit-profile-picture-btn");
    const removeAvatarButton = document.querySelector("#remove-profile-picture-btn");
    const removeAvatarFlag = document.querySelector("#is_remove_avatar");
    
    const profileHeaderName = document.querySelector("#profile-header-name");
    const profileHeaderEmail = document.querySelector("#profile-header-email");

    const defaultAvatar = '/assets/images/user/user6.jpg'; // Using root-relative path

    // --- 2. Helper Functions ---
    const showToast = (message, type = "error") => {
        const background = (type === "success")
          ? "linear-gradient(to right, #00b09b, #96c93d)"
          : "linear-gradient(to right, #ff5f6d, #ffc371)";
        Toastify({ text: message, duration: 3000, close: true, gravity: "top", position: "right", style: { background } }).showToast();
    };

    const setButtonLoading = (isLoading) => {
        saveButton.disabled = isLoading;
        buttonText.style.display = isLoading ? "none" : "inline-block";
        loadingSpinner.style.display = isLoading ? "inline-block" : "none";
    };

    const showError = (inputElement, message) => {
        inputElement.classList.add("is-invalid");
        const errorElement = inputElement.closest(".form-group").querySelector(".invalid-feedback");
        if (errorElement) errorElement.textContent = message;
    };

    const clearErrors = () => {
        editForm.querySelectorAll(".is-invalid").forEach(input => {
            input.classList.remove("is-invalid");
        });
    };

    // --- 3. Avatar Upload Logic ---
    if(editAvatarButton) {
        editAvatarButton.addEventListener("click", (e) => {
            e.preventDefault();
            avatarUploadInput.click();
        });
    }

    if(removeAvatarButton) {
        removeAvatarButton.addEventListener("click", (e) => {
            e.preventDefault();
            avatarPreview.src = defaultAvatar;
            avatarUploadInput.value = "";
            removeAvatarFlag.value = "1";
        });
    }

    if(avatarUploadInput) {
        avatarUploadInput.addEventListener("change", () => {
            const file = avatarUploadInput.files[0];
            if (file) {
                if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                    showToast("Invalid file type. Please use JPG, PNG, or WEBP.", "error");
                    avatarUploadInput.value = "";
                    return;
                }
                if (file.size > 2 * 1024 * 1024) {
                    showToast("File is too large. Max 2MB allowed.", "error");
                    avatarUploadInput.value = "";
                    return;
                }

                const reader = new FileReader();
                reader.onload = (e) => {
                    avatarPreview.src = e.target.result;
                };
                reader.readAsDataURL(file);
                removeAvatarFlag.value = "0";
            }
        });
    }

    // --- 4. Client-side Validation ---
    function validateProfileForm() {
        let isValid = true;
        clearErrors();

        const firstName = firstNameInput.value.trim();
        const lastName = lastNameInput.value.trim();

        if (firstName && !/^[a-zA-Z\-'\s]+$/.test(firstName)) {
            showError(firstNameInput, "First name contains invalid characters.");
            isValid = false;
        }
        if (lastName && !/^[a-zA-Z\-'\s]+$/.test(lastName)) {
            showError(lastNameInput, "Last name contains invalid characters.");
            isValid = false;
        }

        if (emailInput.value.trim() === "") {
            showError(emailInput, "Email cannot be empty.");
            isValid = false;
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailInput.value.trim())) {
            showError(emailInput, "Please enter a valid email address.");
            isValid = false;
        }
        
        return isValid;
    }

    // --- 5. Submit Handler (AJAX with FormData) ---
    editForm.addEventListener("submit", async (event) => {
        event.preventDefault();

        if (!validateProfileForm()) {
            return;
        }

        setButtonLoading(true);
        const formData = new FormData(editForm);

        try {
            const response = await fetch('/update-profile', { // Use root-relative URL for API
                method: 'POST',
                body: formData 
            });

            const data = await response.json();

            if (response.ok) {
                showToast(data.message, 'success');
                
                if (profileHeaderName) profileHeaderName.textContent = data.user.fullName;
                if (profileHeaderEmail) profileHeaderEmail.textContent = data.user.email;
                
                const newAvatarPath = data.user.avatar_url || defaultAvatar;
                if (avatarHeader) avatarHeader.src = newAvatarPath;
                if (avatarPreview) avatarPreview.src = newAvatarPath;
                
                avatarUploadInput.value = "";
                removeAvatarFlag.value = "0";
                
                const modal = document.querySelector("#edit-profile-modal");
                const modalInstance = bootstrap.Modal.getInstance(modal);
                if (modalInstance) {
                     modalInstance.hide();
                }

            } else {
                if (data.field) {
                    const fieldInput = document.querySelector(`[name="${data.field}"]`);
                    if(fieldInput) showError(fieldInput, data.message);
                } else {
                    showToast(data.message || 'An unknown server error occurred.', 'error');
                }
            }
        } catch (error) {
            console.error('Update failed:', error);
            showToast('Could not connect to the server. Please try again.', 'error');
        } finally {
            setButtonLoading(false);
        }
    });
});
</script>