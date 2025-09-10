<?php
session_start();
function getInitials($name) {
    $words = explode(' ', $name);
    $initials = '';
    foreach ($words as $word) {
        $initials .= strtoupper($word[0]);
    }
    return substr($initials, 0, 2);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
 <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>Mammy Coker - Find the Perfect Digital Service</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    body { font-family: 'Segoe UI', sans-serif; }
    .hero-section { background-color: #f8f9fa; padding: 60px 0; }
    .stats-section { background-color: #6f42c1; color: white; padding: 40px 0; text-align: center; }
    .footer { background-color: #212529; color: white; padding: 40px 0; }
    .footer a { color: #ccc; text-decoration: none; }
    .footer a:hover { color: white; }
    .section-title { margin-bottom: 30px; }
    
    /*the mobile friendly part*/
    
    @media screen and (max-width: 768px) {
  .sidebar {
    display: none;
  }

  .content {
    width: 100%;
    padding: 10px;
  }
}

  </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-light bg-light px-4 shadow-sm">
    <a class="navbar-brand fw-bold" href="index.php"><img src="images/logonbg.png" style="width:70px; height:70px; border-radius:50%; margin:-5px;"/></a>
    <div class="ms-auto">
        <?php if (!isset($_SESSION['user_id'])): ?>
          

            <a href="auth.php" class="btn btn-outline-primary me-2">Sign In</a>
            <a href="auth.php" class="btn btn-primary">Sign Up</a>
        <?php else: ?>
            <?php $initials = getInitials($_SESSION['name']); ?>
            <div class="dropdown d-inline">
                <button class="btn btn-secondary dropdown-toggle rounded-circle text-uppercase"
                        type="button" id="userDropdown"
                        data-bs-toggle="dropdown" aria-expanded="false"
                        style="width: 45px; height: 45px; padding: 0;">
                    <?= $initials ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                    <!-- <li><a class="dropdown-item" href="builder_profile.php">Profile</a></li>
                    <li><a class="dropdown-item" href="settings.php">Settings</a></li>
                    <li><a class="dropdown-item" href="messages.php">Messages</a></li> -->

                    <li><a class="dropdown-item" href="profile.php">👤 Profile</a></li>
                        <li><a class="dropdown-item" href="settings.php">⚙️ Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php">🚪 Logout</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <!-- <li><a class="dropdown-item text-danger" href="logout.php">Logout</a></li> -->
                </ul>
            </div>
        <?php endif; ?>
    </div>
</nav>




<!-- Hero Section -->
<section class="hero-section py-5">
  <div class="container">
    <div class="row align-items-center">
      <!-- Text and Buttons -->
      <div class="col-md-6 mb-4 mb-md-0 text-center text-md-start">
        <h1 class="fw-bold mb-3">Find the perfect digital service</h1>
        <p class="mb-4">Connect with skilled freelancers offering digital services. From development to graphic design, find exactly what you’re looking for.</p>

        <div class="d-grid gap-3 d-sm-flex justify-content-sm-center justify-content-md-start">
          <?php if (isset($_SESSION['user'])): ?>
            <a href="browse_gigs.php" class="btn btn-outline-primary btn-lg">Find Services</a>
            <a href="create_gig.php" class="btn btn-primary btn-lg">Become a Seller</a>
          <?php else: ?>
            <a href="auth.php" class="btn btn-outline-primary btn-lg">Find Services</a>
            <a href="auth.php" class="btn btn-primary btn-lg">Become a Seller</a>
          <?php endif; ?>
        </div>
      </div>

      <!-- Image -->
      <div class="col-md-6 text-center">
        <img src="voce.jpg" class="img-fluid rounded" alt="Team work" />
      </div>
    </div>
  </div>
</section>






<!-- Popular Categories
<section class="py-5 bg-light">
    <div class="container">
      <h3 class="mb-4">Popular Categories</h3>
      <div class="row row-cols-2 row-cols-md-4 g-4">
  
        <div class="col">
          <div class="card text-center border-0 shadow-sm py-4">
            <div class="card-body">
              <i class="bi bi-code-slash fs-1 text-primary"></i>
              <h6 class="mt-3">Web Development</h6>
            </div>
          </div>
        </div>
  
        <div class="col">
          <div class="card text-center border-0 shadow-sm py-4">
            <div class="card-body">
              <i class="bi bi-brush fs-1 text-danger"></i>
              <h6 class="mt-3">Graphic Design</h6>
            </div>
          </div>
        </div>
  
        <div class="col">
          <div class="card text-center border-0 shadow-sm py-4">
            <div class="card-body">
              <i class="bi bi-translate fs-1 text-success"></i>
              <h6 class="mt-3">Translation</h6>
            </div>
          </div>
        </div>
  
        <div class="col">
          <div class="card text-center border-0 shadow-sm py-4">
            <div class="card-body">
              <i class="bi bi-mic-fill fs-1 text-warning"></i>
              <h6 class="mt-3">Voice Over</h6>
            </div>
          </div>
        </div>
  
        <div class="col">
          <div class="card text-center border-0 shadow-sm py-4">
            <div class="card-body">
              <i class="bi bi-camera-video fs-1 text-purple"></i>
              <h6 class="mt-3">Video Editing</h6>
            </div>
          </div>
        </div>
  
        <div class="col">
          <div class="card text-center border-0 shadow-sm py-4">
            <div class="card-body">
              <i class="bi bi-bar-chart-line fs-1 text-dark"></i>
              <h6 class="mt-3">Marketing</h6>
            </div>
          </div>
        </div>
  
        <div class="col">
          <div class="card text-center border-0 shadow-sm py-4">
            <div class="card-body">
              <i class="bi bi-music-note-beamed fs-1 text-info"></i>
              <h6 class="mt-3">Music & Audio</h6>
            </div>
          </div>
        </div>
  
        <div class="col">
          <div class="card text-center border-0 shadow-sm py-4">
            <div class="card-body">
              <i class="bi bi-pen fs-1 text-secondary"></i>
              <h6 class="mt-3">Content Writing</h6>
            </div>
          </div>
        </div>
  
      </div>
    </div>
  </section> -->

  <!-- Popular Categories (Compact) -->
<section class="py-4 bg-light">
    <div class="container">
      <h4 class="mb-3">Popular Categories</h4>
      <div class="row row-cols-2 row-cols-md-4 row-cols-lg-6 g-3">
  
        <div class="col">
          <div class="card text-center border-0 shadow-sm py-2">
            <div class="card-body">
              <i class="bi bi-code-slash fs-3 text-primary"></i>
              <h6 class="mt-2 small">Web Development</h6>
            </div>
          </div>
        </div>
  
        <div class="col">
          <div class="card text-center border-0 shadow-sm py-2">
            <div class="card-body">
              <i class="bi bi-brush fs-3 text-danger"></i>
              <h6 class="mt-2 small">Graphic Design</h6>
            </div>
          </div>
        </div>
  
        <div class="col">
          <div class="card text-center border-0 shadow-sm py-2">
            <div class="card-body">
              <i class="bi bi-translate fs-3 text-success"></i>
              <h6 class="mt-2 small">Translation</h6>
            </div>
          </div>
        </div>
  
        <div class="col">
          <div class="card text-center border-0 shadow-sm py-2">
            <div class="card-body">
              <i class="bi bi-mic-fill fs-3 text-warning"></i>
              <h6 class="mt-2 small">Voice Over</h6>
            </div>
          </div>
        </div>
  
        <div class="col">
          <div class="card text-center border-0 shadow-sm py-2">
            <div class="card-body">
              <i class="bi bi-camera-video fs-3 text-purple"></i>
              <h6 class="mt-2 small">Video Editing</h6>
            </div>
          </div>
        </div>
  
        <div class="col">
          <div class="card text-center border-0 shadow-sm py-2">
            <div class="card-body">
              <i class="bi bi-bar-chart-line fs-3 text-dark"></i>
              <h6 class="mt-2 small">Marketing</h6>
            </div>
          </div>
        </div>
  
      </div>
    </div>
  </section>
  <!-- Featured Services -->
<section class="py-5 bg-light">
    <div class="container">
      <h3 class="mb-4">Featured Services</h3>
      <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4">
  
        <!-- Card 1 -->
        <div class="col">
          <div class="card h-100 shadow-sm">
            <img src="graphicdesign.jpg" class="card-img-top" alt="Gig Image">
            <div class="card-body">
              <div class="d-flex align-items-center mb-2">
                <img src="tej.jpeg" class="rounded-circle me-2" width="40" height="40" alt="Seller">
                <div>
                  <strong>Mohamed Kamara</strong><br>
                  <small class="text-muted">★★★★★ (5.0)</small>
                </div>
              </div>
              <p class="mb-0">I will design a creative business logo within 24 hours.</p>
            </div>
            <div class="card-footer d-flex justify-content-between">
              <span class="fw-bold text-primary">NLe 750</span>
              <span class="badge bg-success">Available</span>
            </div>
          </div>
        </div>
  
        <!-- Card 2 -->
        <div class="col">
          <div class="card h-100 shadow-sm">
            <img src="webdev.jpg" class="card-img-top" alt="Gig Image">
            <div class="card-body">
              <div class="d-flex align-items-center mb-2">
                <img src="female.jpeg" class="rounded-circle me-2" width="40" height="40" alt="Seller">
                <div>
                  <strong>Fatmata Kamara</strong><br>
                  <small class="text-muted">★★★★☆ (4.7)</small>
                </div>
              </div>
              <p class="mb-0">I will develop your personal or business website with modern tools.</p>
            </div>
            <div class="card-footer d-flex justify-content-between">
              <span class="fw-bold text-primary">NLe 1,200</span>
              <span class="badge bg-success">Available</span>
            </div>
          </div>
        </div>
  
        <!-- Card 3 -->
        <div class="col">
          <div class="card h-100 shadow-sm">
            <img src="voce.jpg" class="card-img-top" alt="Gig Image">
            <div class="card-body">
              <div class="d-flex align-items-center mb-2">
                <img src="setelect.jpg" class="rounded-circle me-2" width="40" height="40" alt="Seller">
                <div>
                  <strong>Alhaji Conteh</strong><br>
                  <small class="text-muted">★★★★★ (4.9)</small>
                </div>
              </div>
              <p class="mb-0">I will record professional voice overs in Krio or English.</p>
            </div>
            <div class="card-footer d-flex justify-content-between">
              <span class="fw-bold text-primary">NLe 500</span>
              <span class="badge bg-success">Available</span>
            </div>
          </div>
        </div>
  
        <!-- Card 4 -->
        <div class="col">
          <div class="card h-100 shadow-sm">
            <img src="translate.jpg" class="card-img-top" alt="Gig Image">
            <div class="card-body">
              <div class="d-flex align-items-center mb-2">
                <img src="female.jpeg" class="rounded-circle me-2" width="40" height="40" alt="Seller">
                <div>
                  <strong>Mariama Sesay</strong><br>
                  <small class="text-muted">★★★★☆ (4.6)</small>
                </div>
              </div>
              <p class="mb-0">I will translate documents from English to French or Krio fluently.</p>
            </div>
            <div class="card-footer d-flex justify-content-between">
              <span class="fw-bold text-primary">NLe 600</span>
              <span class="badge bg-success">Available</span>
            </div>
          </div>
        </div>
  
      </div>
    </div>
  </section>

  
  
  
<!-- How It Works Section -->
<section class="py-5">
    <div class="container">
      <h3 class="mb-4 text-center">How Mammy Coker Works</h3>
      <div class="row row-cols-1 row-cols-md-3 g-4">
  
        <!-- Step 1 -->
        <div class="col">
          <div class="card h-100 text-center border-0 shadow-sm p-3">
            <div class="card-body">
              <i class="bi bi-search fs-2 text-primary mb-3"></i>
              <h5 class="card-title">Find the right service</h5>
              <p class="card-text small">Browse categories, read reviews, and compare options to find the perfect service for your needs.</p>
            </div>
          </div>
        </div>
  
        <!-- Step 2 -->
        <div class="col">
          <div class="card h-100 text-center border-0 shadow-sm p-3">
            <div class="card-body">
              <i class="bi bi-chat-dots fs-2 text-success mb-3"></i>
              <h5 class="card-title">Connect & collaborate</h5>
              <p class="card-text small">Communicate clearly with the seller before placing your order. Track progress and provide input easily.</p>
            </div>
          </div>
        </div>
  
        <!-- Step 3 -->
        <div class="col">
          <div class="card h-100 text-center border-0 shadow-sm p-3">
            <div class="card-body">
              <i class="bi bi-shield-check fs-2 text-warning mb-3"></i>
              <h5 class="card-title">Pay securely & get work done</h5>
              <p class="card-text small">Your payment is held in escrow until you're satisfied. Release funds once you get what you paid for.</p>
            </div>
          </div>
        </div>
  
      </div>
    </div>
  </section>
  
<!-- Choose Role -->
<section class="py-5 bg-light">
  <div class="container text-center">
    <div class="row">
      <div class="col-md-6">
        <h4>I need a service</h4>
        <p>Looking for a professional to help with your digital projects? Find the best freelancers with the skills you need.</p>
        <a href="#" class="btn btn-primary">Find Services</a>
      </div>
      <div class="col-md-6">
        <h4>I want to offer services</h4>
        <p>Share your skills with clients worldwide. Create your seller profile and start earning on your own terms.</p>
        <a href="#" class="btn btn-outline-primary">Become a Seller</a>
      </div>
    </div>
  </div>
</section>

<!-- Stats -->
<section class="stats-section">
  <div class="container">
    <div class="row text-center">
      <div class="col-md-3">
        <h3>10,000+</h3>
        <p>Services</p>
      </div>
      <div class="col-md-3">
        <h3>8,500+</h3>
        <p>Freelancers</p>
      </div>
      <div class="col-md-3">
        <h3>25,000+</h3>
        <p>Projects Completed</p>
      </div>
      <div class="col-md-3">
        <h3>50+</h3>
        <p>Countries</p>
      </div>
    </div>
    <div class="mt-4">
      <a href="#" class="btn btn-light me-2">Find Services</a>
      <a href="#" class="btn btn-outline-light">Become a Seller</a>
    </div>
  </div>
</section>

<!-- Footer -->
<footer class="footer">
  <div class="container">
    <div class="row">
      <div class="col-md-3">
        <h5>Mammy Coker</h5>
        <p>The leading marketplace for digital services, connecting skilled freelancers with clients worldwide.</p>
        <div>
          <a href="#" class="me-2"><i class="bi bi-facebook"></i></a>
          <a href="#" class="me-2"><i class="bi bi-twitter"></i></a>
          <a href="#"><i class="bi bi-instagram"></i></a>
        </div>
      </div>
      <div class="col-md-3">
        <h6>For Clients</h6>
        <ul class="list-unstyled">
          <li><a href="#">How to Hire</a></li>
          <li><a href="#">Post a Project</a></li>
          <li><a href="#">Payment Protection</a></li>
          <li><a href="#">Client Success Stories</a></li>
        </ul>
      </div>
      <div class="col-md-3">
        <h6>For Freelancers</h6>
        <ul class="list-unstyled">
          <li><a href="#">Seller Guide</a></li>
          <li><a href="#">Create Profile</a></li>
          <li><a href="#">Success Stories</a></li>
          <li><a href="#">Freelancer Resources</a></li>
        </ul>
      </div>
      <div class="col-md-3">
        <h6>Resources</h6>
        <ul class="list-unstyled">
          <li><a href="#">Help & Support</a></li>
          <li><a href="#">Community Guidelines</a></li>
          <li><a href="#">Blog</a></li>
          <li><a href="#">Language: English</a></li>
        </ul>
      </div>
    </div>
    <hr class="border-light" />
    <p class="text-center small">&copy; 2025 Mammy Coker. All rights reserved.</p>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- jQuery for simplicity -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
function fetchNotificationCount() {
    $.get("notification_count.php", function (data) {
        const count = parseInt(data);
        const badge = $("#notif-badge");
        if (count > 0) {
            badge.text(count).show();
        } else {
            badge.hide();
        }
    });
}

setInterval(fetchNotificationCount, 5000); // Every 5 seconds
</script>

</body>
</html>
