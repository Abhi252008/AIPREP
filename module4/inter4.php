<?php
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

// Generate CSRF token for API requests
$csrfToken = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configure AI Mock Interview | MockAI</title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=Inter:wght@400;500;600&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- LINK TO YOUR MODULE 1 CSS FILE -->
    <link rel="stylesheet" href="style.css"> 

    <style>
        /* --- Responsive Grids for Desktop & Mobile --- */
        .card-grid {
            display: grid;
            margin-top: 1.5rem;
        }

        /* Desktop Layout (Laptop) - Increased Sizes */
        @media (min-width: 901px) {
            .grid-4-cols { grid-template-columns: repeat(4, 1fr); gap: 2rem; }
            .grid-3-cols { grid-template-columns: repeat(3, 1fr); gap: 2rem; }
            
            .company-grid {
                display: grid;
                grid-template-columns: repeat(5, 1fr);
                gap: 1.5rem;
                margin-top: 1.5rem;
            }

            /* Making cards larger on desktop */
            .option-card {
                padding: 2.2rem 1.5rem !important;
            }
            .option-card h3 {
                font-size: 1.25rem !important;
                margin-bottom: 0.8rem;
            }
            .option-card p {
                font-size: 0.95rem !important;
            }
            .icon-wrap {
                width: 54px !important;
                height: 54px !important;
                font-size: 1.5rem !important;
                margin-bottom: 1.2rem !important;
            }
            .difficulty-card i {
                font-size: 2.5rem !important;
                margin-bottom: 1.5rem !important;
            }
            .company-badge {
                padding: 1.8rem 1rem !important;
            }
            .company-badge img {
                max-height: 32px !important;
            }
            .company-badge span {
                font-size: 0.95rem !important;
            }
        }

        /* Mobile Layout */
        @media (max-width: 900px) {
            .card-grid { gap: 1.2rem; }
            .card-grid, .company-grid {
                display: flex;
                overflow-x: auto;
                scroll-snap-type: x mandatory;
                padding-bottom: 1rem;
                -webkit-overflow-scrolling: touch;
            }
            .card-grid::-webkit-scrollbar, .company-grid::-webkit-scrollbar {
                height: 6px;
            }
            .card-grid::-webkit-scrollbar-thumb, .company-grid::-webkit-scrollbar-thumb {
                background: #cbd5e1;
                border-radius: 10px;
            }
            .option-card {
                flex: 0 0 82%; 
                scroll-snap-align: center;
            }
            .company-badge {
                flex: 0 0 120px;
                scroll-snap-align: start;
                gap: 1rem;
            }
        }

        /* --- Dynamic Summary Box --- */
        .summary-box {
            display: none;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1.5rem;
            margin-bottom: 2rem;
            padding: 1.8rem 2.5rem;
            border-left: 5px solid var(--cyan);
        }
        .summary-item { display: flex; flex-direction: column; gap: 6px; }
        .summary-label {
            font-family: var(--font-mono); font-size: 0.8rem;
            color: var(--mist); text-transform: uppercase; letter-spacing: 0.1em;
        }
        .summary-value {
            font-family: var(--font-display); font-size: 1.3rem;
            color: var(--paper); font-weight: 600;
        }
    </style>
