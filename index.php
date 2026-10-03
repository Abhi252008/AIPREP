<?php
$pageTitle = "Home";
require_once __DIR__ . '/includes/header.php';
?>

<!-- ============================= HERO ============================= -->
<section class="hero">
  <div class="container">
    <div class="row align-items-center">
      <div class="col-lg-6" data-reveal>
       
        <h1>Walk into your real interview <span class="gradient-text">already having done it.</span></h1>
        <p class="lead-text">
          Pick a role and difficulty, answer AI-generated questions the way you would in a real
          interview, and get scored feedback on what to fix before it counts.
        </p>
        <div class="hero-actions">
          <a href="<?= BASE_URL ?>/register.php" class="btn-gradient">Start Free Mock Interview</a>
          <a href="#features" class="btn-outline-glass">See how it works</a>
        </div>
      </div>

      <div class="col-lg-6">
        <!-- Signature element: a live demo of the actual product, not a decorative graphic -->
        <div class="interview-card glass" data-reveal>
          <div class="top-row">
            <span class="badge-live">LIVE MOCK INTERVIEW</span>
            <span class="timer"><i class="bi bi-clock"></i> 01:24</span>
          </div>
          <div class="question-box">
            <span id="heroQuestionText"></span><span class="cursor" id="heroCursor"></span>
          </div>
          <div class="score-row">
            <span>AI Score</span>
            <div class="score-track"><div class="score-fill" id="heroScoreFill"></div></div>
            <span id="heroScoreValue">—</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============================= FEATURES ============================= -->
<section class="section" id="features">
  <span class="section-eyebrow" data-reveal>Features</span>
  <h2 class="section-title" data-reveal>Everything an interview round actually tests</h2>
  <p class="section-sub" data-reveal>Not just questions — the full loop of answer, evaluate, and improve.</p>

  <div class="row g-4">
    <div class="col-md-4" data-reveal>
      <div class="feature-card glass">
        <div class="icon-wrap"><i class="bi bi-robot"></i></div>
        <h3>AI-generated questions</h3>
        <p>Fresh questions per session, tailored to your chosen role, difficulty, and target company.</p>
      </div>
    </div>
    <div class="col-md-4" data-reveal>
      <div class="feature-card glass">
        <div class="icon-wrap"><i class="bi bi-clipboard-data"></i></div>
        <h3>Real scored feedback</h3>
        <p>Every answer gets a score out of 10, plus what was missing and how to say it better.</p>
      </div>
    </div>
    <div class="col-md-4" data-reveal>
      <div class="feature-card glass">
        <div class="icon-wrap"><i class="bi bi-graph-up-arrow"></i></div>
        <h3>Progress you can see</h3>
        <p>Track scores across sessions and see exactly which skills are improving and which aren't.</p>
      </div>
    </div>
    <div class="col-md-4" data-reveal>
      <div class="feature-card glass">
        <div class="icon-wrap"><i class="bi bi-diagram-3"></i></div>
        <h3>Five interview types</h3>
        <p>HR, Technical, Aptitude, Coding, and Company-specific rounds — practice the one you need.</p>
      </div>
    </div>
    <div class="col-md-4" data-reveal>
      <div class="feature-card glass">
        <div class="icon-wrap"><i class="bi bi-file-earmark-pdf"></i></div>
        <h3>Downloadable reports</h3>
        <p>Get a PDF summary of strengths, weak areas, and suggestions after every session.</p>
      </div>
    </div>
    <div class="col-md-4" data-reveal>
      <div class="feature-card glass">
        <div class="icon-wrap"><i class="bi bi-shield-lock"></i></div>
        <h3>Private by default</h3>
        <p>Your answers and resume stay tied to your account only — never shared or public.</p>
      </div>
    </div>
  </div>
</section>

