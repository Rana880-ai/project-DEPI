<?php
session_start();
if(isset($_SESSION['user_id'])) {
    if($_SESSION['user_type'] == 'user') {
        header("Location: index.php");
    } else {
        header("Location: dashboard-manager.php");
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartPark - Intelligent Parking Solutions</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', 'Segoe UI', sans-serif;
        }

        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #f59e0b;
            --accent: #10b981;
            --dark: #1e293b;
            --light: #f8fafc;
            --gradient: linear-gradient(135deg, #011054ff 0%, #38006fff 100%);
        }

        body {
            min-height: 100vh;
            background: var(--gradient);
            overflow-x: hidden;
            position: relative;
        }

        /* Particles Background */
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
        }

        .particle {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg) scale(1); }
            33% { transform: translateY(-30px) rotate(120deg) scale(1.1); }
            66% { transform: translateY(15px) rotate(240deg) scale(0.9); }
        }

        /* Main Container */
        .hero-container {
            position: relative;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            z-index: 2;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 32px;
            padding: 60px 50px;
            max-width: 1200px;
            width: 100%;
            box-shadow: 
                0 25px 50px rgba(0, 0, 0, 0.25),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .glass-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transition: 0.5s;
        }

        .glass-card:hover::before {
            left: 100%;
        }

        /* Logo & Header */
        .logo {
            margin-bottom: 40px;
        }

        .logo-icon {
            font-size: 4em;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 15px;
            display: inline-block;
            animation: bounce 2s ease-in-out infinite;
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-10px); }
            60% { transform: translateY(-5px); }
        }

        .logo h1 {
            font-size: 4.5em;
            font-weight: 800;
            background: linear-gradient(135deg, #fff, var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
            letter-spacing: -1px;
        }

        .tagline {
            font-size: 1.4em;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 50px;
            font-weight: 300;
            line-height: 1.6;
        }

        /* Project Description */
        .project-description {
            max-width: 800px;
            margin: 0 auto 50px;
        }

        .description-text {
            font-size: 1.2em;
            color: rgba(255, 255, 255, 0.85);
            line-height: 1.8;
            margin-bottom: 30px;
            text-align: left;
        }

        .highlight {
            background: linear-gradient(120deg, transparent 0%, rgba(255, 215, 0, 0.2) 50%, transparent 100%);
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: 600;
        }

        /* Features Grid */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
            margin-bottom: 50px;
        }

        .feature-card {
            background: rgba(255, 255, 255, 0.08);
            padding: 35px 25px;
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }

        .feature-card:hover {
            transform: translateY(-10px) scale(1.02);
            background: rgba(255, 255, 255, 0.12);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }

        .feature-icon {
            font-size: 2.5em;
            color: var(--secondary);
            margin-bottom: 20px;
        }

        .feature-card h3 {
            font-size: 1.4em;
            color: #fff;
            margin-bottom: 15px;
            font-weight: 600;
        }

        .feature-card p {
            color: rgba(255, 255, 255, 0.7);
            line-height: 1.6;
            font-size: 0.95em;
        }

        /* Action Buttons */
        .action-section {
            margin-top: 50px;
        }

        .btn-group {
            display: flex;
            justify-content: center;
            gap: 25px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 18px 45px;
            border: none;
            border-radius: 16px;
            font-size: 1.1em;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.4s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            min-width: 200px;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.4);
        }

        .btn-secondary {
            background: linear-gradient(135deg, var(--secondary), #eab308);
            color: #fff;
            box-shadow: 0 10px 30px rgba(245, 158, 11, 0.4);
        }

        .btn:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
        }

        .btn:active {
            transform: translateY(-2px) scale(1.02);
        }

        /* Stats */
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 25px;
            margin-top: 60px;
            padding-top: 40px;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 2.8em;
            font-weight: 800;
            background: linear-gradient(135deg, var(--secondary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 8px;
            display: block;
        }

        .stat-label {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9em;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .glass-card {
                padding: 40px 30px;
                border-radius: 24px;
            }
            
            .logo h1 {
                font-size: 3.2em;
            }
            
            .tagline {
                font-size: 1.2em;
            }
            
            .btn-group {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                max-width: 300px;
            }
            
            .features-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .glass-card {
                padding: 30px 20px;
            }
            
            .logo h1 {
                font-size: 2.5em;
            }
            
            .tagline {
                font-size: 1.1em;
            }
            
            .description-text {
                font-size: 1.1em;
            }
        }

        /* Animations */
        .fade-in {
            animation: fadeIn 1s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .slide-in {
            animation: slideIn 0.8s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
    </style>
</head>
<body>
    <!-- Animated Background Particles -->
    <div class="particles" id="particles"></div>

    <div class="hero-container">
        <div class="glass-card fade-in">
            <!-- Logo & Header -->
            <div class="logo">
                <div class="logo-icon">
                    <i class="fas fa-parking"></i>
                </div>
                <h1>SmartPark</h1>
                <div class="tagline">
                    Revolutionizing Urban Mobility with Intelligent Parking Solutions
                </div>
            </div>

            <!-- Project Description -->
            <div class="project-description slide-in">
                <p class="description-text">
                    <span class="highlight">SmartPark</span> is an innovative parking management platform that leverages 
                    cutting-edge technology to solve urban parking challenges. Our intelligent system provides 
                    <span class="highlight">real-time parking availability</span>, seamless booking experiences, and 
                    comprehensive management tools for both users and parking facility operators.
                </p>
                <p class="description-text">
                    By combining <span class="highlight">IoT sensors</span>, <span class="highlight">machine learning algorithms</span>, 
                    and <span class="highlight">user-friendly interfaces</span>, we're transforming how cities approach 
                    parking infrastructure. Our platform reduces congestion, saves time, and creates a more sustainable 
                    urban environment.
                </p>
            </div>

            <!-- Features Grid -->
            <div class="features-grid">
                <div class="feature-card slide-in">
                    <div class="feature-icon">
                        <i class="fas fa-brain"></i>
                    </div>
                    <h3>AI-Powered Analytics</h3>
                    <p>Smart algorithms predict parking patterns and optimize space utilization in real-time.</p>
                </div>
                
                <div class="feature-card slide-in">
                    <div class="feature-icon">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <h3>Instant Booking</h3>
                    <p>Reserve your parking spot in seconds with our streamlined booking process.</p>
                </div>
                
                <div class="feature-card slide-in">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3>Secure & Reliable</h3>
                    <p>Bank-level security and 99.9% uptime ensure your parking experience is always protected.</p>
                </div>
                
                <div class="feature-card slide-in">
                    <div class="feature-icon">
                        <i class="fas fa-chart-network"></i>
                    </div>
                    <h3>Smart Integration</h3>
                    <p>Seamlessly integrates with city infrastructure and third-party applications.</p>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="action-section">
                <div class="btn-group">
                    <a href="login.php" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Login to Dashboard
                    </a>
                    <a href="signup.php" class="btn btn-secondary">
                        <i class="fas fa-rocket"></i> Get Started Free
                    </a>
                </div>
            </div>

            <!-- Statistics -->
            <div class="stats">
                <div class="stat-item">
                    <span class="stat-number" data-target="15000">0</span>
                    <div class="stat-label">Active Users</div>
                </div>
                <div class="stat-item">
                    <span class="stat-number" data-target="850">0</span>
                    <div class="stat-label">Parking Locations</div>
                </div>
                <div class="stat-item">
                    <span class="stat-number" data-target="45">0</span>
                    <div class="stat-label">Cities Served</div>
                </div>
                <div class="stat-item">
                    <span class="stat-number" data-target="99.9">0</span>
                    <div class="stat-label">Satisfaction Rate</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Create animated particles
        function createParticles() {
            const particlesContainer = document.getElementById('particles');
            const particleCount = 15;
            
            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                
                // Random properties
                const size = Math.random() * 100 + 50;
                const posX = Math.random() * 100;
                const posY = Math.random() * 100;
                const delay = Math.random() * 5;
                const duration = Math.random() * 10 + 10;
                
                particle.style.width = `${size}px`;
                particle.style.height = `${size}px`;
                particle.style.left = `${posX}%`;
                particle.style.top = `${posY}%`;
                particle.style.animationDelay = `${delay}s`;
                particle.style.animationDuration = `${duration}s`;
                
                particlesContainer.appendChild(particle);
            }
        }

        // Animate statistics numbers
        function animateStats() {
            const statNumbers = document.querySelectorAll('.stat-number');
            
            statNumbers.forEach(stat => {
                const target = parseInt(stat.getAttribute('data-target'));
                const increment = target / 100;
                let current = 0;
                
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        current = target;
                        clearInterval(timer);
                    }
                    stat.textContent = target % 1 === 0 ? Math.floor(current) : current.toFixed(1);
                }, 20);
            });
        }

        // Add hover effects to feature cards
        document.querySelectorAll('.feature-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-10px) scale(1.02)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });

        // Initialize everything when page loads
        document.addEventListener('DOMContentLoaded', function() {
            createParticles();
            setTimeout(animateStats, 1000);
            
            // Add scroll animations
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.animationPlayState = 'running';
                    }
                });
            }, observerOptions);
            
            document.querySelectorAll('.slide-in').forEach(el => {
                observer.observe(el);
            });
        });
    </script>
</body>
</html>