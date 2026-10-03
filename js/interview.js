/* ==========================================================================
   MODULE 5 — AI Mock Interview Engine (client-side)
   Handles moving between questions, the per-question timer, and
   auto-saving each answer via api/save_answer.php.
   Relies on SESSION_ID, SAVE_URL, FINISH_URL, QUESTIONS from interview.php.
   ========================================================================== */

let currentIndex = 0;
let timerInterval = null;
let secondsLeft = 120; // 2 minutes per question

const questionText = document.getElementById('questionText');
const questionCounter = document.getElementById('questionCounter');
const answerBox = document.getElementById('answerBox');
const progressFill = document.getElementById('progressFill');
const timerText = document.getElementById('timerText');
const saveStatus = document.getElementById('saveStatus');
const prevBtn = document.getElementById('prevBtn');
const nextBtn = document.getElementById('nextBtn');

function renderQuestion(index) {
  const q = QUESTIONS[index];
  questionText.textContent = q.text;
  questionCounter.textContent = `Question ${index + 1} of ${QUESTIONS.length}`;
  answerBox.value = q.answer || '';
  progressFill.style.width = ((index) / QUESTIONS.length * 100) + '%';
  prevBtn.disabled = index === 0;
  nextBtn.textContent = (index === QUESTIONS.length - 1) ? 'Finish Interview' : 'Next';
  saveStatus.textContent = '';
  resetTimer();
}

function resetTimer() {
  clearInterval(timerInterval);
  secondsLeft = 120;
  updateTimerDisplay();
  timerInterval = setInterval(() => {
    secondsLeft--;
    updateTimerDisplay();
    if (secondsLeft <= 0) {
      clearInterval(timerInterval);
      // Time's up — auto-save whatever was typed and move on.
      saveCurrentAnswer().then(() => goNext());
    }
  }, 1000);
}

function updateTimerDisplay() {
  const m = Math.floor(Math.max(secondsLeft, 0) / 60).toString().padStart(2, '0');
  const s = Math.max(secondsLeft, 0).toString().padStart(2, '0').slice(-2);
  timerText.textContent = `${m}:${(secondsLeft % 60).toString().padStart(2, '0')}`;
}

async function saveCurrentAnswer() {
  const q = QUESTIONS[currentIndex];
  q.answer = answerBox.value;
  saveStatus.textContent = 'Saving...';

  try {
    const res = await fetch(SAVE_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        session_id: SESSION_ID,
        question_id: q.id,
        answer_text: q.answer
      })
    });
    const data = await res.json();
    saveStatus.textContent = data.success ? 'Saved' : (data.error || 'Could not save');
  } catch (err) {
    saveStatus.textContent = 'Could not reach the server — answer kept locally for now.';
  }
}

async function goNext() {
  await saveCurrentAnswer();
  if (currentIndex < QUESTIONS.length - 1) {
    currentIndex++;
    renderQuestion(currentIndex);
  } else {
    clearInterval(timerInterval);
    window.location.href = FINISH_URL;
  }
}

async function goPrev() {
  await saveCurrentAnswer();
  if (currentIndex > 0) {
    currentIndex--;
    renderQuestion(currentIndex);
  }
}

nextBtn.addEventListener('click', goNext);
prevBtn.addEventListener('click', goPrev);

// Auto-save on typing pause (debounced) as an extra safety net,
// on top of the save that already happens on Next/Previous/timeout.
let debounceTimer = null;
answerBox.addEventListener('input', () => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(saveCurrentAnswer, 2000);
});

renderQuestion(currentIndex);