<!-- ============================= ABOUT ============================= -->
<section class="section" id="about">
  <div class="row align-items-center g-5">
    <div class="col-lg-6" data-reveal>
      <span class="section-eyebrow">About</span>
      <h2 class="section-title">Built by students, for the placement season</h2>
      <p class="section-sub" style="margin-bottom:1.5rem;">
        This platform exists because mock interviews with a senior or a friend only go so far —
        they're not always available, and feedback is inconsistent. An AI interviewer is available
        at 2am before your placement test, and it never gets tired of giving you another round.
      </p>
    </div>
    <div class="col-lg-6">
      <div class="row g-4" data-reveal>
        <div class="col-6"><div class="stat-block glass" style="padding:1.5rem;"><div class="num">5</div><div class="label">Interview types</div></div></div>
        <div class="col-6"><div class="stat-block glass" style="padding:1.5rem;"><div class="num">3</div><div class="label">Difficulty levels</div></div></div>
        <div class="col-6"><div class="stat-block glass" style="padding:1.5rem;"><div class="num">100%</div><div class="label">AI-scored feedback</div></div></div>
        <div class="col-6"><div class="stat-block glass" style="padding:1.5rem;"><div class="num">24/7</div><div class="label">Available to practice</div></div></div>
      </div>
    </div>
  </div>
</section>

<!-- ============================= TESTIMONIALS ============================= -->
<section class="section" id="testimonials">
  <span class="section-eyebrow" data-reveal>Testimonials</span>
  <h2 class="section-title" data-reveal>What early users are saying</h2>
  <p class="section-sub" data-reveal>Feedback from students who used this to prepare for placements.</p>

  <div class="row g-4">
    <div class="col-md-4" data-reveal>
      <div class="testimonial-card glass">
        <p class="quote">"I practiced HR questions the night before my TCS interview and the AI caught exactly the vague answer I kept giving."</p>
        <div class="person">
          <div class="avatar">R</div>
          <div><div class="name">Rahul S.</div><div class="role">Final Year, CSE</div></div>
        </div>
      </div>
    </div>
    <div class="col-md-4" data-reveal>
      <div class="testimonial-card glass">
        <p class="quote">"The scoring felt strict but fair — it pointed out that I never actually answered the question directly."</p>
        <div class="person">
          <div class="avatar">P</div>
          <div><div class="name">Priya M.</div><div class="role">Final Year, IT</div></div>
        </div>
      </div>
    </div>
    <div class="col-md-4" data-reveal>
      <div class="testimonial-card glass">
        <p class="quote">"Being able to see my score go up over three sessions was honestly motivating before placement week."</p>
        <div class="person">
          <div class="avatar">A</div>
          <div><div class="name">Arjun K.</div><div class="role">Diploma, CSE</div></div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============================= FAQ ============================= -->
<section class="section" id="faq">
  <span class="section-eyebrow" data-reveal>FAQ</span>
  <h2 class="section-title" data-reveal>Questions people ask before starting</h2>

  <div data-reveal>
    <div class="faq-item">
      <button class="faq-question">Is this free to use? <span class="plus">+</span></button>
      <div class="faq-answer">Yes — creating an account and taking mock interviews is free.</div>
    </div>
    <div class="faq-item">
      <button class="faq-question">How does the AI generate questions? <span class="plus">+</span></button>
      <div class="faq-answer">It uses the Gemini API to generate questions based on your chosen role, category, and difficulty level.</div>
    </div>
    <div class="faq-item">
      <button class="faq-question">Can I practice for a specific company? <span class="plus">+</span></button>
      <div class="faq-answer">Yes — the Company-Specific category lets you pick a target company and get relevant questions.</div>
    </div>
    <div class="faq-item">
      <button class="faq-question">Is my data private? <span class="plus">+</span></button>
      <div class="faq-answer">Your answers, resume, and reports are tied to your account only and are never shared publicly.</div>
    </div>
  </div>
</section>

<!-- ============================= CONTACT ============================= -->
<section class="section" id="contact">
  <div class="row g-5">
    <div class="col-lg-5" data-reveal>
      <span class="section-eyebrow">Contact</span>
      <h2 class="section-title">Have a question or found a bug?</h2>
      <p class="section-sub">Reach out and we'll get back to you.</p>
    </div>
    <div class="col-lg-7" data-reveal>
      <form class="contact-form glass p-4" action="#" method="post">
        <div class="row g-3">
          <div class="col-md-6">
            <label for="contactName">Name</label>
            <input type="text" class="form-control" id="contactName" name="name" placeholder="Your name" required>
          </div>
          <div class="col-md-6">
            <label for="contactEmail">Email</label>
            <input type="email" class="form-control" id="contactEmail" name="email" placeholder="[email protected]" required>
          </div>
          <div class="col-12">
            <label for="contactMessage">Message</label>
            <textarea class="form-control" id="contactMessage" name="message" rows="4" placeholder="How can we help?" required></textarea>
          </div>
          <div class="col-12">
            <button type="submit" class="btn-gradient w-100">Send message</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
