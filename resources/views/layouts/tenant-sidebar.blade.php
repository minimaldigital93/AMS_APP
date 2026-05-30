<nav class="space-y-1">
    <style>
        /* Modern sidebar styling */
        .sidebar-transition {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .nav-link {
            position: relative;
            overflow: hidden;
            border-radius: 0.75rem;
            background: linear-gradient(135deg, transparent 0%, transparent 100%);
            margin-bottom: 0.25rem;
        }

        .nav-link::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.12) 0%, rgba(79, 70, 229, 0.12) 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
            border-radius: 0.75rem;
            box-shadow: inset 0 0 0 1px rgba(99, 102, 241, 0.2);
        }

        .nav-link:hover::before {
            opacity: 1;
            box-shadow: inset 0 0 0 1px rgba(99, 102, 241, 0.4);
        }

        .nav-link::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            transition: left 0.7s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: 0.75rem;
        }

        .nav-link:hover::after {
            left: 100%;
        }

        .nav-icon {
            flex-shrink: 0;
            transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 0.625rem;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.15) 0%, rgba(79, 70, 229, 0.12) 100%);
            position: relative;
        }

        .nav-icon::after {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 0.625rem;
            background: radial-gradient(circle at 30% 30%, rgba(255, 255, 255, 0.2) 0%, transparent 70%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .nav-link:hover .nav-icon {
            transform: scale(1.25) rotate(-8deg);
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.28) 0%, rgba(79, 70, 229, 0.25) 100%);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.3), inset 0 1px 0 rgba(255, 255, 255, 0.3);
        }

        .nav-link:hover .nav-icon::after {
            opacity: 1;
        }

        .nav-link:hover .nav-icon svg {
            filter: drop-shadow(0 2px 4px rgba(99, 102, 241, 0.3));
        }

        .nav-link.active {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.22) 0%, rgba(79, 70, 229, 0.18) 100%);
            color: #4f46e5;
            font-weight: 600;
            box-shadow: inset 0 0 0 2px rgba(99, 102, 241, 0.35), 0 4px 12px rgba(99, 102, 241, 0.15);
            position: relative;
        }

        .nav-link.active::before {
            opacity: 1;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.18) 0%, rgba(79, 70, 229, 0.15) 100%);
        }

        .nav-link.active .nav-icon {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.35) 0%, rgba(79, 70, 229, 0.3) 100%);
            box-shadow: 0 8px 24px rgba(99, 102, 241, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.4);
        }

        .nav-link.active .nav-icon svg {
            animation: iconBounce 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        @keyframes iconBounce {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.3); }
        }

        .nav-text {
            transition: all 0.2s ease;
            font-weight: 500;
            color: inherit;
        }

        .nav-separator {
            height: 1.5px;
            background: linear-gradient(to right, transparent 0%, rgba(99, 102, 241, 0.2) 50%, transparent 100%);
            margin: 1.25rem 0;
            border-radius: 1px;
            position: relative;
        }

        .nav-separator::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, transparent, rgba(79, 70, 229, 0.1), transparent);
            border-radius: 1px;
            filter: blur(0.5px);
        }

        .nav-link:focus-visible {
            outline: 2px solid #6366f1;
            outline-offset: 2px;
        }

        @keyframes pulseGlow {
            0%, 100% { box-shadow: 0 0 0 0 rgba(99, 102, 241, 0.7); }
            50% { box-shadow: 0 0 0 6px rgba(99, 102, 241, 0); }
        }

        .nav-link.active::after {
            animation: pulseGlow 2s ease-in-out infinite;
        }
    </style>

    {{-- Dashboard --}}
    <a href="{{ route('tenant.dashboard') }}"
       class="nav-link flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium transition-all duration-200 {{ request()->routeIs('tenant.dashboard') ? 'text-indigo-700 active' : 'text-gray-700 hover:text-indigo-700' }}">
        <span class="nav-icon sidebar-transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7m-9 2v8m4-8v8m5-12l2 2m-2-2v12a1 1 0 01-1 1h-4m-6 0H4a1 1 0 01-1-1V10m0 0l2-2" />
            </svg>
        </span>
        <span class="nav-text truncate sidebar-label">{{ __('messages.dashboard') }}</span>
    </a>

    <div class="nav-separator"></div>

    {{-- My Payments --}}
    <span class="nav-link flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium text-gray-400 cursor-not-allowed opacity-60">
        <span class="nav-icon sidebar-transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
        </span>
        <span class="nav-text truncate sidebar-label">{{ __('messages.my_payments') }}</span>
    </span>
</nav>
