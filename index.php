<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CodeVault | Future-Ready Coding Solutions</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="CSS/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    
</head>
<body class="light-mode">
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-logo">
                <a href="#home" class="logo-link">
                    <span class="logo-icon">C</span>
                    <span class="logo-text">CodeVault</span>
                </a>
            </div>
            <div class="nav-menu" id="nav-menu">
                <a href="#home" class="nav-link active">Home</a>
                <a href="#about" class="nav-link">About</a>
                <a href="#services" class="nav-link">Services</a>
                <a href="#contact" class="nav-link">Contact</a>
                <a href="extra_pages/faq.html" class="nav-link">Query</a>
                <div class="theme-toggle">
                    <input type="checkbox" id="dark-mode-toggle" class="toggle-checkbox">
                    <label for="dark-mode-toggle" class="toggle-label">
                        <i class="fas fa-sun"></i>
                        <i class="fas fa-moon"></i>
                        <span class="toggle-ball"></span>
                    </label>
                </div>
                <a href="auth/login.php" class="nav-link profile-link">
                    <ion-icon name="person-circle-outline"></ion-icon>
                </a>
            </div>
            <div class="nav-toggle" id="nav-toggle">
                <span class="bar"></span>
                <span class="bar"></span>
                <span class="bar"></span>
            </div>
        </div>
    </nav>

    <iframe 
        src="https://www.chatbase.co/chatbot-iframe/UnymFxsRJNzaOAHRLvt86" 
        title="Descriptive title"           <!-- Required for accessibility -->
        width="800"                         <!-- Fallback width -->
        height="800"                        <!-- Fallback height -->
        frameborder="0"                     <!-- Remove border -->
        allowfullscreen                     <!-- Enable fullscreen -->
        loading="lazy"                      <!-- Lazy loading for performance -->
        allow="permissions-here"            <!-- Specific permissions -->
    >
        <!-- Fallback content for browsers that don't support iframes -->
        <p>Your browser does not support iframes. <a href="url">View content here</a>.</p>
    </iframe>
    <button class="btn-chat-open">
        <ion-icon name="chatbubbles-outline"></ion-icon>
    </button>

    <!-- Hero Section -->
    <section id="home" class="hero">
        <div class="hero-container">
            <div class="hero-content"><br>
                <div class="hero-badge">
                    <span>Your Coding Partner</span>
                </div>
                <h1 class="hero-title">
                    <div class="typewriter-container">
                        <span class="title-line" id="typeWriter"></span>
                        <span class="cursor"></span>
                    </div>
                    <span class="title-line highlight">Coding Journey</span>
                </h1>
                <p class="hero-description">
                    We create innovative digital platform for people to interact with each other and share 
                    codes. Experience the coding journey of yous with us.
                </p>
                <div class="hero-buttons">
                    <a href="auth/register.php" class="btn btn-primary">
                        <span>Get Started</span>
                        <i class="fas fa-arrow-right"></i>
                    </a>
                    <a href="#about" class="btn btn-secondary">
                        <span>Learn More</span>
                    </a>
                </div>
                <div class="hero-stats">
                    <div class="stat">
                        <div class="stat-number" data-count="150">0</div>
                        <div class="stat-label">Projects</div>
                    </div>
                    <div class="stat">
                        <div class="stat-number" data-count="98">0</div>
                        <div class="stat-label">User Satisfaction</div>
                    </div>
                    <div class="stat">
                        <div class="stat-number" data-count="12">0</div>
                        <div class="stat-label">Years Experience</div>
                    </div>
                </div>
            </div>
            <div class="hero-visual">
                <div class="floating-card card-1">
                    <i class="fas fa-rocket"></i>
                    <h3>Innovation</h3>
                </div>
                <div class="floating-card card-2">
                    <i class="fas fa-chart-line"></i>
                    <h3>Growth</h3>
                </div>
                <div class="floating-card card-3">
                    <i class="fas fa-shield-alt"></i>
                    <h3>Security</h3>
                </div>
                <div class="hero-graphic">
                    <div class="graphic-circle circle-1"></div>
                    <div class="graphic-circle circle-2"></div>
                    <div class="graphic-circle circle-3"></div>
                    <div class="graphic-center">
                        <i class="fas fa-bolt"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="scroll-indicator">
            <div class="scroll-line"></div>
            <span>Scroll Down</span>
        </div>
    </section>

    <!-- About Section -->
    <section class="about-section" id="about">
        <!-- Background Decorations -->
        <div class="bg-decoration decoration-1"></div>
        <div class="bg-decoration decoration-2"></div>
        <div class="bg-decoration decoration-3"></div>

        <div class="about-container">
            <div class="about-content">
                <div class="about-text">
                    <div class="about-badge">Our Story</div>
                    <h1 class="about-title">
                        Crafting <span class="highlight">Coding Excellence</span> Since 2012
                    </h1>
                    <p class="about-description">
                        We are a passionate team of designers, developers, and strategists dedicated to creating 
                        exceptional coding experiences. Our journey began with a simple vision: to transform 
                        ideas into impactful coding solutions that drive growth and innovation.
                    </p>

                    <div class="about-buttons">
                        <a href="extra_pages/about.html" class="btn btn-primary">
                            <span>Our Portfolio</span>
                            <i class="fas fa-arrow-right"></i>
                        </a>
                        <a href="#contact" class="btn btn-secondary">
                            <span>Contact Us</span>
                        </a>
                    </div>
                </div>

                <div class="about-visual">
                    <div class="visual-main">
                        <div class="visual-content">
                            <div class="visual-icon">
                                <i class="fas fa-lightbulb"></i>
                            </div>
                            <div class="visual-text">
                                Innovation & Creativity at Heart
                            </div>
                        </div>
                    </div>

                    <div class="floating-element element-1">
                        <div class="element-icon">
                            <i class="fas fa-rocket"></i>
                        </div>
                        <div class="element-text">
                            <h4>Fast Delivery</h4>
                            <p>Quick turnaround</p>
                        </div>
                    </div>

                    <div class="floating-element element-2">
                        <div class="element-icon">
                            <i class="fas fa-medal"></i>
                        </div>
                        <div class="element-text">
                            <h4>Premium Quality</h4>
                            <p>Award-winning work</p>
                        </div>
                    </div>

                    <div class="floating-element element-3">
                        <div class="element-icon">
                            <i class="fas fa-headset"></i>
                        </div>
                        <div class="element-text">
                            <h4>24/7 Support</h4>
                            <p>Always here to help</p>
                        </div>
                    </div>

                    <div class="floating-element element-4">
                        <div class="element-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div class="element-text">
                            <h4>Secure & Safe</h4>
                            <p>Your data protected</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="team-showcase">
                <h2 class="showcase-title">Meet Our Creative Team</h2>
                <div class="team-grid">
                    <div class="team-member">
                        <div class="member-avatar">JD</div>
                        <h3 class="member-name">John Doe</h3>
                        <div class="member-role">Creative Director</div>
                        <p class="member-desc">Leading our design vision with 10+ years of experience in digital creativity.</p>
                        <div class="member-social">
                            <a href="#" class="social-link">
                                <i class="fab fa-linkedin-in"></i>
                            </a>
                            <a href="#" class="social-link">
                                <i class="fab fa-twitter"></i>
                            </a>
                            <a href="#" class="social-link">
                                <i class="fab fa-dribbble"></i>
                            </a>
                        </div>
                    </div>

                    <div class="team-member">
                        <div class="member-avatar">SJ</div>
                        <h3 class="member-name">Sarah Johnson</h3>
                        <div class="member-role">Lead Developer</div>
                        <p class="member-desc">Transforming designs into flawless digital experiences with cutting-edge tech.</p>
                        <div class="member-social">
                            <a href="#" class="social-link">
                                <i class="fab fa-github"></i>
                            </a>
                            <a href="#" class="social-link">
                                <i class="fab fa-twitter"></i>
                            </a>
                            <a href="#" class="social-link">
                                <i class="fab fa-linkedin-in"></i>
                            </a>
                        </div>
                    </div>

                    <div class="team-member">
                        <div class="member-avatar">MW</div>
                        <h3 class="member-name">Michael Wang</h3>
                        <div class="member-role">UX Strategist</div>
                        <p class="member-desc">Crafting user-centered experiences that drive engagement and conversion.</p>
                        <div class="member-social">
                            <a href="#" class="social-link">
                                <i class="fab fa-behance"></i>
                            </a>
                            <a href="#" class="social-link">
                                <i class="fab fa-linkedin-in"></i>
                            </a>
                            <a href="#" class="social-link">
                                <i class="fab fa-medium"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="contact-section" id="contact">
        <div class="contact-container">
            <div class="contact-info">
                <div class="contact-badge">Get In Touch</div>
                <h1 class="contact-title">
                    Let's <span class="highlight">Connect</span> And Create Something Amazing
                </h1>
                <p class="contact-description">
                    Have a project in mind or want to discuss potential collaboration? 
                    We'd love to hear from you. Send us a message and we'll respond as soon as possible.
                </p>
                
                <div class="contact-details">
                    <div class="contact-detail">
                        <div class="contact-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div class="contact-detail-text">
                            <h3>Our Location</h3>
                            <p>123 Innovation Street, Tech City</p>
                        </div>
                    </div>
                    
                    <div class="contact-detail">
                        <div class="contact-icon">
                            <i class="fas fa-phone-alt"></i>
                        </div>
                        <div class="contact-detail-text">
                            <h3>Phone Number</h3>
                            <p>+1 (555) 123-4567</p>
                        </div>
                    </div>
                    
                    <div class="contact-detail">
                        <div class="contact-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="contact-detail-text">
                            <h3>Email Address</h3>
                            <p>hello@codevault.com</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="form-container">
                <div class="contact-form">
                    <h2 class="form-title">Send Us a Message</h2>
                    <p class="form-subtitle">We'll get back to you within 24 hours</p>
                    
                    <form id="contactForm" method="POST">
                        <div class="form-group">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" id="name" class="form-input" placeholder="Enter your full name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" id="email" class="form-input" placeholder="Enter your email address" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="subject" class="form-label">Subject</label>
                            <input type="text" id="subject" class="form-input" placeholder="What is this regarding?" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="message" class="form-label">Message</label>
                            <textarea id="message" class="form-textarea" placeholder="Tell us about your project or inquiry..." required></textarea>
                        </div>
                        
                        <button type="submit" class="submit-btn">
                            <span>Send Message</span>
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </form>
                </div>
                
                <div class="form-decoration form-decoration-1"></div>
                <div class="form-decoration form-decoration-2"></div>
                <div class="form-decoration form-decoration-3"></div>
            </div>
        </div>
    </section>

    <!-- App Section -->
    <section class="app-section" id="app">
        <div class="app-bg-decoration app-bg-1"></div>
        <div class="app-bg-decoration app-bg-2"></div>
        
        <div class="app-container">
            <div class="app-content">
                <div class="app-badge">
                    <span>Mobile Experience</span>
                </div>
                <h1 class="app-title">
                    Code Anywhere with Our <span class="highlight">Mobile App</span>
                </h1>
                <p class="app-description">
                    Take your coding journey with you. Our mobile app lets you write, test, and collaborate on code 
                    from anywhere. Access your projects, join coding sessions, and learn on the go with our 
                    intuitive mobile experience.
                </p>
                
                <div class="app-features">
                    <div class="app-feature">
                        <div class="app-feature-icon">
                            <i class="fas fa-code"></i>
                        </div>
                        <div class="app-feature-text">Code Editor</div>
                    </div>
                    <div class="app-feature">
                        <div class="app-feature-icon">
                            <i class="fas fa-cloud"></i>
                        </div>
                        <div class="app-feature-text">Cloud Sync</div>
                    </div>
                    <div class="app-feature">
                        <div class="app-feature-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="app-feature-text">Real-time Collaboration</div>
                    </div>
                    <div class="app-feature">
                        <div class="app-feature-icon">
                            <i class="fas fa-bolt"></i>
                        </div>
                        <div class="app-feature-text">Fast Execution</div>
                    </div>
                </div>
                
                <div class="store-buttons">
                    <a href="#" class="store-btn">
                        <i class="fab fa-apple store-icon"></i>
                        <div class="store-text">
                            <small>Download on the</small>
                            <span>App Store</span>
                        </div>
                    </a>
                    <a href="#" class="store-btn">
                        <i class="fab fa-google-play store-icon"></i>
                        <div class="store-text">
                            <small>Get it on</small>
                            <span>Google Play</span>
                        </div>
                    </a>
                </div>
            </div>
            
            <div class="app-visual">
                <div class="phone-mockup">
                    <div class="phone-notch"></div>
                    <div class="phone-screen">
                        <div class="app-header">
                            <div class="app-icon">
                                <i class="fas fa-code"></i>
                            </div>
                            <h3 style="margin: 0; color: white;">CodeVault</h3>
                            <p style="margin: 5px 0 0 0; opacity: 0.8; font-size: 14px;">Mobile Code Editor</p>
                        </div>
                        <div class="app-content-screen">
                            <div class="app-feature-item">
                                <div class="feature-icon">
                                    <i class="fas fa-laptop-code"></i>
                                </div>
                                <div class="feature-text">
                                    <h4>Code Editor</h4>
                                    <p>Syntax highlighting & auto-complete</p>
                                </div>
                            </div>
                            <div class="app-feature-item">
                                <div class="feature-icon">
                                    <i class="fas fa-share-alt"></i>
                                </div>
                                <div class="feature-text">
                                    <h4>Collaborate</h4>
                                    <p>Real-time pair programming</p>
                                </div>
                            </div>
                            <div class="app-feature-item">
                                <div class="feature-icon">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                </div>
                                <div class="feature-text">
                                    <h4>Cloud Sync</h4>
                                    <p>Access projects anywhere</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
    </section>

    <script>
        // Navigation Toggle
        const navToggle = document.getElementById('nav-toggle');
        const navMenu = document.getElementById('nav-menu');
        
        if (navToggle) {
            navToggle.addEventListener('click', () => {
                navMenu.classList.toggle('active');
                navToggle.classList.toggle('active');
            });
        }
        
        // Close mobile menu when clicking on a nav link
        const navLinks = document.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            link.addEventListener('click', () => {
                navMenu.classList.remove('active');
                navToggle.classList.remove('active');
                
                // Update active link
                navLinks.forEach(l => l.classList.remove('active'));
                link.classList.add('active');
            });
        });
        
        // Dark Mode Toggle
        const darkModeToggle = document.getElementById('dark-mode-toggle');
        const body = document.body;
        
        // Check for saved theme preference or respect OS preference
        if (localStorage.getItem('theme') === 'dark' || 
            (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            body.classList.add('dark-mode');
            darkModeToggle.checked = true;
        }
        
        darkModeToggle.addEventListener('change', () => {
            if (darkModeToggle.checked) {
                body.classList.add('dark-mode');
                localStorage.setItem('theme', 'dark');
            } else {
                body.classList.remove('dark-mode');
                localStorage.setItem('theme', 'light');
            }
        });

        const chatOpenBtn = document.querySelector('.btn-chat-open');
        const chat = document.querySelector('iframe');

        // Ensure chat iframe starts hidden
        if (chat) {
            chat.style.display = chat.style.display || 'none';
        }

        // Toggle chat visibility when chat button is clicked
        if (chatOpenBtn) {
            chatOpenBtn.addEventListener('click', (e) => {
                // Prevent the document click handler from immediately hiding it
                e.stopPropagation();

                if (!chat) return;

                const computed = window.getComputedStyle(chat);
                const isVisible = computed && computed.display !== 'none';

                chat.style.display = isVisible ? 'none' : 'block';
            });
        }

        // Hide chat when clicking outside of the iframe and button
        document.addEventListener('click', (e) => {
            if (!chat || !chatOpenBtn) return;

            const computed = window.getComputedStyle(chat);
            const isVisible = computed && computed.display !== 'none';

            if (isVisible && !chat.contains(e.target) && !chatOpenBtn.contains(e.target)) {
                chat.style.display = 'none';
            }
        });

        document.addEventListener('DOMContentLoaded', () => {
            const heroText = [
                'Upgrade Your',
                'Grow with your',
                'Ignite your'
            ];

            let index = 0;
            let charIndex = 0;

            const textElem = document.getElementById('typeWriter');
            if (!textElem) return;

            function type() {
                if (charIndex < heroText[index].length) {
                    textElem.textContent += heroText[index].charAt(charIndex);
                    charIndex++;
                    setTimeout(type, 150);
                } else {
                    setTimeout(erase, 2000);
                }
            }

            function erase() {
                if (charIndex > 0) {
                    textElem.textContent = heroText[index].substring(0, charIndex - 1);
                    charIndex--;
                    setTimeout(erase, 100);
                } else {
                    index = (index + 1) % heroText.length;
                    setTimeout(type, 500);
                }
            }

            type();
        });

        
        // Animated Counter for Stats
        const statNumbers = document.querySelectorAll('.stat-number, .about-stat-number');
        
        const animateValue = (element, start, end, duration) => {
            let startTimestamp = null;
            const step = (timestamp) => {
                if (!startTimestamp) startTimestamp = timestamp;
                const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                const value = Math.floor(progress * (end - start) + start);
                element.textContent = value;
                if (progress < 1) {
                    window.requestAnimationFrame(step);
                }
            };
            window.requestAnimationFrame(step);
        };
        
        // Intersection Observer for stats animation
        const statsObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    statNumbers.forEach(stat => {
                        const target = parseInt(stat.getAttribute('data-count'));
                        animateValue(stat, 0, target, 2000);
                    });
                }
            });
        }, { threshold: 0.5 });
        
        const statsSections = document.querySelectorAll('.hero-stats, .about-stats');
        statsSections.forEach(section => {
            if (section) {
                statsObserver.observe(section);
            }
        });
        
        // Contact Form Handler
        const contactForm = document.getElementById('contactForm');
        const sendEmailBtn = document.querySelector('#contactForm .submit-btn');
        
        if (contactForm  && sendEmailBtn) {
            contactForm.addEventListener('submit', function(e) {
                e.preventDefault();
                sendMail();
            });
            
            // Add input focus effects
            const formInputs = document.querySelectorAll('.form-input, .form-textarea');
            
            formInputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.classList.add('focused');
                });
                
                input.addEventListener('blur', function() {
                    if (this.value === '') {
                        this.parentElement.classList.remove('focused');
                    }
                });
            });
        }

        function sendMail(emailToUse = null) {
            const name = emailToUse || document.getElementById('name').value;
            const email = emailToUse || document.getElementById('email').value;
            const subject = emailToUse || document.getElementById('subject').value;
            const message = emailToUse || document.getElementById('message').value;

            const formData = new FormData();
            formData.append('name', name);
            formData.append('email', email);
            formData.append('subject', subject);
            formData.append('message', message);

            // Get the form container
            const formContainer = document.querySelector('.contact-form');
            // Remove any existing status messages
            const existingStatus = formContainer.querySelector('.form-status-message');
            if (existingStatus) {
                existingStatus.remove();
            }

            fetch('send-mail.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(response => {
                console.log(response);
                const statusMessage = document.createElement('div');
                statusMessage.classList.add('form-status-message');
                
                // Style the status message
                statusMessage.style.padding = '10px';
                statusMessage.style.marginTop = '15px';
                statusMessage.style.borderRadius = '4px';
                statusMessage.style.textAlign = 'center';
                
                if (response.includes('Sent')) {
                    statusMessage.style.backgroundColor = '#e7f6e7';
                    statusMessage.style.color = '#2e7d32';
                    statusMessage.style.border = '1px solid #c3e6c3';
                    statusMessage.textContent = 'Message sent successfully!';
                    contactForm.reset();
                } else {
                    statusMessage.style.backgroundColor = '#ffebee';
                    statusMessage.style.color = '#c62828';
                    statusMessage.style.border = '1px solid #ffcdd2';
                    statusMessage.textContent = 'Failed to send message';
                }

                document.body.appendChild(statusMessage);

                setTimeout(() => {
                    statusMessage.style.transition = 'opacity 0.5s ease-out';
                    statusMessage.style.opacity = '0';
                    setTimeout(() => statusMessage.remove(), 500);
                }, 5000);
            })
            .catch(error => {
                const statusMessage = document.createElement('div');
                statusMessage.classList.add('form-status-message');
                statusMessage.style.backgroundColor = '#ffebee';
                statusMessage.style.color = '#c62828';
                statusMessage.style.border = '1px solid #ffcdd2';
                statusMessage.style.padding = '10px';
                statusMessage.style.marginTop = '15px';
                statusMessage.style.borderRadius = '4px';
                statusMessage.style.textAlign = 'center';
                statusMessage.textContent = 'Network error. Please try again.';
                formContainer.appendChild(statusMessage);
            });
        }
        
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                
                const targetId = this.getAttribute('href');
                if (targetId === '#') return;
                
                const targetElement = document.querySelector(targetId);
                if (targetElement) {
                    window.scrollTo({
                        top: targetElement.offsetTop - 80,
                        behavior: 'smooth'
                    });
                }
            });
        });
        
        // Update active nav link on scroll
        window.addEventListener('scroll', () => {
            let current = '';
            const sections = document.querySelectorAll('section');
            
            sections.forEach(section => {
                const sectionTop = section.offsetTop;
                const sectionHeight = section.clientHeight;
                if (scrollY >= (sectionTop - 100)) {
                    current = section.getAttribute('id');
                }
            });
            
            navLinks.forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href') === `#${current}`) {
                    link.classList.add('active');
                }
            });
        });
        
        // Team member hover effects
        const teamMembers = document.querySelectorAll('.team-member');
        
        teamMembers.forEach(member => {
            member.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-10px)';
            });
            
            member.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
    </script>
</body>
</html>