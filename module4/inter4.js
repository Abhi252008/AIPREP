// ============================================================
// MODULE 4 — Interview Setup Page JS (inter4.js)
// ============================================================

// 1. Scroll-reveal animation on load
document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        document.querySelectorAll('[data-reveal]').forEach(el => {
            el.classList.add('revealed');
        });
    }, 100);
});

// 2. Interactive elements
const categoryCards    = document.querySelectorAll('#categoryGrid .option-card');
const difficultyCards  = document.querySelectorAll('#difficultyGrid .option-card');
const companyBadges    = document.querySelectorAll('#companyGrid .company-badge');

// 3. Hidden state inputs
const selectedCategoryInput   = document.getElementById('selectedCategory');
const selectedDifficultyInput = document.getElementById('selectedDifficulty');
const selectedCompanyInput    = document.getElementById('selectedCompany');
const startBtn                = document.getElementById('startBtn');

// Map internal category values → friendly display labels
const CATEGORY_LABELS = {
    'HR Interview': 'HR & Behavioral',
    'Technical':    'Technical Core',
    'Coding':       'Coding & DSA',
    'Aptitude':     'Aptitude & Logic',
};

// 4. Category selection
categoryCards.forEach(card => {
    card.addEventListener('click', () => {
        categoryCards.forEach(c => c.classList.remove('selected'));
        card.classList.add('selected');
        selectedCategoryInput.value = card.getAttribute('data-category');
        updateSummaryAndValidate();
        // Scroll to company section
        setTimeout(() => {
            document.getElementById('companyGrid')
                    .closest('.step-section')
                    .scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 150);
    });
});

// 5. Company badge selection
companyBadges.forEach(badge => {
    badge.addEventListener('click', () => {
        companyBadges.forEach(b => b.classList.remove('selected'));
        badge.classList.add('selected');
        selectedCompanyInput.value = badge.getAttribute('data-company');
        updateSummaryAndValidate();
        // Scroll to difficulty section
        setTimeout(() => {
            document.getElementById('difficultyGrid')
                    .closest('.step-section')
                    .scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 150);
    });
});

// 6. Difficulty selection
difficultyCards.forEach(card => {
    card.addEventListener('click', () => {
        difficultyCards.forEach(c => c.classList.remove('selected'));
        card.classList.add('selected');
        selectedDifficultyInput.value = card.getAttribute('data-difficulty');
        updateSummaryAndValidate();
        // Scroll down to the submit button
        setTimeout(() => {
            document.getElementById('startBtn')
                    .scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 150);
    });
});

// 7. Update live summary + enable/disable submit button
function updateSummaryAndValidate() {
    const cat  = selectedCategoryInput.value;
    const diff = selectedDifficultyInput.value;
    const comp = selectedCompanyInput.value;

    const summaryBox    = document.getElementById('liveSummary');
    const dispDomain    = document.getElementById('dispDomain');
    const dispCompany   = document.getElementById('dispCompany');
    const dispDifficulty = document.getElementById('dispDifficulty');

    if (cat || diff) summaryBox.style.display = 'flex';

    if (cat && CATEGORY_LABELS[cat]) dispDomain.innerText = CATEGORY_LABELS[cat];
    if (comp)  dispCompany.innerText   = comp;
    if (diff)  dispDifficulty.innerText = diff;

    if (cat && diff) {
        startBtn.disabled = false;
        startBtn.classList.remove('btn-disabled');
    } else {
        startBtn.disabled = true;
        startBtn.classList.add('btn-disabled');
    }
}

// 8. Submit: POST to api/save_setup.php via AJAX, then redirect
async function submitSetup() {
    const cat  = selectedCategoryInput.value;
    const diff = selectedDifficultyInput.value;
    const comp = selectedCompanyInput.value || 'General';

    if (!cat || !diff) {
        showStatus('Please select a category and difficulty level.', 'error');
        return;
    }

    // Disable button & show loading
    startBtn.disabled = true;
    startBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Initializing…';

    try {
        const response = await fetch('api/save_setup.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                category:   cat,
                difficulty: diff,
                company:    comp,
                csrf_token: window.CSRF_TOKEN || ''
            })
        });

        const data = await response.json();

        if (data.success && data.session_id) {
            showStatus('Session created! Redirecting to your interview…', 'success');
            setTimeout(() => {
                window.location.href = 'session-created.php?session_id=' + data.session_id;
            }, 800);
        } else {
            showStatus(data.message || 'Something went wrong. Please try again.', 'error');
            resetBtn();
        }
    } catch (err) {
        console.error(err);
        showStatus('Network error. Please check your connection and try again.', 'error');
        resetBtn();
    }
}

// Helpers
function showStatus(msg, type) {
    const el = document.getElementById('statusMsg');
    if (!el) return;
    el.style.display = 'block';
    el.style.background = type === 'error'
        ? 'rgba(239, 68, 68, 0.15)'
        : 'rgba(52, 211, 153, 0.15)';
    el.style.border = type === 'error'
        ? '1px solid rgba(239, 68, 68, 0.4)'
        : '1px solid rgba(52, 211, 153, 0.4)';
    el.style.color = type === 'error' ? '#fca5a5' : '#6ee7b7';
    el.innerHTML = (type === 'error' ? '⚠ ' : '✓ ') + msg;
    el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function resetBtn() {
    startBtn.disabled = false;
    startBtn.classList.remove('btn-disabled');
    startBtn.innerHTML = 'Initialize Engine <i class="fa-solid fa-rocket" style="margin-left:10px;"></i>';
}