<?php
require_once 'includes/auth.php';
$base_path = '';
$page_title = 'BU Bank Ltd — Bank Loan Management & Prediction System';
include 'includes/header.php';
?>

<!-- =========================================================================
     1. FULL-SCREEN HERO IMAGE SLIDER SECTION
     Background image spans 100% full screen with dark overlay and centered alignment.
     To change images anytime, update the src in <img class="slide-bg">!
     ========================================================================= -->
<section class="slider slider-fullscreen" id="home-slider" aria-roledescription="carousel" aria-label="Ledger bank highlights">

  <!-- SLIDE 1 -->
  <div class="slide is-active" aria-hidden="false">
    <img src="assets/images/slider1.jpg" alt="Modern Banking Headquarters" class="slide-bg">
    <div class="slide-overlay"></div>
    <div class="slide-copy slide-center">
     
      <h1>Every loan decision, <em>transparent &amp; accelerated</em>.</h1>
      <p class="lede">BU Bank Ltd digitizes account opening, loan application, and instant eligibility prediction powered by 5 key financial indicators.</p>
      <div class="hero-actions">
        <a href="register.php" class="btn btn-brass">Open an account</a>
        <a href="login.php" class="btn btn-ghost">Log in to dashboard</a>
      </div>
    </div>
  </div>

  <!-- SLIDE 2 -->
  <div class="slide" aria-hidden="true">
    <img src="assets/images/slider2.jpg" alt="Financial Analytics & AI Scoring" class="slide-bg">
    <div class="slide-overlay"></div>
    <div class="slide-copy slide-center">
     
      <h1>Five financial signals, <em>one clear result</em>.</h1>
      <p class="lede">Every application is scored instantly upon submission with a plain-language confidence breakdown of income, debt ratio, and credit history.</p>
      <div class="hero-actions">
        <a href="loan_apply.php" class="btn btn-brass">Apply for a loan</a>
        <a href="#guidance" class="btn btn-ghost">Banking guide</a>
      </div>
    </div>
  </div>

  <!-- SLIDE 3 -->
  <div class="slide" aria-hidden="true">
    <img src="assets/images/slider3.jpg" alt="Customer Approval Success" class="slide-bg">
    <div class="slide-overlay"></div>
    <div class="slide-copy slide-center">
      
      <h1>Empowering your personal &amp; <em>business growth</em>.</h1>
      <p class="lede">Whether you are purchasing a home, expanding an SME, or consolidating debt, our tailored credit solutions support your ambition.</p>
      <div class="hero-actions">
        <a href="#services" class="btn btn-brass">Explore services</a>
        <a href="register.php" class="btn btn-ghost">Join BU Bank Ltd</a>
      </div>
    </div>
  </div>

  <!-- SLIDE 4 -->
  <div class="slide" aria-hidden="true">
    <img src="assets/images/slider1.jpg" alt="Admin & Portfolio Analytics" class="slide-bg">
    <div class="slide-overlay"></div>
    <div class="slide-copy slide-center">
    
      <h1>Complete credit oversight, <em>at a glance</em>.</h1>
      <p class="lede">Administrators can review loan applications, filter customer profiles, and monitor real-time approval ratios across all regions.</p>
      <div class="hero-actions">
        <a href="login.php" class="btn btn-brass">Admin login</a>
        <a href="#governor-messages" class="btn btn-ghost">Governor messages</a>
      </div>
    </div>
  </div>

  <!-- SLIDER CONTROLS -->
  <button class="slider-arrow prev" type="button" aria-label="Previous slide">&#8249;</button>
  <button class="slider-arrow next" type="button" aria-label="Next slide">&#8250;</button>
  <div class="slider-dots" role="tablist" aria-label="Slide selector">
    <button type="button" class="active" role="tab" aria-selected="true" aria-label="Slide 1"></button>
    <button type="button" role="tab" aria-selected="false" aria-label="Slide 2"></button>
    <button type="button" role="tab" aria-selected="false" aria-label="Slide 3"></button>
    <button type="button" role="tab" aria-selected="false" aria-label="Slide 4"></button>
  </div>
  <div class="slider-progress"><div class="slider-progress-bar" id="slider-progress-bar"></div></div>
</section>

<!-- =========================================================================
     2. BANKING GUIDANCE / HOW-TO SECTION (Standard Center Aligned)
     ========================================================================= -->
<section class="section alt" id="guidance">
  <div class="container">
    <div class="section-head text-center">
     
      <h2>How to Use BU Bank Ltd System</h2>
      <p>Follow these simple steps to open your account, request credit, and get instant eligibility decisions.</p>
    </div>

    <div class="guidance-grid">
      <!-- Step 1 -->
      <div class="guidance-card text-center">
        <div class="step-badge">01</div>
        <h3>How to Create an Account</h3>
        <p>Click on <strong>Open an account</strong> in the top menu. Enter your full name, valid email address, contact phone number, and a secure password to register your personal banking profile.</p>
        <a href="register.php" class="guidance-link">Create Account &rarr;</a>
      </div>

      <!-- Step 2 -->
      <div class="guidance-card text-center">
        <div class="step-badge">02</div>
        <h3>How to Apply for a Loan</h3>
        <p>Log in to your customer dashboard and click <strong>Apply for a loan</strong>. Enter your monthly earnings, co-applicant income, requested principal, term (in months), credit history, and property location.</p>
        <a href="loan_apply.php" class="guidance-link">Start Loan Application &rarr;</a>
      </div>

      <!-- Step 3 -->
      <div class="guidance-card text-center">
        <div class="step-badge">03</div>
        <h3>How Eligibility Scoring Works</h3>
        <p>Our scoring algorithm processes 5 core financial factors (Income-to-Loan ratio, Credit Record, Dependents, Education, and Property Area) and calculates a clear probability percentage instantly.</p>
        <a href="#how-it-works" class="guidance-link">View Scoring Details &rarr;</a>
      </div>

      <!-- Step 4 -->
      <div class="guidance-card text-center">
        <div class="step-badge">04</div>
        <h3>Track History &amp; Status</h3>
        <p>Visit your <strong>Loan History</strong> tab anytime to inspect past submissions, view model confidence scores, read factor breakdowns, and check official approval decisions.</p>
        <a href="loan_history.php" class="guidance-link">Check Status &rarr;</a>
      </div>
    </div>
  </div>
