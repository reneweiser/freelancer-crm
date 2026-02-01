<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Open source, self-hostable CRM for freelance developers and IT consultants. Manage clients, projects, invoices, and time tracking with German tax compliance.">

    <title>Freelancer CRM - Open Source CRM for Developers</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=jetbrains-mono:400,500,600,700|space-grotesk:400,500,600,700" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root {
            --color-bg: #0a0f1a;
            --color-bg-elevated: #111827;
            --color-bg-card: #1a2332;
            --color-primary: #22c55e;
            --color-primary-glow: rgba(34, 197, 94, 0.15);
            --color-text: #f1f5f9;
            --color-text-muted: #94a3b8;
            --color-border: #1e293b;
            --color-accent: #3b82f6;
        }

        body {
            font-family: 'Space Grotesk', system-ui, sans-serif;
            background: var(--color-bg);
            color: var(--color-text);
        }

        .font-mono {
            font-family: 'JetBrains Mono', ui-monospace, monospace;
        }

        /* Terminal-inspired glow effect */
        .terminal-glow {
            box-shadow:
                0 0 20px var(--color-primary-glow),
                0 0 40px var(--color-primary-glow),
                inset 0 1px 0 rgba(255,255,255,0.05);
        }

        /* Animated grid background */
        .grid-bg {
            background-image:
                linear-gradient(rgba(34, 197, 94, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(34, 197, 94, 0.03) 1px, transparent 1px);
            background-size: 50px 50px;
            animation: gridMove 20s linear infinite;
        }

        @keyframes gridMove {
            0% { background-position: 0 0; }
            100% { background-position: 50px 50px; }
        }

        /* Gradient text */
        .gradient-text {
            background: linear-gradient(135deg, #22c55e 0%, #3b82f6 50%, #8b5cf6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Feature card hover */
        .feature-card {
            background: linear-gradient(145deg, var(--color-bg-card) 0%, var(--color-bg-elevated) 100%);
            border: 1px solid var(--color-border);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .feature-card:hover {
            border-color: var(--color-primary);
            transform: translateY(-4px);
            box-shadow: 0 20px 40px -20px rgba(34, 197, 94, 0.3);
        }

        /* Code block styling */
        .code-block {
            background: var(--color-bg);
            border: 1px solid var(--color-border);
            border-radius: 0.75rem;
            overflow: hidden;
        }

        .code-header {
            background: var(--color-bg-elevated);
            border-bottom: 1px solid var(--color-border);
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .code-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }

        /* Typing animation */
        .typing-cursor::after {
            content: '|';
            animation: blink 1s step-end infinite;
            color: var(--color-primary);
        }

        @keyframes blink {
            50% { opacity: 0; }
        }

        /* Staggered animation */
        .stagger-1 { animation-delay: 0.1s; }
        .stagger-2 { animation-delay: 0.2s; }
        .stagger-3 { animation-delay: 0.3s; }
        .stagger-4 { animation-delay: 0.4s; }
        .stagger-5 { animation-delay: 0.5s; }
        .stagger-6 { animation-delay: 0.6s; }

        .fade-in-up {
            opacity: 0;
            transform: translateY(20px);
            animation: fadeInUp 0.6s ease-out forwards;
        }

        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* CTA Button */
        .cta-button {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .cta-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s ease;
        }

        .cta-button:hover::before {
            left: 100%;
        }

        .cta-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px -10px rgba(34, 197, 94, 0.5);
        }

        /* Badge styling */
        .tech-badge {
            background: var(--color-bg-elevated);
            border: 1px solid var(--color-border);
            transition: all 0.2s ease;
        }

        .tech-badge:hover {
            border-color: var(--color-primary);
            background: var(--color-bg-card);
        }

        /* Floating nav */
        .nav-blur {
            backdrop-filter: blur(12px);
            background: rgba(10, 15, 26, 0.8);
        }

        /* Reduced motion */
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>
<body class="antialiased min-h-screen">
    <!-- Background Grid -->
    <div class="fixed inset-0 grid-bg pointer-events-none" aria-hidden="true"></div>

    <!-- Navigation -->
    <nav class="fixed top-4 left-4 right-4 z-50 nav-blur border border-[#1e293b] rounded-xl px-6 py-4">
        <div class="max-w-7xl mx-auto flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="size-8 rounded-lg bg-gradient-to-br from-green-500 to-blue-500 flex items-center justify-center">
                    <svg class="size-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="20" height="20">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <span class="font-semibold text-lg">Freelancer<span class="text-green-500">CRM</span></span>
            </div>
            <div class="flex items-center gap-4">
                <a href="#features" class="text-slate-400 hover:text-white transition-colors text-sm hidden sm:block">Features</a>
                <a href="#self-hosted" class="text-slate-400 hover:text-white transition-colors text-sm hidden sm:block">Self-Hosted</a>
                <a href="https://github.com/reneweiser/freelancer-crm" target="_blank" rel="noopener" class="cta-button text-white font-medium px-4 py-2 rounded-lg text-sm flex items-center gap-2 cursor-pointer">
                    <svg class="size-5" fill="currentColor" viewBox="0 0 24 24" width="20" height="20">
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.477 2 2 6.477 2 12c0 4.42 2.865 8.17 6.839 9.49.5.092.682-.217.682-.482 0-.237-.008-.866-.013-1.7-2.782.604-3.369-1.34-3.369-1.34-.454-1.156-1.11-1.464-1.11-1.464-.908-.62.069-.608.069-.608 1.003.07 1.531 1.03 1.531 1.03.892 1.529 2.341 1.087 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.11-4.555-4.943 0-1.091.39-1.984 1.029-2.683-.103-.253-.446-1.27.098-2.647 0 0 .84-.269 2.75 1.025A9.578 9.578 0 0112 6.836c.85.004 1.705.114 2.504.336 1.909-1.294 2.747-1.025 2.747-1.025.546 1.377.203 2.394.1 2.647.64.699 1.028 1.592 1.028 2.683 0 3.842-2.339 4.687-4.566 4.935.359.309.678.919.678 1.852 0 1.336-.012 2.415-.012 2.743 0 .267.18.578.688.48C19.138 20.167 22 16.418 22 12c0-5.523-4.477-10-10-10z"/>
                    </svg>
                    <span class="hidden sm:inline">View on GitHub</span>
                </a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="relative min-h-screen flex items-center justify-center px-4 pt-24 pb-16">
        <div class="max-w-5xl mx-auto text-center">
            <!-- Badge -->
            <div class="fade-in-up stagger-1 inline-flex items-center gap-2 px-4 py-2 rounded-full border border-green-500/30 bg-green-500/10 text-green-400 text-sm font-medium mb-8">
                <svg class="size-4" fill="currentColor" viewBox="0 0 24 24" width="16" height="16">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.477 2 2 6.477 2 12c0 4.42 2.865 8.17 6.839 9.49.5.092.682-.217.682-.482 0-.237-.008-.866-.013-1.7-2.782.604-3.369-1.34-3.369-1.34-.454-1.156-1.11-1.464-1.11-1.464-.908-.62.069-.608.069-.608 1.003.07 1.531 1.03 1.531 1.03.892 1.529 2.341 1.087 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.11-4.555-4.943 0-1.091.39-1.984 1.029-2.683-.103-.253-.446-1.27.098-2.647 0 0 .84-.269 2.75 1.025A9.578 9.578 0 0112 6.836c.85.004 1.705.114 2.504.336 1.909-1.294 2.747-1.025 2.747-1.025.546 1.377.203 2.394.1 2.647.64.699 1.028 1.592 1.028 2.683 0 3.842-2.339 4.687-4.566 4.935.359.309.678.919.678 1.852 0 1.336-.012 2.415-.012 2.743 0 .267.18.578.688.48C19.138 20.167 22 16.418 22 12c0-5.523-4.477-10-10-10z"/>
                </svg>
                Open Source & Self-Hosted
            </div>

            <!-- Headline -->
            <h1 class="fade-in-up stagger-2 text-4xl sm:text-5xl md:text-6xl lg:text-7xl font-bold leading-tight mb-6">
                The CRM Built for
                <span class="gradient-text">Freelance Developers</span>
            </h1>

            <!-- Subheadline -->
            <p class="fade-in-up stagger-3 text-lg sm:text-xl text-slate-400 max-w-3xl mx-auto mb-8 leading-relaxed">
                Manage clients, projects, invoices, and time tracking in one place.
                German tax compliant. Self-hostable. No subscription fees.
            </p>

            <!-- CTA Buttons -->
            <div class="fade-in-up stagger-4 flex flex-col sm:flex-row items-center justify-center gap-4 mb-12">
                <a href="https://github.com/reneweiser/freelancer-crm" target="_blank" rel="noopener" class="cta-button text-white font-semibold px-8 py-4 rounded-xl text-lg flex items-center gap-3 cursor-pointer">
                    <svg class="size-6" fill="currentColor" viewBox="0 0 24 24" width="24" height="24">
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.477 2 2 6.477 2 12c0 4.42 2.865 8.17 6.839 9.49.5.092.682-.217.682-.482 0-.237-.008-.866-.013-1.7-2.782.604-3.369-1.34-3.369-1.34-.454-1.156-1.11-1.464-1.11-1.464-.908-.62.069-.608.069-.608 1.003.07 1.531 1.03 1.531 1.03.892 1.529 2.341 1.087 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.11-4.555-4.943 0-1.091.39-1.984 1.029-2.683-.103-.253-.446-1.27.098-2.647 0 0 .84-.269 2.75 1.025A9.578 9.578 0 0112 6.836c.85.004 1.705.114 2.504.336 1.909-1.294 2.747-1.025 2.747-1.025.546 1.377.203 2.394.1 2.647.64.699 1.028 1.592 1.028 2.683 0 3.842-2.339 4.687-4.566 4.935.359.309.678.919.678 1.852 0 1.336-.012 2.415-.012 2.743 0 .267.18.578.688.48C19.138 20.167 22 16.418 22 12c0-5.523-4.477-10-10-10z"/>
                    </svg>
                    Clone from GitHub
                </a>
                <a href="#features" class="border border-slate-600 hover:border-slate-500 text-slate-300 hover:text-white font-medium px-8 py-4 rounded-xl text-lg transition-all cursor-pointer">
                    Explore Features
                </a>
            </div>

            <!-- Terminal Preview -->
            <div class="fade-in-up stagger-5 code-block terminal-glow max-w-2xl mx-auto text-left">
                <div class="code-header">
                    <div class="code-dot bg-red-500"></div>
                    <div class="code-dot bg-yellow-500"></div>
                    <div class="code-dot bg-green-500"></div>
                    <span class="text-slate-500 text-sm font-mono ml-2">terminal</span>
                </div>
                <div class="p-6 font-mono text-sm">
                    <div class="text-slate-500"># Clone and start in minutes</div>
                    <div class="mt-2">
                        <span class="text-green-400">$</span>
                        <span class="text-slate-300"> git clone https://github.com/reneweiser/freelancer-crm.git</span>
                    </div>
                    <div class="mt-1">
                        <span class="text-green-400">$</span>
                        <span class="text-slate-300"> cd freelancer-crm && ./vendor/bin/sail up -d</span>
                    </div>
                    <div class="mt-1">
                        <span class="text-green-400">$</span>
                        <span class="text-slate-300"> ./vendor/bin/sail artisan migrate --seed</span>
                    </div>
                    <div class="mt-3 text-slate-500"># Ready at http://localhost</div>
                </div>
            </div>

            <!-- Tech Stack Badges -->
            <div class="fade-in-up stagger-6 flex flex-wrap items-center justify-center gap-3 mt-10">
                <div class="tech-badge px-4 py-2 rounded-lg flex items-center gap-2 cursor-pointer">
                    <svg class="size-5 text-red-500" viewBox="0 0 50 52" fill="currentColor" width="20" height="20">
                        <path d="M49.626 11.564a.809.809 0 0 1 .028.209v10.972a.8.8 0 0 1-.402.694l-9.209 5.302V39.25c0 .286-.152.55-.4.694L20.42 51.01c-.044.025-.092.041-.14.058-.018.006-.035.017-.054.022a.805.805 0 0 1-.41 0c-.022-.006-.042-.018-.063-.026-.044-.016-.09-.03-.132-.054L.402 39.944A.801.801 0 0 1 0 39.25V6.334c0-.072.01-.142.028-.21.006-.023.02-.044.028-.067.015-.042.029-.085.051-.124.015-.026.037-.047.055-.071.023-.032.044-.065.071-.093.023-.023.053-.04.079-.06.029-.024.055-.05.088-.069h.001l9.61-5.533a.802.802 0 0 1 .8 0l9.61 5.533h.002c.032.02.059.045.088.068.026.02.055.038.078.06.028.029.048.062.072.094.017.024.04.045.054.071.023.04.036.082.052.124.008.023.022.044.028.068a.809.809 0 0 1 .028.209v20.559l8.008-4.611v-10.51c0-.07.01-.141.028-.208.007-.024.02-.045.028-.068.016-.042.03-.085.052-.124.015-.026.037-.047.054-.071.024-.032.044-.065.072-.093.023-.023.052-.04.078-.06.03-.024.056-.05.088-.069h.001l9.611-5.533a.801.801 0 0 1 .8 0l9.61 5.533c.034.02.06.045.09.068.025.02.054.038.077.06.028.029.048.062.072.094.018.024.04.045.054.071.023.039.036.082.052.124.009.023.022.044.028.068zm-1.574 10.718v-9.124l-3.363 1.936-4.646 2.675v9.124l8.01-4.611zm-9.61 16.505v-9.13l-4.57 2.61-13.05 7.448v9.216l17.62-10.144zM1.602 7.719v31.068L19.22 48.93v-9.214l-9.204-5.209-.003-.002-.004-.002c-.031-.018-.057-.044-.086-.066-.025-.02-.054-.036-.076-.058l-.002-.003c-.026-.025-.044-.056-.066-.084-.02-.027-.044-.05-.06-.078l-.001-.003c-.018-.03-.029-.066-.042-.1-.013-.03-.03-.058-.038-.09v-.001c-.01-.038-.012-.078-.016-.117-.004-.03-.012-.06-.012-.09v-21.483L4.965 9.654 1.602 7.72zm8.81-5.994L2.405 6.334l8.005 4.609 8.006-4.61-8.006-4.608zm4.164 28.764l4.645-2.674V7.719l-3.363 1.936-4.646 2.675v20.096l3.364-1.937zM39.243 7.164l-8.006 4.609 8.006 4.609 8.005-4.61-8.005-4.608zm-.801 10.605l-4.646-2.675-3.363-1.936v9.124l4.645 2.674 3.364 1.937v-9.124zM20.02 38.33l11.743-6.704 5.87-3.35-8-4.606-9.211 5.303-8.395 4.833 7.993 4.524z"/>
                    </svg>
                    <span class="text-sm text-slate-300">Laravel 12</span>
                </div>
                <div class="tech-badge px-4 py-2 rounded-lg flex items-center gap-2 cursor-pointer">
                    <svg class="size-5 text-amber-500" viewBox="0 0 24 24" fill="currentColor" width="20" height="20">
                        <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
                    </svg>
                    <span class="text-sm text-slate-300">Filament 4</span>
                </div>
                <div class="tech-badge px-4 py-2 rounded-lg flex items-center gap-2 cursor-pointer">
                    <svg class="size-5 text-indigo-400" viewBox="0 0 24 24" fill="currentColor" width="20" height="20">
                        <path d="M7.01 10.207h-.944l-.515 2.648h.838c.556 0 .97-.105 1.242-.314.272-.21.455-.559.55-1.049.092-.47.05-.802-.124-.995-.175-.193-.523-.29-1.047-.29zM12 5.688C5.373 5.688 0 8.514 0 12s5.373 6.313 12 6.313S24 15.486 24 12c0-3.486-5.373-6.312-12-6.312zm-3.26 7.451c-.261.25-.575.438-.917.551-.336.108-.765.164-1.285.164H5.357l-.327 1.681H3.652l1.23-6.326h2.65c.797 0 1.378.209 1.744.628.366.418.476 1.002.33 1.752a2.836 2.836 0 0 1-.865 1.55zm2.967-1.084c.142-.155.32-.281.533-.377a2.014 2.014 0 0 1 .741-.162c.083-.003.149.004.196.021.047.017.082.043.106.078.024.035.039.076.045.124.006.047.003.097-.008.149l-.012.06c-.018.09-.045.173-.08.253-.035.08-.077.153-.126.222-.049.068-.106.132-.169.189a1.273 1.273 0 0 1-.208.154c-.076.046-.16.084-.254.114a2.062 2.062 0 0 1-.31.072c-.112.017-.237.027-.375.027h-.219l.14-.723zm-1.473 1.653h1.022l.079-.407a2.28 2.28 0 0 0 .355.293c.146.094.316.165.51.213.193.048.408.072.647.072.355 0 .678-.047.97-.141a2.338 2.338 0 0 0 .772-.403c.224-.172.414-.374.568-.605.154-.232.27-.479.345-.743.064-.224.098-.438.102-.64a1.12 1.12 0 0 0-.08-.454 1.011 1.011 0 0 0-.241-.357 1.395 1.395 0 0 0-.378-.254 2.028 2.028 0 0 0-.488-.154 3.45 3.45 0 0 0-.573-.053c-.178 0-.355.017-.53.05a2.94 2.94 0 0 0-.502.139l.093-.485h-1.021l-.65 3.329zm6.334-3.329h-1.068l-.178.915h1.068c.107-.004.191-.023.252-.057a.36.36 0 0 0 .149-.146.562.562 0 0 0 .073-.21c.014-.08.02-.169.02-.268 0-.077-.015-.139-.044-.185a.253.253 0 0 0-.12-.097.547.547 0 0 0-.178-.047c-.068-.003-.14-.005-.215-.005h.241zm1.02-.915h-2.268l-1.206 6.326h1.379l.435-2.223h.88c.398 0 .728-.052.99-.157.263-.104.472-.249.627-.433.155-.185.265-.403.33-.654.065-.25.081-.52.05-.807a1.416 1.416 0 0 0-.192-.58 1.067 1.067 0 0 0-.403-.395 1.631 1.631 0 0 0-.557-.192 4.034 4.034 0 0 0-.657-.05l.592-1.835z"/>
                    </svg>
                    <span class="text-sm text-slate-300">PHP 8.4</span>
                </div>
                <div class="tech-badge px-4 py-2 rounded-lg flex items-center gap-2 cursor-pointer">
                    <svg class="size-5 text-cyan-400" viewBox="0 0 24 24" fill="currentColor" width="20" height="20">
                        <path d="M12.001,4.8c-3.2,0-5.2,1.6-6,4.8c1.2-1.6,2.6-2.2,4.2-1.8c0.913,0.228,1.565,0.89,2.288,1.624 C13.666,10.618,15.027,12,18.001,12c3.2,0,5.2-1.6,6-4.8c-1.2,1.6-2.6,2.2-4.2,1.8c-0.913-0.228-1.565-0.89-2.288-1.624 C16.337,6.182,14.976,4.8,12.001,4.8z M6.001,12c-3.2,0-5.2,1.6-6,4.8c1.2-1.6,2.6-2.2,4.2-1.8c0.913,0.228,1.565,0.89,2.288,1.624 c1.177,1.194,2.538,2.576,5.512,2.576c3.2,0,5.2-1.6,6-4.8c-1.2,1.6-2.6,2.2-4.2,1.8c-0.913-0.228-1.565-0.89-2.288-1.624 C10.337,13.382,8.976,12,6.001,12z"/>
                    </svg>
                    <span class="text-sm text-slate-300">Tailwind CSS</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="relative py-24 px-4">
        <div class="max-w-7xl mx-auto">
            <!-- Section Header -->
            <div class="text-center mb-16">
                <h2 class="text-3xl sm:text-4xl md:text-5xl font-bold mb-4">
                    Everything You Need to
                    <span class="gradient-text">Run Your Business</span>
                </h2>
                <p class="text-slate-400 text-lg max-w-2xl mx-auto">
                    From first contact to final invoice. Complete workflow management designed for freelance developers and IT consultants.
                </p>
            </div>

            <!-- Feature Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- AI Agent Integration - Featured -->
                <div class="feature-card rounded-2xl p-6 cursor-pointer md:col-span-2 lg:col-span-1 relative overflow-hidden">
                    <div class="absolute top-0 right-0 px-3 py-1 bg-gradient-to-r from-purple-500 to-blue-500 text-xs font-semibold rounded-bl-lg">Coming Soon</div>
                    <div class="size-12 rounded-xl bg-gradient-to-br from-purple-500/20 to-blue-500/20 flex items-center justify-center mb-4">
                        <svg class="size-6 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="24" height="24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">AI Agent Integration</h3>
                    <p class="text-slate-400 text-sm leading-relaxed mb-4">
                        Let Claude Code manage your CRM via MCP. Create projects from meeting notes, generate invoices with natural language, get daily summaries.
                    </p>
                    <div class="code-block text-xs">
                        <div class="p-3 font-mono text-slate-400">
                            <span class="text-slate-500">"Create a project for Acme Corp from my meeting notes"</span>
                        </div>
                    </div>
                </div>

                <!-- Clients & Projects -->
                <div class="feature-card rounded-2xl p-6 cursor-pointer">
                    <div class="size-12 rounded-xl bg-green-500/10 flex items-center justify-center mb-4">
                        <svg class="size-6 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="24" height="24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">Clients & Projects</h3>
                    <p class="text-slate-400 text-sm leading-relaxed">
                        Track companies and contacts with full history. Manage projects from draft offer through completion with built-in status workflows.
                    </p>
                </div>

                <!-- Invoicing & Documents -->
                <div class="feature-card rounded-2xl p-6 cursor-pointer">
                    <div class="size-12 rounded-xl bg-amber-500/10 flex items-center justify-center mb-4">
                        <svg class="size-6 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="24" height="24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">Invoicing & Documents</h3>
                    <p class="text-slate-400 text-sm leading-relaxed">
                        Auto-numbered invoices with German tax compliance. Generate professional PDFs for offers and invoices. Send via email with one click.
                    </p>
                </div>

                <!-- Time & Billing -->
                <div class="feature-card rounded-2xl p-6 cursor-pointer">
                    <div class="size-12 rounded-xl bg-blue-500/10 flex items-center justify-center mb-4">
                        <svg class="size-6 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="24" height="24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">Time & Billing</h3>
                    <p class="text-slate-400 text-sm leading-relaxed">
                        Log billable hours per project. Convert time entries to invoice line items automatically. Track what you've earned and what's outstanding.
                    </p>
                </div>

                <!-- Automation & Reminders -->
                <div class="feature-card rounded-2xl p-6 cursor-pointer">
                    <div class="size-12 rounded-xl bg-rose-500/10 flex items-center justify-center mb-4">
                        <svg class="size-6 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="24" height="24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">Automation & Reminders</h3>
                    <p class="text-slate-400 text-sm leading-relaxed">
                        Schedule follow-ups and payment reminders. Auto-detect overdue invoices. Email templates for offers, invoices, and reminders.
                    </p>
                </div>

                <!-- Dashboard -->
                <div class="feature-card rounded-2xl p-6 cursor-pointer">
                    <div class="size-12 rounded-xl bg-indigo-500/10 flex items-center justify-center mb-4">
                        <svg class="size-6 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="24" height="24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">Dashboard Overview</h3>
                    <p class="text-slate-400 text-sm leading-relaxed">
                        See open invoices, monthly revenue, active projects, and upcoming tasks at a glance. Know where your business stands instantly.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Self-Hosted Benefits -->
    <section id="self-hosted" class="relative py-24 px-4 border-t border-slate-800">
        <div class="max-w-7xl mx-auto">
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                <!-- Left: Content -->
                <div>
                    <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border border-blue-500/30 bg-blue-500/10 text-blue-400 text-sm font-medium mb-6">
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/>
                        </svg>
                        Self-Hosted
                    </div>
                    <h2 class="text-3xl sm:text-4xl font-bold mb-6">
                        Your Data. Your Server.
                        <span class="gradient-text">Your Rules.</span>
                    </h2>
                    <p class="text-slate-400 text-lg mb-8 leading-relaxed">
                        Host on your own infrastructure. No vendor lock-in, no monthly fees, no data leaving your control. Perfect for privacy-conscious freelancers and GDPR compliance.
                    </p>

                    <ul class="space-y-4">
                        <li class="flex items-start gap-3">
                            <div class="size-6 rounded-full bg-green-500/20 flex items-center justify-center shrink-0 mt-0.5">
                                <svg class="size-4 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                            <div>
                                <span class="font-medium">100% Open Source</span>
                                <p class="text-slate-500 text-sm">MIT licensed. Fork it, modify it, make it yours.</p>
                            </div>
                        </li>
                        <li class="flex items-start gap-3">
                            <div class="size-6 rounded-full bg-green-500/20 flex items-center justify-center shrink-0 mt-0.5">
                                <svg class="size-4 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                            <div>
                                <span class="font-medium">Zero Subscription Fees</span>
                                <p class="text-slate-500 text-sm">No monthly costs. Just your hosting.</p>
                            </div>
                        </li>
                        <li class="flex items-start gap-3">
                            <div class="size-6 rounded-full bg-green-500/20 flex items-center justify-center shrink-0 mt-0.5">
                                <svg class="size-4 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                            <div>
                                <span class="font-medium">Docker Ready</span>
                                <p class="text-slate-500 text-sm">Production-ready Docker images included.</p>
                            </div>
                        </li>
                        <li class="flex items-start gap-3">
                            <div class="size-6 rounded-full bg-green-500/20 flex items-center justify-center shrink-0 mt-0.5">
                                <svg class="size-4 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                            <div>
                                <span class="font-medium">GDPR Compliant</span>
                                <p class="text-slate-500 text-sm">Keep client data on your own EU servers.</p>
                            </div>
                        </li>
                    </ul>
                </div>

                <!-- Right: Code Block -->
                <div class="code-block terminal-glow">
                    <div class="code-header">
                        <div class="code-dot bg-red-500"></div>
                        <div class="code-dot bg-yellow-500"></div>
                        <div class="code-dot bg-green-500"></div>
                        <span class="text-slate-500 text-sm font-mono ml-2">docker-compose.yml</span>
                    </div>
                    <div class="p-6 font-mono text-sm overflow-x-auto">
<pre class="text-slate-300"><span class="text-purple-400">version</span>: <span class="text-green-400">'3.8'</span>

<span class="text-purple-400">services</span>:
  <span class="text-blue-400">app</span>:
    <span class="text-purple-400">image</span>: <span class="text-green-400">freelancer-crm:latest</span>
    <span class="text-purple-400">ports</span>:
      - <span class="text-amber-400">"80:80"</span>
    <span class="text-purple-400">environment</span>:
      - <span class="text-slate-400">DB_HOST=db</span>
      - <span class="text-slate-400">APP_KEY=${APP_KEY}</span>

  <span class="text-blue-400">db</span>:
    <span class="text-purple-400">image</span>: <span class="text-green-400">mariadb:10.11</span>
    <span class="text-purple-400">volumes</span>:
      - <span class="text-slate-400">db_data:/var/lib/mysql</span>

<span class="text-purple-400">volumes</span>:
  <span class="text-blue-400">db_data</span>:</pre>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="relative py-24 px-4 border-t border-slate-800">
        <div class="max-w-3xl mx-auto text-center">
            <h2 class="text-3xl sm:text-4xl md:text-5xl font-bold mb-6">
                Ready to Take Control?
            </h2>
            <p class="text-slate-400 text-lg mb-10">
                Clone the repository and start managing your freelance business today. Free forever, no strings attached.
            </p>

            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="https://github.com/reneweiser/freelancer-crm" target="_blank" rel="noopener" class="cta-button text-white font-semibold px-10 py-5 rounded-xl text-lg flex items-center gap-3 cursor-pointer">
                    <svg class="size-6" fill="currentColor" viewBox="0 0 24 24" width="24" height="24">
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.477 2 2 6.477 2 12c0 4.42 2.865 8.17 6.839 9.49.5.092.682-.217.682-.482 0-.237-.008-.866-.013-1.7-2.782.604-3.369-1.34-3.369-1.34-.454-1.156-1.11-1.464-1.11-1.464-.908-.62.069-.608.069-.608 1.003.07 1.531 1.03 1.531 1.03.892 1.529 2.341 1.087 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.11-4.555-4.943 0-1.091.39-1.984 1.029-2.683-.103-.253-.446-1.27.098-2.647 0 0 .84-.269 2.75 1.025A9.578 9.578 0 0112 6.836c.85.004 1.705.114 2.504.336 1.909-1.294 2.747-1.025 2.747-1.025.546 1.377.203 2.394.1 2.647.64.699 1.028 1.592 1.028 2.683 0 3.842-2.339 4.687-4.566 4.935.359.309.678.919.678 1.852 0 1.336-.012 2.415-.012 2.743 0 .267.18.578.688.48C19.138 20.167 22 16.418 22 12c0-5.523-4.477-10-10-10z"/>
                    </svg>
                    Get Started on GitHub
                </a>
            </div>

            <!-- Stars Badge -->
            <div class="mt-8 inline-flex items-center gap-2 text-slate-500 text-sm">
                <svg class="size-5 text-amber-400" fill="currentColor" viewBox="0 0 24 24" width="20" height="20">
                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                </svg>
                <span>Star on GitHub if you find this useful</span>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="relative py-12 px-4 border-t border-slate-800">
        <div class="max-w-7xl mx-auto">
            <div class="flex flex-col md:flex-row items-center justify-between gap-6">
                <!-- Logo -->
                <div class="flex items-center gap-3">
                    <div class="size-8 rounded-lg bg-gradient-to-br from-green-500 to-blue-500 flex items-center justify-center">
                        <svg class="size-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="20" height="20">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <span class="font-semibold">Freelancer<span class="text-green-500">CRM</span></span>
                </div>

                <!-- Links -->
                <div class="flex items-center gap-6 text-sm text-slate-400">
                    <a href="https://github.com/reneweiser/freelancer-crm" target="_blank" rel="noopener" class="hover:text-white transition-colors flex items-center gap-2 cursor-pointer">
                        <svg class="size-5" fill="currentColor" viewBox="0 0 24 24" width="20" height="20">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.477 2 2 6.477 2 12c0 4.42 2.865 8.17 6.839 9.49.5.092.682-.217.682-.482 0-.237-.008-.866-.013-1.7-2.782.604-3.369-1.34-3.369-1.34-.454-1.156-1.11-1.464-1.11-1.464-.908-.62.069-.608.069-.608 1.003.07 1.531 1.03 1.531 1.03.892 1.529 2.341 1.087 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.11-4.555-4.943 0-1.091.39-1.984 1.029-2.683-.103-.253-.446-1.27.098-2.647 0 0 .84-.269 2.75 1.025A9.578 9.578 0 0112 6.836c.85.004 1.705.114 2.504.336 1.909-1.294 2.747-1.025 2.747-1.025.546 1.377.203 2.394.1 2.647.64.699 1.028 1.592 1.028 2.683 0 3.842-2.339 4.687-4.566 4.935.359.309.678.919.678 1.852 0 1.336-.012 2.415-.012 2.743 0 .267.18.578.688.48C19.138 20.167 22 16.418 22 12c0-5.523-4.477-10-10-10z"/>
                        </svg>
                        GitHub
                    </a>
                    <span class="text-slate-700">|</span>
                    <span>MIT License</span>
                </div>

                <!-- Copyright -->
                <div class="text-sm text-slate-500">
                    &copy; {{ date('Y') }} Freelancer CRM. Built with Laravel & Filament.
                </div>
            </div>
        </div>
    </footer>
</body>
</html>
