// 1. Initial Page Load Animation
// This removes the invisible state (opacity: 0) and triggers the slide-up animation
document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        document.querySelectorAll('[data-reveal]').forEach(el => {
            el.classList.add('revealed');
        });
    }, 100);
});

// 2. Select all interactive elements
const categoryCards = document.querySelectorAll('#categoryGrid .option-card');
const difficultyCards = document.querySelectorAll('#difficultyGrid .option-card');
const companyBadges = document.querySelectorAll('#companyGrid .company-badge');

// 3. Select hidden inputs and submit button
const selectedCategoryInput = document.getElementById('selectedCategory');
const selectedDifficultyInput = document.getElementById('selectedDifficulty');
const selectedCompanyInput = document.getElementById('selectedCompany');
const startBtn = document.getElementById('startBtn');

// 4. Handle Category Selection & Scroll
categoryCards.forEach(card => {
    card.addEventListener('click', () => {
        // Remove 'selected' from all category cards, add to clicked
        categoryCards.forEach(c => c.classList.remove('selected'));
        card.classList.add('selected');
        
        // Update hidden input
        selectedCategoryInput.value = card.getAttribute('data-category');
        
        updateSummaryAndValidate();

        // Smooth scroll to the Company section
        setTimeout(() => {
            document.getElementById('companyGrid').closest('.step-section').scrollIntoView({ 
                behavior: 'smooth', 
                block: 'center' 
            });
        }, 150);
    });
});

// 5. Handle Company Selection & Scroll
companyBadges.forEach(badge => {
    badge.addEventListener('click', () => {
        // Remove 'selected' from all company badges, add to clicked
        companyBadges.forEach(b => b.classList.remove('selected'));
        badge.classList.add('selected');
        
        // Update hidden input
        selectedCompanyInput.value = badge.getAttribute('data-company');
        
        updateSummaryAndValidate();

        // Smooth scroll to the Difficulty section
        setTimeout(() => {
            document.getElementById('difficultyGrid').closest('.step-section').scrollIntoView({ 
                behavior: 'smooth', 
                block: 'center' 
            });
        }, 150);
    });
});

// 6. Handle Difficulty Selection & Scroll
difficultyCards.forEach(card => {
    card.addEventListener('click', () => {
        // Remove 'selected' from all difficulty cards, add to clicked
        difficultyCards.forEach(c => c.classList.remove('selected'));
        card.classList.add('selected');
        
        // Update hidden input
        selectedDifficultyInput.value = card.getAttribute('data-difficulty');
        
        updateSummaryAndValidate();

        // Smooth scroll down to the Submit button
        setTimeout(() => {
            document.getElementById('startBtn').scrollIntoView({ 
                behavior: 'smooth', 
                block: 'center' 
            });
        }, 150);
    });
});

// 7. Core Function: Update the Summary Box & Validate the Form Button
function updateSummaryAndValidate() {
    const cat = selectedCategoryInput.value;
    const diff = selectedDifficultyInput.value;
    const comp = selectedCompanyInput.value;
    
    const summaryBox = document.getElementById('liveSummary');
    const dispDomain = document.getElementById('dispDomain');
    const dispCompany = document.getElementById('dispCompany');
    const dispDifficulty = document.getElementById('dispDifficulty');

    // Show the summary box if at least one item is clicked
    if (cat || diff) {
        summaryBox.style.display = 'flex';
    }
    
    // Update Category Text
    if (cat) {
        if (cat === 'hr') dispDomain.innerText = "HR & Behavioral";
        else if (cat === 'technical') dispDomain.innerText = "Technical Core";
        else if (cat === 'coding') dispDomain.innerText = "Coding & DSA";
        else if (cat === 'aptitude') dispDomain.innerText = "Aptitude & Logic";
    }
    
    // Update Company & Difficulty Text
    if (comp) {
        dispCompany.innerText = comp;
    }
    if (diff) {
        dispDifficulty.innerText = diff;
    }

    // Validation: Both Category and Difficulty must be selected to proceed
    if (cat && diff) {
        startBtn.disabled = false;
        startBtn.classList.remove('btn-disabled');
    } else {
        startBtn.disabled = true;
        startBtn.classList.add('btn-disabled');
    }
}