</section>

<!-- =========================================================================
     3. GOVERNOR MESSAGES SECTION 
     Layout: Two Sections / Cards with Minimized Image on Left & Message on Right
     ========================================================================= -->
<section class="section" id="governor-messages">
  <div class="container">
    <div class="section-head text-center">
      
      <h2>Leadership &amp; Governance Messages</h2>
      <p>Perspectives from our Managing Director and Central Bank Leadership on digital banking and ethical credit access.</p>
    </div>

    <div class="governor-list">
      <!-- SECTION 1: BANK GOVERNOR / MD (Left Side Minimized Image, Right Side Message) -->
      <div class="governor-horizontal-card">
        <div class="governor-left">
          <div class="governor-avatar-wrap">
            <img src="assets/images/governor.jpg" alt="Bank Managing Director & Governor" class="governor-minimized-img">
          </div>
          <div class="governor-mini-badge">BU Bank Ltd Leadership</div>
        </div>
        <div class="governor-right">
          <div class="governor-quote-box">
            "At BU Bank Ltd, our goal is to democratize credit access through objective, automated evaluation. By removing friction from loan processing, we empower entrepreneurs, families, and businesses across Bangladesh with speed and integrity."
          </div>
          <div class="governor-details">
            <h4>Dr. A. K. M. Rahman</h4>
            <span>Managing Director &amp; CEO, BU Bank Ltd</span>
          </div>
        </div>
      </div>

      <!-- SECTION 2: BANGLADESH BANK GOVERNOR (Left Side Minimized Image, Right Side Message) -->
      <div class="governor-horizontal-card">
        <div class="governor-left">
          <div class="governor-avatar-wrap">
            <img src="assets/images/bb_governor.jpg" alt="Bangladesh Bank Governor" class="governor-minimized-img">
          </div>
          <div class="governor-mini-badge">Central Bank Regulator</div>
        </div>
        <div class="governor-right">
          <div class="governor-quote-box">
            "Financial inclusion and technology-driven banking are vital pillars for Bangladesh's economic development. We urge all financial institutions to implement transparent digital tools that safeguard consumer interest and ensure sound monetary governance."
          </div>
          <div class="governor-details">
            <h4>Dr. Ahsan H. Mansur</h4>
            <span>Governor, Bangladesh Bank (Central Bank of Bangladesh)</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- =========================================================================
     4. OUR SERVICES SECTION (Standard Center Aligned)
     ========================================================================= -->
<section class="section alt" id="services">
  <div class="container">
    <div class="section-head text-center">
     
      <h2>Our Core Financial Services</h2>
      <p>Comprehensive banking and loan services tailored for individuals, entrepreneurs, and institutions.</p>
    </div>

    <div class="services-grid">
      <div class="service-card text-center">
        <div class="service-icon">🏠</div>
        <h3>Personal &amp; Home Loans</h3>
        <p>Competitive interest rates with flexible repayment periods from 10 to 30 years designed for home acquisition and personal needs.</p>
      </div>

      <div class="service-card text-center">
        <div class="service-icon">📈</div>
        <h3>SME &amp; Business Financing</h3>
        <p>Targeted capital injection for small and medium enterprises to expand inventory, purchase machinery, and scale business operations.</p>
      </div>

      <div class="service-card text-center">
        <div class="service-icon">⚡</div>
        <h3>Instant Credit Eligibility</h3>
        <p>Algorithmic preliminary loan scoring that delivers real-time confidence metrics and detailed positive/negative factor feedback.</p>
      </div>

      <div class="service-card text-center">
        <div class="service-icon">💳</div>
        <h3>Savings &amp; Term Deposits</h3>
        <p>High-yield fixed deposit schemes and flexible savings accounts with digital passbook tracking and automated interest payouts.</p>
      </div>

      <div class="service-card text-center">
        <div class="service-icon">🛡</div>
        <h3>Transparent Risk Management</h3>
        <p>Fully explainable scoring criteria protecting applicants from arbitrary loan rejections and ensuring equal financial opportunity.</p>
      </div>

      <div class="service-card text-center">
        <div class="service-icon">🌐</div>
        <h3>24/7 Digital Banking Portal</h3>
        <p>Manage your account, track loan history, submit new credit applications, and contact support anytime from any device.</p>
      </div>
    </div>
  </div>
</section>



<!-- =========================================================================
     6. FOOTER SECTION
     ========================================================================= -->
<?php include 'includes/footer.php'; ?>
