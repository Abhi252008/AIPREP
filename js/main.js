/* ==========================================================================
   AI Interview Preparation Platform — Main JS
   Small, dependency-free interactions: mobile nav, FAQ accordion,
   the animated hero "live interview" card, and scroll reveals.
   ========================================================================== */

document.addEventListener("DOMContentLoaded", function () {

  /* ---------- Mobile nav toggle ---------- */
  const navToggle = document.getElementById("navToggle");
  const navLinks = document.getElementById("navLinks");
  if (navToggle && navLinks) {
    navToggle.addEventListener("click", function () {
      navLinks.classList.toggle("show-mobile-nav");
    });
  }

  /* ---------- FAQ accordion ---------- */
  document.querySelectorAll(".faq-item").forEach(function (item) {
    const question = item.querySelector(".faq-question");
    question.addEventListener("click", function () {
      const isOpen = item.classList.contains("open");
      document.querySelectorAll(".faq-item").forEach(i => i.classList.remove("open"));
      if (!isOpen) item.classList.add("open");
    });
  });

  /* ---------- Hero "live interview" card animation ---------- */
  // This mimics what Module 6 (AI Mock Interview Engine) will actually do:
  // a question types out, then Module 7 (AI Evaluation) fills in a score.
  const sampleQuestion = "Tell me about a time you solved a difficult technical problem.";
  const questionEl = document.getElementById("heroQuestionText");
  const cursorEl = document.getElementById("heroCursor");
  const scoreFillEl = document.getElementById("heroScoreFill");
  const scoreValueEl = document.getElementById("heroScoreValue");

  function typeQuestion() {
    if (!questionEl) return;
    questionEl.textContent = "";
    let i = 0;
    const typingSpeed = 35;

    function typeChar() {
      if (i < sampleQuestion.length) {
        questionEl.textContent += sampleQuestion.charAt(i);
        i++;
        setTimeout(typeChar, typingSpeed);
      } else {
        // typing done — reveal the score after a short pause
        setTimeout(revealScore, 600);
      }
    }
    typeChar();
  }

  function revealScore() {
    if (!scoreFillEl) return;
    const finalScore = 8.4;
    scoreFillEl.style.width = (finalScore / 10 * 100) + "%";
    if (scoreValueEl) scoreValueEl.textContent = finalScore.toFixed(1) + " / 10";

    // Loop the demo after a pause so visitors see the full cycle again
    setTimeout(function () {
      if (scoreFillEl) scoreFillEl.style.width = "0%";
      if (scoreValueEl) scoreValueEl.textContent = "—";
      setTimeout(typeQuestion, 800);
    }, 3500);
  }

  typeQuestion();

  /* ---------- Scroll reveal for sections ---------- */
  const revealTargets = document.querySelectorAll("[data-reveal]");
  const revealObserver = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (entry.isIntersecting) {
        entry.target.classList.add("revealed");
        revealObserver.unobserve(entry.target);
      }
    });
  }, { threshold: 0.15 });

  revealTargets.forEach(function (el) { revealObserver.observe(el); });

});