</head>
<body>

    <!-- Glass Navbar -->
    <header class="navbar-glass glass">
        <div class="brand">
            <div class="dot"></div> MockAI
        </div>
        <nav id="navLinks" style="display: flex; align-items: center; gap: 2rem;">
            <a href="../index.php"><i class="fa-solid fa-arrow-left"></i> Dashboard</a>
            <a href="../user/profile.php" style="color: var(--cyan); font-size: 1.4rem; display: flex; align-items: center; gap: 8px;" title="User Profile">
                <i class="fa-solid fa-circle-user"></i>
            </a>
        </nav>
    </header>

    <!-- Main Setup Section -->
    <main class="section" style="padding-top: 120px; max-width: 1300px;">
        
        <div style="text-align: center; margin-bottom: 3.5rem;" data-reveal class="revealed">
            <span class="section-eyebrow" style="font-size: 0.9rem;">Parameter Configuration</span>
            <h1 class="section-title" style="font-size: clamp(2.2rem, 4vw, 3rem);"><span class="gradient-text">Setup Your Interview</span></h1>
            <p class="section-sub" style="margin: 0 auto; font-size: 1.1rem; max-width: 600px;">Select your target domain and difficulty threshold to generate a real-time AI session.</p>
        </div>

        <!-- Error/Loading Message Box -->
        <div id="statusMsg" style="display:none; padding: 1rem 1.5rem; border-radius: var(--radius-md); margin-bottom: 1.5rem; font-family: var(--font-mono); font-size: 0.9rem;"></div>

        <form id="setupForm" onsubmit="return false;">

            <!-- Step 1: Category -->
            <div class="step-section" data-reveal class="revealed">
                <h3 style="color: var(--paper); display: flex; align-items: center; gap: 12px; font-size: 1.3rem;">
                    <span style="background: rgba(34, 211, 238, 0.2); color: var(--cyan); width: 32px; height: 32px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 1rem; font-weight: bold;">1</span>
                    Select Interview Category
                </h3>
                
                <div class="card-grid grid-4-cols" id="categoryGrid">
                    <div class="feature-card glass option-card" data-category="HR Interview">
                        <div class="icon-wrap" style="background: rgba(108, 92, 231, 0.2); color: var(--violet);"><i class="fa-solid fa-user-tie"></i></div>
                        <h3>HR & Behavioral</h3>
                        <p>Culture fit, STAR format, leadership.</p>
                    </div>

                    <div class="feature-card glass option-card" data-category="Technical">
                        <div class="icon-wrap" style="background: rgba(34, 211, 238, 0.2); color: var(--cyan);"><i class="fa-solid fa-laptop-code"></i></div>
                        <h3>Technical Core</h3>
                        <p>System Design, OS, DBMS, and OOPs.</p>
                    </div>

                    <div class="feature-card glass option-card" data-category="Coding">
                        <div class="icon-wrap" style="background: rgba(52, 211, 153, 0.2); color: var(--success);"><i class="fa-solid fa-terminal"></i></div>
                        <h3>Coding & DSA</h3>
                        <p>Data structures, logic, and algorithms.</p>
                    </div>

                    <div class="feature-card glass option-card" data-category="Aptitude">
                        <div class="icon-wrap" style="background: rgba(251, 191, 36, 0.2); color: var(--warning);"><i class="fa-solid fa-calculator"></i></div>
                        <h3>Aptitude & Logic</h3>
                        <p>Quantitative, Verbal, and Logical.</p>
                    </div>
                </div>
                <input type="hidden" name="category" id="selectedCategory" required>
            </div>

            <!-- Step 2: Company Format -->
            <div class="step-section glass" data-reveal class="revealed" style="padding: 2.5rem;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
                    <div>
                        <h3 style="color: var(--paper); display: flex; align-items: center; gap: 12px; font-size: 1.3rem;">
                            <span style="background: rgba(108, 92, 231, 0.2); color: var(--violet); width: 32px; height: 32px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 1rem; font-weight: bold;">2</span>
                            Target Enterprise <span class="badge-pro" style="font-size: 0.75rem; padding: 4px 10px;">Pro</span>
                        </h3>
                        <p style="color: var(--mist); font-size: 1rem; margin-top: 0.5rem; margin-left: 44px;">Choose a specific company format for tailored questions (Optional).</p>
                    </div>
                </div>

                <div class="company-grid" id="companyGrid">

                    <div class="company-badge selected" data-company="General">
                        <i class="fa-solid fa-globe" style="font-size:28px; color:#2563eb;"></i>
                        <span>General / All</span>
                    </div>

                    <div class="company-badge" data-company="Google">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/2/2f/Google_2015_logo.svg" alt="Google">
                        <span>Google</span>
                    </div>

                    <div class="company-badge" data-company="Amazon">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/a/a9/Amazon_logo.svg" alt="Amazon">
                        <span>Amazon</span>
                    </div>

                    <div class="company-badge" data-company="Microsoft">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/9/96/Microsoft_logo_%282012%29.svg" alt="Microsoft">
                        <span>Microsoft</span>
                    </div>

                    <div class="company-badge" data-company="Meta">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/7/7b/Meta_Platforms_Inc._logo.svg" alt="Meta">
                        <span>Meta</span>
                    </div>

                    <div class="company-badge" data-company="Apple">
                        <i class="fa-brands fa-apple" style="font-size:28px; color:#333333;"></i>
                        <span>Apple</span>
                    </div>

                    <div class="company-badge" data-company="TCS">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 90 34" style="height:28px; display:block;">
                            <text x="2" y="28" font-family="'Arial Black',Arial,sans-serif" font-weight="900" font-size="30" fill="#003087" letter-spacing="-1">TCS</text>
                        </svg>
                        <span>TCS</span>
                    </div>

                    <div class="company-badge" data-company="Infosys">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/9/95/Infosys_logo.svg" alt="Infosys">
                        <span>Infosys</span>
                    </div>

                    <div class="company-badge" data-company="Accenture">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/c/cd/Accenture.svg" alt="Accenture">
                        <span>Accenture</span>
                    </div>

                </div>
                <input type="hidden" name="company" id="selectedCompany" value="General">
            </div>

            <!-- Step 3: Difficulty -->
            <div class="step-section" data-reveal class="revealed">
                <h3 style="color: var(--paper); display: flex; align-items: center; gap: 12px; font-size: 1.3rem;">
                    <span style="background: rgba(34, 211, 238, 0.2); color: var(--cyan); width: 32px; height: 32px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 1rem; font-weight: bold;">3</span>
                    Difficulty Threshold
                </h3>
                
                <div class="card-grid grid-3-cols" id="difficultyGrid">
                    <div class="feature-card glass option-card difficulty-card" data-difficulty="Beginner" style="text-align: center;">
                        <i class="fa-solid fa-seedling" style="color: var(--success);"></i>
                        <h3>Beginner</h3>
                    </div>
                    <div class="feature-card glass option-card difficulty-card" data-difficulty="Intermediate" style="text-align: center;">
                        <i class="fa-solid fa-gauge-high" style="color: var(--cyan);"></i>
                        <h3>Intermediate</h3>
                    </div>
                    <div class="feature-card glass option-card difficulty-card" data-difficulty="Advanced" style="text-align: center;">
                        <i class="fa-solid fa-fire-flame-curved" style="color: var(--warning);"></i>
                        <h3>Advanced</h3>
                    </div>
                </div>
                <input type="hidden" name="difficulty" id="selectedDifficulty" required>
            </div>

            <!-- Live Selection Summary -->
            <div class="summary-box glass" id="liveSummary">
                <div class="summary-item">
                    <span class="summary-label">Domain</span>
                    <span class="summary-value" id="dispDomain" style="color: var(--cyan);">—</span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Target Format</span>
                    <span class="summary-value" id="dispCompany" style="color: var(--violet);">General / All</span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Difficulty</span>
                    <span class="summary-value" id="dispDifficulty" style="color: var(--warning);">—</span>
                </div>
            </div>

            <!-- Submit Action -->
            <div class="action-container" data-reveal class="revealed">
                <button type="button" id="startBtn" disabled class="btn-gradient btn-disabled" style="font-size: 1.2rem; padding: 1.2rem 3rem; max-width: 500px;" onclick="submitSetup()">
                    Initialize Engine <i class="fa-solid fa-rocket" style="margin-left: 10px;"></i>
                </button>
            </div>
        </form>

    </main>

    <!-- Footer -->
    <footer>
        <div style="text-align: center;">
            <div class="brand" style="margin-bottom: 0.5rem;"><div class="dot" style="display:inline-block; width:8px; height:8px; border-radius:50%; background:var(--success); margin-right:5px;"></div>MockAI Platform</div>
            <p>&copy; 2026 AI Interview Preparation Platform.</p>
        </div>
    </footer>

    <!-- CSRF Token for API Calls -->
    <script>window.CSRF_TOKEN = <?= json_encode($csrfToken) ?>;</script>

    <!-- EXTERNAL JAVASCRIPT LINK -->
    <script src="inter4.js"></script>

</body>
</html>